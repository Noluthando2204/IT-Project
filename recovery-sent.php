<?php
declare(strict_types=1);
require_once __DIR__.'/shared-components/auth.php';
$user=auth_require_login();
$pdo=eventnest_db();
$error='';
if(empty($_SESSION['recovery_sent_at'])) auth_redirect('settings.php');
if($_SERVER['REQUEST_METHOD']==='POST') {
 eventnest_require_post();
 try {
  if(time()-(int)$_SESSION['recovery_sent_at']<60) throw new DomainException('Please wait until the timer finishes.');
  $pdo->beginTransaction();eventnest_send_account_recovery($user);$pdo->commit();
  $_SESSION['recovery_sent_at']=time();auth_redirect('recovery-sent.php');
 } catch(Throwable $e) {
  if($pdo->inTransaction())$pdo->rollBack();
  $error=$e instanceof DomainException?$e->getMessage():'We could not send your recovery email. Please try again later.';
 }
}
$remaining=max(0,60-(time()-(int)$_SESSION['recovery_sent_at']));
$page_title='Check your email';
require __DIR__.'/shared-components/header.php';
?>
<main class="auth-container content-card recovery-confirmation">
<div class="recovery-envelope" aria-hidden="true">&#9993;</div><h1>Check your email</h1>
<p>Recovery instructions were sent to <strong><?= escape_html(eventnest_mask_email($user['Email'])) ?></strong>.</p>
<p>Check your inbox and spam folder. The recovery link expires in ten minutes.</p>
<?php if($error): ?><p class="alert alert-error" role="alert"><?= escape_html($error) ?></p><?php endif; ?>
<form method="post"><?php eventnest_csrf_field(); ?><div class="form-actions">
<button class="btn btn-blue" type="submit" data-resend-seconds="<?= $remaining ?>" <?= $remaining?'disabled':'' ?>><?= $remaining?'Resend link in '.$remaining.'s':'Resend link' ?></button>
<a class="btn btn-outline" href="settings.php">Go back</a></div></form>
</main>
<script src="<?= escape_html(eventnest_url('scripts/recovery-countdown.js')) ?>" defer></script>
<?php require __DIR__.'/shared-components/footer.php'; ?>
