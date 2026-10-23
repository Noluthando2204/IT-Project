<?php
declare(strict_types=1);
require_once __DIR__.'/shared-components/auth.php';
$user=auth_require_attendee();
$pdo=eventnest_db();
$message='';
$event_id=eventnest_id($_POST['event_id'] ?? $_GET['event_id'] ?? null);
$booking_id=eventnest_id($_POST['booking_id'] ?? $_GET['booking_id'] ?? null);
$stmt=$pdo->prepare('SELECT EventID,EventName,Status FROM EVENTS WHERE EventID=?');
$stmt->execute([$event_id]);
$event=$stmt->fetch();
$booking=null;
if($booking_id) {
    $stmt=$pdo->prepare('SELECT BookingReference FROM BOOKINGS WHERE BookingID=? AND UserID=? AND EventID=?');
    $stmt->execute([$booking_id,$user['UserID'],$event_id]);
    $booking=$stmt->fetch();
    if(!$booking) {
        http_response_code(404);
        exit('Booking not found for your account.');
    }
}
if(!$event || ($event['Status']==='draft' && !$booking)) {
    http_response_code(404);
    exit('Event not found.');
}
$issues=['Event canceled','Event did not take place','Payment or refund','Suspected fraud','Other'];
if($_SERVER['REQUEST_METHOD']==='POST') {
    eventnest_require_post();
    $issue=trim_input($_POST['issue_category'] ?? '');
    $explanation=trim_input($_POST['explanation'] ?? '');
    if(!in_array($issue,$issues,true) || mb_strlen($explanation)<20 || mb_strlen($explanation)>5000) {
        $message='Choose an issue and explain it in 20?5,000 characters.';
    }
    else {
        $body='Event: '.$event['EventName'].' (#'.$event_id.')'."\nBooking: ".($booking['BookingReference'] ?? 'Not supplied')."\nIssue: ".$issue."\nAttendee: ".$user['Email']."\n\n".$explanation;
        $sent=eventnest_send_mail((string)getenv('EVENTNEST_SUPPORT_EMAIL'),'EventNest event report',$body,$user['Email']);
        $message=$sent?'Your report was emailed to EventNest support for review.':'The report could not be sent. Please try again later; no report has been recorded.';
    }
}
$page_title='Report an event';
require __DIR__.'/shared-components/header.php';
?>
<main class="policy content-card"><h1>Report <?= escape_html($event['EventName']) ?></h1><p>Reports are emailed to support. Submission does not prove fraud or guarantee an immediate refund. An administrator must investigate.</p><?php if($message): ?><p class="alert alert-info" role="status"><?= escape_html($message) ?></p><?php endif; ?>
<form method="post"><?php eventnest_csrf_field(); ?><input type="hidden" name="event_id" value="<?= $event_id ?>"><input type="hidden" name="booking_id" value="<?= $booking_id ?>"><div class="field"><label for="issue_category">Issue category</label><select id="issue_category" name="issue_category" required><?php foreach($issues as $issue): ?><option><?= escape_html($issue) ?></option><?php endforeach; ?></select></div><div class="field"><label for="explanation">Explain your concern</label><textarea id="explanation" name="explanation" minlength="20" maxlength="5000" required><?= escape_html(trim_input($_POST['explanation'] ?? '')) ?></textarea></div><p>Contact email: <?= escape_html($user['Email']) ?></p><button class="btn btn-orange">Send report</button><a class="btn btn-outline" href="my-bookings.php">My bookings</a></form></main>
<?php require __DIR__.'/shared-components/footer.php'; ?>
