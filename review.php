<?php
declare(strict_types=1);
require_once __DIR__ . '/shared-components/auth.php';
$attendee=auth_require_attendee(); $pdo=auth_db();
function review_h(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$error=''; $success=''; $bookingId=(int)($_GET['booking_id']??$_POST['booking_id']??0);
if($_SERVER['REQUEST_METHOD']==='POST') {
    if(!eventnest_verify_csrf_token(isset($_POST['csrf_token'])?(string)$_POST['csrf_token']:null)) { http_response_code(400); $error='Your session token expired. Refresh and try again.'; }
    else {
        $bookingId=(int)($_POST['booking_id']??0); $rating=(int)($_POST['rating']??0); $comment=trim((string)($_POST['comment']??'')); $tags=trim((string)($_POST['tags']??'')); $wantsContact=isset($_POST['wants_contact']) && (string)$_POST['wants_contact']==='1'; $contactEmail=trim((string)($_POST['contact_email']??''));
        if($bookingId<=0) $error='Choose a valid booking.';
        elseif($rating<1 || $rating>5) $error='Choose a rating from 1 to 5.';
        elseif($rating>=4 && ($tags==='' || mb_strlen($tags)>255)) $error='For a rating of 4 or 5, provide tags (maximum 255 characters).';
        elseif($rating>=4 && $comment!=='') $error='For ratings of 4 or 5, use tags rather than a written comment.';
        elseif($rating<=3 && ($comment==='' || mb_strlen($comment)>5000)) $error='For a rating of 1 to 3, provide a comment of up to 5,000 characters.';
        elseif($rating<=3 && $tags!=='') $error='For ratings of 1 to 3, use a comment rather than tags.';
        elseif($wantsContact && !filter_var($contactEmail,FILTER_VALIDATE_EMAIL)) $error='Enter a valid contact email if you want the organiser to contact you.';
        elseif(!$wantsContact) $contactEmail='';
        else {
            try {
                $check=$pdo->prepare("SELECT b.BookingID,b.EventID FROM BOOKINGS b JOIN EVENTS e ON e.EventID=b.EventID WHERE b.BookingID=:booking_id AND b.UserID=:user_id AND b.Status='paid' AND e.Status='completed' LIMIT 1");
                $check->execute([':booking_id'=>$bookingId,':user_id'=>(int)$attendee['UserID']]); $booking=$check->fetch(PDO::FETCH_ASSOC);
                if(!$booking) $error='You can review only a paid booking for an event marked completed.';
                else {
                    $existing=$pdo->prepare('SELECT ReviewID FROM REVIEWS WHERE BookingID=:booking_id LIMIT 1'); $existing->execute([':booking_id'=>$bookingId]);
                    if($existing->fetchColumn()) $error='You have already reviewed this booking.';
                    else {
                        $stmt=$pdo->prepare('INSERT INTO REVIEWS (BookingID,UserID,EventID,Rating,Comment,Tags,WantsContact,ContactEmail) VALUES (:booking_id,:user_id,:event_id,:rating,:comment,:tags,:wants_contact,:contact_email)');
                        $stmt->execute([':booking_id'=>$bookingId,':user_id'=>(int)$attendee['UserID'],':event_id'=>(int)$booking['EventID'],':rating'=>$rating,':comment'=>$rating<=3?$comment:null,':tags'=>$rating>=4?$tags:null,':wants_contact'=>$wantsContact?1:0,':contact_email'=>$wantsContact?$contactEmail:null]);
                        $success='Thank you. Your review has been submitted.';
                    }
                }
            } catch(PDOException $e) { if($e->getCode()==='23000') $error='A review already exists for this booking.'; else { error_log('EventNest review submission failed: '.$e->getMessage()); $error='Your review could not be saved. Please try again.'; } }
        }
    }
}
