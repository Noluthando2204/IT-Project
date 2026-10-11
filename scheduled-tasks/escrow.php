<?php

declare(strict_types=1);

$is_cli = PHP_SAPI === 'cli';

if ($is_cli) {
    require_once __DIR__ . '/../database-config/connection.php';
    require_once __DIR__ . '/../shared-components/functions.php';
} else {
    require_once __DIR__ . '/../shared-components/auth.php';
    require_once __DIR__ . '/../shared-components/functions.php';
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (isset($conn) && $conn instanceof PDO) {
        $pdo = $conn;
    } else {
        if ($is_cli) {
            fwrite(STDERR, "EventNest database connection is unavailable.\n");
            exit(1);
        }
        http_response_code(500);
        exit('EventNest is temporarily unavailable.');
    }
}

function escrow_positive_id(mixed $value): int
{
    return is_string($value) && ctype_digit($value) && (int) $value > 0 ? (int) $value : 0;
}

function escrow_load_payment(PDO $pdo, int $payment_id, bool $for_update = false): ?array
{
    $sql =
        "SELECT p.PaymentID, p.BookingID, p.PaymentDate, p.Amount, p.PaymentStatus,
                p.TransactionID, p.Escrow_status,
                b.BookingReference, b.Status AS BookingStatus, b.TotalAmount,
                e.EventID, e.EventName, e.Status AS EventStatus, e.EventDate, e.UpdatedAt
         FROM PAYMENTS p
         INNER JOIN BOOKINGS b ON b.BookingID = p.BookingID
         INNER JOIN EVENTS e ON e.EventID = b.EventID
         WHERE p.PaymentID = :payment_id
         LIMIT 1";
    if ($for_update) {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':payment_id' => $payment_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function escrow_release_ready(array $payment): bool
{
    if ($payment['PaymentStatus'] !== 'success' || $payment['Escrow_status'] !== 'held') {
        return false;
    }
    if ($payment['BookingStatus'] !== 'paid' || $payment['EventStatus'] !== 'completed') {
        return false;
    }

    $completion_time = !empty($payment['UpdatedAt']) ? strtotime((string) $payment['UpdatedAt']) : false;
    if ($completion_time === false) {
        $completion_time = strtotime((string) $payment['EventDate']);
    }
    return $completion_time !== false && $completion_time <= time() - 24 * 60 * 60;
}

function escrow_log(string $action, int $payment_id, string $result): void
{
    error_log('EventNest escrow ' . $action . ' PaymentID=' . $payment_id . ' result=' . $result);
}

if ($is_cli) {
    // The scheduled runner identifies payments that have passed the lower edge of the
    // 24-48 hour review window. It deliberately does not auto-release them because the
    // current ERD has no unresolved-issue/admin-approval field. Admin authorisation is
    // represented by the protected release POST below.
    $stmt = $pdo->query(
        "SELECT p.PaymentID, p.BookingID, p.PaymentDate, p.Amount, p.PaymentStatus,
                p.TransactionID, p.Escrow_status,
                b.BookingReference, b.Status AS BookingStatus,
                e.EventID, e.EventName, e.Status AS EventStatus, e.EventDate, e.UpdatedAt
         FROM PAYMENTS p
         INNER JOIN BOOKINGS b ON b.BookingID = p.BookingID
         INNER JOIN EVENTS e ON e.EventID = b.EventID
         WHERE p.PaymentStatus = 'success'
           AND p.Escrow_status = 'held'
           AND b.Status = 'paid'
           AND e.Status = 'completed'
           AND COALESCE(e.UpdatedAt, e.EventDate) <= DATE_SUB(NOW(), INTERVAL 24 HOUR)
         ORDER BY COALESCE(e.UpdatedAt, e.EventDate) ASC, p.PaymentID ASC"
    );
    $eligible = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo 'EventNest escrow eligibility check: ' . count($eligible) . " payment(s) awaiting admin release review.\n";
    foreach ($eligible as $payment) {
        echo 'PaymentID ' . (int) $payment['PaymentID']
            . ' | Booking ' . $payment['BookingReference']
            . ' | EventID ' . (int) $payment['EventID'] . "\n";
    }
    exit(0);
}

$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('The request could not be verified.');
}

$payment_id = escrow_positive_id($_POST['payment_id'] ?? '');
$action = is_string($_POST['action'] ?? null) ? trim($_POST['action']) : '';
if ($payment_id <= 0 || !in_array($action, ['release', 'refund'], true)) {
    http_response_code(400);
    exit('Invalid escrow action.');
}

$result_code = 'error';

try {
    $pdo->beginTransaction();
    $payment = escrow_load_payment($pdo, $payment_id, true);
    if (!$payment) {
        throw new DomainException('Payment not found.');
    }

    if ($action === 'release') {
        if ($payment['Escrow_status'] === 'released') {
            $result_code = 'already_released';
        } elseif ($payment['Escrow_status'] === 'refunded' || $payment['PaymentStatus'] === 'refunded') {
            throw new DomainException('A refunded payment cannot be released.');
        } elseif (!escrow_release_ready($payment)) {
            throw new DomainException('This payment has not passed all EventNest release checks yet.');
        } else {
            $update = $pdo->prepare(
                "UPDATE PAYMENTS
                 SET Escrow_status = 'released'
                 WHERE PaymentID = :payment_id
                   AND PaymentStatus = 'success'
                   AND Escrow_status = 'held'"
            );
            $update->execute([':payment_id' => $payment_id]);
            if ($update->rowCount() !== 1) {
                throw new DomainException('The payment state changed before release could be completed.');
            }
            $result_code = 'released';
        }
    } else {
        if ($payment['PaymentStatus'] === 'refunded' || $payment['Escrow_status'] === 'refunded') {
            $result_code = 'already_refunded';
        } elseif ($payment['Escrow_status'] === 'released') {
            throw new DomainException('Released escrow requires separate administrator/payment-provider follow-up and cannot be silently changed to refunded here.');
        } elseif ($payment['PaymentStatus'] !== 'success' || $payment['Escrow_status'] !== 'held') {
            throw new DomainException('Only a successful held payment can enter this refund path.');
        } elseif ($payment['EventStatus'] !== 'canceled') {
            throw new DomainException('This refund path is available only for a canceled event.');
        } else {
            // Student-prototype refund state. PayFast sandbox/dashboard activity is tested
            // separately; no real customer money is represented as being held by EventNest.
            $refund = $pdo->prepare(
                "UPDATE PAYMENTS
                 SET PaymentStatus = 'refunded', Escrow_status = 'refunded'
                 WHERE PaymentID = :payment_id
                   AND PaymentStatus = 'success'
                   AND Escrow_status = 'held'"
            );
            $refund->execute([':payment_id' => $payment_id]);
            if ($refund->rowCount() !== 1) {
                throw new DomainException('The payment state changed before refund could be completed.');
            }

            $booking = $pdo->prepare("UPDATE BOOKINGS SET Status = 'refunded' WHERE BookingID = :booking_id AND Status = 'paid'");
            $booking->execute([':booking_id' => (int) $payment['BookingID']]);
            $result_code = 'refunded';
        }
    }

    $pdo->commit();
    escrow_log($action, $payment_id, $result_code);
} catch (DomainException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    escrow_log($action, $payment_id, 'denied');
    $result_code = 'denied';
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    escrow_log($action, $payment_id, 'error');
    error_log('EventNest escrow processing error: ' . get_class($exception));
    $result_code = 'error';
}

header('Location: ' . eventnest_url('admin-panel/escrow.php?result=' . rawurlencode($result_code)));
exit;
