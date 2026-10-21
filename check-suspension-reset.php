<?php
declare(strict_types=1);
// Scheduled maintenance is never triggered by a public web request.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Command-line access only.');
}
require_once __DIR__ . '/database-config/connection.php';
require_once __DIR__ . '/shared-components/functions.php';
try {
    // Repeat suspensions stay suspended; "banned" is not a schema status.
    $stmt = eventnest_db()->prepare("UPDATE USERS SET UserStatus = 'active', Suspension_start_date = NULL
        WHERE UserStatus = 'suspended' AND Suspension_count < 2
        AND Suspension_start_date <= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    echo $stmt->rowCount() . " expired suspension(s) reset.\n";
} catch (Throwable $e) {
    error_log('EventNest suspension reset failed: ' . get_class($e));
    exit(1);
}
