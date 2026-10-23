<?php
declare(strict_types=1);
require_once __DIR__.'/shared-components/auth.php';
$user=auth_require_verified_organiser();
$pdo=eventnest_db();
$event_id=eventnest_id($_GET['event_id'] ?? null);
$status=trim_input($_GET['status'] ?? '');
if(!in_array($status,['pending','paid','failed','canceled','refunded'],true)) {
    $status='';
}
$sql='SELECT b.*,e.EventName,e.EventDate,t.TicketType,u.Email,u.FirstName,u.LastName FROM BOOKINGS b JOIN EVENTS e ON e.EventID=b.EventID JOIN TICKETS t ON t.TicketID=b.TicketID AND t.EventID=b.EventID JOIN USERS u ON u.UserID=b.UserID WHERE e.OrganiserID=?';
$params=[$user['UserID']];
if($event_id) {
    $sql.=' AND e.EventID=?';
    $params[]=$event_id;
}
if($status!=='') {
    $sql.=' AND b.Status=?';
    $params[]=$status;
}
$stmt=$pdo->prepare($sql.' ORDER BY b.BookingDate DESC LIMIT 200');
$stmt->execute($params);
$bookings=$stmt->fetchAll();
$page_title='Manage bookings';
require __DIR__.'/shared-components/header.php';
?>
<div class="dashboard-layout"><?php eventnest_sidebar('organiser'); ?><main class="dashboard-main"><h1>Manage bookings</h1><p>Booking context for your events. Event completion records organiser confirmation; it is not proof of attendance.</p>
<form method="get"><input type="hidden" name="event_id" value="<?= $event_id ?>"><label>Booking status <select name="status"><option value="">All</option><?php foreach(['pending','paid','failed','canceled','refunded'] as $value): ?><option <?= $value===$status?'selected':'' ?>><?= $value ?></option><?php endforeach; ?></select></label><button class="btn btn-orange">Filter</button></form>
<div class="table-wrap"><table><thead><tr><th>Reference / event</th><th>Attendee</th><th>Ticket / quantity</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach($bookings as $booking): ?><tr><td><?= escape_html($booking['BookingReference']) ?><br><a href="event-detail.php?event_id=<?= (int)$booking['EventID'] ?>"><?= escape_html($booking['EventName']) ?></a></td><td><?= escape_html($booking['FirstName'].' '.$booking['LastName']) ?><br><?= escape_html($booking['Email']) ?></td><td><?= escape_html($booking['TicketType']) ?> ? <?= (int)$booking['Quantity'] ?></td><td><?= eventnest_status_badge($booking['Status']) ?></td><td><form method="post" action="block-user.php" data-confirm="Block this attendee from your events?"><?php eventnest_csrf_field(); ?><input type="hidden" name="booking_id" value="<?= (int)$booking['BookingID'] ?>"><label>Reason<input name="block_reason" maxlength="255" required></label><button class="btn btn-red">Block attendee</button></form></td></tr><?php endforeach; ?><?php if(!$bookings): ?><tr><td colspan="5">No matching bookings.</td></tr><?php endif; ?></tbody></table></div><p><a href="manage-reviews.php">Private reviews</a></p></main></div>
<?php require __DIR__.'/shared-components/footer.php'; ?>
