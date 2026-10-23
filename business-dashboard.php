<?php
declare(strict_types=1);
require_once __DIR__ . '/shared-components/auth.php';
$user=auth_require_organiser();
$pdo=eventnest_db();
$stmt=$pdo->prepare("SELECT COUNT(*) AS Events,COALESCE(SUM(Status='published'),0) AS Published FROM EVENTS WHERE OrganiserID=?");
$stmt->execute([$user['UserID']]);
$totals=$stmt->fetch();
$stmt=$pdo->prepare("SELECT COUNT(*) FROM BOOKINGS b JOIN EVENTS e ON e.EventID=b.EventID WHERE e.OrganiserID=?");
$stmt->execute([$user['UserID']]);
$booking_count=$stmt->fetchColumn();
$stmt=$pdo->prepare('SELECT EventID,EventName,EventDate,Status FROM EVENTS WHERE OrganiserID=? AND EventDate>NOW() ORDER BY EventDate LIMIT 10');
$stmt->execute([$user['UserID']]);
$events=$stmt->fetchAll();
$page_title='Organiser dashboard';
require __DIR__.'/shared-components/header.php';
?>
<div class="dashboard-layout"><?php eventnest_sidebar('organiser'); ?><main class="dashboard-main"><h1>Organiser overview</h1><p>Hi, <?= escape_html($user['FirstName']) ?>. Verification: <?= eventnest_status_badge($user['VerificationStatus']) ?></p>
<div class="stat-grid"><div class="stat-card"><span>Total events</span><strong><?= (int)$totals['Events'] ?></strong></div><div class="stat-card"><span>Published events</span><strong><?= (int)$totals['Published'] ?></strong></div><div class="stat-card"><span>Bookings</span><strong><?= (int)$booking_count ?></strong></div></div>
<?php if($user['VerificationStatus']==='verified'): ?><p><a class="btn btn-orange" href="create-event.php">Create event</a></p><?php else: ?><p class="callout">Your account requires document approval before managing events. <a href="organiser-verification.php">View verification</a>.</p><?php endif; ?>
<section class="content-card"><h2>Upcoming events</h2><?php foreach($events as $event): ?><p><?= escape_html($event['EventName']) ?> ? <?= escape_html(format_datetime($event['EventDate'])) ?> <?= eventnest_status_badge($event['Status']) ?></p><?php endforeach; ?><?php if(!$events): ?><p>No upcoming events.</p><?php endif; ?></section>
<p class="callout callout-teal">Annual fee coverage: <?= auth_has_current_verification_fee($user)?'Active until '.escape_html(format_datetime($user['VerificationFeePaidUntil'])):'Not active. New bookings remain disabled until coverage is renewed.' ?></p></main></div>
<?php require __DIR__.'/shared-components/footer.php'; ?>
