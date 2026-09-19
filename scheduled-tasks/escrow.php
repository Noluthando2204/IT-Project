<?php
declare(strict_types=1);

$is_cli = PHP_SAPI === 'cli';

if (!$is_cli) {
    session_start();
}

require_once __DIR__ . '/../database-config/connection.php';

// Shared componenets code should be placed here

function escrow_output(string $message, int $status_code = 200): never
{
    if (PHP_SAPI !== 'cli') {
        http_response_code($status_code);
    }

    exit($message);
}

function escrow_admin_id(PDO $pdo): ?int
{
    if (PHP_SAPI === 'cli') {
        return null;
    }

    $user_id = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);

    if (!$user_id) {
        return null;
    }

    $statement = $pdo->prepare(
        "SELECT UserID
         FROM USERS
         WHERE UserID = :user_id
           AND Role = 'admin'
         LIMIT 1"
    );
    $statement->execute(['user_id' => $user_id]);

    $admin = $statement->fetch();

    return $admin ? (int) $admin['UserID'] : null;
}

/*
 Approved Payfast refund code should be 
 placed here. It must only return true after
 the sandbox refund is confirmed.
 */
function escrow_request_refund(array $payment): bool
{
    return false;
}

if ($is_cli) {
    /*
    admin-panel/escrow.php can send the approved PaymentID back to this
    file for the release action.
     */
    $eligible_statement = $pdo->query(
        "SELECT
            p.PaymentID,
            p.Amount,
            p.PaymentStatus,
            p.Escrow_status,
            b.BookingID,
            e.EventID,
            e.EventName,
            e.EventDate,
            e.Status AS EventStatus
         FROM PAYMENTS p
         INNER JOIN BOOKINGS b ON b.BookingID = p.BookingID
         INNER JOIN EVENTS e ON e.EventID = b.EventID
         WHERE p.PaymentStatus = 'success'
           AND p.Escrow_status = 'held'
           AND e.Status = 'completed'
           AND e.EventDate <= DATE_SUB(NOW(), INTERVAL 24 HOUR)
         ORDER BY e.EventDate ASC"
    );

    $eligible = $eligible_statement->fetchAll();

    foreach ($eligible as $row) {
        echo 'Eligible held payment: PaymentID '
            . (int) $row['PaymentID']
            . ' | Event '
            . (int) $row['EventID']
            . ' | R'
            . number_format((float) $row['Amount'], 2)
            . PHP_EOL;
    }

    echo 'Eligible count: ' . count($eligible) . PHP_EOL;
    exit;
}

$admin_id = escrow_admin_id($pdo);

if (!$admin_id) {
    escrow_output('Admin access required.', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    escrow_output('This action requires POST.', 405);
}

$payment_id = filter_input(INPUT_POST, 'payment_id', FILTER_VALIDATE_INT);
$action = strtolower(trim((string) ($_POST['action'] ?? '')));

if (!$payment_id || !in_array($action, ['release', 'refund'], true)) {
    escrow_output('Invalid escrow action.', 400);
}

try {
    $pdo->beginTransaction();

    $payment_statement = $pdo->prepare(
        'SELECT
            p.PaymentID,
            p.Amount,
            p.PaymentStatus,
            p.Escrow_status,
            p.Payfast_transaction_id,
            b.BookingID,
            b.Status AS BookingStatus,
            e.EventID,
            e.Status AS EventStatus,
            e.EventDate
         FROM PAYMENTS p
         INNER JOIN BOOKINGS b ON b.BookingID = p.BookingID
         INNER JOIN EVENTS e ON e.EventID = b.EventID
         WHERE p.PaymentID = :payment_id
         FOR UPDATE'
    );
    $payment_statement->execute(['payment_id' => $payment_id]);
    $payment = $payment_statement->fetch();

    if (!$payment) {
        throw new RuntimeException('Payment not found.');
    }

    if ($payment['Escrow_status'] === 'released' && $action === 'release') {
        $pdo->commit();
        escrow_output('Payment was already released.');
    }

    if ($payment['Escrow_status'] === 'refunded' && $action === 'refund') {
        $pdo->commit();
        escrow_output('Payment was already refunded.');
    }

    if ($action === 'release') {
        if (
            $payment['PaymentStatus'] !== 'success'
            || $payment['Escrow_status'] !== 'held'
            || $payment['EventStatus'] !== 'completed'
            || strtotime((string) $payment['EventDate']) > (time() - 24 * 60 * 60)
        ) {
            throw new RuntimeException('This payment is not eligible for release.');
        }

        /*
         Final admin issue check can be placed here 
         if we decide to find a way to record unresolved email issues 
         outside of the ERD.
         */

        $release_statement = $pdo->prepare(
            "UPDATE PAYMENTS
             SET Escrow_status = 'released'
             WHERE PaymentID = :payment_id
               AND PaymentStatus = 'success'
               AND Escrow_status = 'held'"
        );
        $release_statement->execute(['payment_id' => $payment_id]);

        if ($release_statement->rowCount() !== 1) {
            throw new RuntimeException('The payment state changed before release.');
        }

        $pdo->commit();

        error_log(
            'Escrow released by admin ' . $admin_id
            . ' for PaymentID ' . $payment_id
        );

        escrow_output('Escrow released successfully.');
    }

    if (
        $payment['Escrow_status'] === 'released'
        && $action === 'refund'
    ) {
        throw new RuntimeException('Released escrow requires manual/admin investigation before refund.');
    }

    if (!in_array($payment['EventStatus'], ['cancelled'], true)) {
        throw new RuntimeException('Only cancelled/fraud-confirmed event payments may be refunded here.');
    }

    if (!escrow_request_refund($payment)) {
        throw new RuntimeException('The PayFast sandbox refund has not been confirmed.');
    }

    $refund_statement = $pdo->prepare(
        "UPDATE PAYMENTS
         SET PaymentStatus = 'refunded',
             Escrow_status = 'refunded'
         WHERE PaymentID = :payment_id
           AND Escrow_status <> 'refunded'"
    );
    $refund_statement->execute(['payment_id' => $payment_id]);

    $booking_statement = $pdo->prepare(
        "UPDATE BOOKINGS
         SET Status = 'refunded'
         WHERE BookingID = :booking_id"
    );
    $booking_statement->execute([
        'booking_id' => $payment['BookingID']
    ]);

    $pdo->commit();

    error_log(
        'Escrow refunded by admin ' . $admin_id
        . ' for PaymentID ' . $payment_id
    );

    escrow_output('Refund completed successfully.');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Escrow operation failed: ' . $exception->getMessage());
    escrow_output($exception->getMessage(), 409);
}

