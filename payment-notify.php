<?php
declare(strict_types=1);
require_once __DIR__ . '/shared-components/payments.php';
require_once __DIR__ . '/database-config/connection.php';
header('Content-Type: text/plain; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST'); http_response_code(405); exit('Method not allowed.');
}
try {
    payment_success_record_itn(eventnest_db(), $_POST);
    echo 'OK';
} catch (Throwable $exception) {
    error_log('EventNest PayFast ITN rejected: ' . get_class($exception));
    http_response_code($exception instanceof PDOException ? 503 : 400);
    echo 'INVALID';
}
