<?php
declare(strict_types=1);
require_once __DIR__ . '/shared-components/auth.php';
$pdo = eventnest_db();
$organiser_intent = ($_POST['account_type'] ?? $_GET['account_type'] ?? '') === 'organiser';
$page_title = $organiser_intent ? 'Create organiser account' : 'Create account';
$message = '';
$categories = $pdo->query('SELECT CategoryName FROM CATEGORIES ORDER BY CategoryName')->fetchAll(PDO::FETCH_COLUMN);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventnest_require_post();
    $first_name = trim_input($_POST['first_name'] ?? '');
    $last_name = trim_input($_POST['last_name'] ?? '');
    $email = strtolower(trim_input($_POST['email'] ?? ''));
    $phone = trim_input($_POST['phone'] ?? '');
    $career_field = trim_input($_POST['career_field'] ?? '');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirmation = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
    try {
        foreach ([[$first_name,50],[$last_name,50],[$email,100],[$phone,15]] as [$value,$limit]) {
            if (mb_strlen($value) > $limit) {
                throw new DomainException('A field exceeds its allowed length.');
            }
        }
        if ($first_name === '' || $last_name === '' || !filter_var($email,FILTER_VALIDATE_EMAIL) || !eventnest_password_valid($password) || $password !== $confirmation || ($career_field !== '' && !in_array($career_field,$categories,true))) {
            throw new DomainException('Complete your name, valid email, career field and matching passwords (8?72 bytes).');
        }
        // Account type is only registration intent. Privileged roles and statuses are never accepted.
        $stmt = $pdo->prepare("INSERT INTO USERS (FirstName,LastName,Email,PasswordHash,Phone,CareerField,Role,UserStatus,VerificationStatus,RecommendationOptIn)
            VALUES (?,?,?,?,?,?,?,'active',?,?)");
        $stmt->execute([$first_name,$last_name,$email,password_hash($password,PASSWORD_DEFAULT),$phone ?: null,$career_field ?: null, $organiser_intent ? 'organiser' : 'attendee',$organiser_intent ? 'pending' : 'unverified', !$organiser_intent && ($_POST['recommendation_opt_in'] ?? '') === '1' ? 1 : 0]);
        auth_set_flash('Account created. Log in to continue' . ($organiser_intent ? ' to private document verification.' : '.'),'success');
        auth_redirect('login.php?registered=1');
    }
    catch (DomainException $exception) {
        $message = $exception->getMessage();
    }
    catch (PDOException $exception) {
        $message = $exception->getCode() === '23000' ? 'An account with that email already exists.' : 'Registration is temporarily unavailable.';
        error_log('EventNest registration failed: ' . $exception->getCode());
    }
}
require __DIR__ . '/shared-components/header.php';
?>
<main class="registration-layout"><section class="content-card"><h1><?= escape_html($page_title) ?></h1>
<p>Discover career events, learn new skills and connect.</p>
<?php if ($message): ?><p class="alert alert-danger" role="alert"><?= escape_html($message) ?></p><?php endif; ?>
<form method="post" action="<?= escape_html(eventnest_url('register.php')) ?>" data-validate="true">
<?php eventnest_csrf_field(); ?><input type="hidden" name="account_type" value="<?= $organiser_intent ? 'organiser' : 'attendee' ?>">
<div class="form-grid">
<?php foreach (['first_name'=>'First name','last_name'=>'Last name','email'=>'Email address','phone'=>'Phone'] as $field=>$label): ?>
<div class="field"><label for="<?= $field ?>"><?= $label ?></label><input id="<?= $field ?>" name="<?= $field ?>" type="<?= $field==='email' ? 'email':'text' ?>" maxlength="<?= $field==='email'?100:($field==='phone'?15:50) ?>" value="<?= escape_html(trim_input($_POST[$field] ?? '')) ?>" <?= $field!=='phone'?'required':'' ?>></div>
<?php endforeach; ?>
<div class="field full"><label for="career_field">Career field</label><select id="career_field" name="career_field"><option value="">Choose your field</option><?php foreach ($categories as $category): ?><option <?= trim_input($_POST['career_field'] ?? '')===$category?'selected':'' ?>><?= escape_html($category) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="password">Password</label><input id="password" name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required></div>
<div class="field"><label for="confirm_password">Confirm password</label><input id="confirm_password" name="confirm_password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required></div>
</div>
<?php if (!$organiser_intent): ?><p><label><input type="checkbox" name="recommendation_opt_in" value="1"> Opt in to personalised recommendations using your selected career field. This may use OpenAI.</label></p><?php else: ?><p>After logging in, submit your ID or CIPC document privately for administrator review.</p><?php endif; ?>
<div class="form-actions"><button class="btn btn-orange">Create account</button><a href="<?= escape_html(eventnest_url($organiser_intent?'register.php':'business-register.php')) ?>"><?= $organiser_intent?'Attendee registration':'Register as an organiser' ?></a></div>
</form><p>Already have an account? <a href="<?= escape_html(eventnest_url('login.php')) ?>">Log in</a></p></section><aside class="callout callout-teal"><h2><?= $organiser_intent ? 'Verification follows registration' : 'Registering as an organiser?' ?></h2><p><?= $organiser_intent ? 'Log in after registration to upload a private ID or CIPC document for administrator review.' : 'Create an organiser account to submit events and share career updates after verification.' ?></p><a class="btn btn-teal" href="<?= escape_html(eventnest_url($organiser_intent?'register.php':'business-register.php')) ?>"><?= $organiser_intent?'Attendee registration':'Register as an organiser' ?></a></aside></main>
<script src="<?= escape_html(eventnest_url('scripts/validation.js')) ?>" defer></script>
<?php require __DIR__ . '/shared-components/footer.php'; ?>
