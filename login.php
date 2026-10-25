<?php
declare(strict_types=1);
require_once __DIR__ . '/shared-components/auth.php';
require_once __DIR__ . '/shared-components/functions.php';
$pdo = eventnest_db();
$message = '';
$message_type = 'error';
$email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

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
            $stmt = $pdo->prepare('SELECT UserID, FirstName, LastName, Email, PasswordHash,
                UserStatus, Role, VerificationStatus, Is_email_verified,
                Suspension_count, Suspension_start_date
                FROM USERS WHERE Email = ? LIMIT 1 FOR UPDATE');
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                throw new DomainException('Account not found.');
            }
            if (!is_string($user['PasswordHash']) || $user['PasswordHash'] === '') {
                throw new DomainException('Account password unavailable.');
            }
            if (!password_verify($password, $user['PasswordHash'])) {
                throw new DomainException('Invalid email or password.');
            }
            if ($user['UserStatus'] === 'deleted' || (int) $user['Suspension_count'] >= 2) {
                throw new DomainException('This account is no longer available.');
            }
            if (!in_array($user['Role'], ['attendee', 'organiser', 'admin'], true)) {
                throw new DomainException('This account cannot log in.');
            }
            if (!empty($user['Suspension_start_date']) || $user['UserStatus'] === 'suspended') {
                $started = strtotime((string) ($user['Suspension_start_date'] ?? ''));
                if ($started === false || strtotime('+30 days', $started) > time()) {
                    throw new DomainException('Your account is suspended. Please contact support.');
                }
                if ($user['UserStatus'] === 'suspended') {
                    $stmt = $pdo->prepare("UPDATE USERS SET UserStatus = 'active', Suspension_start_date = NULL
                        WHERE UserID = ? AND UserStatus = 'suspended' AND Suspension_count < 2");
                    $stmt->execute([(int) $user['UserID']]);
                    $user['UserStatus'] = 'active';
                }
            }
            if ($user['UserStatus'] !== 'active') {
                throw new DomainException('This account cannot log in.');
            }
            $stmt = $pdo->prepare('UPDATE USERS SET LastLoginAt = CURRENT_TIMESTAMP WHERE UserID = ?');
            $stmt->execute([(int) $user['UserID']]);
            // A second login in the same timestamp can correctly affect zero changed rows.
            $pdo->commit();
            auth_start_user_session($user);
            auth_redirect(auth_dashboard_path($user['Role']));
        } catch (DomainException $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            // Fixed reason codes distinguish SIT data issues without logging credentials or identities.
            $reason = match ($exception->getMessage()) {
                'Account not found.' => 'account_not_found',
                'Account password unavailable.' => 'password_unavailable',
                'Invalid email or password.' => 'password_mismatch',
                'This account is no longer available.' => 'deleted_or_permanently_suspended',
                'Your account is suspended. Please contact support.' => 'suspended',
                default => 'account_not_eligible',
            };
            error_log('EventNest login rejected: reason=' . $reason);
            $message = 'Unable to sign in with those details. Check your credentials or contact support.';
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $database_error = $exception instanceof PDOException
                ? ' SQLSTATE=' . $exception->getCode() . ' driver=' . (int) ($exception->errorInfo[1] ?? 0)
                : '';
            error_log('EventNest login failed: ' . get_class($exception) . $database_error);
            $message = 'Login is temporarily unavailable. Please try again later.';
        }
    }
}
?>

<?php require_once __DIR__ . '/shared-components/header.php'; ?>

<main class="auth-container content-card">

    <h1>Log in to EventNest</h1>

    <?php if (!empty($message)): ?>

        <p style="color: <?= $message_type === 'success' ? 'green' : 'red'; ?>">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
        </p>

    <?php endif; ?>

    <form method="POST" action="login.php">
        <input type="hidden" name="csrf_token" value="<?= escape_html(eventnest_csrf_token()) ?>">

        <label for="email">Email address</label><input
            id="email" type="email"
            name="email"
            placeholder="Email Address"
            autocomplete="email"
            value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
            required
        >

        <br><br>

        <label for="password">Password</label><input
            id="password" type="password"
            name="password"
            placeholder="Password"
            autocomplete="current-password"
            required
        >

        <br><br>

        <button class="btn btn-orange" type="submit">Log in</button>

    </form>

    <p>
        Don't have an account?
        <a href="register.php">Register here</a>
    </p>

</main>

<?php require_once __DIR__ . '/shared-components/footer.php'; ?>
