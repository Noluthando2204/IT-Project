<?php
session_start();

require_once __DIR__ . '/database-config/connection.php';

//The user must be logged in before viewing a booking payment result.

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$bookingId = filter_input(INPUT_GET, 'booking_id', FILTER_VALIDATE_INT);

if (!$bookingId || $bookingId <= 0) {
    exit('Invalid booking ID.');
}

// Ensure that the booking is loaded from the database.

$sql = "
    SELECT
        b.booking_id,
        b.booking_reference,
        b.quantity,
        b.status,
        b.event_id,
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
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':booking_id' => $bookingId,
    ':user_id' => $userId
]);

$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    exit('Booking not found or you are not allowed to view it.');
}

//The total is calculated from the database.

$quantity = (int) $booking['quantity'];
$ticketPrice = (float) $booking['ticket_price'];
$totalAmount = round($quantity * $ticketPrice, 2);

/*
A person reaching this page does not mean payment failed.
The booking stays pending and ticket quantity is not released here. 
A separate expiry or cancel process can
handle confirmed abandoned bookings safely.
*/
$canRetry = $booking['status'] === 'pending';
$alreadyPaid = $booking['status'] === 'paid';

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
    <title>Payment Not Completed</title>

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

        .paid-message {
            color: #155724;
            background: #d4edda;
            border: 1px solid #b8dfc2;
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
        }

        .bookings-button {
            color: white;
            background: #1877f2;
        }

        .outline-button {
            color: #1877f2;
            background: white;
            border: 2px solid #1877f2;
        }
    </style>
</head>
<body>

    <main class="result-card">
        <?php if ($alreadyPaid): ?>
            <div class="status-message paid-message">
                This booking has already been paid for. No payment details were changed.
            </div>
        <?php else: ?>
            <div class="status-message not-completed">
                <strong>Payment not completed.</strong>
                Your booking has not been marked as paid.
            </div>
        <?php endif; ?>

        <h1>Payment Result</h1>

        <div class="summary-row">
            <span>Booking reference</span>
            <span><?= safeOutput($booking['booking_reference']) ?></span>
        </div>

        <div class="summary-row">
            <span>Event</span>
            <span><?= safeOutput($booking['event_title']) ?></span>
        </div>

        <div class="summary-row">
            <span>Event date</span>
            <span><?= safeOutput($booking['event_date']) ?></span>
        </div>

        <div class="summary-row">
            <span>Ticket</span>
            <span><?= safeOutput($booking['ticket_name']) ?></span>
        </div>

        <div class="summary-row">
            <span>Quantity</span>
            <span><?= $quantity ?></span>
        </div>

        <div class="summary-row">
            <span>Total</span>
            <span>R <?= number_format($totalAmount, 2) ?></span>
        </div>

        <div class="buttons">
            <?php if ($canRetry): ?>
                <a class="button retry-button"
                   href="payment.php?booking_id=<?= $bookingId ?>">
                    Retry payment
                </a>
            <?php endif; ?>

            <a class="button bookings-button" href="pages/my-bookings.php">
                View my bookings
            </a>

            <a class="button outline-button"
               href="pages/event-detail.php?event_id=<?= (int) $booking['event_id'] ?>">
                Back to event
            </a>

            <a class="button outline-button" href="pages/events.php">
                Browse events
            </a>
        </div>
    </main>

</body>
</html>
