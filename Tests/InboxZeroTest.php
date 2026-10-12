<?php

namespace Modules\InboxZero\Tests;

use App\Folder;
use App\Mailbox;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Route;
use Modules\InboxZero\DailyAnimal;
use PHPUnit\Framework\TestCase;

class InboxZeroTest extends TestCase
{
    private $application;

    protected function setUp(): void
    {
        $this->application = require __DIR__.'/bootstrap.php';
        $this->setViewer(false);
    }

    protected function tearDown(): void
    {
        $this->application['files']->deleteDirectory($this->application['config']['view.compiled']);
        \Illuminate\Support\Facades\Facade::clearResolvedInstances();
        $this->application->flush();
        parent::tearDown();
    }

    private function setViewer($restricted, $canUpdateSettings = true)
    {
        $viewer = new class($restricted, $canUpdateSettings) {
            private $restricted;
            private $canUpdateSettings;

            public function __construct($restricted, $canUpdateSettings)
            {
                $this->restricted = $restricted;
                $this->canUpdateSettings = $canUpdateSettings;
            }

            public function can($ability, $mailbox)
            {
                return $ability === 'updateSettings' && $this->canUpdateSettings;
            }

            public function canSeeOnlyAssignedConversations()
            {
                return $this->restricted;
            }
        };
        $guard = new \Illuminate\Auth\RequestGuard(function () use ($viewer) {
            return $viewer;
        }, $this->application['request']);
        $this->application->instance('auth', $guard);
    }

    private function renderEmpty($routeName, $folderType, $total = 0, $input = [], $data = [], $mailbox = null)
    {
        $request = Request::create('/mailbox/1', 'GET', $input);
        $route = (new Route('GET', '/mailbox/1', function () {}))->name($routeName);
        $request->setRouteResolver(function () use ($route) {
            return $route;
        });
        $this->application->instance('request', $request);
        $folder = new Folder();
        $folder->type = $folderType;
        if ($mailbox === null) {
            $mailbox = new Mailbox();
            $mailbox->setMeta('inboxzero_enabled', true);
        }
        $folder->setRelation('mailbox', $mailbox);

        // Render the actual core table, including its inherited-data empty partial.
        return $this->application['view']->make('conversations/conversations_table', array_merge([
            'folder' => $folder,
            'conversations' => new LengthAwarePaginator([], $total, 50),
        ], $data))->render();
    }

    public function testOnlyGenuinelyEmptyUnassignedListsGetTheAnimal()
    {
        foreach (['mailboxes.view', 'mailboxes.view.folder', 'conversations.ajax'] as $routeName) {
            $html = $this->renderEmpty($routeName, Folder::TYPE_UNASSIGNED, 0, ['action' => 'conversations_pagination']);
            $this->assertStringContainsString('class="inboxzero-animal"', $html);
            $this->assertStringContainsString('No unassigned conversations', $html);
            $this->assertStringNotContainsString('glyphicon-ok', $html);
        }
        foreach ([Folder::TYPE_MINE, Folder::TYPE_ASSIGNED, Folder::TYPE_CLOSED, Folder::TYPE_DELETED] as $folderType) {
            $this->assertStringNotContainsString('inboxzero-animal', $this->renderEmpty('mailboxes.view.folder', $folderType));
        }
        $this->assertStringNotContainsString('inboxzero-animal', $this->renderEmpty('mailboxes.view', Folder::TYPE_UNASSIGNED, 3));
        $this->assertStringNotContainsString('inboxzero-animal', $this->renderEmpty('conversations.search', Folder::TYPE_UNASSIGNED));
        $this->assertStringNotContainsString('inboxzero-animal', $this->renderEmpty('conversations.ajax', Folder::TYPE_UNASSIGNED, 0, ['action' => 'search']));
        $this->assertStringNotContainsString('inboxzero-animal', $this->renderEmpty('mailboxes.view', Folder::TYPE_UNASSIGNED, 0, [], ['conversations_filter' => ['status' => 1]]));
        $this->setViewer(true);
        $this->assertStringNotContainsString('inboxzero-animal', $this->renderEmpty('mailboxes.view', Folder::TYPE_UNASSIGNED));
    }

    public function testMailboxOptInIsIndependentAndCanBeDisabled()
    {
        $enabledMailbox = new Mailbox();
        $defaultMailbox = new Mailbox();
        $enabledMailbox->setMeta('other_module', 'preserved');
        $render = function ($mailbox) {
            return $this->renderEmpty('mailboxes.view', Folder::TYPE_UNASSIGNED, 0, [], [], $mailbox);
        };
        $this->assertStringContainsString('glyphicon-ok', $render($enabledMailbox));
        \Eventy::action('mailbox.settings_before_save', $enabledMailbox, Request::create('/', 'POST', ['inboxzero_enabled' => '1']));
        $this->assertStringContainsString('inboxzero-animal', $render($enabledMailbox));
        $this->assertStringContainsString('glyphicon-ok', $render($defaultMailbox));
        $this->assertSame('preserved', $enabledMailbox->getMeta('other_module'));

        // Other forms and signature-only users cannot change this preference.
        \Eventy::action('mailbox.settings_before_save', $enabledMailbox, Request::create('/', 'POST', ['signature' => 'Hello']));
        $this->assertTrue($enabledMailbox->getMeta('inboxzero_enabled'));
        $this->setViewer(false, false);
        \Eventy::action('mailbox.settings_before_save', $enabledMailbox, Request::create('/', 'POST', ['inboxzero_enabled' => '0']));
        $this->assertTrue($enabledMailbox->getMeta('inboxzero_enabled'));
        $this->setViewer(false);
        \Eventy::action('mailbox.settings_before_save', $enabledMailbox, Request::create('/', 'POST', ['inboxzero_enabled' => '0']));
        $this->assertStringContainsString('glyphicon-ok', $render($enabledMailbox));
        $this->assertFalse($enabledMailbox->getMeta('inboxzero_enabled'));
    }

    public function testSettingsShowSavedAndOldInputOnlyToAuthorizedUsers()
    {
        $mailbox = new Mailbox();
        // Never start or save this session; old input stays in the Store's memory.
        $session = new \Illuminate\Session\Store('inboxzero-test', new \SessionHandler());
        $this->application['request']->setLaravelSession($session);
        $render = function () use ($mailbox) {
            ob_start();
            \Eventy::action('mailbox.update.after_signature', $mailbox);

            return ob_get_clean();
        };
        $this->assertStringContainsString('Enable InboxZero for this mailbox', $render());
        $this->assertStringNotContainsString(' checked', $render());
        $mailbox->setMeta('inboxzero_enabled', true);
        $this->assertStringContainsString(' checked', $render());
        $session->flashInput(['inboxzero_enabled' => '0']);
        $this->assertStringNotContainsString(' checked', $render());
        $this->application->setLocale('de');
        $this->assertStringContainsString('InboxZero für dieses Postfach aktivieren', $render());
        $this->setViewer(false, false);
        $this->assertSame('', $render());
    }

    public function testColleaguesShareTheAnimalButGetTheirOwnLanguage()
    {
        $english = $this->renderEmpty('mailboxes.view', Folder::TYPE_UNASSIGNED);
        $this->application->setLocale('de');
        $this->setViewer(false);
        $german = $this->renderEmpty('mailboxes.view', Folder::TYPE_UNASSIGNED);
        preg_match('/src="([^"]+)"/', $english, $englishImage);
        preg_match('/src="([^"]+)"/', $german, $germanImage);
        $this->assertSame($englishImage[1], $germanImage[1]);
        $this->assertStringContainsString('Keine unzugewiesenen Tickets', $german);
        $this->assertStringNotContainsString('inboxzero::', $german);
        $this->application->setLocale('fr');
        $fallback = $this->renderEmpty('mailboxes.view', Folder::TYPE_UNASSIGNED);
        $this->assertStringContainsString('No unassigned conversations', $fallback);
        $this->assertStringNotContainsString('inboxzero::', $fallback);
    }

    public function testRotationUsesSharedCalendarDaysAcrossMidnightDstAndYearBoundaries()
    {
        $cases = [
            ['2025-12-31T22:59:59Z', 'pangolin'],
            ['2025-12-31T23:00:00Z', 'fox'],
            ['2026-01-01T22:59:59Z', 'fox'],
            ['2026-01-01T23:00:00Z', 'snail'],
            ['2026-03-29T00:30:00Z', 'rooster'],
            ['2026-03-29T01:30:00Z', 'rooster'],
            ['2026-03-29T21:59:59Z', 'rooster'],
            ['2026-03-29T22:00:00Z', 'turkey'],
            ['2026-04-11T10:00:00Z', 'fox'],
            ['2026-10-25T00:30:00Z', 'armadillo'],
            ['2026-10-25T01:30:00Z', 'armadillo'],
            ['2026-10-25T22:59:59Z', 'armadillo'],
            ['2026-10-25T23:00:00Z', 'anteater'],
            ['2026-12-31T22:59:59Z', 'rhino'],
            ['2026-12-31T23:00:00Z', 'lion'],
            ['2028-02-28T12:00:00Z', 'turkey'],
            ['2028-02-29T12:00:00Z', 'goose'],
            ['2028-03-01T12:00:00Z', 'bat'],
        ];
        foreach ($cases as $case) {
            $this->assertSame($case[1], DailyAnimal::forDate(new \DateTimeImmutable($case[0]), 'Europe/Berlin'), $case[0]);
        }
        $this->assertSame('fox', DailyAnimal::forDate(new \DateTimeImmutable('2026-01-01T18:00:00-05:00'), 'UTC'));
        $this->assertSame('snail', DailyAnimal::forDate(new \DateTimeImmutable('2026-01-01T18:00:00-05:00'), 'Europe/Berlin'));
        $seen = [];
        $day = new \DateTimeImmutable('2026-01-01T12:00:00Z');
        for ($i = 0; $i < 100; $i++) {
            $seen[] = DailyAnimal::forDate($day->modify('+'.$i.' days'), 'Europe/Berlin');
        }
        $this->assertCount(100, array_unique($seen));
    }

    public function testEveryAnimalHasBothTranslationsAndADistinctTransparentPng()
    {
        $english = require __DIR__.'/../Resources/lang/en/messages.php';
        $german = require __DIR__.'/../Resources/lang/de/messages.php';
        $this->assertCount(100, DailyAnimal::ANIMALS);
        $this->assertSame(DailyAnimal::ANIMALS, array_keys($english['animals']));
        $this->assertSame(DailyAnimal::ANIMALS, array_keys($german['animals']));
        $hashes = [];
        foreach (DailyAnimal::ANIMALS as $animal) {
            foreach ([$english, $german] as $language) {
                $this->assertNotEmpty($language['animals'][$animal]['name']);
                $this->assertNotEmpty($language['animals'][$animal]['message']);
            }
            $path = __DIR__.'/../Public/images/animals/'.$animal.'.png';
            $this->assertFileExists($path);
            $this->assertSame(IMAGETYPE_PNG, getimagesize($path)[2]);
            // PNG color type 6 is truecolor with alpha (IHDR byte 25).
            $this->assertSame(6, ord(file_get_contents($path, false, null, 25, 1)));
            $hashes[] = hash_file('sha256', $path);
        }
        $this->assertCount(100, array_unique($hashes));
    }
}
