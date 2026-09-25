<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/database-config/connection.php';

// Include  functions.php  
// require_once __DIR__ . '/shared-components/functions.php';

function payment_success_escape(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
 Converts PayFast POST values into simple strings.
 Array values are ignored because the expected PayFast fields
 are scalar values.
 */
function normalise_payfast_data(array $source): array
{
    $result = [];

    foreach ($source as $key => $value) {

        if (is_scalar($value)) {
            $result[(string) $key] =
                trim((string) $value);
        }
    }

    return $result;
}


/*
 * Creates the parameter string used for PayFast validation.
 The signature field itself is not included when calculating
 the signature.
 */
function payfast_parameter_string(array $data): string
{
    $parts = [];

    foreach ($data as $key => $value) {

        if (
            $key === 'signature'
            || $value === ''
        ) {
            continue;
        }

        $parts[] =
            $key
            . '='
            . urlencode(
                trim((string) $value)
            );
    }

    return implode('&', $parts);
}


/*
 Calculates the signature from the PayFast notification.
 */
function payment_success_signature(
    string $parameter_string,
    string $passphrase
): string {

    if ($passphrase !== '') {

        $parameter_string .=
            '&passphrase='
            . urlencode(
                trim($passphrase)
            );
    }

    return md5($parameter_string);
}


/*
 Sends the notification information back to PayFast
 for server-to-server confirmation.
 */
function payfast_server_validation(
    string $parameter_string
): bool {

    $validation_url =
        trim(
            (string) getenv(
                'PAYFAST_VALIDATE_URL'
            )
        );

    if ($validation_url === '') {
        $validation_url =
            'https://sandbox.payfast.co.za/eng/query/validate';
    }


    if (!function_exists('curl_init')) {
        error_log(
            'PayFast validation failed because cURL is not enabled.'
        );

        return false;
    }


    $curl = curl_init();

    curl_setopt_array(
        $curl,
        [
            CURLOPT_URL => $validation_url,
            CURLOPT_POST => true,

            CURLOPT_POSTFIELDS =>
                $parameter_string,

            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,

            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,

            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => true,

            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ]
        ]
    );


    $response = curl_exec($curl);

    $http_code = (int) curl_getinfo(
        $curl,
        CURLINFO_HTTP_CODE
    );


    if ($response === false) {

        error_log(
            'PayFast server validation request failed: '
            . curl_error($curl)
        );

        curl_close($curl);

        return false;
    }


    curl_close($curl);


    return
        $http_code === 200
        && trim((string) $response) === 'VALID';
}


/*
 Only a POST notification may change the payment state.
 A normal browser GET further below is display-only.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $notification =
        normalise_payfast_data($_POST);


    /*
     Load the configured PayFast details.
     */
    $merchant_id =
        trim(
            (string) getenv(
                'PAYFAST_MERCHANT_ID'
            )
        );

    $passphrase =
        trim(
            (string) getenv(
                'PAYFAST_PASSPHRASE'
            )
        );


    if ($merchant_id === '') {
        error_log(
            'PayFast merchant ID is not configured.'
        );

        http_response_code(500);
        exit('Payment could not be verified.');
    }


    /*
     Confirm that the notification is for EventNest's
     configured PayFast merchant account.
     */
    $received_merchant =
        $notification['merchant_id'] ?? '';

    if (
        $received_merchant === ''
        || !hash_equals(
            $merchant_id,
            $received_merchant
        )
    ) {
        error_log(
            'PayFast merchant ID mismatch.'
        );

        http_response_code(400);
        exit('Invalid notification.');
    }


    /*
     Validate the PayFast signature.
     */
    $received_signature =
        $notification['signature'] ?? '';

    if ($received_signature === '') {
        http_response_code(400);
        exit('Missing signature.');
    }


    $parameter_string =
        payfast_parameter_string(
            $notification
        );


    $calculated_signature =
        payment_success_signature(
            $parameter_string,
            $passphrase
        );


    if (
        !hash_equals(
            $calculated_signature,
            $received_signature
        )
    ) {
        error_log(
            'PayFast signature mismatch.'
        );

        http_response_code(400);
        exit('Invalid notification.');
    }


    /*
    Confirm the notification directly with PayFast.
     */
    if (
        !payfast_server_validation(
            $parameter_string
        )
    ) {
        error_log(
            'PayFast server validation failed.'
        );

        http_response_code(400);
        exit('Invalid notification.');
    }

    $booking_reference =
        trim(
            (string) (
                $notification['m_payment_id']
                ?? ''
            )
        );


    $booking_id =
        filter_var(
            $notification['custom_int1']
                ?? null,
            FILTER_VALIDATE_INT
        );


    $payment_status =
        strtoupper(
            trim(
                (string) (
                    $notification['payment_status']
                    ?? ''
                )
            )
        );


    $transaction_id =
        trim(
            (string) (
                $notification['pf_payment_id']
                ?? ''
            )
        );


    $gross_amount =
        filter_var(
            $notification['amount_gross']
                ?? null,
            FILTER_VALIDATE_FLOAT
        );


    /*
    The payment can only be finalised when all required
     notification fields are present.
     */
    if (
        !$booking_id
        || $booking_id < 1
        || $booking_reference === ''
        || $payment_status !== 'COMPLETE'
        || $transaction_id === ''
        || $gross_amount === false
    ) {
        http_response_code(400);
        exit('Invalid payment data.');
    }


    try {

        $pdo->beginTransaction();


        /*
        Lock the booking while the PayFast payment is being
        processed. Both BookingID and Booking_reference must match.
         */
        $booking_statement = $pdo->prepare(
            'SELECT
                b.BookingID,
                b.Booking_reference,
                b.Quantity,
                b.TotalAmount,
                b.Status,
                b.EventID,
                b.TicketID,

                t.EventID AS TicketEventID,
                t.Price

             FROM BOOKINGS b

             INNER JOIN TICKETS t
                ON t.TicketID = b.TicketID

             WHERE b.BookingID = :booking_id
               AND b.Booking_reference = :booking_reference

             LIMIT 1

             FOR UPDATE'
        );


        $booking_statement->execute([
            'booking_id' => $booking_id,

            'booking_reference' =>
                $booking_reference
        ]);


        $booking =
            $booking_statement->fetch();


        if (!$booking) {
            throw new RuntimeException(
                'Unknown booking or booking reference.'
            );
        }


        /*
         Confirm that the ticket still belongs to the booking event.
         */
        if (
            (int) $booking['TicketEventID']
            !==
            (int) $booking['EventID']
        ) {
            throw new RuntimeException(
                'Ticket/event mismatch.'
            );
        }


        /*
         Recalculate the amount again from the ticket information.
         */
        $stored_amount =
            round(
                (float) $booking['TotalAmount'],
                2
            );


        $recalculated_amount =
            round(
                (float) $booking['Price']
                *
                (int) $booking['Quantity'],
                2
            );


        /*
         The database total, recalculated total and PayFast
         amount must all agree.
         */
        if (
            $stored_amount <= 0
            ||
            abs(
                $stored_amount
                - $recalculated_amount
            ) > 0.009
            ||
            abs(
                $stored_amount
                - (float) $gross_amount
            ) > 0.009
        ) {
            throw new RuntimeException(
                'Payment amount mismatch.'
            );
        }


        /*
         Checks whether this PayFast transaction has already
         been saved. PayFast may send the same valid 
         notification again,so repeated notifications must 
         not create duplicate PAYMENTS records.
         */
        $duplicate_statement = $pdo->prepare(
            'SELECT
                PaymentID,
                BookingID

             FROM PAYMENTS

             WHERE Payfast_transaction_id =
                   :transaction_id

             LIMIT 1'
        );


        $duplicate_statement->execute([
            'transaction_id' =>
                $transaction_id
        ]);


        $duplicate_payment =
            $duplicate_statement->fetch();


        if ($duplicate_payment) {

            if (
                (int) $duplicate_payment['BookingID']
                !==
                (int) $booking_id
            ) {
                throw new RuntimeException(
                    'Transaction belongs to another booking.'
                );
            }


            /*
             Same PayFast transaction received again.
             Nothing else needs to be inserted.
             */
            $pdo->commit();

            http_response_code(200);
            exit('OK');
        }


        /*
         A new successful payment may only complete a pending booking.
         */
        if ($booking['Status'] !== 'pending') {

            throw new RuntimeException(
                'Booking is not pending.'
            );
        }


        /*
        Mark the booking as paid.
         */
        $update_booking = $pdo->prepare(
            "UPDATE BOOKINGS
             SET Status = 'paid'
             WHERE BookingID = :booking_id
               AND Status = 'pending'"
        );


        $update_booking->execute([
            'booking_id' => $booking_id
        ]);


        if ($update_booking->rowCount() !== 1) {
            throw new RuntimeException(
                'Booking payment state changed.'
            );
        }


        /*
         Save the successful PayFast payment.
         The payment stays held until the EventNest escrow
         process later releases it.
         */
        $insert_payment = $pdo->prepare(
            "INSERT INTO PAYMENTS
                (
                    PaymentDate,
                    Amount,
                    PaymentMethod,
                    PaymentStatus,
                    Payfast_transaction_id,
                    Escrow_status,
                    BookingID
                )

             VALUES
                (
                    NOW(),
                    :amount,
                    'PayFast',
                    'success',
                    :transaction_id,
                    'held',
                    :booking_id
                )"
        );


        $insert_payment->execute([
            'amount' =>
                number_format(
                    $stored_amount,
                    2,
                    '.',
                    ''
                ),

            'transaction_id' =>
                $transaction_id,

            'booking_id' =>
                $booking_id
        ]);


        $pdo->commit();


        /*
         * PayFast only needs a successful HTTP response here.
         */
        http_response_code(200);
        exit('OK');


    } catch (Throwable $exception) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }


        error_log(
            'PayFast payment processing failed: '
            . $exception->getMessage()
        );


        http_response_code(400);

        exit(
            'Payment could not be verified.'
        );
    }
}


/*
 Displays the payment state that has already been
 verified and stored.
 */
$user_id =
    filter_var(
        $_SESSION['user_id'] ?? null,
        FILTER_VALIDATE_INT
    );


if (!$user_id || $user_id < 1) {
    header('Location: login.php');
    exit;
}


/*
Confirm the session belongs to an attendee.
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

$current_user =
    $user_statement->fetch();


if (
    !$current_user
    || $current_user['Role'] !== 'attendee'
) {
    http_response_code(403);

    exit(
        'Only attendee accounts can view this payment result.'
    );
}


$booking_id =
    filter_input(
        INPUT_GET,
        'booking_id',
        FILTER_VALIDATE_INT
    );


if (!$booking_id || $booking_id < 1) {
    http_response_code(400);

    exit(
        'A valid booking is required.'
    );
}


/*
 Load the booking and the latest payment record.
 The browser cannot supply any payment status or amount.
 */
$display_statement = $pdo->prepare(
    'SELECT
        b.BookingID,
        b.Booking_reference,
        b.Quantity,
        b.TotalAmount,
        b.Status,
        b.UserID,
        b.EventID,

        e.EventName,

        t.TicketType,

        p.PaymentStatus,
        p.Escrow_status

     FROM BOOKINGS b

     INNER JOIN EVENTS e
        ON e.EventID = b.EventID

     INNER JOIN TICKETS t
        ON t.TicketID = b.TicketID

     LEFT JOIN PAYMENTS p
        ON p.BookingID = b.BookingID

     WHERE b.BookingID = :booking_id
       AND b.UserID = :user_id

     ORDER BY p.PaymentDate DESC

     LIMIT 1'
);


$display_statement->execute([
    'booking_id' => $booking_id,
    'user_id' => $user_id
]);


$booking =
    $display_statement->fetch();


if (!$booking) {
    http_response_code(404);
    exit('Booking not found.');
}


/*
 Free bookings do not create a PAYMENTS record.
 */
$is_free =
    in_array(
        $booking['Status'],
        ['free', 'confirmed'],
        true
    )
    &&
    (float) $booking['TotalAmount'] <= 0;


/*
 A paid booking is only displayed as successfully paid
 when a successful PAYMENTS record also exists.
 */
$is_verified_paid =
    in_array(
        $booking['Status'],
        ['paid', 'confirmed'],
        true
    )
    &&
    $booking['PaymentStatus'] === 'success';


if ($is_free || $is_verified_paid) {

    $title =
        'Booking confirmed';

    $message =
        'Your EventNest booking has been recorded successfully.';

} else {

    $title =
        'Payment is still being verified';

    $message =
        'EventNest has not received a verified successful payment yet. Please check My bookings before trying the payment again.';
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= payment_success_escape($title) ?>
        | EventNest
    </title>


    <!-- INSERT THANDO'S SHARED HEADER/CSS COMPONENT LINKS HERE -->

    <style>

        body {
            margin: 0;
            padding: 40px 20px;
            background: #f5f5f5;
            font-family: Arial, sans-serif;
            color: #222;
        }

        .result-card {
            max-width: 650px;
            margin: auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .status-message {
            padding: 14px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .success-message {
            color: #155724;
            background: #d4edda;
            border: 1px solid #b8dfc2;
        }

        .waiting-message {
            color: #765600;
            background: #fff3cd;
            border: 1px solid #f2d68b;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 13px 0;
            border-bottom: 1px solid #eeeeee;
        }

        .summary-row span:last-child {
            text-align: right;
            font-weight: bold;
        }

        .buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 28px;
        }

        .button {
            padding: 12px 18px;
            border-radius: 5px;
            font-weight: bold;
            text-decoration: none;
        }

        .bookings-button {
            color: white;
            background: #1877f2;
            border: 2px solid #1877f2;
        }

        .outline-button {
            color: #1877f2;
            background: white;
            border: 2px solid #1877f2;
        }

    </style>

</head>


<body>


<!-- INSERT SHARED header.php / navbar.php HERE -->


<main class="result-card">


    <?php if ($is_free || $is_verified_paid): ?>

        <div class="status-message success-message">

            <strong>
                Booking confirmed.
            </strong>

            Your booking has been recorded successfully.

        </div>

    <?php else: ?>

        <div class="status-message waiting-message">

            <strong>
                Payment verification pending.
            </strong>

            EventNest has not confirmed a successful payment yet.

        </div>

    <?php endif; ?>


    <h1>
        <?= payment_success_escape($title) ?>
    </h1>


    <p>
        <?= payment_success_escape($message) ?>
    </p>


    <div class="summary-row">

        <span>Booking reference</span>

        <span>
            <?= payment_success_escape(
                (string) $booking['Booking_reference']
            ) ?>
        </span>

    </div>


    <div class="summary-row">

        <span>Event</span>

        <span>
            <?= payment_success_escape(
                (string) $booking['EventName']
            ) ?>
        </span>

    </div>


    <div class="summary-row">

        <span>Ticket</span>

        <span>
            <?= payment_success_escape(
                (string) $booking['TicketType']
            ) ?>
        </span>

    </div>


    <div class="summary-row">

        <span>Quantity</span>

        <span>
            <?= (int) $booking['Quantity'] ?>
        </span>

    </div>


    <div class="summary-row">

        <span>Total</span>

        <span>
            R<?= number_format(
                (float) $booking['TotalAmount'],
                2
            ) ?>
        </span>

    </div>


    <div class="buttons">

        <a
            class="button bookings-button"
            href="my-bookings.php"
        >
            View my bookings
        </a>


        <a
            class="button outline-button"
            href="events.php"
        >
            Browse events
        </a>

    </div>

</main>


<!-- INSERT SHARED footer.php HERE -->


</body>
</html>