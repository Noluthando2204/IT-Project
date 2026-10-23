<?php
declare(strict_types=1);
require_once __DIR__.'/shared-components/auth.php';
$account_mode=$account_mode ?? false;
$user=$account_mode?auth_require_organiser():auth_require_attendee();
$pdo=eventnest_db();
$message='';
$categories=$pdo->query('SELECT CategoryName FROM CATEGORIES ORDER BY CategoryName')->fetchAll(PDO::FETCH_COLUMN);
if($_SERVER['REQUEST_METHOD']==='POST') {
    eventnest_require_post();
    try {
        $first=trim_input($_POST['first_name'] ?? '');
        $last=trim_input($_POST['last_name'] ?? '');
        $phone=trim_input($_POST['phone'] ?? '');
        $career=trim_input($_POST['career_field'] ?? '');
        if($first==='' || $last==='' || mb_strlen($first)>50 || mb_strlen($last)>50 || strlen($phone)>15 || ($career!=='' && !in_array($career,$categories,true))) {
            throw new DomainException('Check your name, phone length and selected career field.');
        }
        // Email stays read-only until a complete email verification flow is available.
        $pdo->beginTransaction();
        $stmt=$pdo->prepare('UPDATE USERS SET FirstName=?,LastName=?,Phone=?,CareerField=? WHERE UserID=?');
        $stmt->execute([$first,$last,$phone ?: null,$career ?: null,$user['UserID']]);
        $stmt=$pdo->prepare('DELETE FROM USER_EVENT_RECOMMENDATIONS WHERE UserID=?');
        $stmt->execute([$user['UserID']]);
        $pdo->commit();
        $_SESSION['first_name']=$first;
        $user=auth_current_user();
        $message='Account details saved.';
    }
    catch(Throwable $exception) {
        if($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message=$exception instanceof DomainException?$exception->getMessage():'Unable to save your details.';
    }
}
$page_title=$account_mode?'Organiser account':'My profile';
require __DIR__.'/shared-components/header.php';
?>
<div class="dashboard-layout"><?php eventnest_sidebar($user['Role']); ?><main class="dashboard-main"><h1><?= escape_html($page_title) ?></h1>
<?php if($message): ?><p class="alert alert-info" role="status"><?= escape_html($message) ?></p><?php endif; ?>
<form class="content-card" method="post"><?php eventnest_csrf_field(); ?><div class="form-grid">
<?php foreach(['first_name'=>['First name','FirstName',50],'last_name'=>['Last name','LastName',50],'phone'=>['Phone','Phone',15]] as $field=>[$label,$column,$max]): ?><div class="field"><label for="<?= $field ?>"><?= $label ?></label><input id="<?= $field ?>" name="<?= $field ?>" maxlength="<?= $max ?>" value="<?= escape_html($user[$column]) ?>" <?= $field!=='phone'?'required':'' ?>></div><?php endforeach; ?>
<div class="field"><label for="email">Email address</label><input id="email" type="email" value="<?= escape_html($user['Email']) ?>" readonly><small>Email updates require a verification process. Contact support for help.</small></div>
<div class="field"><label for="career_field">Career field</label><select id="career_field" name="career_field"><option value="">Not selected</option><?php foreach($categories as $category): ?><option <?= $category===$user['CareerField']?'selected':'' ?>><?= escape_html($category) ?></option><?php endforeach; ?></select></div>
<div class="field"><label>Account status</label><p><?= eventnest_status_badge($user['UserStatus']) ?></p></div></div>
<p>Changing your career field updates future recommendations when you opt in.</p><button class="btn btn-blue">Save <?= $account_mode?'account':'profile' ?></button></form>
<?php if($account_mode): ?><section class="content-card"><h2>Verification and fee coverage</h2><p><?= eventnest_status_badge($user['VerificationStatus']) ?> ? <?= auth_has_current_verification_fee($user)?'Paid until '.escape_html(format_datetime($user['VerificationFeePaidUntil'])):'No active paid coverage' ?></p><a class="btn btn-outline" href="organiser-verification.php">Verification and renewal</a></section>
<section class="content-card danger-zone"><h2>Delete account</h2><p>Upcoming events, held payments and unresolved refunds prevent deletion. Contact support first about any unresolved emailed report.</p><form method="post" action="delete-account.php" data-confirm="Delete and anonymise this account? This cannot be undone."><?php eventnest_csrf_field(); ?><label>Current password<input type="password" name="current_password" autocomplete="current-password" required></label><button class="btn btn-red">Delete account</button></form></section>
<?php else: ?><p><a href="settings.php">Password and recommendation settings</a></p><?php endif; ?></main></div>
<?php require __DIR__.'/shared-components/footer.php'; ?>
