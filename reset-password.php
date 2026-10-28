<?php
declare(strict_types=1);

require_once __DIR__ . '/shared-components/functions.php';
require_once __DIR__ . '/database-config/connection.php';

$pdo = eventnest_db();
eventnest_ensure_session();
$page_title = 'Reset password';
$token = is_string($_POST['token'] ?? $_GET['token'] ?? null) ? trim((string) ($_POST['token'] ?? $_GET['token'])) : '';
$message = '';
$message_type = 'danger';
$token_data = eventnest_read_password_reset_token($token);
$user = null;

if ($token_data !== null) {
    $stmt = $pdo->prepare(
        "SELECT UserID, Email, PasswordHash, UserStatus
         FROM USERS
         WHERE UserID = :user_id AND LOWER(Email) = LOWER(:email)
         LIMIT 1"
    );
    $stmt->execute([
        ':user_id' => $token_data['user_id'],
        ':email' => $token_data['email'],
    ]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (
        !$user ||
        $user['UserStatus'] === 'deleted' ||
        !hash_equals($token_data['password_marker'], hash('sha256', (string) $user['PasswordHash']))
    ) {
        $token_data = null;
        $user = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_data !== null && $user !== null) {
    $password = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
    $confirm = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

    if (!eventnest_verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $message = 'Your session expired. Please refresh and try again.';
    } elseif (!eventnest_password_valid($password)) {
        $message = 'Use a password that is at least 8 characters long.';
    } elseif ($password !== $confirm) {
        $message = 'The two passwords do not match.';
    } else {
        try {
            $new_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                'UPDATE USERS SET PasswordHash = :new_hash WHERE UserID = :user_id AND PasswordHash = :old_hash'
            );
            $stmt->execute([
                ':new_hash' => $new_hash,
                ':user_id' => (int) $user['UserID'],
                ':old_hash' => (string) $user['PasswordHash'],
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Password changed before reset completed.');
            }

            /*
            The token contains a fingerprint of the old password hash.
            Once the password changes, the same reset link can no longer be used again.

            */
            header('Location: ' . eventnest_url('login.php?reset=success'));
            exit;
        } catch (Throwable $exception) {
            error_log('EventNest reset-password failed: ' . get_class($exception));
            $message = 'The password could not be changed. Request a new reset link and try again.';
        }
    }
}

require_once __DIR__ . '/shared-components/header.php';
?>
<main class="auth-container content-card">
    <h1>Reset password</h1>

    <?php if ($token_data === null || $user === null): ?>
        <div class="alert alert-warning" role="alert">
            This reset link is invalid, has expired, or has already been used.
        </div>
        <a class="btn btn-orange" href="forgot-password.php">Request a new reset link</a>
    <?php else: ?>
        <?php if ($message !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= escape_html($message) ?></div>
        <?php endif; ?>

        <form method="post" action="reset-password.php" data-validate="true">
            <?php eventnest_csrf_field(); ?>
            <input type="hidden" name="token" value="<?= escape_html($token) ?>">

            <div class="field">
                <label for="new_password">New password</label>
                <input id="new_password" name="new_password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
            </div>

            <div class="field">
                <label for="confirm_password">Confirm new password</label>
                <input id="confirm_password" name="confirm_password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
            </div>

            <div class="form-actions">
                <button class="btn btn-orange" type="submit">Update password</button>
                <a class="btn btn-outline" href="login.php">Back to login</a>
            </div>
        </form>
    <?php endif; ?>
</main>
<script src="<?= escape_html(eventnest_url('scripts/validation.js')) ?>" defer></script>
<?php require_once __DIR__ . '/shared-components/footer.php'; ?>
