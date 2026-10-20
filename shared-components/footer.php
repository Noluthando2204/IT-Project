<?php
/* Shared public footer. Page content and dashboard-shell must be closed by the including page. */
require_once __DIR__ . '/navbar.php';
$en_footer_links = [
    ['label' => 'Events', 'path' => 'events.php'],
    ['label' => 'Terms', 'path' => 'pages/terms.php'],
    ['label' => 'Privacy', 'path' => 'pages/privacy-policy.php'],
    ['label' => 'Cookies', 'path' => 'pages/cookie-policy.php'],
    ['label' => 'Contact', 'path' => 'pages/contact.php'],
];
?>
<footer class="site-footer">
    <div class="footer-container">
        <div class="footer-brand">
            <strong>About EventNest</strong>
            <p>Connecting people through career and professional-development events.</p>
            <small>&copy; <?= en_e(date('Y')) ?> EventNest. All rights reserved.</small>
        </div>
        <nav aria-label="Footer navigation" class="footer-nav">
            <ul class="footer-links">
                <?php foreach ($en_footer_links as $en_footer_link): ?>
                    <li><a href="<?= en_e(en_url($en_footer_link['path'])) ?>"><?= en_e($en_footer_link['label']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </div>
</footer>
</body>
</html>
