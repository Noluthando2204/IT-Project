<?php
declare(strict_types=1);
require_once __DIR__.'/shared-components/auth.php';
$organiser=auth_require_verified_organiser();
eventnest_require_post();
$pdo=eventnest_db();
try {
    $booking_id=eventnest_id($_POST['booking_id'] ?? null);
    $email=strtolower(trim_input($_POST['blocked_user_email'] ?? ''));
    $reason=trim_input($_POST['block_reason'] ?? '');
    if($reason==='' || mb_strlen($reason)>255) {
        throw new DomainException('Provide a block reason of up to 255 characters.');
    }
    $pdo->beginTransaction();
    // The attendee must be connected to this organiser's event booking.
    $stmt=$pdo->prepare("SELECT u.UserID,u.Email,u.Suspension_count,u.Suspension_start_date,u.UserStatus FROM USERS u JOIN BOOKINGS b ON b.UserID=u.UserID JOIN EVENTS e ON e.EventID=b.EventID WHERE e.OrganiserID=? AND u.Role='attendee' AND (b.BookingID=? OR u.Email=?) LIMIT 1 FOR UPDATE");
    $stmt->execute([$organiser['UserID'],$booking_id,$email]);
    $attendee=$stmt->fetch();
    if(!$attendee || $attendee['UserStatus']==='deleted') {
        throw new DomainException('No eligible attendee relationship was found.');
    }
    $stmt=$pdo->prepare('SELECT BlockID FROM BLOCKS WHERE OrganiserID=? AND BlockedEmail=?');
    $stmt->execute([$organiser['UserID'],$attendee['Email']]);
    if($stmt->fetchColumn()) {
        throw new DomainException('You have already blocked this attendee.');
    }
    $stmt=$pdo->prepare('INSERT INTO BLOCKS (OrganiserID,BlockedEmail,Reason) VALUES (?,?,?)');
    $stmt->execute([$organiser['UserID'],$attendee['Email'],$reason]);
    $stmt=$pdo->prepare('SELECT COUNT(DISTINCT OrganiserID) FROM BLOCKS WHERE BlockedEmail=?');
    $stmt->execute([$attendee['Email']]);
    $distinct=(int)$stmt->fetchColumn();
    // Each lifetime suspension consumes three distinct organiser blocks; old blocks cannot trigger it again.
    $suspensions=(int)$attendee['Suspension_count'];
    $cycle=max(0,$distinct-3*$suspensions);
    $active=!empty($attendee['Suspension_start_date']) && strtotime($attendee['Suspension_start_date'].' +30 days')>time();
    $suspended=$cycle>=3 && !$active && $suspensions<2;
    if($suspended) {
        $stmt=$pdo->prepare("UPDATE USERS SET Blocks_count=0,Suspension_count=Suspension_count+1,Suspension_start_date=NOW(),UserStatus='suspended' WHERE UserID=?");
        $stmt->execute([$attendee['UserID']]);
    }
    else {
        $stmt=$pdo->prepare('UPDATE USERS SET Blocks_count=? WHERE UserID=?');
        $stmt->execute([$cycle,$attendee['UserID']]);
    }
    $pdo->commit();
    if($suspended) {
        eventnest_send_mail($attendee['Email'],'EventNest account suspension','Your account has been suspended after blocks from three different organisers. A first suspension lasts 30 days. Two suspensions prevent future access. Contact EventNest support for review.');
    }
    auth_set_flash('The attendee is now blocked from your events and reactions.','success');
}
catch(Throwable $exception) {
    if($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    auth_set_flash($exception instanceof DomainException?$exception->getMessage():'Unable to process the block.');
}
auth_redirect('manage-bookings.php');
