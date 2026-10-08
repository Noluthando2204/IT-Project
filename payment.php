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

function payment_positive_id(mixed $value): int
{
    return is_string($value) && ctype_digit($value) && (int) $value > 0 ? (int) $value : 0;
}

function payment_absolute_url(string $path): string
{
    $configured = trim((string) getenv('EVENTNEST_BASE_URL'));
    if ($configured !== '') {
        return rtrim($configured, '/') . '/' . ltrim($path, '/');
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $https ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $scheme . '://' . $host . eventnest_url($path);
}

function payment_payfast_signature(array $data, string $passphrase = ''): string
{
    $parts = [];
    foreach ($data as $key => $value) {
        if ($key === 'signature' || $value === '' || $value === null) {
            continue;
        }
        $encoded = urlencode(trim((string) $value));
        $parts[] = $key . '=' . $encoded;
    }
    $query = implode('&', $parts);
    if ($passphrase !== '') {
        $query .= '&passphrase=' . urlencode(trim($passphrase));
    }
    return md5($query);
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

$expected_total = round((float) $booking['Price'] * (int) $booking['Quantity'], 2);
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
if (abs($expected_total - $stored_total) > 0.009) {
    error_log('EventNest payment total mismatch for booking ' . $booking_id);
    $errors[] = 'The booking total could not be verified. Please contact EventNest support.';
}

$csrf_token = get_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$errors) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your form session expired. Please try again.';
    } else {
        // Re-read the booking immediately before preparing the gateway request. Retry
        // payment uses the same BookingID and BookingReference rather than creating a new booking.
        $booking = payment_load_booking($pdo, $booking_id, $user_id);
        if (!$booking || $booking['Status'] !== 'pending') {
            $errors[] = 'This booking is no longer waiting for payment.';
        } else {
            $expected_total = round((float) $booking['Price'] * (int) $booking['Quantity'], 2);
            if (abs($expected_total - round((float) $booking['TotalAmount'], 2)) > 0.009 || $expected_total <= 0.0) {
                $errors[] = 'The booking total could not be verified.';
            }
        }
    }

    if (!$errors) {
        $merchant_id = trim((string) getenv('PAYFAST_MERCHANT_ID'));
        $merchant_key = trim((string) getenv('PAYFAST_MERCHANT_KEY'));
        $passphrase = trim((string) getenv('PAYFAST_PASSPHRASE'));

        if ($merchant_id === '' || $merchant_key === '') {
            $errors[] = 'Sandbox payment is not configured on this server yet.';
        } else {
            $payfast_form = [
                'merchant_id' => $merchant_id,
                'merchant_key' => $merchant_key,
                'return_url' => payment_absolute_url('payment-success.php?booking_id=' . $booking_id),
                'cancel_url' => payment_absolute_url('payment-cancel.php?booking_id=' . $booking_id),
                'notify_url' => payment_absolute_url('payment-success.php'),
                'name_first' => (string) $booking['FirstName'],
                'name_last' => (string) $booking['LastName'],
                'email_address' => (string) $booking['Email'],
                'm_payment_id' => (string) $booking['BookingReference'],
                'amount' => number_format($expected_total, 2, '.', ''),
                'item_name' => mb_substr('EventNest - ' . (string) $booking['EventName'], 0, 100, 'UTF-8'),
                'custom_int1' => (string) $booking_id,
            ];
            $payfast_form['signature'] = payment_payfast_signature($payfast_form, $passphrase);
        }
    }
}

$page_title = 'Secure sandbox payment';
require_once __DIR__ . '/shared-components/header.php';
?>
<main class="page-content payment-page">
    <p><a class="btn btn-outline" href="my-bookings.php">Back to my bookings</a></p>
    <h1>Confirm and pay</h1>
    <p>Step 2 of 3 · PayFast sandbox</p>

    <div class="alert alert-info" role="status">
        <strong>Prototype sandbox:</strong> this EventNest build uses PayFast sandbox for development testing.
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
            <form method="post" action="https://sandbox.payfast.co.za/eng/process">
                <?php foreach ($payfast_form as $name => $value): ?>
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
    <?php elseif ($booking['Status'] === 'pending' && $errors && !in_array('This booking is no longer waiting for payment.', $errors, true)): ?>
        <form method="post" action="payment.php">
            <input type="hidden" name="booking_id" value="<?= $booking_id ?>">
            <input type="hidden" name="csrf_token" value="<?= escape_html($csrf_token) ?>">
            <button class="btn btn-primary" type="submit">Try payment setup again</button>
        </form>
    <?php endif; ?>
</main>
<?php require_once __DIR__ . '/shared-components/footer.php'; ?>
