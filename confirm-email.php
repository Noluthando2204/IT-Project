<?php
declare(strict_types=1);

require_once __DIR__ . '/shared-components/functions.php';
require_once __DIR__ . '/database-config/connection.php';

$pdo = eventnest_db();
eventnest_ensure_session();
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
$page_title = 'Confirm email change';
$token = is_string($_POST['token'] ?? $_GET['token'] ?? null) ? trim((string) ($_POST['token'] ?? $_GET['token'])) : '';
$data = eventnest_read_email_change_token($token);
$state = $data === null ? 'invalid' : 'ready';
$new_email = $data['new_email'] ?? '';

// Opening the link only shows a confirmation button; the change itself needs a POST so mail scanners cannot trigger it.
if ($data !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!eventnest_verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $state = 'csrf';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT UserID, Email, UserStatus FROM USERS WHERE UserID = ? FOR UPDATE");
            $stmt->execute([$data['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user || $user['UserStatus'] === 'deleted' || strcasecmp((string) $user['Email'], $data['old_email']) !== 0) {
                // Account changed or the link was already used.
                $pdo->rollBack();
                $state = 'invalid';
            } else {
                $stmt = $pdo->prepare('SELECT 1 FROM USERS WHERE LOWER(Email) = LOWER(?) AND UserID <> ? LIMIT 1');
                $stmt->execute([$data['new_email'], $data['user_id']]);
                if ($stmt->fetchColumn()) {
                    $pdo->rollBack();
                    $state = 'taken';
                } else {
                    // The user proved they own the new address by opening the link sent to it.
                    $stmt = $pdo->prepare('UPDATE USERS SET Email = ?, Is_email_verified = 1 WHERE UserID = ? AND LOWER(Email) = LOWER(?)');
                    $stmt->execute([$data['new_email'], $data['user_id'], $data['old_email']]);
                    $changed = $stmt->rowCount() === 1;
                    if ($changed) {
                        $stmt = $pdo->prepare('DELETE FROM USER_EVENT_RECOMMENDATIONS WHERE UserID = ?');
                        $stmt->execute([$data['user_id']]);
                    }
                    $pdo->commit();
                    $state = $changed ? 'done' : 'invalid';
                }
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('EventNest email change failed: ' . get_class($exception));
            $state = 'error';
        }
    }
}

require_once __DIR__ . '/shared-components/header.php';
?>
<main class="auth-container content-card">
    <h1>Confirm email change</h1>
    <?php if ($state === 'done'): ?>
        <div class="alert alert-success" role="status">Your email address is now <strong><?= escape_html($new_email) ?></strong>. Use it the next time you log in.</div>
        <a class="btn btn-orange" href="login.php">Go to login</a>
    <?php elseif ($state === 'ready'): ?>
        <p>Change your EventNest account email to <strong><?= escape_html($new_email) ?></strong>?</p>
        <form method="post" action="confirm-email.php">
            <?php eventnest_csrf_field(); ?>
            <input type="hidden" name="token" value="<?= escape_html($token) ?>">
            <button class="btn btn-orange">Confirm email change</button>
        </form>
    <?php elseif ($state === 'taken'): ?>
        <div class="alert alert-warning" role="alert">That email address is already used by another account. Request a change to a different address.</div>
        <a class="btn btn-outline" href="profile.php">Back to profile</a>
    <?php elseif ($state === 'csrf'): ?>
        <div class="alert alert-danger" role="alert">Your session expired. Reload this page and try again.</div>
    <?php elseif ($state === 'error'): ?>
        <div class="alert alert-danger" role="alert">The email could not be changed. Request a new confirmation link and try again.</div>
        <a class="btn btn-outline" href="profile.php">Back to profile</a>
    <?php else: ?>
        <div class="alert alert-warning" role="alert">This confirmation link is invalid, expired or already used. Request a new one from your profile.</div>
        <a class="btn btn-outline" href="profile.php">Back to profile</a>
    <?php endif; ?>
</main>
<?php require_once __DIR__ . '/shared-components/footer.php'; ?>
