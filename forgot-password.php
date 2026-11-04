<?php
declare(strict_types=1);

require_once __DIR__ . '/shared-components/functions.php';
require_once __DIR__ . '/database-config/connection.php';

$pdo = eventnest_db();
eventnest_ensure_session();
header('Cache-Control: no-store');
$page_title = 'Forgot password';
$email = is_string($_POST['email'] ?? $_GET['email'] ?? null) ? strtolower(trim((string) ($_POST['email'] ?? $_GET['email']))) : '';
$message = '';
$message_type = 'info';
$cooldown = max(0, 60 - (time() - (int) ($_SESSION['password_reset_last_sent'] ?? 0)));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!eventnest_verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $message = 'Your session expired. Please refresh and try again.';
        $message_type = 'danger';
    } elseif ($cooldown > 0) {
        $message = 'Please wait ' . $cooldown . ' seconds before requesting another reset email.';
        $message_type = 'warning';
    } elseif (eventnest_password_reset_secret() === '' || trim((string) getenv('EVENTNEST_SMTP_HOST')) === ''
        || !is_file(__DIR__ . '/vendor/autoload.php')) {
        $message = 'Password recovery is not configured on this server yet. Please contact EventNest support.';
        $message_type = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        $message = 'Enter a valid email address.';
        $message_type = 'danger';
    } else {
        try {
            // Validate the configured origin before looking up an account.
            $reset_page_url = eventnest_absolute_url('reset-password.php');
            // Apply equally to known and unknown accounts; this limiter is session scoped.
            $_SESSION['password_reset_last_sent'] = time();
            $cooldown = 60;
            $stmt = $pdo->prepare(
                "SELECT UserID, Email, PasswordHash, UserStatus
                 FROM USERS
                 WHERE LOWER(Email) = LOWER(?)
                 LIMIT 1"
            );
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && $user['UserStatus'] !== 'deleted') {
                $token = eventnest_create_password_reset_token($user);

                if ($token !== null) {
                    $reset_url = $reset_page_url . '?token=' . rawurlencode($token);
                    if (!eventnest_send_password_reset((string) $user['Email'], $reset_url)) {
                        error_log('EventNest password reset mail was not accepted by the configured transport.');
                    }
                }
            }

            /*
            The same response is shown whether the email exists or not.
            This prevents the recovery form from becoming a way to discover registered accounts.

            */
            $message = 'If an EventNest account is registered with that email address, password reset instructions have been sent. Please check your inbox and spam folder. The link is valid for 10 minutes. You can request another email after 60 seconds.';
            $message_type = 'success';
        } catch (Throwable $exception) {
            error_log('EventNest forgot-password failed: ' . get_class($exception));
            $message = 'Password recovery is temporarily unavailable. Please try again later.';
            $message_type = 'danger';
        }
    }
}

require_once __DIR__ . '/shared-components/header.php';
?>
<main class="auth-container content-card">
    <h1>Forgot password</h1>
    <p>Enter the email address you use to log in to EventNest.</p>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= escape_html($message_type) ?>" role="status"><?= escape_html($message) ?></div>
    <?php endif; ?>

    <form method="post" action="forgot-password.php" data-validate="true">
        <?php eventnest_csrf_field(); ?>
        <div class="field">
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" maxlength="100" autocomplete="email" value="<?= escape_html($email) ?>" required>
        </div>
        <div class="form-actions">
            <button id="reset-send" class="btn btn-orange" type="submit" data-cooldown-until="<?= (time() + $cooldown) * 1000 ?>" <?= $cooldown > 0 ? 'disabled' : '' ?>><?= isset($_SESSION['password_reset_last_sent']) ? 'Resend email' : 'Send reset link' ?></button>
            <span id="reset-cooldown" role="status"></span>
            <a class="btn btn-outline" href="login.php">Back to login</a>
        </div>
    </form>
</main>
<script src="<?= escape_html(eventnest_url('scripts/validation.js')) ?>" defer></script>
<script src="<?= escape_html(eventnest_url('scripts/password-reset.js')) ?>" defer></script>
<?php require_once __DIR__ . '/shared-components/footer.php'; ?>
