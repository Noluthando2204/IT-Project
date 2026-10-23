<?php
declare(strict_types=1);
require_once __DIR__.'/../shared-components/auth.php';
$user=auth_require_admin();
$pdo=eventnest_db();
$totals=$pdo->query("SELECT (SELECT COUNT(*) FROM USERS WHERE UserStatus<>'deleted') AS Users,(SELECT COUNT(*) FROM USERS WHERE Role='organiser' AND VerificationStatus='pending') AS Pending,(SELECT COUNT(*) FROM BOOKINGS) AS Bookings,(SELECT COALESCE(SUM(Amount),0) FROM PAYMENTS WHERE PaymentStatus='success' AND Escrow_status='held') AS Held")->fetch();
$events=$pdo->query('SELECT Status,COUNT(*) AS Total FROM EVENTS GROUP BY Status')->fetchAll();
$recent=$pdo->query("SELECT EventID,EventName,EventDate FROM EVENTS WHERE Status='draft' ORDER BY Created_at DESC LIMIT 10")->fetchAll();
$page_title='Admin overview';
require __DIR__.'/../shared-components/header.php';
?>
<div class="dashboard-layout"><?php eventnest_sidebar('admin'); ?><main class="dashboard-main"><h1>Admin overview</h1><div class="stat-grid"><?php foreach(['Users'=>['Registered users','manage-users.php'],'Pending'=>['Pending verification','manage-verifications.php'],'Bookings'=>['Total bookings','manage-bookings.php'],'Held'=>['Held escrow','escrow.php']] as $key=>[$label,$link]): ?><a class="stat-card" href="<?= $link ?>"><span><?= $label ?></span><strong><?= $key==='Held'?escape_html(format_money($totals[$key])):(int)$totals[$key] ?></strong></a><?php endforeach; ?></div>
<h2>Event status totals</h2><div class="inline-actions"><?php foreach($events as $event): ?><a class="chip" href="manage-events.php?status=<?= escape_html($event['Status']) ?>"><?= escape_html(eventnest_status_label($event['Status'])) ?>: <?= (int)$event['Total'] ?></a><?php endforeach; ?></div>
<section class="content-card"><h2>Draft events requiring review</h2><?php foreach($recent as $event): ?><p><a href="manage-events.php?event_id=<?= (int)$event['EventID'] ?>"><?= escape_html($event['EventName']) ?></a> ? <?= escape_html(format_datetime($event['EventDate'])) ?></p><?php endforeach; ?><?php if(!$recent): ?><p>No draft events awaiting review.</p><?php endif; ?></section><p class="callout">Review emailed event concerns before authorising escrow release. External integrations require their server configuration.</p></main></div>
<?php require __DIR__.'/../shared-components/footer.php'; ?>
