<?php
require_once __DIR__ . '/functions.php';
eventnest_ensure_session();
$page_title = isset($page_title) ? $page_title : 'EventNest';
$flash = function_exists('auth_get_flash') ? auth_get_flash() : null;
$flashes = $flash ? [$flash] : [];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="EventNest career-event discovery and booking platform">
    <title><?= e($page_title) ?> | EventNest</title>
    <link rel="stylesheet" href="<?= e(app_url('styles/main.css')) ?>">
    <link rel="stylesheet" href="<?= e(app_url('styles/responsive.css')) ?>">
</head>
<body>
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="site-header">
    <div class="site-header-inner">
        <a class="brand" href="<?= e(app_url('index.php')) ?>" aria-label="EventNest home">
            <img src="<?= e(app_url('images/eventnest-logo.png')) ?>" alt="EventNest">
        </a>
        <?php require __DIR__ . '/navbar.php'; ?>
    </div>
</header>
<?php if ($flashes): ?>
<div class="flash-stack" aria-live="polite">
    <?php foreach ($flashes as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<div id="main-content" class="page-shell">
