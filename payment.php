<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/database-config/connection.php';

// ===== INSERT SHARED COMPONENTS CODE HERE =====
// Include the approved auth.php and functions.php files here
// require_once __DIR__ . '/shared-components/auth.php';
// require_once __DIR__ . '/shared-components/functions.php';

function payment_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}


function payment_redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}


/*
 Creates the PayFast signature.
 The passphrase is only used on the server and must not be added
 as a hidden form field.
 */
function payment_payfast_signature(array $data, string $passphrase): string
{
    $parameter_string = '';

    foreach ($data as $key => $value) {
        if ($value !== '' && $value !== null) {
            $parameter_string .=
                $key . '=' . urlencode(trim((string) $value)) . '&';
        }
    }

    $parameter_string = rtrim($parameter_string, '&');

    if ($passphrase !== '') {
        $parameter_string .=
            '&passphrase=' . urlencode(trim($passphrase));
    }

    return md5($parameter_string);
}


/*
 The user must be logged in.
 */
$user_id = filter_var(
    $_SESSION['user_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$user_id || $user_id < 1) {
    payment_redirect('login.php');
}


/*
 Confirm that the logged-in account is an attendee.
 Replaced with Cynthia's shared authentication helper.
 */
$user_statement = $pdo->prepare(
    'SELECT UserID, Role
     FROM USERS
     WHERE UserID = :user_id
     LIMIT 1'
);

$user_statement->execute([
    'user_id' => $user_id
]);

$current_user = $user_statement->fetch();


if (!$current_user || $current_user['Role'] !== 'attendee') {
    http_response_code(403);
    exit('Only attendee accounts can make payments.');
}


/*
 booking.php sends the BookingID in the URL.
 */
$booking_id = filter_input(
    INPUT_GET,
    'booking_id',
    FILTER_VALIDATE_INT
);

if (!$booking_id || $booking_id < 1) {
    http_response_code(400);
    exit('A valid booking is required.');
}


/*
 * Load the booking using both the BookingID and session UserID.
 The ticket EventID is loaded separately so that its relationship
 with the booking can also be checked.
 */
$booking_statement = $pdo->prepare(
    'SELECT
        b.BookingID,
        b.Booking_reference,
        b.Quantity,
        b.TotalAmount,
        b.Status,
        b.UserID,
        b.EventID,
        b.TicketID,

        e.EventName,

        t.EventID AS TicketEventID,
        t.TicketType,
        t.Price

     FROM BOOKINGS b

     INNER JOIN EVENTS e
        ON e.EventID = b.EventID

     INNER JOIN TICKETS t
        ON t.TicketID = b.TicketID

     WHERE b.BookingID = :booking_id
       AND b.UserID = :user_id

     LIMIT 1'
);

$booking_statement->execute([
    'booking_id' => $booking_id,
    'user_id' => $user_id
]);

$booking = $booking_statement->fetch();


if (!$booking) {
    http_response_code(404);
    exit('Booking not found.');
}


/*
 A free or already completed payment should not go through
 PayFast again.
 */
if (
    in_array(
        $booking['Status'],
        ['paid', 'free', 'confirmed'],
        true
    )
) {
    payment_redirect(
        'payment-success.php?booking_id=' . $booking_id
    );
}


/*
 Only pending bookings can continue to PayFast.
 */
if ($booking['Status'] !== 'pending') {
    http_response_code(409);
    exit('This booking cannot be paid in its current state.');
}


/*
 Confirm that the ticket stored against the booking belongs
 to the same event.
 */
if (
    (int) $booking['TicketEventID']
    !==
    (int) $booking['EventID']
) {
    error_log(
        'Ticket/event mismatch for booking ' . $booking_id
    );

    http_response_code(409);
    exit('The booking information could not be verified.');
}


/*
 Recalculate the total from the ticket information stored
 in the database.
 No amount is received from the browser.
 */
$quantity = (int) $booking['Quantity'];
$ticket_price = (float) $booking['Price'];

if ($quantity < 1 || $ticket_price < 0) {
    http_response_code(409);
    exit('The booking amount could not be verified.');
}

$expected_total = round(
    $ticket_price * $quantity,
    2
);

$stored_total = round(
    (float) $booking['TotalAmount'],
    2
);


/*
 payment.php must only handle paid bookings.
 */
if ($expected_total <= 0) {
    http_response_code(409);
    exit('Free bookings do not require payment.');
}


/*
 The booking total must match the total recalculated
 using the ticket price.
 */
if (
    abs($expected_total - $stored_total) > 0.009
) {
    error_log(
        'Payment amount mismatch for booking '
        . $booking_id
    );

    http_response_code(409);
    exit('The booking total could not be verified.');
}


/*
PAYFAST SETTINGS
 */
$merchant_id =
    trim((string) getenv('PAYFAST_MERCHANT_ID'));

$merchant_key =
    trim((string) getenv('PAYFAST_MERCHANT_KEY'));

$passphrase =
    trim((string) getenv('PAYFAST_PASSPHRASE'));


/*
 Sandbox processing URL.
 PAYFAST_PROCESS_URL can later be changed in the server
 configuration without editing this file.
 */
$process_url =
    trim((string) getenv('PAYFAST_PROCESS_URL'));

if ($process_url === '') {
    $process_url =
        'https://sandbox.payfast.co.za/eng/process';
}


/*
 * APP_BASE_URL must point to the EventNest site.
 Example after deployment:
 https://www.eventnest.co.za
 For real sandbox ITN testing, PayFast must be able to reach
 this address from the internet.
 */
$base_url =
    rtrim(
        trim((string) getenv('APP_BASE_URL')),
        '/'
    );


if (
    $merchant_id === ''
    || $merchant_key === ''
    || $base_url === ''
) {
    http_response_code(503);

    exit(
        'Payment settings have not been configured yet.'
    );
}


if (!filter_var($base_url, FILTER_VALIDATE_URL)) {
    http_response_code(503);
    exit('The payment callback URL is not configured correctly.');
}


/*
 * PayFast URLs.
 */
$return_url =
    $base_url
    . '/payment-success.php?booking_id='
    . $booking_id;

$cancel_url =
    $base_url
    . '/payment-cancel.php?booking_id='
    . $booking_id;

$notify_url =
    $base_url
    . '/payment-success.php';


/*
 The PayFast request is generated completely on the server.
 Booking_reference is used as m_payment_id.
 BookingID is also passed through custom_int1 so the ITN can
 verify both values against the database.
 */
$payfast_data = [
    'merchant_id' => $merchant_id,
    'merchant_key' => $merchant_key,

    'return_url' => $return_url,
    'cancel_url' => $cancel_url,
    'notify_url' => $notify_url,

    'm_payment_id' =>
        (string) $booking['Booking_reference'],

    'amount' =>
        number_format(
            $stored_total,
            2,
            '.',
            ''
        ),

    'item_name' =>
        substr(
            (string) $booking['EventName'],
            0,
            100
        ),

    'item_description' =>
        substr(
            'EventNest booking '
            . $booking['Booking_reference']
            . ' - '
            . $booking['TicketType'],
            0,
            255
        ),

    'custom_int1' =>
        (string) $booking_id
];


/*
Add the signature after the other PayFast fields have
been prepared.
 */
$payfast_data['signature'] =
    payment_payfast_signature(
        $payfast_data,
        $passphrase
    );

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Payment | EventNest</title>


    <!-- INSERT THANDO'S SHARED HEADER/CSS COMPONENT LINKS HERE -->

    <style>

        body {
            margin: 0;
            padding: 40px 20px;
            background: #f5f5f5;
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
            background: #fff3cd;
            border: 1px solid #f2d68b;
            border-radius: 5px;
            color: #765600;
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
            border-bottom: none;
            font-size: 18px;
        }

        .buttons {
            display: flex;
            gap: 12px;
            margin-top: 25px;
            align-items: center;
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

    </style>

</head>


<body>


<!-- INSERT SHARED header.php / navbar.php HERE -->


<main class="payment-box">

    <div class="sandbox-message">

        <strong>Sandbox prototype:</strong>
        No real payment will be taken.

    </div>


    <h1>Payment Summary</h1>

    <p>
        Please check your booking details before continuing.
    </p>


    <div class="summary-row">

        <span>Event</span>

        <span>
            <?= payment_escape(
                (string) $booking['EventName']
            ) ?>
        </span>

    </div>


    <div class="summary-row">

        <span>Ticket type</span>

        <span>
            <?= payment_escape(
                (string) $booking['TicketType']
            ) ?>
        </span>

    </div>


    <div class="summary-row">

        <span>Quantity</span>

        <span>
            <?= $quantity ?>
        </span>

    </div>


    <div class="summary-row">

        <span>Booking reference</span>

        <span>
            <?= payment_escape(
                (string) $booking['Booking_reference']
            ) ?>
        </span>

    </div>


    <div class="summary-row total">

        <span>Total</span>

        <span>
            R<?= number_format($stored_total, 2) ?>
        </span>

    </div>


    <div class="buttons">

        <a
            class="back-button"
            href="booking.php?event_id=<?= (int) $booking['EventID'] ?>"
        >
            Back
        </a>


        <form
            method="post"
            action="<?= payment_escape($process_url) ?>"
        >

            <?php foreach ($payfast_data as $name => $value): ?>

                <input
                    type="hidden"
                    name="<?= payment_escape(
                        (string) $name
                    ) ?>"
                    value="<?= payment_escape(
                        (string) $value
                    ) ?>"
                >

            <?php endforeach; ?>


            <button
                class="pay-button"
                type="submit"
            >
                Continue to secure payment
            </button>

        </form>

    </div>


    <p>
        <a href="my-bookings.php">
            View my bookings
        </a>
    </p>

</main>


<!-- INSERT SHARED footer.php HERE -->


</body>
</html>