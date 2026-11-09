<?php

declare(strict_types=1);

// Sandbox-only stand-in for PayFast's payment notification, for demonstrations while the
// PayFast sandbox is unavailable. It records the payment exactly as a verified sandbox ITN
// would (booking paid, payment held in escrow). It is refused in live mode and can be switched
// off with PAYFAST_SIMULATOR=0.

require_once __DIR__ . '/shared-components/auth.php';
require_once __DIR__ . '/shared-components/functions.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = eventnest_db();
}

$auth_user = auth_require_attendee();
$user_id = (int) $auth_user['UserID'];
$booking_id = (is_string($_POST['booking_id'] ?? null) && ctype_digit($_POST['booking_id'])) ? (int) $_POST['booking_id'] : 0;

function payment_simulate_fail(int $booking_id, string $message): never
{
    if (function_exists('auth_set_flash')) { auth_set_flash($message, 'error'); }
    header('Location: ' . eventnest_url('payment.php?booking_id=' . $booking_id));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || $booking_id <= 0) {
    http_response_code(405);
    exit('Method not allowed.');
}
if (!eventnest_payfast_simulator_enabled()) {
    payment_simulate_fail($booking_id, 'Simulated payments are only available in PayFast sandbox mode.');
}
if (!eventnest_verify_csrf_token($_POST['csrf_token'] ?? null)) {
    payment_simulate_fail($booking_id, 'Your form session expired. Please try again.');
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        "SELECT b.BookingID, b.Status, b.TotalAmount, b.BookingDate > DATE_SUB(NOW(), INTERVAL 4 MINUTE) AS Unexpired
         FROM BOOKINGS b WHERE b.BookingID = ? AND b.UserID = ? LIMIT 1 FOR UPDATE"
    );
    $stmt->execute([$booking_id, $user_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$booking || $booking['Status'] !== 'pending') {
        throw new DomainException('This booking is no longer waiting for payment.');
    }
    if (!(int) $booking['Unexpired'] && !eventnest_checkout_started($booking_id)) {
        throw new DomainException('This reservation has expired. Please book again.');
    }
    $amount = round((float) $booking['TotalAmount'], 2);
    if ($amount <= 0.0) {
        throw new DomainException('Free bookings do not use PayFast.');
    }
    if (eventnest_demo_insufficient_funds($pdo, $auth_user, $amount)) {
        throw new DomainException('Insufficient funds. Your available balance is R10.00.');
    }
    $pdo->prepare("UPDATE BOOKINGS SET Status = 'paid' WHERE BookingID = ? AND Status = 'pending'")->execute([$booking_id]);
    $pdo->prepare(
        "INSERT INTO PAYMENTS (BookingID, PaymentDate, Amount, PaymentMethod, PaymentStatus, TransactionID, Escrow_status)
         VALUES (?, NOW(), ?, 'PayFast sandbox (simulated)', 'success', ?, 'held')"
    )->execute([$booking_id, number_format($amount, 2, '.', ''), 'SIM-' . strtoupper(bin2hex(random_bytes(8)))]);
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('EventNest simulated payment refused: ' . get_class($exception));
    payment_simulate_fail($booking_id, $exception instanceof DomainException ? $exception->getMessage() : 'The simulated payment could not be recorded.');
}

header('Location: ' . eventnest_url('payment-success.php?booking_id=' . $booking_id));
exit;
