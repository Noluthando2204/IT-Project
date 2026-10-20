<?php

declare(strict_types=1);

require_once __DIR__ . '/shared-components/auth.php';
require_once __DIR__ . '/shared-components/functions.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (isset($conn) && $conn instanceof PDO) {
        $pdo = $conn;
    } else {
        http_response_code(500);
        exit('EventNest is temporarily unavailable.');
    }
}

$auth_user = require_attendee();
$user_id = (int) $auth_user['UserID'];

$cancel_requested = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($cancel_requested && !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('The cancellation request could not be verified.');
}

function payment_cancel_id(mixed $value): int
{
    return is_string($value) && ctype_digit($value) && (int) $value > 0 ? (int) $value : 0;
}

$booking_id = payment_cancel_id($_GET['booking_id'] ?? $_POST['booking_id'] ?? '');
$reference = is_string($_GET['m_payment_id'] ?? null) ? trim($_GET['m_payment_id']) : '';

if ($booking_id <= 0 && $reference === '') {
    http_response_code(404);
    exit('Booking not found.');
}

$result_message = '';
$result_state = '';
$booking = null;

try {
    $pdo->beginTransaction();

    $sql =
        "SELECT b.BookingID, b.BookingReference, b.Status, b.Quantity, b.TotalAmount,
                b.EventID, b.TicketID, b.UserID,
                e.EventName, e.EventDate, t.TicketType
         FROM BOOKINGS b
         INNER JOIN EVENTS e ON e.EventID = b.EventID
         INNER JOIN TICKETS t ON t.TicketID = b.TicketID AND t.EventID = b.EventID
         WHERE b.UserID = :user_id";
    $params = [':user_id' => $user_id];
    if ($booking_id > 0) {
        $sql .= ' AND b.BookingID = :booking_id';
        $params[':booking_id'] = $booking_id;
    } else {
        $sql .= ' AND b.BookingReference = :reference';
        $params[':reference'] = $reference;
    }
    $sql .= ' LIMIT 1 FOR UPDATE';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$booking) {
        throw new DomainException('The booking could not be found for your account.');
    }
    $booking_id = (int) $booking['BookingID'];

    $payment_stmt = $pdo->prepare("SELECT PaymentID FROM PAYMENTS WHERE BookingID = :booking_id AND PaymentStatus = 'success' LIMIT 1 FOR UPDATE");
    $payment_stmt->execute([':booking_id' => $booking_id]);
    $verified_success = (bool) $payment_stmt->fetchColumn();

    if ($verified_success) {
        // Verified success always wins over a late browser cancellation return.
        if ($cancel_requested && $booking['Status'] === 'pending') {
            $paid_stmt = $pdo->prepare("UPDATE BOOKINGS SET Status = 'paid' WHERE BookingID = :booking_id AND Status = 'pending'");
            $paid_stmt->execute([':booking_id' => $booking_id]);
            $booking['Status'] = 'paid';
        }
        $result_state = 'success';
        $result_message = 'Payment was already verified successfully. This late cancellation return did not change your booking.';
    } elseif ($booking['Status'] === 'pending' && !$cancel_requested) {
        $result_state = 'pending';
        $result_message = 'Payment is not verified. Confirm cancellation below to cancel this pending booking and release its reserved tickets, or retry payment.';
    } elseif ($booking['Status'] === 'pending') {
        $cancel_stmt = $pdo->prepare("UPDATE BOOKINGS SET Status = 'canceled' WHERE BookingID = :booking_id AND Status = 'pending'");
        $cancel_stmt->execute([':booking_id' => $booking_id]);

        if ($cancel_stmt->rowCount() === 1) {
            // Capacity is restored only when this request actually performs the pending-to-canceled transition.
            $restore_stmt = $pdo->prepare(
                'UPDATE TICKETS SET QuantityAvailable = LEAST(Capacity, QuantityAvailable + :quantity) WHERE TicketID = :ticket_id'
            );
            $restore_stmt->execute([
                ':quantity' => (int) $booking['Quantity'],
                ':ticket_id' => (int) $booking['TicketID'],
            ]);
            $booking['Status'] = 'canceled';
            $result_state = 'canceled';
            $result_message = 'Payment was not completed. The pending booking was canceled and the reserved ticket quantity was restored.';
        } else {
            $result_state = 'unchanged';
            $result_message = 'The booking had already changed state, so no ticket quantity was restored again.';
        }
    } elseif ($booking['Status'] === 'canceled') {
        $result_state = 'canceled';
        $result_message = 'This payment was already canceled. No ticket quantity was restored again.';
    } else {
        $result_state = 'unchanged';
        $result_message = 'This booking is no longer pending, so the payment-cancel page did not change it.';
    }

    $pdo->commit();
} catch (DomainException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(404);
    $result_state = 'error';
    $result_message = $exception->getMessage();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('EventNest payment cancellation error: ' . get_class($exception));
    http_response_code(500);
    $result_state = 'error';
    $result_message = 'The payment status could not be checked safely. Please open My Bookings before trying again.';
}

$page_title = 'Payment not completed';
require_once __DIR__ . '/shared-components/header.php';
?>
<main class="page-content payment-result-page">
    <section class="result-card">
        <?php if ($result_state === 'success'): ?>
            <div class="status status-success">Payment confirmed</div>
            <h1>Your booking is confirmed</h1>
        <?php elseif ($result_state === 'canceled'): ?>
            <div class="status status-error">Payment not completed</div>
            <h1>Payment not completed</h1>
        <?php else: ?>
            <div class="status status-warning">Booking status checked</div>
            <h1>Payment status</h1>
        <?php endif; ?>

        <p><?= escape_html($result_message) ?></p>

        <?php if ($booking): ?>
            <dl>
                <div><dt>Booking reference</dt><dd><?= escape_html($booking['BookingReference']) ?></dd></div>
                <div><dt>Event</dt><dd><?= escape_html($booking['EventName']) ?></dd></div>
                <div><dt>Ticket</dt><dd><?= escape_html($booking['TicketType']) ?> × <?= (int) $booking['Quantity'] ?></dd></div>
                <div><dt>Total</dt><dd><?= escape_html(format_money($booking['TotalAmount'])) ?></dd></div>
            </dl>
        <?php endif; ?>

        <div class="button-row">
            <?php if ($booking && $booking['Status'] === 'pending'): ?>
                <form method="post" action="payment-cancel.php">
                    <input type="hidden" name="booking_id" value="<?= (int) $booking['BookingID'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= escape_html(get_csrf_token()) ?>">
                    <button class="btn btn-outline" type="submit">Confirm payment cancellation</button>
                </form>
                <a class="btn btn-primary" href="payment.php?booking_id=<?= (int) $booking['BookingID'] ?>">Retry payment</a>
            <?php endif; ?>
            <a class="btn btn-secondary" href="my-bookings.php">View my bookings</a>
            <?php if ($booking): ?>
                <a class="btn btn-outline" href="event-detail.php?event_id=<?= (int) $booking['EventID'] ?>">View event</a>
            <?php else: ?>
                <a class="btn btn-outline" href="events.php">Browse events</a>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/shared-components/footer.php'; ?>
