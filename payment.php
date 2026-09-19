<?php
session_start();

require_once __DIR__ . '/database-config/connection.php';

/*
This checks if the user is logged in. Person A  must not be able to pay
 for person B's booking.
*/
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

/*
Gets the booking ID from booking.php.
*/
$bookingId = filter_input(INPUT_GET, 'booking_id', FILTER_VALIDATE_INT);

if (!$bookingId || $bookingId <= 0) {
    exit('Invalid booking ID.');
}

/*
The user_id check makes sure the booking belongs to the logged-in user.
The status check makes sure that only pending bookings can be paid for.
The event_id check makes sure the selected ticket belongs to that event.
*/
$sql = "
    SELECT
        b.booking_id,
        b.booking_reference,
        b.quantity,
        b.status,
        e.title AS event_title,
        e.event_date,
        t.ticket_name,
        t.price AS ticket_price
    FROM bookings b
    INNER JOIN events e ON b.event_id = e.event_id
    INNER JOIN tickets t
        ON b.ticket_id = t.ticket_id
        AND b.event_id = t.event_id
    WHERE b.booking_id = :booking_id
      AND b.user_id = :user_id
      AND b.status = 'pending'
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':booking_id' => $bookingId,
    ':user_id' => $userId
]);

$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    exit('Booking not found or you are not allowed to pay for this booking.');
}

//Calculates the total from the database
$quantity = (int) $booking['quantity'];
$ticketPrice = (float) $booking['ticket_price'];

if ($quantity <= 0 || $ticketPrice < 0) {
    exit('There is a problem with the booking amount.');
}

$totalAmount = round($quantity * $ticketPrice, 2);

//Ensures that free events don't go through PayFast.

if ($totalAmount <= 0) {
    header('Location: payment-success.php?booking_id=' . $bookingId);
    exit;
}

//PayFast details come from the server environment.
$merchantId = getenv('PAYFAST_MERCHANT_ID');
$merchantKey = getenv('PAYFAST_MERCHANT_KEY');
$passphrase = getenv('PAYFAST_PASSPHRASE');
$siteUrl = rtrim(getenv('APP_URL'), '/');

if (!$merchantId || !$merchantKey || !$siteUrl) {
    exit('Payment settings have not been configured yet.');
}

/*
payment-success.php verifies the PayFast notification before
changing a booking to paid.
*/

$returnUrl = $siteUrl . '/payment-success.php?booking_id=' . $bookingId;
$cancelUrl = $siteUrl . '/payment-cancel.php?booking_id=' . $bookingId;
$notifyUrl = $siteUrl . '/payment-success.php';

//PayFast needs these fields before it can process the payment.

$payfastData = [
    'merchant_id' => $merchantId,
    'merchant_key' => $merchantKey,
    'return_url' => $returnUrl,
    'cancel_url' => $cancelUrl,
    'notify_url' => $notifyUrl,
    'm_payment_id' => $booking['booking_id'],
    'amount' => number_format($totalAmount, 2, '.', ''),
    'item_name' => $booking['event_title'],
    'item_description' => 'Booking reference: ' . $booking['booking_reference']
];

//Create the PayFast signature.The passphrase is only used on the server.

$signatureString = http_build_query($payfastData);

if (!empty($passphrase)) {
    $signatureString .= '&passphrase=' . urlencode($passphrase);
}

$payfastData['signature'] = md5($signatureString);

function safeOutput(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment</title>

    <style>
        body {
            margin: 0;
            padding: 40px 20px;
            background-color: #f5f5f5;
            font-family: Arial, sans-serif;
            color: #222;
        }

        .payment-box {
            max-width: 650px;
            margin: auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .sandbox-message {
            padding: 12px;
            margin-bottom: 20px;
            color: #765600;
            background-color: #fff3cd;
            border: 1px solid #f2d68b;
            border-radius: 5px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 14px 0;
            border-bottom: 1px solid #e5e5e5;
        }

        .summary-row span:last-child {
            text-align: right;
            font-weight: bold;
        }

        .total {
            font-size: 18px;
            border-bottom: none;
        }

        .buttons {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        .back-button,
        .pay-button {
            padding: 12px 18px;
            border-radius: 5px;
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
        }

        .back-button {
            color: #e57210;
            border: 2px solid #f58220;
            background: white;
        }

        .pay-button {
            color: white;
            border: 2px solid #f58220;
            background: #f58220;
        }

        .pay-button:hover {
            background: #d96b0b;
            border-color: #d96b0b;
        }
    </style>
</head>
<body>

    <div class="payment-box">
        <div class="sandbox-message">
            <strong>Sandbox prototype:</strong> No real payment will be taken.
        </div>

        <h1>Payment Summary</h1>
        <p>Please check your booking details before continuing.</p>

        <div class="summary-row">
            <span>Event</span>
            <span><?= safeOutput($booking['event_title']) ?></span>
        </div>

        <div class="summary-row">
            <span>Event date</span>
            <span><?= safeOutput($booking['event_date']) ?></span>
        </div>

        <div class="summary-row">
            <span>Ticket type</span>
            <span><?= safeOutput($booking['ticket_name']) ?></span>
        </div>

        <div class="summary-row">
            <span>Quantity</span>
            <span><?= $quantity ?></span>
        </div>

        <div class="summary-row">
            <span>Booking reference</span>
            <span><?= safeOutput($booking['booking_reference']) ?></span>
        </div>

        <div class="summary-row total">
            <span>Total</span>
            <span>R <?= number_format($totalAmount, 2) ?></span>
        </div>

        <div class="buttons">
            <a class="back-button" href="pages/my-bookings.php">Back</a>

            <form action="https://sandbox.payfast.co.za/eng/process" method="post">
                <?php foreach ($payfastData as $name => $value): ?>
                    <input
                        type="hidden"
                        name="<?= safeOutput($name) ?>"
                        value="<?= safeOutput($value) ?>"
                    >
                <?php endforeach; ?>

                <button class="pay-button" type="submit">
                    Continue to secure payment
                </button>
            </form>
        </div>
    </div>

</body>
</html>