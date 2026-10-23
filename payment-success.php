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

function payment_success_identifier(mixed $value): int
{
    return is_string($value) && ctype_digit($value) && (int) $value > 0 ? (int) $value : 0;
}

function payment_success_record_itn(PDO $pdo, array $post): void
{
    foreach ($post as $value) {
        if (!is_scalar($value)) {
            throw new RuntimeException('Invalid payment notification value.');
        }
    }
    $merchant_id = trim((string) getenv('PAYFAST_MERCHANT_ID'));
    $passphrase = trim((string) getenv('PAYFAST_PASSPHRASE'));

    $received_signature = strtolower(trim((string) ($post['signature'] ?? '')));
    $reference = trim((string) ($post['m_payment_id'] ?? ''));
    $provider_status = strtoupper(trim((string) ($post['payment_status'] ?? '')));
    $transaction_id = trim((string) ($post['pf_payment_id'] ?? ''));
    $posted_merchant = trim((string) ($post['merchant_id'] ?? ''));
    $amount_gross = (string) ($post['amount_gross'] ?? '');

    if ($merchant_id === '' || $received_signature === '' || $reference === '' || $transaction_id === '' || !is_numeric($amount_gross)) {
        throw new RuntimeException('Invalid payment notification shape.');
    }
    if ($posted_merchant === '' || !hash_equals($merchant_id, $posted_merchant)) {
        throw new RuntimeException('Payment merchant mismatch.');
    }

    $expected_signature = strtolower(payfast_signature($post, $passphrase));
    if (!hash_equals($expected_signature, $received_signature)) {
        throw new RuntimeException('Payment notification signature failed.');
    }
    if (!payfast_validate_with_provider($post)) {
        throw new RuntimeException('Payment notification provider validation failed.');
    }

    $pdo->beginTransaction();
    try {
        $booking_stmt = $pdo->prepare(
            "SELECT b.BookingID, b.BookingReference, b.Status, b.Quantity, b.TotalAmount,
                    b.EventID, b.TicketID, t.Price, e.Status AS EventStatus
             FROM BOOKINGS b
             INNER JOIN TICKETS t ON t.TicketID = b.TicketID AND t.EventID = b.EventID
             INNER JOIN EVENTS e ON e.EventID = b.EventID
             WHERE b.BookingReference = :reference
             LIMIT 1 FOR UPDATE"
        );
        $booking_stmt->execute([':reference' => $reference]);
        $booking = $booking_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$booking) {
            throw new RuntimeException('Payment booking not found.');
        }

        $expected_amount = round((float) $booking['Price'] * (int) $booking['Quantity'], 2);
        $stored_amount = round((float) $booking['TotalAmount'], 2);
        $received_amount = round((float) $amount_gross, 2);
        if ($expected_amount <= 0.0 || abs($expected_amount - $stored_amount) > 0.009 || abs($stored_amount - $received_amount) > 0.009) {
            throw new RuntimeException('Payment amount mismatch.');
        }

        $existing_stmt = $pdo->prepare('SELECT PaymentID, BookingID, PaymentStatus, Escrow_status FROM PAYMENTS WHERE TransactionID = :transaction_id LIMIT 1 FOR UPDATE');
        $existing_stmt->execute([':transaction_id' => $transaction_id]);
        $existing_payment = $existing_stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_payment && (int) $existing_payment['BookingID'] !== (int) $booking['BookingID']) {
            throw new RuntimeException('Payment transaction belongs to another booking.');
        }
        // Retries must never reset released escrow or undo a verified refund.
        if ($existing_payment && in_array($existing_payment['PaymentStatus'], ['success', 'refunded'], true)) {
            $pdo->commit();
            return;
        }
        if ($booking['Status'] === 'refunded') {
            throw new RuntimeException('Payment notification requires review for a refunded booking.');
        }

        if ($provider_status === 'COMPLETE') {
            // Keep a late charge for a canceled event held for refund review, without reviving the booking.
            $inventory_available = ($booking['EventStatus'] ?? 'published') !== 'canceled';
            $other_success = $pdo->prepare("SELECT PaymentID FROM PAYMENTS WHERE BookingID = ? AND PaymentStatus IN ('success','refunded') AND (TransactionID IS NULL OR TransactionID <> ?) LIMIT 1 FOR UPDATE");
            $other_success->execute([(int) $booking['BookingID'], $transaction_id]);
            if ($other_success->fetchColumn()) {
                throw new RuntimeException('Another verified transaction already exists for this booking; provider review is required.');
            }
            // Verified provider success is authoritative over an earlier explicit cancel or timeout.
            // If that cancellation already restored stock, re-reserve the quantity where possible.
            if ($booking['Status'] === 'canceled' && $inventory_available) {
                $ticket_stmt = $pdo->prepare('SELECT QuantityAvailable FROM TICKETS WHERE TicketID = :ticket_id LIMIT 1 FOR UPDATE');
                $ticket_stmt->execute([':ticket_id' => (int) $booking['TicketID']]);
                $available = (int) ($ticket_stmt->fetchColumn() ?: 0);
                if ($available >= (int) $booking['Quantity']) {
                    $reserve_stmt = $pdo->prepare('UPDATE TICKETS SET QuantityAvailable = QuantityAvailable - :quantity WHERE TicketID = :ticket_id AND QuantityAvailable >= :minimum_quantity');
                    $reserve_stmt->execute([':quantity' => (int) $booking['Quantity'], ':minimum_quantity' => (int) $booking['Quantity'], ':ticket_id' => (int) $booking['TicketID']]);
                } else {
                    // Provider payment truth must not be discarded. The inventory mismatch is
                    // retained for admin review instead of inventing a new booking/payment state.
                    error_log('EventNest late verified payment inventory mismatch for booking ' . (int) $booking['BookingID']);
                    $inventory_available = false;
                }
            }

            if ($inventory_available && !in_array($booking['Status'], ['refunded'], true)) {
                $update_booking = $pdo->prepare("UPDATE BOOKINGS SET Status = 'paid' WHERE BookingID = :booking_id AND Status IN ('pending','canceled','failed','paid')");
                $update_booking->execute([':booking_id' => (int) $booking['BookingID']]);
            }

            if ($existing_payment) {
                $payment_stmt = $pdo->prepare(
                    "UPDATE PAYMENTS
                     SET PaymentDate = NOW(), Amount = :amount, PaymentMethod = 'PayFast Sandbox',
                         PaymentStatus = 'success', Escrow_status = 'held', BookingID = :booking_id
                     WHERE PaymentID = :payment_id"
                );
                $payment_stmt->execute([
                    ':amount' => number_format($stored_amount, 2, '.', ''),
                    ':booking_id' => (int) $booking['BookingID'],
                    ':payment_id' => (int) $existing_payment['PaymentID'],
                ]);
            } else {
                $payment_stmt = $pdo->prepare(
                    "INSERT INTO PAYMENTS
                        (BookingID, PaymentDate, Amount, PaymentMethod, PaymentStatus, TransactionID, Escrow_status)
                     VALUES
                        (:booking_id, NOW(), :amount, 'PayFast Sandbox', 'success', :transaction_id, 'held')"
                );
                $payment_stmt->execute([
                    ':booking_id' => (int) $booking['BookingID'],
                    ':amount' => number_format($stored_amount, 2, '.', ''),
                    ':transaction_id' => $transaction_id,
                ]);
            }
        } elseif ($provider_status === 'FAILED') {
            // A verified failed attempt does not cancel the pending booking. The attendee can
            // retry the same BookingID until explicit cancellation or the four-minute cleanup.
            if ($existing_payment) {
                $failed_stmt = $pdo->prepare(
                    "UPDATE PAYMENTS
                     SET PaymentDate = NOW(), Amount = :amount, PaymentMethod = 'PayFast Sandbox',
                         PaymentStatus = 'failed', Escrow_status = NULL, BookingID = :booking_id
                     WHERE PaymentID = :payment_id AND PaymentStatus <> 'success'"
                );
                $failed_stmt->execute([
                    ':amount' => number_format($stored_amount, 2, '.', ''),
                    ':booking_id' => (int) $booking['BookingID'],
                    ':payment_id' => (int) $existing_payment['PaymentID'],
                ]);
            } else {
                $failed_stmt = $pdo->prepare(
                    "INSERT INTO PAYMENTS
                        (BookingID, PaymentDate, Amount, PaymentMethod, PaymentStatus, TransactionID, Escrow_status)
                     VALUES
                        (:booking_id, NOW(), :amount, 'PayFast Sandbox', 'failed', :transaction_id, NULL)"
                );
                $failed_stmt->execute([
                    ':booking_id' => (int) $booking['BookingID'],
                    ':amount' => number_format($stored_amount, 2, '.', ''),
                    ':transaction_id' => $transaction_id,
                ]);
            }
        } else {
            throw new RuntimeException('Unsupported payment status.');
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

// PayFast server notification path. A browser GET never enters this branch and therefore
// can never change a payment or booking state by merely visiting this page.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['m_payment_id'], $_POST['signature'])) {
    header('Content-Type: text/plain; charset=utf-8');
    try {
        payment_success_record_itn($pdo, $_POST);
        http_response_code(200);
        echo 'OK';
    } catch (Throwable $exception) {
        error_log('EventNest PayFast ITN rejected: ' . $exception->getMessage());
        http_response_code(400);
        echo 'INVALID';
    }
    exit;
}

$auth_user = auth_require_attendee();
$user_id = (int) $auth_user['UserID'];
$booking_id = payment_success_identifier($_GET['booking_id'] ?? '');

$booking_stmt = $pdo->prepare(
    "SELECT b.BookingID, b.BookingReference, b.Quantity, b.TotalAmount, b.Status,
            e.EventID, e.EventName, e.EventDate, e.Venue, e.Format,
            t.TicketType,
            EXISTS(
                SELECT 1 FROM PAYMENTS p
                WHERE p.BookingID = b.BookingID AND p.PaymentStatus = 'success'
            ) AS HasVerifiedPayment
     FROM BOOKINGS b
     INNER JOIN EVENTS e ON e.EventID = b.EventID
     INNER JOIN TICKETS t ON t.TicketID = b.TicketID AND t.EventID = b.EventID
     WHERE b.BookingID = :booking_id AND b.UserID = :user_id
     LIMIT 1"
);
$booking_stmt->execute([':booking_id' => $booking_id, ':user_id' => $user_id]);
$booking = $booking_stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    http_response_code(404);
    $page_title = 'Booking not found';
    require_once __DIR__ . '/shared-components/header.php';
    echo '<main class="page-content"><div class="empty-state"><h1>Booking not found</h1><p>This booking is not available for your account.</p><a class="btn btn-outline" href="my-bookings.php">View my bookings</a></div></main>';
    require_once __DIR__ . '/shared-components/footer.php';
    exit;
}

$is_free = (float) $booking['TotalAmount'] <= 0.0;
$is_confirmed = $booking['Status'] === 'paid' && ($is_free || (int) $booking['HasVerifiedPayment'] === 1);
$is_pending = $booking['Status'] === 'pending';

$page_title = $is_confirmed ? 'Booking confirmed' : 'Payment status';
require_once __DIR__ . '/shared-components/header.php';
?>
<main class="page-content payment-result-page">
    <section class="result-card">
        <?php if ($is_confirmed): ?>
            <div class="status status-success">Confirmed</div>
            <h1>Booking confirmed</h1>
            <p><?= $is_free ? 'Your free booking is confirmed.' : 'EventNest has verified the PayFast payment for this booking.' ?></p>
        <?php elseif ($is_pending): ?>
            <div class="status status-warning">Payment pending</div>
            <h1>Payment is still being verified</h1>
            <p>Returning to this page does not prove payment. EventNest will only confirm the booking after a valid provider notification is verified.</p>
        <?php else: ?>
            <div class="status status-warning"><?= escape_html(ucfirst((string) $booking['Status'])) ?></div>
            <h1>Booking status updated</h1>
            <p>This booking is not currently in a confirmed payment state.</p>
        <?php endif; ?>

        <dl>
            <div><dt>Event</dt><dd><?= escape_html($booking['EventName']) ?></dd></div>
            <div><dt>Date</dt><dd><?= escape_html(format_datetime($booking['EventDate'])) ?></dd></div>
            <div><dt>Ticket</dt><dd><?= escape_html($booking['TicketType']) ?> × <?= (int) $booking['Quantity'] ?></dd></div>
            <div><dt>Booking reference</dt><dd><?= escape_html($booking['BookingReference']) ?></dd></div>
            <div><dt>Total</dt><dd><?= escape_html(format_money($booking['TotalAmount'])) ?></dd></div>
        </dl>

        <div class="button-row">
            <a class="btn btn-secondary" href="my-bookings.php">View my bookings</a>
            <?php if ($is_pending): ?>
                <a class="btn btn-primary" href="payment.php?booking_id=<?= (int) $booking['BookingID'] ?>">Retry payment</a>
            <?php endif; ?>
            <a class="btn btn-outline" href="events.php">Browse events</a>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/shared-components/footer.php'; ?>
