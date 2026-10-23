<?php
declare(strict_types=1);
require_once __DIR__.'/shared-components/functions.php';
eventnest_ensure_session();
$message='';
$sent=false;
if($_SERVER['REQUEST_METHOD']==='POST') {
    eventnest_require_post();
    $name=trim_input($_POST['name'] ?? '');
    $email=trim_input($_POST['email'] ?? '');
    $subject=trim_input($_POST['subject'] ?? '');
    $body=trim_input($_POST['message'] ?? '');
    if($name==='' || mb_strlen($name)>100 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>100 || $subject==='' || mb_strlen($subject)>150 || preg_match('/[\r\n]/',$subject) || mb_strlen($body)<10 || mb_strlen($body)>5000) {
        $message='Complete all fields with a valid email and a message of 10?5,000 characters.';
    }
    else {
        // Recipient comes from server configuration, never a submitted form field.
        $sent=eventnest_send_mail((string)getenv('EVENTNEST_SUPPORT_EMAIL'),'EventNest contact: '.$subject,"From: ".$name."\nEmail: ".$email."\n\n".$body,$email);
        $message=$sent?'Your message was sent to EventNest support.':'Your message could not be sent. Please try again later; support email may be unavailable.';
    }
}
$page_title='Contact EventNest';
require __DIR__.'/shared-components/header.php';
?>
<main class="policy"><h1>Contact EventNest</h1><p>Questions about your account, bookings or organiser verification? Send our support team a message.</p><?php if($message): ?><p role="status" class="alert <?= $sent?'alert-success':'alert-danger' ?>"><?= escape_html($message) ?></p><?php endif; ?>
<div class="support-layout"><form class="content-card" method="post"><?php eventnest_csrf_field(); ?><?php foreach(['name'=>['Your name','text',100],'email'=>['Email address','email',100],'subject'=>['Subject','text',150]] as $field=>[$label,$type,$max]): ?><div class="field"><label for="<?= $field ?>"><?= $label ?></label><input id="<?= $field ?>" type="<?= $type ?>" name="<?= $field ?>" maxlength="<?= $max ?>" value="<?= escape_html($sent?'':trim_input($_POST[$field] ?? '')) ?>" required></div><?php endforeach; ?><div class="field"><label for="message">Message</label><textarea id="message" name="message" minlength="10" maxlength="5000" required><?= escape_html($sent?'':trim_input($_POST['message'] ?? '')) ?></textarea></div><button class="btn btn-orange">Send message</button></form><aside class="callout callout-teal"><h2>Need to report an event?</h2><p>Choose the event, then select Report event so support receives the correct context.</p><a class="btn btn-blue" href="events.php">Find the event</a><p>We review emailed concerns before refund or escrow decisions.</p></aside></div><p>For an event-specific concern, open that event and choose <strong>Report event</strong>.</p></main>
<?php require __DIR__.'/shared-components/footer.php'; ?>
