<?php
declare(strict_types=1);
require_once __DIR__.'/shared-components/auth.php';$user=auth_require_attendee();$pdo=eventnest_db();
eventnest_expire_pending($pdo,(int)$user['UserID']);
if($_SERVER['REQUEST_METHOD']==='POST'){
    eventnest_require_post();$booking_id=eventnest_id($_POST['booking_id'] ?? null);
    try{
        $pdo->beginTransaction();
        // Re-read ownership and time under a lock before changing stock or booking state.
        $stmt=$pdo->prepare('SELECT b.*,e.EventDate,e.Status AS EventStatus FROM BOOKINGS b JOIN EVENTS e ON e.EventID=b.EventID WHERE b.BookingID=? AND b.UserID=? FOR UPDATE');$stmt->execute([$booking_id,$user['UserID']]);$booking=$stmt->fetch();
        if(!$booking || !in_array($booking['Status'],['pending','paid'],true) || $booking['EventStatus']!=='published' || strtotime($booking['EventDate'])-time()<=86400){throw new DomainException('Cancellation requires an active booking more than 24 hours before the event.');}
        $stmt=$pdo->prepare("SELECT PaymentID FROM PAYMENTS WHERE BookingID=? AND PaymentStatus='success' FOR UPDATE");$stmt->execute([$booking_id]);$paid=(bool)$stmt->fetchColumn();
        $stmt=$pdo->prepare("UPDATE BOOKINGS SET Status='canceled' WHERE BookingID=? AND Status IN ('pending','paid')");$stmt->execute([$booking_id]);
        if($stmt->rowCount()===1){$stmt=$pdo->prepare('UPDATE TICKETS SET QuantityAvailable=LEAST(Capacity,QuantityAvailable+?) WHERE TicketID=?');$stmt->execute([$booking['Quantity'],$booking['TicketID']]);eventnest_waitlist_release($pdo,(int)$booking['TicketID']);}
        // A local cancellation cannot prove a provider refund. Retain payment/escrow history.
        $pdo->commit();auth_set_flash($paid?'Booking canceled. Your payment remains recorded; contact support for verified refund processing.':'Booking canceled and tickets restored.','success');
    }catch(Throwable $exception){if($pdo->inTransaction()){$pdo->rollBack();}auth_set_flash($exception instanceof DomainException?$exception->getMessage():'The booking could not be canceled safely.');}
    auth_redirect('my-bookings.php');
}
$stmt=$pdo->prepare('SELECT b.*,e.EventName,e.EventDate,e.Status AS EventStatus,t.TicketType,r.ReviewID FROM BOOKINGS b JOIN EVENTS e ON e.EventID=b.EventID JOIN TICKETS t ON t.TicketID=b.TicketID AND t.EventID=b.EventID LEFT JOIN REVIEWS r ON r.BookingID=b.BookingID WHERE b.UserID=? ORDER BY e.EventDate DESC,b.BookingID DESC');$stmt->execute([$user['UserID']]);$bookings=$stmt->fetchAll();
$page_title='My bookings';require __DIR__.'/shared-components/header.php'; ?>
<div class="dashboard-layout"><?php eventnest_sidebar('attendee'); ?><main class="dashboard-main"><h1>My bookings and ticket history</h1><p>Pending unpaid reservations expire after four minutes. Cancellation is available more than 24 hours before the event.</p>
<?php foreach(['Current bookings','Past and canceled bookings'] as $section): ?><h2><?= $section ?></h2><div class="table-wrap"><table><thead><tr><th>Event / reference</th><th>Date</th><th>Ticket</th><th>Total / status</th><th>Actions</th></tr></thead><tbody>
<?php $count=0;foreach($bookings as $booking):$current=strtotime($booking['EventDate'])>time() && in_array($booking['Status'],['pending','paid'],true) && $booking['EventStatus']==='published';if(($section==='Current bookings')!==$current){continue;}$count++; ?>
<tr><td><?= escape_html($booking['EventName']) ?><br><?= escape_html($booking['BookingReference']) ?></td><td><?= escape_html(format_datetime($booking['EventDate'])) ?></td><td><?= escape_html($booking['TicketType']) ?> ? <?= (int)$booking['Quantity'] ?></td><td><?= escape_html(format_money($booking['TotalAmount'])) ?><br><?= eventnest_status_badge($booking['Status']) ?></td><td><div class="inline-actions"><a class="btn btn-outline" href="event-detail.php?event_id=<?= (int)$booking['EventID'] ?>">View event</a>
<?php if($current && $booking['Status']==='pending'): ?><a class="btn btn-orange" href="payment.php?booking_id=<?= (int)$booking['BookingID'] ?>">Retry payment</a><?php endif; ?>
<?php if($booking['EventStatus']==='completed' && $booking['Status']==='paid' && !$booking['ReviewID']): ?><a class="btn btn-blue" href="leave-review.php?booking_id=<?= (int)$booking['BookingID'] ?>">Leave private review</a><?php endif; ?>
<a class="btn btn-outline" href="report-event.php?event_id=<?= (int)$booking['EventID'] ?>&amp;booking_id=<?= (int)$booking['BookingID'] ?>">Report event</a>
<?php if($current && strtotime($booking['EventDate'])-time()>86400): ?><form method="post" data-confirm="Cancel this booking? Paid refunds require separate verification."><?php eventnest_csrf_field(); ?><input type="hidden" name="booking_id" value="<?= (int)$booking['BookingID'] ?>"><button class="btn btn-red">Cancel booking</button></form><?php endif; ?></div></td></tr><?php endforeach; ?>
<?php if(!$count): ?><tr><td colspan="5">No bookings in this section. <a href="events.php">Browse events</a>.</td></tr><?php endif; ?></tbody></table></div><?php endforeach; ?></main></div>
<?php require __DIR__.'/shared-components/footer.php'; ?>
