<?php
declare(strict_types=1);
require_once __DIR__ . '/shared-components/auth.php';
$user=auth_require_verified_organiser();
$pdo=eventnest_db();
$stmt=$pdo->prepare('SELECT e.*, (SELECT COUNT(*) FROM BOOKINGS b WHERE b.EventID=e.EventID) AS BookingCount FROM EVENTS e WHERE e.OrganiserID=? ORDER BY e.EventDate DESC');
$stmt->execute([$user['UserID']]);
$events=$stmt->fetchAll();
$page_title='Manage events';
require __DIR__.'/shared-components/header.php';
?>
<div class="dashboard-layout"><?php eventnest_sidebar('organiser'); ?><main class="dashboard-main"><h1>Manage events</h1><p><a class="btn btn-orange" href="create-event.php">Create event</a></p>
<div class="table-wrap"><table><thead><tr><th>Event</th><th>Date</th><th>Status</th><th>Bookings</th><th>Actions</th></tr></thead><tbody>
<?php foreach($events as $event): ?><tr><td><?= escape_html($event['EventName']) ?></td><td><?= escape_html(format_datetime($event['EventDate'])) ?></td><td><?= eventnest_status_badge($event['Status']) ?></td><td><?= (int)$event['BookingCount'] ?></td><td><div class="inline-actions">
<?php if(in_array($event['Status'],['draft','published'],true)): ?><a class="btn btn-blue" href="edit-event.php?event_id=<?= (int)$event['EventID'] ?>">Edit</a><a class="btn btn-red" href="cancel-event.php?event_id=<?= (int)$event['EventID'] ?>">Cancel event</a><?php endif; ?>
<?php if($event['Status']==='published' && strtotime($event['EventDate'])<=time()): ?><a class="btn btn-green" href="event-confirm.php?event_id=<?= (int)$event['EventID'] ?>">Confirm completion</a><?php endif; ?>
<a class="btn btn-outline" href="manage-bookings.php?event_id=<?= (int)$event['EventID'] ?>">Bookings</a><a class="btn btn-outline" href="event-detail.php?event_id=<?= (int)$event['EventID'] ?>">View event</a></div></td></tr><?php endforeach; ?>
<?php if(!$events): ?><tr><td colspan="5">No events yet. Create your first draft above.</td></tr><?php endif; ?></tbody></table></div></main></div>
<?php require __DIR__.'/shared-components/footer.php'; ?>
