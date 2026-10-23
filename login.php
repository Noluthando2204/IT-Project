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
            if (!$user || !is_string($user['PasswordHash']) || !password_verify($password, $user['PasswordHash'])) {
                throw new DomainException('Invalid email or password.');
            }
            if ($user['UserStatus'] === 'deleted' || (int) $user['Suspension_count'] >= 2) {
                throw new DomainException('This account is no longer available.');
            }
            if ((int) $user['Is_email_verified'] !== 1) {
                throw new DomainException('Please verify your email address before logging in.');
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
            session_regenerate_id(true);
            $_SESSION = [
                'user_id' => (int) $user['UserID'],
                'role' => $user['Role'],
                'user_status' => $user['UserStatus'],
                'verification_status' => $user['VerificationStatus'],
                'first_name' => (string) $user['FirstName'],
                'name' => trim($user['FirstName'] . ' ' . $user['LastName']),
                'email' => $user['Email'],
                'loggedin' => true,
            ];
            auth_redirect($user['Role'] === 'admin' ? 'admin-panel/manage-bookings.php' : 'index.php');
        } catch (DomainException $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $message = $exception->getMessage();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('EventNest login failed: ' . get_class($exception));
            $message = 'Login is temporarily unavailable. Please try again later.';
        }
    }
}
?>

<?php require_once __DIR__ . '/shared-components/header.php'; ?>

<div class="container">

    <h2>User Login</h2>

    <?php if (!empty($message)): ?>

        <p style="color: <?= $message_type === 'success' ? 'green' : 'red'; ?>">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
        </p>

    <?php endif; ?>

    <form method="POST" action="login.php">
        <input type="hidden" name="csrf_token" value="<?= escape_html(eventnest_csrf_token()) ?>">

        <input
            type="email"
            name="email"
            placeholder="Email Address"
            autocomplete="email"
            value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
            required
        >

        <br><br>

        <input
            type="password"
            name="password"
            placeholder="Password"
            autocomplete="current-password"
            required
        >

        <br><br>

        <button type="submit">Login</button>

    </form>

    <p>
        Don't have an account?
        <a href="register.php">Register here</a>
    </p>

</div>

<?php require_once __DIR__ . '/shared-components/footer.php'; ?>
