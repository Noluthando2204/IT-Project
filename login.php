<?php
declare(strict_types=1);

require_once __DIR__ . '/shared-components/auth.php';
require_once __DIR__ . '/shared-components/functions.php';

$pdo = eventnest_db();
$message = '';
$message_type = 'error';
$email = is_string($_POST['email'] ?? null) ? strtolower(trim($_POST['email'])) : '';
$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

if (($_GET['reset'] ?? '') === 'success') {
    $message = 'Your password has been updated. You can log in with the new password.';
    $message_type = 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!eventnest_verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $message = 'Your session expired. Please refresh and try again.';
    } elseif ($email === '' || $password === '') {
        $message = 'Please enter both email and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        $message = 'Please enter a valid email address.';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'SELECT UserID, FirstName, LastName, Email, PasswordHash,
                        UserStatus, Role, VerificationStatus, Is_email_verified,
                        Suspension_count, Suspension_start_date
                 FROM USERS
                 WHERE LOWER(Email) = LOWER(?)
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !is_string($user['PasswordHash']) || !password_verify($password, $user['PasswordHash'])) {
                throw new DomainException('Invalid login.');
            }

            if ($user['UserStatus'] === 'deleted' || (int) $user['Suspension_count'] >= 2) {
                throw new DomainException('Account unavailable.');
            }

            if (!in_array($user['Role'], ['attendee', 'organiser', 'admin'], true)) {
                throw new DomainException('Account unavailable.');
            }

            if (!empty($user['Suspension_start_date']) || $user['UserStatus'] === 'suspended') {
                $started = strtotime((string) ($user['Suspension_start_date'] ?? ''));
                if ($started === false || strtotime('+30 days', $started) > time()) {
                    throw new DomainException('Suspended.');
                }

                if ($user['UserStatus'] === 'suspended') {
                    $stmt = $pdo->prepare(
                        "UPDATE USERS
                         SET UserStatus = 'active', Suspension_start_date = NULL
                         WHERE UserID = ? AND UserStatus = 'suspended' AND Suspension_count < 2"
                    );
                    $stmt->execute([(int) $user['UserID']]);
                    $user['UserStatus'] = 'active';
                }
            }

            if ($user['UserStatus'] !== 'active') {
                throw new DomainException('Account unavailable.');
            }

            $stmt = $pdo->prepare('UPDATE USERS SET LastLoginAt = CURRENT_TIMESTAMP WHERE UserID = ?');
            $stmt->execute([(int) $user['UserID']]);
            $pdo->commit();

            auth_start_user_session($user);
            auth_redirect(auth_dashboard_path($user['Role']));
        } catch (DomainException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            /*
            The login page keeps the public error general so it does not confirm which email addresses exist.
            The password recovery page can still help a real user regain access.

            */
            $message = $exception->getMessage() === 'Suspended.'
                ? 'Your account is currently suspended. Please contact EventNest support if you need help.'
                : 'Unable to sign in with those details. Check your email and password, or reset your password.';
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('EventNest login failed: ' . get_class($exception));
            $message = 'Login is temporarily unavailable. Please try again later.';
        }
    }
}

$page_title = 'Log in';
require_once __DIR__ . '/shared-components/header.php';
?>
<main class="auth-container content-card">
    <h1>Log in to EventNest</h1>
    <p>Use the email address you registered with. EventNest does not use separate usernames.</p>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'danger' ?>" role="status">
            <?= escape_html($message) ?>
        </div>
    <?php endif; ?>

    <form method="post" action="login.php" data-validate="true">
        <?php eventnest_csrf_field(); ?>

        <div class="field">
            <label for="email">Email address</label>
            <input id="email" type="email" name="email" maxlength="100" autocomplete="email" value="<?= escape_html($email) ?>" required>
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" autocomplete="current-password" required>
        </div>

        <p class="auth-help"><a href="forgot-password.php">Forgot your password?</a></p>

        <div class="form-actions">
            <button class="btn btn-orange" type="submit">Log in</button>
            <a class="btn btn-outline" href="register.php">Create account</a>
        </div>
    </form>
</main>
<script src="<?= escape_html(eventnest_url('scripts/validation.js')) ?>" defer></script>
<?php require_once __DIR__ . '/shared-components/footer.php'; ?>
