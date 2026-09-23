<?php
/*
|--------------------------------------------------------------------------
| EventNest - Public Navigation Links
|--------------------------------------------------------------------------
| Included by header.php for guest (logged-out) pages only. Logged-in
| pages use the role label + notifications pattern in header.php instead,
| per the wireframe's "one header pattern per role" rule.
|
| Optional: set $current_page before requiring header.php to highlight
| the active link, e.g.
|     $current_page = 'events';
|     require_once __DIR__ . '/../shared-components/header.php';
|--------------------------------------------------------------------------
*/

$current_page = $current_page ?? '';

$nav_links = [
    'home'          => ['label' => 'Home',         'href' => '/IT-Project/index.php'],
    'events'        => ['label' => 'Events',        'href' => '/IT-Project/pages/events.php'],
    'career-fields' => ['label' => 'Career Fields', 'href' => '/IT-Project/pages/career-fields.php'],
    'about'         => ['label' => 'About',         'href' => '/IT-Project/pages/about.php'],
    'contact'       => ['label' => 'Contact',       'href' => '/IT-Project/pages/contact.php'],
];
?>
<nav class="site-nav" aria-label="Primary">
    <ul class="site-nav-list">
        <?php foreach ($nav_links as $key => $link): ?>
            <li>
                <a
                    href="<?php echo htmlspecialchars($link['href']); ?>"
                    class="site-nav-link<?php echo $current_page === $key ? ' active' : ''; ?>"
                    <?php echo $current_page === $key ? 'aria-current="page"' : ''; ?>
                >
                    <?php echo htmlspecialchars($link['label']); ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>