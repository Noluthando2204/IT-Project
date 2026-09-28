<?php
/*
|--------------------------------------------------------------------------
| EventNest - Shared Site Header
|--------------------------------------------------------------------------
| Include this at the top of every page's <body>, e.g.:
|     require_once __DIR__ . '/../shared-components/header.php';
|
| Adapts between:
|   - Guest header:      Logo + public nav (via navbar.php) + Log in / Create account
|   - Logged-in header:  Logo + role label + Notifications + "Hi, <name>"
| This matches the two header patterns shown across the wireframe screens.
|
| Reads from session (set by whatever the team's login flow ends up
| writing to $_SESSION):
|   $_SESSION['user_id']         - presence means logged in
|   $_SESSION['user_role']       - 'Attendee' | 'Organiser' | 'Admin'
|   $_SESSION['user_first_name'] - used in the "Hi, <name>" greeting
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_logged_in = isset($_SESSION['user_id']);
$user_role    = $_SESSION['user_role'] ?? 'Attendee';
$user_name    = $_SESSION['user_first_name'] ?? '';
?>
<header class="site-header">

    <a href="/IT-Project/index.php" class="brand">
        <img src="/IT-Project/images/eventnest-logo.png" alt="EventNest" class="brand-logo">
    </a>

    <?php if (!$is_logged_in): ?>

        <?php require_once __DIR__ . '/navbar.php'; ?>

        <div class="site-header-actions">
            <a href="/IT-Project/pages/login.php" class="btn btn-outline">Log in</a>
            <a href="/IT-Project/pages/register.php" class="btn btn-primary">Create account</a>
        </div>

    <?php else: ?>

        <span class="header-role-badge">
            <?php echo htmlspecialchars($user_role); ?>
        </span>

        <div class="site-header-actions">

            <button class="icon-btn" aria-label="Notifications" type="button">🔔</button>

            <span class="topbar-greeting">
                Hi, <?php echo htmlspecialchars($user_name); ?>
            </span>

        </div>

    <?php endif; ?>

</header>
