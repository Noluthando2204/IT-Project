<?php
declare(strict_types=1);
require_once __DIR__.'/../shared-components/auth.php';
$admin=auth_require_admin();
header('Cache-Control: no-store');
$pdo=eventnest_db();
$message=''; $first=''; $last=''; $email='';
if (($_SERVER['REQUEST_METHOD']??'')==='POST') {
    eventnest_require_post();
    eventnest_rate_limit('create-admin',5,900);
    $first=trim_input($_POST['first_name']??'');
    $last=trim_input($_POST['last_name']??'');
    $email=strtolower(trim_input($_POST['email']??''));
    $password=is_string($_POST['current_password']??null)?$_POST['current_password']:'';
    try {
        if (!password_verify($password,(string)$admin['PasswordHash'])) {
            throw new DomainException('Your current password is incorrect.');
        }
        if ($first==='' || $last==='' || mb_strlen($first)>50 || mb_strlen($last)>50 || strlen($email)>100 || !filter_var($email,FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('Enter a first name, last name and valid email address.');
        }
        $pdo->beginTransaction();
        $s=$pdo->prepare('SELECT * FROM USERS WHERE Email=? FOR UPDATE'); $s->execute([$email]);
        $existing=$s->fetch();
        if ($existing) {
            $s=$pdo->prepare("SELECT 1 FROM ACCOUNT_TOKENS WHERE UserID=? AND Purpose='admin_setup'");
            $s->execute([$existing['UserID']]);
            if ($existing['Role']!=='admin' || $existing['UserStatus']!=='active' || $existing['Is_email_verified'] || !$s->fetchColumn()) {
                throw new DomainException('An account already uses that email. Use a separate email for the new administrator.');
            }
        }
        // No usable password exists until the recipient completes the invitation.
        $hash=$existing?$existing['PasswordHash']:password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT);
        if (!$existing) {
            $pdo->prepare("INSERT INTO USERS(FirstName,LastName,Email,PasswordHash,Role,UserStatus,Is_email_verified) VALUES(?,?,?,?,'admin','active',0)")->execute([$first,$last,$email,$hash]);
        }
        $id=$existing?(int)$existing['UserID']:(int)$pdo->lastInsertId();
        $token=eventnest_account_token(['UserID'=>$id,'PasswordHash'=>$hash],'admin_setup',null,86400);
        $pdo->prepare('INSERT INTO SECURITY_AUDIT(ActorID,EventName,Outcome,Summary) VALUES(?,?,?,?)')->execute([$admin['UserID'],'admin:create','invited',json_encode(['new_admin_id'=>$id])]);
        $sent=eventnest_mail_notice($email,'Set up your EventNest administrator account','You have been invited to administer EventNest. Choose your password within 24 hours: '.eventnest_absolute_url('admin-setup.php').'?token='.$token);
        if (!$sent) throw new DomainException('The invitation could not be sent. No account changes were saved. Check the email configuration and try again.');
        $pdo->commit();
        auth_set_flash('Administrator invitation sent. The recipient has 24 hours to set up their account.','success');
        auth_redirect('admin-panel/manage-users.php');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $message=$e instanceof DomainException?$e->getMessage():'The invitation could not be created. Check whether that email is already registered and try again.';
        if (!$e instanceof DomainException) error_log('EventNest admin invitation failed: '.get_class($e));
    }
}
$page_title='Create administrator'; require __DIR__.'/../shared-components/header.php';
?>
<div class="dashboard-layout"><?php eventnest_sidebar('admin'); ?><main class="dashboard-main"><section class="content-card">
<h1>Create administrator</h1><p>The new administrator will receive an email invitation to choose their own password. Invitations expire after 24 hours. Enter the same email again to resend a pending invitation; the previous link will stop working.</p>
<?php if($message): ?><p class="alert alert-danger" role="alert"><?= escape_html($message) ?></p><?php endif; ?>
<form method="post"><?php eventnest_csrf_field(); ?>
<label>First name<input name="first_name" maxlength="50" autocomplete="off" required value="<?= escape_html($first) ?>"></label>
<label>Last name<input name="last_name" maxlength="50" autocomplete="off" required value="<?= escape_html($last) ?>"></label>
<label>New administrator email<input type="email" name="email" maxlength="100" autocomplete="off" required value="<?= escape_html($email) ?>"></label>
<label>Your current password<input type="password" name="current_password" autocomplete="current-password" required></label>
<p><button class="btn btn-orange">Send administrator invitation</button></p>
<a class="btn btn-outline" href="manage-users.php">Back to users</a></form></section></main></div>
<?php require __DIR__.'/../shared-components/footer.php'; ?>
