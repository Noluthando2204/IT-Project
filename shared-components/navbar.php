<?php
$nav_role = $_SESSION['role'] ?? null;
$nav_first_name = $_SESSION['first_name'] ?? 'User';
$current_page = basename($_SERVER['SCRIPT_NAME'] ?? '');
?>
<nav class="site-nav" aria-label="Primary navigation">
    <button class="nav-toggle" type="button" data-nav-toggle aria-expanded="false" aria-controls="main-nav-links">Menu</button>
    <div class="nav-links" id="main-nav-links" data-nav-links>
        <?php if (!$nav_role): ?>
            <a <?= $current_page === 'index.php' ? 'aria-current="page"' : '' ?> href="<?= e(app_url('index.php')) ?>">Home</a>
            <a <?= $current_page === 'events.php' ? 'aria-current="page"' : '' ?> href="<?= e(app_url('events.php')) ?>">Events</a>
            <a href="<?= e(app_url('career-fields.php')) ?>">Career Fields</a>
            <a href="<?= e(app_url('about.php')) ?>">About</a>
            <a href="<?= e(app_url('contact.php')) ?>">Contact</a>
            <a class="nav-login" href="<?= e(app_url('login.php')) ?>">Log in</a>
            <a class="btn btn-teal btn-small" href="<?= e(app_url('register.php')) ?>">Create account</a>
        <?php else: ?>
            <a class="role-label" href="<?= e($nav_role === 'admin' ? app_url('admin-panel/dashboard.php') : ($nav_role === 'organiser' ? app_url('business-dashboard.php') : app_url('user-dashboard.php'))) ?>"><?= e(ucfirst($nav_role)) ?></a>

            <?php if ($nav_role === 'attendee'): ?>
                <a <?= $current_page === 'events.php' ? 'aria-current="page"' : '' ?> href="<?= e(app_url('events.php')) ?>">Events</a>
                <a href="<?= e(app_url('career-fields.php')) ?>">Career Fields</a>
            <?php endif; ?>

            <a <?= $current_page === 'notifications.php' ? 'aria-current="page"' : '' ?> href="<?= e(app_url('notifications.php')) ?>">Notifications</a>
            <a href="<?= e(app_url('logout.php')) ?>">Log out</a>
            <span class="nav-spacer"></span>
            <a class="account-link" href="<?= e($nav_role === 'admin' ? app_url('admin-panel/dashboard.php') : ($nav_role === 'organiser' ? app_url('organiser-account.php') : app_url('profile.php'))) ?>">Hi, <?= e($nav_first_name) ?></a>
        <?php endif; ?>
    </div>
</nav>
