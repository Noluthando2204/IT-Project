<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/database-config/connection.php';

// Insert auth.php and functions.php files
// require_once __DIR__ . '/shared-components/auth.php';
// require_once __DIR__ . '/shared-components/functions.php';

function payment_cancel_escape(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
 The attendee must be logged in before viewing
 a cancelled/incomplete payment result.
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
 Confirm that the session belongs to an attendee.
 Replaced later shared authentication helper.
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


/*
 payment.php adds BookingID to the PayFast cancel URL.
 */
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
 Load the booking using the current session UserID.
 Payment amount, status and ownership are never accepted
 from the browser.
 */
$booking_statement = $pdo->prepare(
    'SELECT
        b.BookingID,
        b.Booking_reference,
        b.Quantity,
        b.Status,
        b.TotalAmount,
        b.UserID,
        b.EventID,
        b.TicketID,

        e.EventName,

        t.EventID AS TicketEventID,
        t.TicketType,
        t.Price,

        p.PaymentStatus

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


$booking_statement->execute([
    'booking_id' => $booking_id,
    'user_id' => $user_id
]);


$booking =
    $booking_statement->fetch();


if (!$booking) {
    http_response_code(404);
    exit('Booking not found.');
}


/*
 Confirm that the ticket belongs to the same event.
 */
if (
    (int) $booking['TicketEventID']
    !==
    (int) $booking['EventID']
) {
    error_log(
        'Ticket/event mismatch for cancelled payment booking '
        . $booking_id
    );

    http_response_code(409);

    exit(
        'The booking information could not be verified.'
    );
}


/*
Recalculate the amount using database values.
 */
$quantity =
    (int) $booking['Quantity'];

$ticket_price =
    (float) $booking['Price'];

$expected_total =
    round(
        $ticket_price * $quantity,
        2
    );

$stored_total =
    round(
        (float) $booking['TotalAmount'],
        2
    );


if (
    $quantity < 1
    || $ticket_price < 0
    || abs(
        $expected_total
        - $stored_total
    ) > 0.009
) {
    http_response_code(409);

    exit(
        'The booking amount could not be verified.'
    );
}


/*
 If PayFast has already successfully verified this booking,
 a late visit to the cancel URL must not change anything.
 */
$already_paid =
    in_array(
        $booking['Status'],
        ['paid', 'confirmed'],
        true
    )
    &&
    $booking['PaymentStatus'] === 'success';


if ($already_paid) {

    header(
        'Location: payment-success.php?booking_id='
        . $booking_id
    );

    exit;
}


/*
 Free bookings should not appear as cancelled
 PayFast payments.
 */
$is_free =
    in_array(
        $booking['Status'],
        ['free', 'confirmed'],
        true
    )
    &&
    $stored_total <= 0;


if ($is_free) {

    header(
        'Location: payment-success.php?booking_id='
        . $booking_id
    );

    exit;
}

$can_retry =
    $booking['Status'] === 'pending';

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
        Payment not completed | EventNest
    </title>


    <!-- INSERT THANDO'S SHARED HEADER/CSS COMPONENT LINKS HERE -->


    <!--TEMPORARY RESULT STYLING -->
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
            margin-bottom: 22px;
            border-radius: 5px;
        }

        .not-completed {
            color: #7a4b00;
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

        .retry-button {
            color: white;
            background: #f58220;
            border: 2px solid #f58220;
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


    <div class="status-message not-completed">

        <strong>
            Payment not completed.
        </strong>

        EventNest did not receive a verified successful payment.

    </div>


    <h1>Payment Result</h1>


    <?php if ($can_retry): ?>

        <p>
            Your booking is still pending.
            You can retry the payment when you are ready.
        </p>

    <?php else: ?>

        <p>
            This booking cannot currently be paid.
            Please check My bookings for its latest status.
        </p>

    <?php endif; ?>


    <div class="summary-row">

        <span>Booking reference</span>

        <span>
            <?= payment_cancel_escape(
                (string) $booking['Booking_reference']
            ) ?>
        </span>

    </div>


    <div class="summary-row">

        <span>Event</span>

        <span>
            <?= payment_cancel_escape(
                (string) $booking['EventName']
            ) ?>
        </span>

    </div>


    <div class="summary-row">

        <span>Ticket</span>

        <span>
            <?= payment_cancel_escape(
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

        <span>Total</span>

        <span>
            R<?= number_format(
                $stored_total,
                2
            ) ?>
        </span>

    </div>


    <div class="buttons">


        <?php if ($can_retry): ?>

            <a
                class="button retry-button"
                href="payment.php?booking_id=<?= (int) $booking_id ?>"
            >
                Retry payment
            </a>

        <?php endif; ?>


        <a
            class="button bookings-button"
            href="my-bookings.php"
        >
            View my bookings
        </a>


        <a
            class="button outline-button"
            href="event-detail.php?event_id=<?= (int) $booking['EventID'] ?>"
        >
            Back to event
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