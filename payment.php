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

$auth_user = auth_require_attendee();
eventnest_require_email($auth_user);
$user_id = (int) $auth_user['UserID'];
eventnest_expire_pending($pdo, $user_id);

function payment_positive_id(mixed $value): int
{
    return is_string($value) && ctype_digit($value) && (int) $value > 0 ? (int) $value : 0;
}

function payment_absolute_url(string $path): string
{
    return eventnest_absolute_url($path);
}

function payment_payfast_signature(array $data, string $passphrase = ''): string
{
    return payfast_signature($data, $passphrase);
}

function payment_load_booking(PDO $pdo, int $booking_id, int $user_id): ?array
{
    $stmt = $pdo->prepare(
        "SELECT b.BookingID, b.BookingReference, b.BookingDate, b.Quantity, b.TotalAmount, b.Status,
                b.UserID, b.EventID, b.TicketID,
                e.EventName, e.EventDate, e.Status AS EventStatus,
                t.TicketType, t.Price,
                u.FirstName, u.LastName, u.Email
         FROM BOOKINGS b
         INNER JOIN EVENTS e ON e.EventID = b.EventID
         INNER JOIN TICKETS t ON t.TicketID = b.TicketID AND t.EventID = b.EventID
         INNER JOIN USERS u ON u.UserID = b.UserID
         WHERE b.BookingID = :booking_id AND b.UserID = :user_id
         LIMIT 1"
    );
    $stmt->execute([':booking_id' => $booking_id, ':user_id' => $user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

$booking_id = payment_positive_id($_POST['booking_id'] ?? $_GET['booking_id'] ?? '');
$booking = $booking_id > 0 ? payment_load_booking($pdo, $booking_id, $user_id) : null;

if (!$booking) {
    http_response_code(404);
    $page_title = 'Payment unavailable';
    require_once __DIR__ . '/shared-components/header.php';
    echo '<main class="page-content"><div class="empty-state"><h1>Payment unavailable</h1><p>The booking could not be found.</p><a class="btn btn-outline" href="my-bookings.php">View my bookings</a></div></main>';
    require_once __DIR__ . '/shared-components/footer.php';
    exit;
}

$expected_total = round((float) $booking['TotalAmount'], 2);
$stored_total = round((float) $booking['TotalAmount'], 2);
$errors = [];
$payfast_form = null;

if ($booking['Status'] !== 'pending') {
    if ($booking['Status'] === 'paid') {
        header('Location: ' . eventnest_url('payment-success.php?booking_id=' . $booking_id));
        exit;
    }
    $errors[] = 'This booking is no longer waiting for payment.';
}
if ($expected_total <= 0.0) {
    $errors[] = 'Free bookings do not use PayFast.';
}
if ($booking['Status'] === 'pending' && eventnest_checkout_recent($booking_id)) {
    $errors[] = 'Checkout was opened a moment ago. If PayFast showed an error or you closed it, wait a minute and open this page again; if you already paid, check your bookings for the status.';
}
// A tagged local demo balance may reject sandbox checkout; never simulates a payment.
if (eventnest_demo_insufficient_funds($pdo, $auth_user, $expected_total)) {
    $errors[] = 'Insufficient funds. Your available balance is R10.00. Choose a free event or use another account.';
}
$csrf_token = eventnest_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$errors) {
    if (!eventnest_verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your form session expired. Please try again.';
    } else {
        // Re-read the booking immediately before preparing the gateway request. Retry
        // payment uses the same BookingID and BookingReference rather than creating a new booking.
        $booking = payment_load_booking($pdo, $booking_id, $user_id);
        if (!$booking || $booking['Status'] !== 'pending') {
            $errors[] = 'This booking is no longer waiting for payment.';
        } else {
            $expected_total = round((float) $booking['TotalAmount'], 2);
            if ($expected_total <= 0.0) {
                $errors[] = 'The booking total could not be verified.';
            }
        }
    }

    if (!$errors) {
        $credentials = eventnest_payfast_credentials();
        $merchant_id = $credentials['merchant_id'];
        $merchant_key = $credentials['merchant_key'];
        $passphrase = $credentials['passphrase'];

        if ($merchant_id === '' || $merchant_key === '' || !filter_var(getenv('EVENTNEST_BASE_URL'), FILTER_VALIDATE_URL) || parse_url((string)getenv('EVENTNEST_BASE_URL'), PHP_URL_SCHEME) !== 'https') {
            $errors[] = 'PayFast payment and trusted HTTPS callback URLs must be configured on this server.';
        } else {
            $payfast_form = [
                'merchant_id' => $merchant_id,
                'merchant_key' => $merchant_key,
                'return_url' => payment_absolute_url('payment-success.php?booking_id=' . $booking_id),
                'cancel_url' => payment_absolute_url('payment-cancel.php?booking_id=' . $booking_id),
                'notify_url' => payment_absolute_url('payment-notify.php'),
                'name_first' => eventnest_payfast_text((string) $booking['FirstName'], 100),
                'name_last' => eventnest_payfast_text((string) $booking['LastName'], 100),
                'email_address' => eventnest_payfast_email((string) $booking['Email']),
                'm_payment_id' => (string) $booking['BookingReference'],
                'amount' => number_format($expected_total, 2, '.', ''),
                'item_name' => eventnest_payfast_text('EventNest - ' . (string) $booking['EventName'], 100),
                'custom_int1' => (string) $booking_id,
            ];
            $payfast_form['signature'] = payment_payfast_signature($payfast_form, $passphrase);
            try {
                $pdo->beginTransaction();
                $lock = $pdo->prepare("SELECT Status, BookingDate > DATE_SUB(NOW(), INTERVAL 4 MINUTE) AS Unexpired FROM BOOKINGS WHERE BookingID = ? AND UserID = ? FOR UPDATE");
                $lock->execute([$booking_id, $user_id]);
                $current = $lock->fetch();
                if (!$current || $current['Status'] !== 'pending'
                    || (!(int) $current['Unexpired'] && !eventnest_checkout_started($booking_id))) {
                    throw new DomainException('This reservation has expired. Please book again.');
                }
                if (eventnest_checkout_recent($booking_id)) {
                    throw new DomainException('Checkout was opened a moment ago. Wait a minute before trying again, or check your payment status.');
                }
                eventnest_mark_checkout($booking_id);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $payfast_form = null;
                $errors[] = $exception instanceof DomainException ? $exception->getMessage() : 'Secure checkout is temporarily unavailable.';
            }
        }
    }
}

$payment_mode = (getenv('PAYFAST_MODE') ?: 'sandbox') === 'live' ? 'live' : 'sandbox';
$page_title = 'Secure payment';

require_once __DIR__ . '/shared-components/header.php';
?>
<main class="page-content payment-page">
    <p><a class="btn btn-outline" href="my-bookings.php">Back to my bookings</a></p>
    <h1>Confirm and pay</h1>
    <p>Step 2 of 3 &middot; PayFast <?= escape_html($payment_mode) ?></p>

    <div class="alert alert-info" role="status">
        <?= $payment_mode === 'sandbox' ? 'PayFast sandbox is active for development testing.' : 'PayFast live payment is active.' ?>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-error" role="alert">
            <ul><?php foreach ($errors as $error): ?><li><?= escape_html($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <section class="summary-card" aria-labelledby="payment-summary-heading">
        <h2 id="payment-summary-heading">Order summary</h2>
        <dl>
            <div><dt>Event</dt><dd><?= escape_html($booking['EventName']) ?></dd></div>
            <div><dt>Ticket</dt><dd><?= escape_html($booking['TicketType']) ?> × <?= (int) $booking['Quantity'] ?></dd></div>
            <div><dt>Booking reference</dt><dd><?= escape_html($booking['BookingReference']) ?></dd></div>
            <div><dt>Service fee</dt><dd>R0.00</dd></div>
            <div><dt>Total</dt><dd><?= escape_html(format_money($stored_total)) ?></dd></div>
        </dl>
    </section>

    <?php if ($payfast_form !== null): ?>
        <section class="payment-redirect-card">
            <h2>Continue to PayFast</h2>
            <p>Your booking remains pending until EventNest receives and verifies the provider notification.</p>
            <form method="post" action="<?= escape_html(eventnest_payfast_origin() . '/eng/process') ?>">
                <?php foreach ($payfast_form as $name => $value): ?>
                    <?php if ((string) $value === '') { continue; } ?>
                    <input type="hidden" name="<?= escape_html($name) ?>" value="<?= escape_html((string) $value) ?>">
                <?php endforeach; ?>
                <button class="btn btn-primary" type="submit">Continue to secure payment</button>
            </form>
        </section>
    <?php elseif ($booking['Status'] === 'pending' && !$errors): ?>
        <form method="post" action="payment.php">
            <input type="hidden" name="booking_id" value="<?= $booking_id ?>">
            <input type="hidden" name="csrf_token" value="<?= escape_html($csrf_token) ?>">
            <button class="btn btn-primary" type="submit">Continue to secure payment</button>
        </form>
    <?php elseif ($booking['Status'] === 'pending' && eventnest_checkout_recent($booking_id)): ?>
        <div class="button-row">
            <a class="btn btn-primary" href="payment-success.php?booking_id=<?= $booking_id ?>">Check payment status</a>
            <a class="btn btn-outline" href="payment-cancel.php?booking_id=<?= $booking_id ?>">Cancel this checkout</a>
        </div>
    <?php elseif ($booking['Status'] === 'pending' && $errors && !in_array('This booking is no longer waiting for payment.', $errors, true)): ?>
        <form method="post" action="payment.php">
            <input type="hidden" name="booking_id" value="<?= $booking_id ?>">
            <input type="hidden" name="csrf_token" value="<?= escape_html($csrf_token) ?>">
            <button class="btn btn-primary" type="submit">Try payment setup again</button>
        </form>
    <?php endif; ?>

    <?php if ($booking['Status'] === 'pending' && $expected_total > 0 && eventnest_payfast_simulator_enabled()
        && !eventnest_demo_insufficient_funds($pdo, $auth_user, $expected_total)): ?>
        <section class="payment-simulator" aria-labelledby="simulator-heading">
            <h2 id="simulator-heading">PayFast sandbox unavailable?</h2>
            <p>If PayFast's test system shows an error, you can complete this sandbox booking with a simulated payment. No money is involved, and it is recorded as <strong>PayFast sandbox (simulated)</strong>. This option is never shown in live mode.</p>
            <form method="post" action="payment-simulate.php">
                <input type="hidden" name="booking_id" value="<?= $booking_id ?>">
                <input type="hidden" name="csrf_token" value="<?= escape_html($csrf_token) ?>">
                <button class="btn btn-outline" type="submit">Simulate sandbox payment</button>
            </form>
        </section>
    <?php endif; ?>
</main>
<?php require_once __DIR__ . '/shared-components/footer.php'; ?>
