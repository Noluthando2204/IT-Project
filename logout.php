<?php
declare(strict_types=1);
require_once __DIR__ . '/shared-components/auth.php';
$user = auth_current_user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventnest_require_post();
    auth_destroy_session();
    auth_redirect('index.php?logged_out=1');
}
$page_title = 'Log out';
require __DIR__ . '/shared-components/header.php';
?>
<main class="result-card"><h1>Log out of EventNest?</h1><p>Confirm to end your current session.</p>
<form method="post"><?php eventnest_csrf_field(); ?><div class="form-actions"><button class="btn btn-red">Confirm log out</button><a class="btn btn-outline" href="<?= escape_html(eventnest_url('index.php')) ?>">Stay signed in</a></div></form></main>
<?php require __DIR__ . '/shared-components/footer.php'; ?>
