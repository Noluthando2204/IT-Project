<?php
declare(strict_types=1);
require_once __DIR__ . '/shared-components/auth.php';
$user = auth_require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method not allowed.');
}
if (!eventnest_verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('The request could not be verified.');
}
try {
    // Retain booking/payment history required by the schema's foreign keys.
    $stmt = eventnest_db()->prepare("UPDATE USERS SET UserStatus = 'deleted' WHERE UserID = ? AND UserStatus = 'active'");
    $stmt->execute([(int) $user['UserID']]);
    if ($stmt->rowCount() !== 1) { throw new RuntimeException('Account was not updated.'); }
    auth_destroy_session();
    auth_redirect('login.php?deleted=1');
} catch (Throwable $e) {
    error_log('EventNest account deletion failed: ' . get_class($e));
    http_response_code(500);
    exit('Unable to delete your account. Please try again later.');
}
