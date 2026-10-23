<?php
declare(strict_types=1);
require_once __DIR__.'/shared-components/auth.php';
$user=auth_require_login();
eventnest_require_post();
$pdo=eventnest_db();
$return=$user['Role']==='organiser'?'organiser-account.php':'settings.php';
try {
    if($user['Role']==='admin') {
        throw new DomainException('Administrator account removal requires separate review.');
    }
    $pdo->beginTransaction();
    $stmt=$pdo->prepare('SELECT * FROM USERS WHERE UserID=? FOR UPDATE');
    $stmt->execute([$user['UserID']]);
    $current=$stmt->fetch();
    $password=is_string($_POST['current_password'] ?? null)?$_POST['current_password']:'';
    if(!$current || !password_verify($password,(string)$current['PasswordHash'])) {
        throw new DomainException('Your current password could not be verified.');
    }
    $stmt=$pdo->prepare("SELECT COUNT(*) FROM BOOKINGS b JOIN EVENTS e ON e.EventID=b.EventID WHERE b.UserID=? AND b.Status IN ('pending','paid') AND e.EventDate>NOW() AND e.Status<>'canceled'");
    $stmt->execute([$user['UserID']]);
    if($stmt->fetchColumn()) {
        throw new DomainException('Resolve upcoming bookings before deleting your account.');
    }
    $stmt=$pdo->prepare("SELECT COUNT(*) FROM PAYMENTS p JOIN BOOKINGS b ON b.BookingID=p.BookingID JOIN EVENTS e ON e.EventID=b.EventID WHERE (b.UserID=? OR e.OrganiserID=?) AND p.PaymentStatus='success' AND (p.Escrow_status='held' OR b.Status='canceled' OR e.Status='canceled')");
    $stmt->execute([$user['UserID'],$user['UserID']]);
    if($stmt->fetchColumn()) {
        throw new DomainException('Held payments or unresolved refunds prevent deletion. Contact support.');
    }
    $stmt=$pdo->prepare("SELECT COUNT(*) FROM EVENTS WHERE OrganiserID=? AND Status='published' AND EventDate>NOW()");
    $stmt->execute([$user['UserID']]);
    if($stmt->fetchColumn()) {
        throw new DomainException('Resolve upcoming published events before deleting the organiser account.');
    }
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM BLOCKS WHERE BlockedEmail=? OR OrganiserID=?');
    $stmt->execute([$current['Email'],$user['UserID']]);
    if($stmt->fetchColumn()) {
        throw new DomainException('Moderation history needs administrator review before anonymisation.');
    }
    if ($current['Role'] === 'organiser' && $current['VerificationFeePaymentStatus'] === 'pending') {
        throw new DomainException('Resolve the pending verification-fee payment before deleting this account.');
    }
    // Keep foreign-key relationships and financial history, but remove account identity and login access.
    $stmt=$pdo->prepare("UPDATE USERS SET FirstName=NULL,LastName=NULL,Email=?,PasswordHash=NULL,Phone=NULL,CareerField=NULL,UserStatus='deleted',RecommendationOptIn=0,Is_email_verified=0,Verification_docs_path=NULL WHERE UserID=?");
    $stmt->execute(['deleted-'.$user['UserID'].'-'.bin2hex(random_bytes(8)).'@deleted.invalid',$user['UserID']]);
    $stmt=$pdo->prepare('DELETE FROM USER_EVENT_RECOMMENDATIONS WHERE UserID=?');
    $stmt->execute([$user['UserID']]);
    $pdo->commit();
    $root=realpath(eventnest_verification_directory());
    $document=!empty($current['Verification_docs_path'])?realpath($current['Verification_docs_path']):false;
    if($root && $document && str_starts_with($document,$root.DIRECTORY_SEPARATOR) && is_file($document)) {
        @unlink($document);
    }
    auth_destroy_session();
    auth_redirect('index.php?deleted=1');
}
catch(Throwable $exception) {
    if($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    auth_set_flash($exception instanceof DomainException?$exception->getMessage():'Unable to delete the account safely.');
    auth_redirect($return);
}
