<?php
declare(strict_types=1);
require_once __DIR__ . '/shared-components/auth.php';
$user=auth_require_attendee();
$pdo=eventnest_db();
eventnest_expire_pending($pdo,(int)$user['UserID']);
$stmt=$pdo->prepare("SELECT b.*,e.EventName,e.EventDate,t.TicketType FROM BOOKINGS b JOIN EVENTS e ON e.EventID=b.EventID JOIN TICKETS t ON t.TicketID=b.TicketID AND t.EventID=b.EventID WHERE b.UserID=? AND e.EventDate>NOW() AND b.Status IN ('paid','pending') ORDER BY e.EventDate LIMIT 10");
$stmt->execute([$user['UserID']]);
$bookings=$stmt->fetchAll();
$page_title='Attendee dashboard';
require __DIR__.'/shared-components/header.php';
?>
<div class="dashboard-layout"><?php eventnest_sidebar('attendee'); ?><main class="dashboard-main"><h1>Dashboard overview</h1><p>Hi, <?= escape_html($user['FirstName']) ?>.</p><div class="stat-grid"><div class="stat-card"><span>Upcoming bookings shown</span><strong><?= count($bookings) ?></strong></div><div class="stat-card"><span>Career field</span><strong><?= escape_html($user['CareerField'] ?: 'Not selected') ?></strong></div></div>
<h2>Upcoming bookings</h2><div class="table-wrap"><table><thead><tr><th>Event</th><th>Date</th><th>Ticket</th><th>Status / reference</th></tr></thead><tbody><?php foreach($bookings as $booking): ?><tr><td><a href="my-bookings.php?booking_id=<?= (int)$booking['BookingID'] ?>"><?= escape_html($booking['EventName']) ?></a></td><td><?= escape_html(format_datetime($booking['EventDate'])) ?></td><td><?= escape_html($booking['TicketType']) ?></td><td><?= eventnest_status_badge($booking['Status']) ?><br><?= escape_html($booking['BookingReference']) ?></td></tr><?php endforeach; ?><?php if(!$bookings): ?><tr><td colspan="4">No upcoming bookings. <a href="events.php">Browse events</a>.</td></tr><?php endif; ?></tbody></table></div>
<p class="callout"><a class="btn btn-blue" href="index.php">Discover career events</a> <a href="settings.php">Manage recommendation preferences</a></p></main></div>
<?php require __DIR__.'/shared-components/footer.php'; ?>
