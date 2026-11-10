<?php
declare(strict_types=1);
require_once __DIR__.'/shared-components/auth.php';
$account_mode=$account_mode ?? false;
$user=$account_mode?auth_require_organiser():auth_require_attendee();
$pdo=eventnest_db();
$message='';
$message_type='info';
$logo=null;
$categories=$pdo->query('SELECT CategoryName FROM CATEGORIES ORDER BY CategoryName')->fetchAll(PDO::FETCH_COLUMN);
if($_SERVER['REQUEST_METHOD']==='POST') {
    eventnest_require_post();
    try {
        if(($_POST['action'] ?? '')==='change_email') {
            // Email changes need the current password and a confirmation link sent to the NEW address.
            $new_email=strtolower(trim_input($_POST['new_email'] ?? ''));
            $password=is_string($_POST['current_password'] ?? null)?$_POST['current_password']:'';
            $stmt=$pdo->prepare('SELECT Email,PasswordHash FROM USERS WHERE UserID=?');
            $stmt->execute([$user['UserID']]);
            $row=$stmt->fetch();
            if(!$row || !password_verify($password,(string)$row['PasswordHash'])) {
                throw new DomainException('Your current password is incorrect.');
            }
            if(!filter_var($new_email,FILTER_VALIDATE_EMAIL) || strlen($new_email)>100) {
                throw new DomainException('Enter a valid new email address (up to 100 characters).');
            }
            if(strcasecmp($new_email,(string)$row['Email'])===0) {
                throw new DomainException('That is already your account email address.');
            }
            $stmt=$pdo->prepare('SELECT 1 FROM USERS WHERE LOWER(Email)=LOWER(?) LIMIT 1');
            $stmt->execute([$new_email]);
            if($stmt->fetchColumn()) {
                throw new DomainException('That email address cannot be used. Try a different one.');
            }
            $token=eventnest_create_email_change_token((int)$user['UserID'],(string)$row['Email'],$new_email);
            if($token===null) {
                throw new DomainException('Email changes are not configured on this server yet. Contact EventNest support.');
            }
            $link=eventnest_absolute_url('confirm-email.php').'?token='.rawurlencode($token);
            if(!eventnest_send_email_change_verification($new_email,$link)) {
                throw new DomainException('We could not send the confirmation email. Check the address or try again later.');
            }
            $message='We sent a confirmation link to '.$new_email.'. Open it within 60 minutes to finish changing your email.';
            $message_type='success';
            throw new LogicException('email-change-requested');
        }
        $first=trim_input($_POST['first_name'] ?? '');
        $last=trim_input($_POST['last_name'] ?? '');
        $phone=is_string($_POST['phone'] ?? null)?$_POST['phone']:'';
        $career=trim_input($_POST['career_field'] ?? '');
        if($phone!=='' && !eventnest_phone_valid($phone)) { throw new DomainException('Enter a valid 10-digit South African mobile number, without spaces or +27.'); }
        if($first==='' || $last==='' || mb_strlen($first)>50 || mb_strlen($last)>50 || strlen($phone)>15 || ($career!=='' && !in_array($career,$categories,true))) {
            throw new DomainException('Check your name, phone length and selected career field.');
        }
        // Email stays read-only until a complete email verification flow is available.
        $logo=$account_mode?eventnest_save_company_logo($_FILES['company_logo'] ?? null):null;
        $pdo->beginTransaction();
        if ($logo) {
            $stmt=$pdo->prepare("UPDATE USERS SET ProfileImagePath=? WHERE UserID=? AND Role='organiser'");
            $stmt->execute([$logo['relative'],$user['UserID']]);
        }
        $stmt=$pdo->prepare('UPDATE USERS SET FirstName=?,LastName=?,Phone=?,CareerField=? WHERE UserID=?');
        $stmt->execute([$first,$last,$phone ?: null,$career ?: null,$user['UserID']]);
        $stmt=$pdo->prepare('DELETE FROM USER_EVENT_RECOMMENDATIONS WHERE UserID=?');
        $stmt->execute([$user['UserID']]);
        $pdo->commit();
        $_SESSION['first_name']=$first;
        $user=auth_current_user();
        $message='Account details saved.';
        $message_type='success';
    }
    catch(Throwable $exception) {
        if($logo) { @unlink($logo['absolute']); }
        if($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if(!($exception instanceof LogicException && $exception->getMessage()==='email-change-requested')) {
            $message=$exception instanceof DomainException?$exception->getMessage():'Unable to save your details.';
        }
    }
}
$page_title=$account_mode?'Organiser account':'My profile';
require __DIR__.'/shared-components/header.php';
?>
<div class="dashboard-layout"><?php eventnest_sidebar($user['Role']); ?><main class="dashboard-main"><h1><?= escape_html($page_title) ?></h1>
<?php if($message): ?><p class="alert alert-<?= $message_type==="success"?"success":"info" ?>" role="status"><?= escape_html($message) ?></p><?php endif; ?>
<form class="content-card" method="post" enctype="multipart/form-data" data-validate="true"><?php eventnest_csrf_field(); ?><div class="form-grid">
<?php if($account_mode): ?><div class="field full"><?= eventnest_organiser_avatar($user,120) ?><label for="company_logo">Change company logo</label><input type="file" id="company_logo" name="company_logo" accept="image/png,image/jpeg,image/webp" data-logo-input><small>PNG, JPG or WebP; up to 2 MB, 16 to 4096 pixels per side.</small><div data-logo-preview></div></div><?php endif; ?>
<?php foreach(['first_name'=>['First name','FirstName',50],'last_name'=>['Last name','LastName',50],'phone'=>['Phone','Phone',15]] as $field=>[$label,$column,$max]): ?><div class="field"><label for="<?= $field ?>"><?= $label ?></label><input id="<?= $field ?>" name="<?= $field ?>" <?= $field==='phone'?'type="tel" maxlength="10" data-sa-mobile inputmode="numeric" autocomplete="tel-national" pattern="0[0-9]{9}"':'maxlength="'.$max.'"' ?> value="<?= escape_html(is_string($_POST[$field] ?? null)?$_POST[$field]:$user[$column]) ?>" <?= $field!=='phone'?'required':'' ?>></div><?php endforeach; ?>
<div class="field"><label for="email">Email address</label><input id="email" type="email" value="<?= escape_html($user['Email']) ?>" readonly><small>To change your email, use the &ldquo;Change email address&rdquo; form below.</small></div>
<div class="field"><label for="career_field">Career field</label><select id="career_field" name="career_field"><option value="">Not selected</option><?php foreach($categories as $category): ?><option <?= $category===$user['CareerField']?'selected':'' ?>><?= escape_html($category) ?></option><?php endforeach; ?></select></div>
<div class="field"><label>Account status</label><p><?= eventnest_status_badge($user['UserStatus']) ?></p></div></div>
<p>Changing your career field updates future recommendations when you opt in.</p><button class="btn btn-blue">Save <?= $account_mode?'account':'profile' ?></button></form>
<form class="content-card email-change-form" method="post"><?php eventnest_csrf_field(); ?><input type="hidden" name="action" value="change_email"><h2>Change email address</h2><p>We will send a confirmation link to the new address. Your email only changes after you open that link.</p><div class="form-grid"><div class="field"><label for="new_email">New email address</label><input id="new_email" name="new_email" type="email" maxlength="100" autocomplete="email" required></div><div class="field"><label for="email_current_password">Current password</label><input id="email_current_password" name="current_password" type="password" maxlength="72" autocomplete="current-password" required></div></div><button class="btn btn-blue email-confirm-action">Send confirmation link</button></form>
<?php if($account_mode): ?><section class="content-card"><h2>Verification and fee coverage</h2><p><?= eventnest_status_badge($user['VerificationStatus']) ?> · <?= auth_has_current_verification_fee($user)?'Paid until '.escape_html(format_datetime($user['VerificationFeePaidUntil'])):'No active paid coverage' ?></p><a class="btn btn-outline" href="organiser-verification.php">Verification and renewal</a></section>
<section class="content-card danger-zone"><h2>Delete account</h2><p>Upcoming events, held payments and unresolved refunds prevent deletion. Contact support first about any unresolved emailed report.</p><form method="post" action="delete-account.php" data-confirm="Delete and anonymise this account? This cannot be undone."><?php eventnest_csrf_field(); ?><label>Current password<input type="password" name="current_password" autocomplete="current-password" required></label><button class="btn btn-red">Delete account</button></form></section>
<?php else: ?><p><a href="settings.php">Password and recommendation settings</a></p><?php endif; ?></main></div>
<script src="scripts/company-logo.js" defer></script>
<script src="<?= escape_html(eventnest_url('scripts/validation.js')) ?>" defer></script>
<?php require __DIR__.'/shared-components/footer.php'; ?>
