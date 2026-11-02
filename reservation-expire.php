<?php
declare(strict_types=1);
require_once __DIR__ . '/shared-components/auth.php';
$user = auth_require_attendee();
eventnest_require_post();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
eventnest_expire_pending(eventnest_db(), (int) $user['UserID']);
$stmt = eventnest_db()->prepare('SELECT Status FROM BOOKINGS WHERE BookingID = ? AND UserID = ?');
$id = eventnest_id($_POST['booking_id'] ?? null);
$stmt->execute([$id, $user['UserID']]);
$status = $stmt->fetchColumn();
if (!$status) { http_response_code(404); exit('{}'); }
echo json_encode(['status' => $status, 'checkout' => eventnest_checkout_started($id)]);
