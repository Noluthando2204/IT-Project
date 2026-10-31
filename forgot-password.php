<?php
declare(strict_types=1);

require_once __DIR__ . '/shared-components/functions.php';
require_once __DIR__ . '/database-config/connection.php';

$pdo = eventnest_db();
eventnest_ensure_session();
$page_title = 'Forgot password';
$email = is_string($_POST['email'] ?? $_GET['email'] ?? null) ? strtolower(trim((string) ($_POST['email'] ?? $_GET['email']))) : '';
$message = '';
$message_type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!eventnest_verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $message = 'Your session expired. Please refresh and try again.';
        $message_type = 'danger';
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
            $stmt = $pdo->prepare(
                "SELECT UserID, Email, PasswordHash, UserStatus
                 FROM USERS
                 WHERE LOWER(Email) = LOWER(?)
                 LIMIT 1"
            );
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && $user['UserStatus'] !== 'deleted') {
                $token = eventnest_create_password_reset_token($user, 30);

                if ($token !== null) {
                    $reset_url = $reset_page_url . '?token=' . rawurlencode($token);
                    $body = "A password reset was requested for your EventNest account.\n\n"
                        . "Reset your password using this link:\n" . $reset_url . "\n\n"
                        . "The link expires in 30 minutes. If you did not request this, you can ignore this email.";
                    if (!eventnest_send_mail((string) $user['Email'], 'Reset your EventNest password', $body)) {
                        error_log('EventNest password reset mail was not accepted by the configured transport.');
                    }
                }
            }

            /*
            The same response is shown whether the email exists or not.
            This prevents the recovery form from becoming a way to discover registered accounts.

            */
            $message = 'If that email is registered and password recovery is available, you will receive a reset link. Check your inbox and spam folder.';
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
            <button class="btn btn-orange" type="submit">Send reset link</button>
            <a class="btn btn-outline" href="login.php">Back to login</a>
        </div>
    </form>
</main>
<script src="<?= escape_html(eventnest_url('scripts/validation.js')) ?>" defer></script>
<?php require_once __DIR__ . '/shared-components/footer.php'; ?>
