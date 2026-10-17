<?php
/* EventNest shared navigation. Rendering links never replaces server-side authorisation. */
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!function_exists('en_e')) {
    function en_e($value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('en_base_path')) {
    function en_base_path(): string {
        static $base = null;
        if ($base !== null) { return $base; }
        $root = realpath(dirname(__DIR__));
        $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
        if ($root === false || $documentRoot === false) { return $base = '/'; }
        $root = str_replace('\\', '/', $root);
        $documentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');
        if (strcasecmp($root, $documentRoot) === 0) { return $base = '/'; }
        if (stripos($root, $documentRoot . '/') !== 0) { return $base = '/'; }
        $relative = trim(substr($root, strlen($documentRoot)), '/');
        return $base = '/' . implode('/', array_map('rawurlencode', explode('/', $relative))) . '/';
    }
}
if (!function_exists('en_url')) {
    function en_url(string $path = ''): string {
        // This helper accepts only known application-owned paths, never user-supplied redirects.
        return en_base_path() . ltrim($path, '/');
    }
}
if (!function_exists('en_current_path')) {
    function en_current_path(): string {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $base = rawurldecode(en_base_path());
        return stripos($script, $base) === 0 ? substr($script, strlen($base)) : ltrim($script, '/');
    }
}
if (!function_exists('en_session_user')) {
    function en_session_user(): array {
        $role = strtolower(trim((string) ($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
        if (!in_array($role, ['attendee', 'organiser', 'admin'], true)) { $role = ''; }
        $id = $_SESSION['user_id'] ?? null;
        return [
            'logged_in' => (is_int($id) || is_string($id)) && ctype_digit((string) $id) && (int) $id > 0,
            'role' => $role,
            'first_name' => trim((string) ($_SESSION['first_name'] ?? $_SESSION['user_first_name'] ?? '')),
        ];
    }
}
if (!function_exists('en_public_links')) {
    function en_public_links(): array {
        return [
            ['label' => 'Home', 'path' => 'index.php'],
            ['label' => 'Events', 'path' => 'events.php'],
            ['label' => 'Contact', 'path' => 'pages/contact.php'],
        ];
    }
}
if (!function_exists('en_dashboard_path')) {
    function en_dashboard_path(string $role): string {
        switch ($role) {
            case 'attendee':
                return 'pages/user-dashboard.php';
            case 'organiser':
                return 'pages/business-dashboard.php';
            case 'admin':
                return 'admin-panel/dashboard.php';
            default:
                return 'index.php';
        }
    }
}
if (!function_exists('en_role_links')) {
    function en_role_links(string $role): array {
        switch ($role) {
            case 'attendee':
                return [
                    ['label' => 'Overview', 'path' => 'pages/user-dashboard.php'],
                    ['label' => 'My bookings', 'path' => 'pages/my-bookings.php'],
                ];

            case 'organiser':
                return [
                    ['label' => 'Overview', 'path' => 'pages/business-dashboard.php'],
                    ['label' => 'Create event', 'path' => 'pages/create-event.php'],
                    ['label' => 'Private reviews', 'path' => 'pages/manage-reviews.php'],
                ];

            case 'admin':
                return [
                    ['label' => 'Overview', 'path' => 'admin-panel/dashboard.php'],
                    ['label' => 'Verifications', 'path' => 'admin-panel/manage-verifications.php'],
                    ['label' => 'Events', 'path' => 'admin-panel/manage-events.php'],
                    ['label' => 'Bookings', 'path' => 'admin-panel/manage-bookings.php'],
                    ['label' => 'Users', 'path' => 'admin-panel/manage-users.php'],
                    ['label' => 'Businesses', 'path' => 'admin-panel/manage-businesses.php'],
                ];

            default:
                return [];
        }
    }
}
if (!function_exists('en_nav_link')) {
    function en_nav_link(array $link, string $class): string {
        $current = en_current_path() === $link['path'];
        return '<a href="' . en_e(en_url($link['path'])) . '" class="' . en_e($class . ($current ? ' active' : '')) . '"'
            . ($current ? ' aria-current="page"' : '') . '>' . en_e($link['label']) . '</a>';
    }
}
if (!function_exists('en_render_public_nav')) {
    function en_render_public_nav(): void {
        echo '<nav id="en-primary-nav" class="site-nav" aria-label="Primary"><ul class="site-nav-list">';
        foreach (en_public_links() as $link) { echo '<li>' . en_nav_link($link, 'site-nav-link') . '</li>'; }
        echo '</ul></nav>';
    }
}
if (!function_exists('en_render_account_menu')) {
    function en_render_account_menu(array $user): void {
        $name = $user['first_name'] !== '' ? $user['first_name'] : 'there';
        echo '<details class="account-menu"><summary class="account-menu-toggle">Hi, ' . en_e($name) . '</summary>';
        echo '<ul class="account-menu-list">';
        foreach (en_role_links($user['role']) as $link) { echo '<li>' . en_nav_link($link, 'account-menu-link') . '</li>'; }
        echo '<li>' . en_nav_link(['label' => 'Log out', 'path' => 'logout.php'], 'account-menu-link account-menu-logout') . '</li>';
        echo '</ul></details>';
    }
}
if (!function_exists('en_render_sidebar')) {
    function en_render_sidebar(): void {
        $user = en_session_user();
        if (!$user['logged_in'] || $user['role'] === '') { return; }
        $GLOBALS['en_sidebar_rendered'] = true;
        echo '<aside id="en-role-nav" class="dashboard-sidebar">';
        echo '<p class="sidebar-role">' . en_e(ucfirst($user['role'])) . '</p>';
        echo '<nav class="sidebar-nav" aria-label="' . en_e(ucfirst($user['role']) . ' navigation') . '">';
        foreach (en_role_links($user['role']) as $link) {
            echo '<div class="sidebar-nav-item">' . en_nav_link($link, 'sidebar-link') . '</div>';
        }
        echo '<div class="sidebar-nav-item">' . en_nav_link(['label' => 'Log out', 'path' => 'logout.php'], 'sidebar-link sidebar-link-logout') . '</div>';
        echo '</nav></aside>';
    }
}
