<?php

/*
|--------------------------------------------------------------------------
| EventNest - shared-components/navbar.php
| Owner: Thando
|--------------------------------------------------------------------------
| Navigation data and render functions shared by header.php, footer.php
| and the role dashboards. Including this file outputs NOTHING; pages and
| header.php call the render functions below.
|
| SECURITY: showing or hiding a link is NOT authorisation. Every
| destination page must still protect itself with shared-components/auth.php.
|
| Session keys read:
|   $_SESSION['user_id']      presence means logged in
|   $_SESSION['role']         attendee | organiser | admin
|   $_SESSION['first_name']   "Hi, <name>" greeting
|
| Legacy keys user_role / user_first_name are also supported so older pages
| keep working.
|--------------------------------------------------------------------------
*/

if (!function_exists('en_e')) {
    /**
     * Escape any value for safe HTML output.
     */
    function en_e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('en_base_path')) {
    /**
     * URL prefix of the project folder, e.g. "/IT-Project/".
     * Calculated from where this file sits on disk so links work from
     * root pages and admin-panel/ on XAMPP.
     */
    function en_base_path(): string
    {
        static $base = null;

        if ($base !== null) {
            return $base;
        }

        $project_root = str_replace(
            '\\',
            '/',
            (string) realpath(__DIR__ . '/..')
        );

        $doc_root = str_replace(
            '\\',
            '/',
            (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? '')
        );

        $base = '/';

        // Case-insensitive because Windows/XAMPP may report drive letters differently.
        if ($doc_root !== '' && stripos($project_root, $doc_root) === 0) {
            $relative = trim(
                substr($project_root, strlen($doc_root)),
                '/'
            );

            if ($relative !== '') {
                $segments = array_map('rawurlencode', explode('/', $relative));
                $base = '/' . implode('/', $segments) . '/';
            }
        }

        return $base;
    }
}

if (!function_exists('en_url')) {
    /**
     * Build a project-relative URL.
     * Escape the returned value when outputting it into HTML.
     */
    function en_url(string $path): string
    {
        return en_base_path() . ltrim($path, '/');
    }
}

if (!function_exists('en_current_path')) {
    /**
     * Path of the page being viewed, relative to the project root.
     * Used to mark the active navigation link.
     */
    function en_current_path(): string
    {
        $script = str_replace(
            '\\',
            '/',
            (string) ($_SERVER['SCRIPT_NAME'] ?? '')
        );

        $base = rawurldecode(en_base_path());

        if (stripos($script, $base) === 0) {
            return substr($script, strlen($base));
        }

        return ltrim($script, '/');
    }
}

if (!function_exists('en_session_user')) {
    /**
     * Display-only view of the logged-in user.
     * Role is whitelisted so an unexpected session value cannot select
     * another role's navigation.
     */
    function en_session_user(): array
    {
        $role = strtolower(
            trim(
                (string) (
                    $_SESSION['role']
                    ?? $_SESSION['user_role']
                    ?? ''
                )
            )
        );

        if (!in_array($role, ['attendee', 'organiser', 'admin'], true)) {
            $role = '';
        }

        return [
            'logged_in' => isset($_SESSION['user_id']),
            'role' => $role,
            'first_name' => trim(
                (string) (
                    $_SESSION['first_name']
                    ?? $_SESSION['user_first_name']
                    ?? ''
                )
            ),
        ];
    }
}

if (!function_exists('en_public_links')) {
    /**
     * Public navigation shown to guests.
     */
    function en_public_links(): array
    {
        return [
            ['label' => 'Home', 'path' => 'index.php'],
            ['label' => 'Events', 'path' => 'events.php'],
            ['label' => 'Career Fields', 'path' => 'career-fields.php'],
            ['label' => 'About', 'path' => 'about.php'],
            ['label' => 'Contact', 'path' => 'contact.php'],
        ];
    }
}

if (!function_exists('en_dashboard_path')) {
    /**
     * Correct dashboard for each role.
     */
    function en_dashboard_path(string $role): string
    {
        switch ($role) {
            case 'attendee':
                return 'user-dashboard.php';

            case 'organiser':
                return 'business-dashboard.php';

            case 'admin':
                return 'admin-panel/dashboard.php';

            default:
                return 'index.php';
        }
    }
}

if (!function_exists('en_role_links')) {
    /**
     * Role sidebar/account links.
     *
     * Navigation visibility is NOT authorisation. Each destination page
     * must still protect itself through shared-components/auth.php.
     */
    function en_role_links(string $role): array
    {
        switch ($role) {
            case 'attendee':
                return [
                    ['label' => 'Overview', 'path' => 'user-dashboard.php'],
                    ['label' => 'My bookings', 'path' => 'my-bookings.php'],
                    ['label' => 'Profile', 'path' => 'profile.php'],
                    ['label' => 'Settings', 'path' => 'settings.php'],
                ];

            case 'organiser':
                return [
                    ['label' => 'Overview', 'path' => 'business-dashboard.php'],
                    ['label' => 'Create event', 'path' => 'create-event.php'],
                    ['label' => 'Manage events', 'path' => 'manage-events.php'],
                    ['label' => 'Bookings', 'path' => 'manage-bookings.php'],
                    ['label' => 'Posts', 'path' => 'create-post.php'],
                    ['label' => 'Private reviews', 'path' => 'manage-reviews.php'],
                    ['label' => 'Organiser account', 'path' => 'organiser-account.php'],
                ];

            case 'admin':
                return [
                    ['label' => 'Overview', 'path' => 'admin-panel/dashboard.php'],
                    ['label' => 'Verifications', 'path' => 'admin-panel/manage-verifications.php'],
                    ['label' => 'Events', 'path' => 'admin-panel/manage-events.php'],
                    ['label' => 'Bookings', 'path' => 'admin-panel/manage-bookings.php'],
                    ['label' => 'Users', 'path' => 'admin-panel/manage-users.php'],
                    ['label' => 'Moderation', 'path' => 'admin-panel/moderation.php'],
                    ['label' => 'Categories', 'path' => 'admin-panel/categories.php'],
                    ['label' => 'Escrow', 'path' => 'admin-panel/escrow.php'],
                ];

            default:
                return [];
        }
    }
}

if (!function_exists('en_nav_link')) {
    /**
     * Build one escaped navigation link.
     * Marks the current page with .active and aria-current="page".
     */
    function en_nav_link(array $link, string $class): string
    {
        $is_current = en_current_path() === $link['path'];

        return '<a href="' . en_e(en_url($link['path'])) . '"'
            . ' class="' . en_e($class . ($is_current ? ' active' : '')) . '"'
            . ($is_current ? ' aria-current="page"' : '')
            . '>' . en_e($link['label']) . '</a>';
    }
}

if (!function_exists('en_render_public_nav')) {
    /**
     * Guest navigation.
     * The ID is used by scripts/main.js for the mobile menu toggle.
     */
    function en_render_public_nav(): void
    {
        echo '<nav id="en-primary-nav" class="site-nav" aria-label="Primary">';
        echo '<ul class="site-nav-list">';

        foreach (en_public_links() as $link) {
            echo '<li>' . en_nav_link($link, 'site-nav-link') . '</li>';
        }

        echo '</ul></nav>';
    }
}

if (!function_exists('en_render_account_menu')) {
    /**
     * "Hi, <name>" account area.
     * <details> works with keyboard navigation and without JavaScript.
     */
    function en_render_account_menu(array $user): void
    {
        $name = $user['first_name'] !== ''
            ? $user['first_name']
            : 'there';

        $links = en_role_links($user['role']);

        echo '<details class="account-menu">';
        echo '<summary class="account-menu-toggle">Hi, '
            . en_e($name)
            . '</summary>';

        echo '<ul class="account-menu-list">';

        foreach ($links as $link) {
            echo '<li>' . en_nav_link($link, 'account-menu-link') . '</li>';
        }

        echo '<li>'
            . en_nav_link(
                [
                    'label' => 'Log out',
                    'path' => 'logout.php',
                ],
                'account-menu-link account-menu-logout'
            )
            . '</li>';

        echo '</ul></details>';
    }
}

if (!function_exists('en_render_sidebar')) {
    /**
     * Navy role sidebar for role pages.
     * Call inside <div class="dashboard-shell"> before the page's <main>.
     */
    function en_render_sidebar(): void
    {
        $user = en_session_user();

        if (!$user['logged_in'] || $user['role'] === '') {
            return;
        }

        // Tells footer.php this is a role page.
        $GLOBALS['en_sidebar_rendered'] = true;

        echo '<aside id="en-role-nav" class="dashboard-sidebar">';
        echo '<p class="sidebar-role">'
            . en_e(ucfirst($user['role']))
            . '</p>';

        echo '<nav class="sidebar-nav" aria-label="'
            . en_e(ucfirst($user['role']) . ' navigation')
            . '">';

        foreach (en_role_links($user['role']) as $link) {
            echo '<div class="sidebar-nav-item">'
                . en_nav_link($link, 'sidebar-link')
                . '</div>';
        }

        echo '<div class="sidebar-nav-item">'
            . en_nav_link(
                [
                    'label' => 'Log out',
                    'path' => 'logout.php',
                ],
                'sidebar-link sidebar-link-logout'
            )
            . '</div>';

        echo '</nav></aside>';
    }
}
