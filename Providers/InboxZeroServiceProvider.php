<?php

namespace Modules\InboxZero\Providers;

use App\Folder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;
use Modules\InboxZero\DailyAnimal;

class InboxZeroServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'inboxzero');
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'inboxzero');

        \Eventy::addFilter('stylesheets', function ($stylesheets) {
            $stylesheets[] = \Module::getPublicPath('inboxzero').'/css/module.css';

            return $stylesheets;
        });

        \Eventy::addAction('mailbox.update.after_signature', function ($mailbox) {
            if (auth()->user()->can('updateSettings', $mailbox)) {
                echo view('inboxzero::mailbox-settings', compact('mailbox'))->render();
            }
        }, 20, 1);

        \Eventy::addAction('mailbox.settings_before_save', function ($mailbox, $request) {
            // Signature-only forms must not reset this mailbox setting.
            if (auth()->user()->can('updateSettings', $mailbox) && $request->has('inboxzero_enabled')) {
                $mailbox->setMeta('inboxzero_enabled', $request->input('inboxzero_enabled') === '1');
            }
        }, 20, 2);

        // FreeScout has no empty-list hook. Change only this partial's path, leaving
        // the core query, authorization, table and AJAX pagination untouched.
        $this->app['view']->composer('partials/empty', function (View $view) {
            $request = $this->app['request'];
            $route = $request->route();
            if (!$route) {
                return;
            }
            $isMailbox = in_array($route->getName(), ['mailboxes.view', 'mailboxes.view.folder'], true);
            $isPagination = $route->getName() === 'conversations.ajax'
                && $request->input('action') === 'conversations_pagination';
            if (!$isMailbox && !$isPagination) {
                return;
            }

            $data = $view->getData();
            $folder = $data['folder'] ?? null;
            $conversations = $data['conversations'] ?? null;
            if (!$folder instanceof Folder || (int) $folder->type !== Folder::TYPE_UNASSIGNED
                || !$conversations instanceof LengthAwarePaginator
                || $conversations->total() != 0 || $conversations->count() != 0
                || !empty($data['params']) || !empty($data['conversations_filter'])
                || ($data['empty_text'] ?? null) !== __('There are no conversations here')) {
                return;
            }

            // A restricted agent's empty personal result is not a team-wide Inbox Zero.
            $user = $this->app['auth']->user();
            if (!$user || $user->canSeeOnlyAssignedConversations()) {
                return;
            }

            $mailbox = $folder->mailbox;
            if (!$mailbox || !$mailbox->getMeta('inboxzero_enabled', false)) {
                return;
            }

            $animal = DailyAnimal::forDate(new \DateTimeImmutable(), config('app.timezone'));
            $view->with('inboxZeroAnimal', $animal);
            $view->setPath($this->app['view']->getFinder()->find('inboxzero::empty'));
        });
    }
}
