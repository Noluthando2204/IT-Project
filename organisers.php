<?php
declare(strict_types=1);
require_once __DIR__.'/shared-components/auth.php';
$organisers=eventnest_db()->query("SELECT UserID,OrganisationName,FirstName,LastName,CareerField FROM USERS WHERE Role='organiser' AND UserStatus='active' AND VerificationStatus='verified' ORDER BY OrganisationName,UserID")->fetchAll();
$page_title='Organisers';
require __DIR__.'/shared-components/header.php';
?>
<main><h1>Meet the organisers</h1><p>Explore career pathways and updates from verified organisers.</p><div class="event-grid">
<?php foreach($organisers as $organiser): ?><article class="content-card">
<?= eventnest_organiser_avatar($organiser,120) ?>
<h2><?= e(eventnest_organiser_display_name($organiser)) ?></h2><p><?= e($organiser['CareerField']) ?></p>
<a class="btn btn-blue" href="<?= e(eventnest_url('organiser-profile.php?organiser_id='.(int)$organiser['UserID'])) ?>">View profile and posts</a>
</article><?php endforeach; ?></div></main>
<?php require __DIR__.'/shared-components/footer.php'; ?>
