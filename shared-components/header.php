<?php
/* EventNest shared document/header. Protected pages must call their auth guard before this include. */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/navbar.php';
$en_user = en_session_user();
$en_title = isset($page_title) && trim((string) $page_title) !== ''
    ? trim((string) $page_title) . ' | EventNest' : 'EventNest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= en_e($en_title) ?></title>
    <link rel="icon" type="image/png" href="<?= en_e(en_url('images/eventnest-logo.png')) ?>">
    <link rel="stylesheet" href="<?= en_e(en_url('styles/main.css')) ?>">
    <link rel="stylesheet" href="<?= en_e(en_url('styles/responsive.css')) ?>">
    <script src="<?= en_e(en_url('scripts/main.js')) ?>" defer></script>
</head>
<body>
<a class="skip-link" href="#main-content">Skip to main content</a>
<header class="site-header">
    <div class="site-header-start">
        <a href="<?= en_e(en_url('index.php')) ?>" class="brand" aria-label="EventNest homepage">
            <img src="<?= en_e(en_url('images/eventnest-logo.png')) ?>" alt="EventNest" class="brand-logo">
        </a>
        <?php if ($en_user['logged_in']): ?>
            <span class="header-role"><?= en_e($en_user['role'] !== '' ? ucfirst($en_user['role']) : 'Account') ?></span>
        <?php endif; ?>
    </div>
   <?php if (!$en_user['logged_in']): ?>
    <button
        type="button"
        class="nav-toggle"
        aria-controls="en-primary-nav"
        aria-expanded="false"
        hidden
    >
        <span class="nav-toggle-icon" aria-hidden="true"></span>
        <span>Menu</span>
    </button>

    <?php en_render_public_nav(); ?>

    <div class="site-header-actions">
        <a href="<?= en_e(en_url('login.php')) ?>" class="header-login">Log in</a>
        <a href="<?= en_e(en_url('register.php')) ?>" class="btn btn-teal btn-sm">Create account</a>
    </div>
<?php else: ?>
    <?php en_render_public_nav(); ?>

    <div class="site-header-actions">
        <span class="header-notifications" aria-label="Notifications placeholder">Notifications</span>
        <?php en_render_account_menu($en_user); ?>
    </div>
<?php endif; ?>