<?php
declare(strict_types=1);
require_once __DIR__.'/shared-components/auth.php';
$user=auth_require_attendee();
eventnest_require_post();
$pdo=eventnest_db();
try {
    $rating=eventnest_id($_POST['rating'] ?? null);
    $booking_id=eventnest_id($_POST['booking_id'] ?? null);
    $comment=null;
    $tags=null;
    if($rating<1 || $rating>5) {
        throw new DomainException('Choose a rating from 1 to 5.');
    }
    if($rating>=4) {
        $selected=$_POST['compliment_tags'] ?? [];
        if(!is_array($selected) || !$selected || array_diff($selected,['Helpful','Professional','Well organised','Inspiring','Good networking'])) {
            throw new DomainException('Choose at least one approved compliment.');
        }
        $tags=implode(', ',array_unique($selected));
    }
    else {
        $comment=trim_input($_POST['complaint_text'] ?? '');
        if($comment==='' || mb_strlen($comment)>5000) {
            throw new DomainException('Explain what could improve (up to 5,000 characters).');
        }
    }
    $contact=($_POST['wants_contact'] ?? '')==='1';
    $email=$contact?trim_input($_POST['contact_email'] ?? ''):null;
    if($contact && (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>100)) {
        throw new DomainException('Enter a valid contact email.');
    }
    $pdo->beginTransaction();
    // Lock the attendee's booking so simultaneous submissions cannot create two reviews.
    $stmt=$pdo->prepare("SELECT b.EventID FROM BOOKINGS b JOIN EVENTS e ON e.EventID=b.EventID WHERE b.BookingID=? AND b.UserID=? AND b.Status='paid' AND e.Status='completed' FOR UPDATE");
    $stmt->execute([$booking_id,$user['UserID']]);
    $event_id=$stmt->fetchColumn();
    if(!$event_id) {
        throw new DomainException('This booking is not eligible for a review.');
    }
    $stmt=$pdo->prepare('SELECT ReviewID FROM REVIEWS WHERE BookingID=?');
    $stmt->execute([$booking_id]);
    if($stmt->fetchColumn()) {
        throw new DomainException('A review already exists for this booking.');
    }
    $stmt=$pdo->prepare('INSERT INTO REVIEWS (BookingID,UserID,EventID,Rating,Comment,Tags,WantsContact,ContactEmail) VALUES (?,?,?,?,?,?,?,?)');
    $stmt->execute([$booking_id,$user['UserID'],$event_id,$rating,$comment,$tags,$contact?1:0,$email]);
    $pdo->commit();
    auth_set_flash('Your private review was saved.','success');
}
catch(Throwable $exception) {
    if($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    auth_set_flash($exception instanceof DomainException?$exception->getMessage():'Unable to save this review.');
}
auth_redirect('my-bookings.php');
