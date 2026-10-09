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

function payfast_signature(array $data, string $passphrase = ''): string
{
    $parts = [];
    foreach ($data as $key => $value) {
        if ($key === 'signature' || $value === '' || $value === null || is_array($value)) {
            continue;
        }
        $parts[] = $key . '=' . urlencode(trim((string) $value));
    }
    $query = implode('&', $parts);
    if ($passphrase !== '') {
        $query .= '&passphrase=' . urlencode(trim($passphrase));
    }
    return md5($query);
}

function payfast_validate_with_provider(array $data): bool
{
    if (!function_exists('curl_init')) {
        error_log('EventNest PayFast validation unavailable: cURL extension missing.');
        return false;
    }

    $validation_data = $data;
    unset($validation_data['signature']);
    $body = http_build_query($validation_data, '', '&', PHP_QUERY_RFC3986);

    $curl = curl_init('https://sandbox.payfast.co.za/eng/query/validate');
    if ($curl === false) {
        return false;
    }

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'EventNest-PayFast-Sandbox/1.0',
    ]);

    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    return $status === 200 && is_string($response) && trim($response) === 'VALID';
}

function payment_success_identifier(mixed $value): int
{
    return is_string($value) && ctype_digit($value) && (int) $value > 0 ? (int) $value : 0;
}

function payment_success_record_itn(PDO $pdo, array $post): void
{
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
    if ($posted_merchant !== '' && !hash_equals($merchant_id, $posted_merchant)) {
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
                    b.EventID, b.TicketID, t.Price
             FROM BOOKINGS b
             INNER JOIN TICKETS t ON t.TicketID = b.TicketID AND t.EventID = b.EventID
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

        $existing_stmt = $pdo->prepare('SELECT PaymentID, PaymentStatus FROM PAYMENTS WHERE TransactionID = :transaction_id LIMIT 1 FOR UPDATE');
        $existing_stmt->execute([':transaction_id' => $transaction_id]);
        $existing_payment = $existing_stmt->fetch(PDO::FETCH_ASSOC);

        if ($provider_status === 'COMPLETE') {
            // Verified provider success is authoritative over an earlier explicit cancel or timeout.
            // If that cancellation already restored stock, re-reserve the quantity where possible.
            if ($booking['Status'] === 'canceled') {
                $ticket_stmt = $pdo->prepare('SELECT QuantityAvailable FROM TICKETS WHERE TicketID = :ticket_id LIMIT 1 FOR UPDATE');
                $ticket_stmt->execute([':ticket_id' => (int) $booking['TicketID']]);
                $available = (int) ($ticket_stmt->fetchColumn() ?: 0);
                if ($available >= (int) $booking['Quantity']) {
                    $reserve_stmt = $pdo->prepare('UPDATE TICKETS SET QuantityAvailable = QuantityAvailable - :quantity WHERE TicketID = :ticket_id AND QuantityAvailable >= :quantity');
                    $reserve_stmt->execute([':quantity' => (int) $booking['Quantity'], ':ticket_id' => (int) $booking['TicketID']]);
                } else {
                    // Provider payment truth must not be discarded. The inventory mismatch is
                    // retained for admin review instead of inventing a new booking/payment state.
                    error_log('EventNest late verified payment inventory mismatch for booking ' . (int) $booking['BookingID']);
                }
            }

            if (!in_array($booking['Status'], ['refunded'], true)) {
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

$auth_user = require_attendee();
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
