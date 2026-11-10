<?php
declare(strict_types=1);
require_once __DIR__.'/shared-components/auth.php';
header('Cache-Control: no-store'); header('Referrer-Policy: no-referrer');
$token=is_string($_POST['token']??$_GET['token']??null)?trim($_POST['token']??$_GET['token']):'';
$invitation=eventnest_account_token_read($token,'admin_setup');
$message=''; $done=false;
if (($_SERVER['REQUEST_METHOD']??'')==='POST') {
    eventnest_require_post(); eventnest_rate_limit('admin-setup',20,900);
    $password=is_string($_POST['password']??null)?$_POST['password']:'';
    $confirm=is_string($_POST['confirm_password']??null)?$_POST['confirm_password']:'';
    if (!$invitation) $message='This invitation is invalid or expired.';
    elseif (!eventnest_password_valid($password)) $message=eventnest_password_error();
    elseif ($password!==$confirm) $message='The passwords do not match.';
    else {
        $pdo=eventnest_db(); $pdo->beginTransaction();
        try {
            $s=$pdo->prepare("DELETE FROM ACCOUNT_TOKENS WHERE TokenHash=? AND Purpose='admin_setup' AND ExpiresAt>NOW()");
            $s->execute([hash('sha256',$token)]);
            if ($s->rowCount()!==1) throw new RuntimeException('Invitation consumed.');
            $s=$pdo->prepare("UPDATE USERS SET PasswordHash=?,Is_email_verified=1 WHERE UserID=? AND Role='admin' AND UserStatus='active' AND PasswordHash=?");
            $s->execute([password_hash($password,PASSWORD_DEFAULT),$invitation['UserID'],$invitation['PasswordHash']]);
            if ($s->rowCount()!==1) throw new RuntimeException('Account changed.');
            $pdo->prepare('DELETE FROM ACCOUNT_TOKENS WHERE UserID=?')->execute([$invitation['UserID']]);
            $pdo->prepare('INSERT INTO SECURITY_AUDIT(ActorID,EventName,Outcome) VALUES(?,?,?)')->execute([$invitation['UserID'],'admin:setup','completed']);
            $pdo->commit(); $done=true;
        } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); $message='Setup could not be completed. The invitation may have expired or already been used.'; }
    }
}
$page_title='Set up administrator account'; require __DIR__.'/shared-components/header.php';
?>
<main class="auth-container content-card"><h1>Set up administrator account</h1>
<?php if($done): ?><p>Your administrator account is ready.</p><a class="btn btn-orange" href="<?= escape_html(eventnest_url('login.php?role=admin')) ?>">Administrator login</a>
<?php elseif(!$invitation): ?><p>This invitation is invalid or expired. Ask the administrator who invited you for help.</p>
<?php else: ?>
<?php if($message): ?><p class="alert alert-danger" role="alert"><?= escape_html($message) ?></p><?php endif; ?>
<p><?= escape_html(eventnest_password_error()) ?></p><form method="post"><?php eventnest_csrf_field(); ?><input type="hidden" name="token" value="<?= escape_html($token) ?>">
<label>New password<input type="password" name="password" autocomplete="new-password" required></label>
<label>Confirm password<input type="password" name="confirm_password" autocomplete="new-password" required></label>
<p><button class="btn btn-orange">Activate administrator account</button></p></form>
<?php endif; ?></main><?php require __DIR__.'/shared-components/footer.php'; ?>
