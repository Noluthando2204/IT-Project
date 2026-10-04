<?php
/*
|--------------------------------------------------------------------------
| EventNest - shared-components/header.php
| Owner: Thando
|--------------------------------------------------------------------------
| Opens the document (<!DOCTYPE>, <head>, <body>), loads the shared
| stylesheets and script, and renders the site header.
| shared-components/footer.php closes </body></html>.
|
| Page usage (every rendered page):
|
|   <?php
|   require_once __DIR__ . '/shared-components/auth.php';   // protection first (protected pages)
|   $page_title = 'My bookings';                             // optional
|   require_once __DIR__ . '/shared-components/header.php';
|   ?>
|   <!-- Role pages: -->
|   <div class="dashboard-shell">
|       <?php en_render_sidebar(); ?>
|       <main id="main-content" class="dashboard-main"> ... </main>
|   </div>
|   <!-- Public pages: <main id="main-content" class="page-main"> ... </main> -->
|   <?php require_once __DIR__ . '/shared-components/footer.php'; ?>
|
| From admin-panel/ use __DIR__ . '/../shared-components/header.php'.
| Pages must NOT output their own <html>, <head> or <body> tags.
|
| Two header patterns (wireframe Screens 02, 13, 21, 25, 44):
|   Guest:     logo + public nav + Log in text link + teal Create account button
|   Logged in: logo + role label + Notifications + "Hi, <name>" account menu
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/navbar.php';

$en_user  = en_session_user();
$en_title = (isset($page_title) && trim((string) $page_title) !== '')
    ? trim((string) $page_title) . ' | EventNest'
    : 'EventNest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo en_e($en_title); ?></title>
    <link rel="icon" type="image/png" href="<?php echo en_e(en_url('images/eventnest-logo.png')); ?>">
    <link rel="stylesheet" href="<?php echo en_e(en_url('styles/main.css')); ?>">
    <link rel="stylesheet" href="<?php echo en_e(en_url('styles/responsive.css')); ?>">
    <!-- Marks JavaScript as available so the mobile menu only collapses when it can be reopened. -->
    <script>document.documentElement.classList.add('js');</script>
    <script src="<?php echo en_e(en_url('scripts/main.js')); ?>" defer></script>
</head>
<body>

<a class="skip-link" href="#main-content">Skip to main content</a>

<header class="site-header">

    <div class="site-header-start">
        <a href="<?php echo en_e(en_url('index.php')); ?>" class="brand">
            <img src="<?php echo en_e(en_url('images/eventnest-logo.png')); ?>" alt="EventNest" class="brand-logo">
        </a>

        <?php if ($en_user['logged_in']): ?>
            <span class="header-role">
                <?php echo en_e($en_user['role'] !== '' ? ucfirst($en_user['role']) : 'Account'); ?>
            </span>
        <?php endif; ?>
    </div>

    <?php if (!$en_user['logged_in']): ?>

        <button type="button" class="nav-toggle" aria-controls="en-primary-nav" aria-expanded="false">
            <span class="nav-toggle-icon" aria-hidden="true"></span>
            <span>Menu</span>
        </button>

        <?php en_render_public_nav(); ?>

        <div class="site-header-actions">
            <a href="<?php echo en_e(en_url('login.php')); ?>" class="header-login">Log in</a>
            <a href="<?php echo en_e(en_url('register.php')); ?>" class="btn btn-teal btn-sm">Create account</a>
        </div>

    <?php else: ?>

        <!-- Mobile: toggles the role navigation menu through scripts/main.js. -->
        <button type="button" class="nav-toggle" aria-controls="en-role-nav" aria-expanded="false">
            <span class="nav-toggle-icon" aria-hidden="true"></span>
            <span>Menu</span>
        </button>

        <div class="site-header-actions">
            <!-- Placeholder only: no notifications feature exists in the ERD yet. -->
            <span class="header-notifications">Notifications</span>
            <?php en_render_account_menu($en_user); ?>
        </div>

    <?php endif; ?>

</header>
