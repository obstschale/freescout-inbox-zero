<?php

// Isolated Laravel view stack: never boot the installed application's database,
// cached configuration, mail transports or other modules for module tests.
use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\Facade;

$application = new Application(dirname(__DIR__, 3));
$compiledPath = sys_get_temp_dir().'/inboxzero-'.bin2hex(random_bytes(8));
mkdir($compiledPath, 0700, true);
$application->instance('config', new Repository([
    'app' => ['url' => 'http://localhost', 'locale' => 'en', 'fallback_locale' => 'en', 'timezone' => 'Europe/Berlin'],
    'view' => ['paths' => [$application->resourcePath().'/views'], 'compiled' => $compiledPath],
]));
$application->instance('request', Illuminate\Http\Request::create('http://localhost'));
Facade::clearResolvedInstances();
Facade::setFacadeApplication($application);
AliasLoader::getInstance([
    'Eventy' => TorMorten\Eventy\Facades\Events::class,
    'Helper' => App\Misc\Helper::class,
    'Module' => Nwidart\Modules\Facades\Module::class,
])->register();
$application->register(Illuminate\Filesystem\FilesystemServiceProvider::class);
$application->register(Illuminate\View\ViewServiceProvider::class);
$application->register(Illuminate\Translation\TranslationServiceProvider::class);
$application->register(TorMorten\Eventy\EventServiceProvider::class);
$application->instance('modules', new Nwidart\Modules\Laravel\Repository($application));
$application->instance('url', new Illuminate\Routing\UrlGenerator(
    new Illuminate\Routing\RouteCollection(), $application['request']
));
$application->register(Modules\InboxZero\Providers\InboxZeroServiceProvider::class);
$application->boot();

return $application;
