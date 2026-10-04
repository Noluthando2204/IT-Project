<?php
/*
|--------------------------------------------------------------------------
| EventNest - shared-components/footer.php
| Owner: Thando
|--------------------------------------------------------------------------
| Shared public footer (Screen 02). Include once at the end of every page,
| after the page's <main>. Closes the </body></html> opened by header.php.
|
| Role pages (those that call en_render_sidebar()) show no footer band,
| matching Screens 13, 21, 25 and 33; the document is still closed here.
|
| Contains public and policy links only - never private attendee,
| organiser or admin routes. All destinations are fixed internal paths.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/navbar.php';

$en_footer_links = [
    ['label' => 'Events',  'path' => 'events.php'],
    ['label' => 'FAQ',     'path' => 'faq.php'],
    ['label' => 'Terms',   'path' => 'terms.php'],
    ['label' => 'Privacy', 'path' => 'privacy-policy.php'],
    ['label' => 'Cookies', 'path' => 'cookie-policy.php'],
];
?>
<?php if (empty($GLOBALS['en_sidebar_rendered'])): ?>
<footer class="site-footer">
    <div class="footer-container">

        <?php echo en_nav_link(['label' => 'About EventNest', 'path' => 'about.php'], 'footer-link footer-about'); ?>

        <nav class="footer-links" aria-label="Footer">
            <ul>
                <?php foreach ($en_footer_links as $link): ?>
                    <li><?php echo en_nav_link($link, 'footer-link'); ?></li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <?php echo en_nav_link(['label' => 'Contact us', 'path' => 'contact.php'], 'footer-link footer-contact'); ?>

    </div>
</footer>
<?php endif; ?>

</body>
</html>