<?php

declare(strict_types=1);

// Keep old notify URLs working without starting a browser session.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require __DIR__ . '/payment-notify.php';
    exit;
}
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
            ) AS HasVerifiedPayment,
            (SELECT p2.PaymentMethod FROM PAYMENTS p2
             WHERE p2.BookingID = b.BookingID AND p2.PaymentStatus = 'success' LIMIT 1) AS PaymentMethodUsed
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
<main class="page-content payment-result-page" data-payment-pending="<?= $is_pending ? '1' : '0' ?>" data-status-url="<?= escape_html(eventnest_url('payment-status.php?booking_id=' . $booking_id)) ?>">
    <section class="result-card">
        <?php if ($is_confirmed): ?>
            <div class="status status-success">Confirmed</div>
            <h1>Booking confirmed</h1>
            <p><?= $is_free ? 'Your free booking is confirmed.' : (str_contains((string) ($booking['PaymentMethodUsed'] ?? ''), 'simulated') ? 'Your sandbox payment was simulated because the PayFast sandbox was unavailable. No money was taken.' : 'EventNest has verified the PayFast payment for this booking.') ?></p>
        <?php elseif ($is_pending): ?>

            <div class="status status-warning">Payment pending</div>
            <h1>Verifying your payment...</h1>
            <p>Payment confirmation is being processed. We will check for up to one minute. You can also check My bookings later.</p>
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

        <?php if ($is_confirmed): ?>
        <div class="result-next">
            <h2>What happens next</h2>
            <ul>
                <li>Your ticket is saved under <a href="my-bookings.php">My bookings</a>, and a confirmation appears in your notifications.</li>
                <li>You can cancel from My bookings while the event is more than 24 hours away.</li>
                <li>After the event, you can leave the organiser a private review.</li>
            </ul>
        </div>
        <?php endif; ?>

        <div class="button-row">
            <a class="btn btn-secondary" href="my-bookings.php">View my bookings</a>
            <?php if ($is_pending): ?>
                <a class="btn btn-primary" href="payment-success.php?booking_id=<?= (int) $booking['BookingID'] ?>">Check payment status</a>
            <?php endif; ?>
            <a class="btn btn-outline" href="events.php">Browse events</a>
        </div>
    </section>
</main>
<script src="<?= escape_html(eventnest_url('scripts/payment-status.js')) ?>" defer></script>
<?php require_once __DIR__ . '/shared-components/footer.php'; ?>
