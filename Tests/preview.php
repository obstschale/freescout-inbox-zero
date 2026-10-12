<?php

// Run only with PHP's development server; this fixture never boots a database.
if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 3);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$staticPath = null;
if (in_array($path, ['/css/bootstrap.css', '/css/style.css', '/css/fonts.css', '/img/logo-brand.svg'], true)
    || preg_match('#^/fonts/[a-zA-Z0-9/_-]+\.(woff2?|ttf|eot|svg)$#', $path)) {
    $staticPath = $root.'/public'.$path;
} elseif ($path === '/modules/inboxzero/css/module.css') {
    $staticPath = __DIR__.'/../Public/css/module.css';
} elseif (preg_match('#^/modules/inboxzero/images/animals/([a-z-]+)\.png$#', $path, $matches)) {
    $staticPath = __DIR__.'/../Public/images/animals/'.$matches[1].'.png';
}
if ($staticPath && is_file($staticPath)) {
    $types = ['css' => 'text/css', 'svg' => 'image/svg+xml', 'png' => 'image/png', 'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'eot' => 'application/vnd.ms-fontobject'];
    header('Content-Type: '.$types[pathinfo($staticPath, PATHINFO_EXTENSION)]);
    readfile($staticPath);
    return;
}
if ($path !== '/') {
    http_response_code(404);
    exit;
}

require $root.'/vendor/autoload.php';
$application = require __DIR__.'/bootstrap.php';
register_shutdown_function(function () use ($application) {
    $application['files']->deleteDirectory($application['config']['view.compiled']);
});
$locale = ($_GET['lang'] ?? '') === 'en' ? 'en' : 'de';
$application->setLocale($locale);
$request = Illuminate\Http\Request::capture();
$route = (new Illuminate\Routing\Route('GET', '/', function () {}))->name('mailboxes.view');
$request->setRouteResolver(function () use ($route) {
    return $route;
});
$application->instance('request', $request);
$application['url']->setRequest($request);
$application->instance('auth', new Illuminate\Auth\RequestGuard(function () {
    $viewer = new App\User();
    $viewer->role = App\User::ROLE_ADMIN;

    return $viewer;
}, $request));
$folder = new App\Folder();
$folder->type = ($_GET['folder'] ?? '') === 'mine' ? App\Folder::TYPE_MINE : App\Folder::TYPE_UNASSIGNED;
$animal = $_GET['animal'] ?? '';
if (in_array($animal, Modules\InboxZero\DailyAnimal::ANIMALS, true) && $folder->type === App\Folder::TYPE_UNASSIGNED) {
    // Explicit asset previews use the production template; the default uses the core table integration.
    $html = $application['view']->make('inboxzero::empty', ['inboxZeroAnimal' => $animal])->render();
} else {
    $html = $application['view']->make('conversations/conversations_table', [
        'folder' => $folder,
        'conversations' => new Illuminate\Pagination\LengthAwarePaginator([], 0, 50),
    ])->render();
}
$unassigned = $locale === 'de' ? 'Nicht zugewiesen' : 'Unassigned';
$mine = $locale === 'de' ? 'Meine' : 'Mine';
?>
<!doctype html>
<html lang="<?= $locale ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inbox Zero — isolated component preview</title>
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="/css/fonts.css">
    <link rel="stylesheet" href="/css/bootstrap.css">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/modules/inboxzero/css/module.css">
</head>
<body>
    <a class="sr-only sr-only-focusable" href="#inbox">Skip to inbox</a>
    <header class="navbar navbar-default navbar-static-top">
        <div class="container">
            <div class="navbar-header">
                <a class="navbar-brand" href="/"><img src="/img/logo-brand.svg" height="100%" alt="FreeScout"></a>
            </div>
            <ul class="nav navbar-nav hidden-xs"><li class="active"><a href="/"><?= $locale === 'de' ? 'Postfach' : 'Mailbox' ?></a></li></ul>
        </div>
    </header>
    <div class="layout-2col">
        <nav class="sidebar-2col" aria-label="Mailbox folders">
            <div class="sidebar-title sidebar-title-extra">
                <span class="sidebar-title-extra-value"><?= $folder->type === App\Folder::TYPE_MINE ? $mine : $unassigned ?></span>
                <span class="sidebar-title-real mailbox-name">Support</span>
                <span class="sidebar-title-email">support@example.com</span>
            </div>
            <ul class="sidebar-menu">
                <li class="<?= $folder->type === App\Folder::TYPE_UNASSIGNED ? 'active' : '' ?>"><a href="/?lang=<?= $locale ?>"><i class="glyphicon glyphicon-folder-open" aria-hidden="true"></i> <?= $unassigned ?></a></li>
                <li class="<?= $folder->type === App\Folder::TYPE_MINE ? 'active' : '' ?>"><a href="/?folder=mine&amp;lang=<?= $locale ?>"><i class="glyphicon glyphicon-user" aria-hidden="true"></i> <?= $mine ?></a></li>
            </ul>
        </nav>
        <main class="content-2col" id="inbox">
            <h1 class="sr-only"><?= $folder->type === App\Folder::TYPE_MINE ? $mine : $unassigned ?></h1>
            <?= $html ?>
        </main>
    </div>
</body>
</html>
