<?php
declare(strict_types=1);
require_once __DIR__ . '/shared-components/auth.php';
$user = auth_require_attendee();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$stmt = eventnest_db()->prepare('SELECT Status FROM BOOKINGS WHERE BookingID = ? AND UserID = ?');
$stmt->execute([eventnest_id($_GET['booking_id'] ?? null), $user['UserID']]);
$status = $stmt->fetchColumn();
if (!$status) { http_response_code(404); exit('{}'); }
echo json_encode(['status' => $status]);
