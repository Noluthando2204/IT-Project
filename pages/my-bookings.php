<?php
require_once __DIR__ . '/../shared-components/auth.php';
require_once __DIR__ . '/../shared-components/functions.php';
$auth_user = auth_require_attendee();
$pdo = eventnest_db();

$bookings = [];
$error_message = '';

try {

    // Retrieve only bookings belonging to the logged-in user.
    $sql = "SELECT
                b.BookingID,
                b.BookingReference,
                b.BookingDate,
                b.Quantity,
                b.TotalAmount,
                b.Status AS BookingStatus,
                e.EventName,
                e.EventDate,
                e.Venue,
                e.Format,
                e.Status AS EventStatus,
                t.TicketType
            FROM BOOKINGS b
            INNER JOIN EVENTS e
                ON b.EventID = e.EventID
            INNER JOIN TICKETS t
                ON b.TicketID = t.TicketID
            WHERE b.UserID = ?
            ORDER BY b.BookingDate DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['user_id']]);

    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    // Avoid exposing database details to users.
    error_log('My Bookings error: ' . $e->getMessage());
    $error_message = 'Unable to load your bookings right now. Please try again later.';
}

// Escape output before displaying it in HTML.
function escapeBooking($value)
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Format booking status for display.
function formatBookingStatus($status)
{
    return ucfirst((string) $status);
}
?>

<?php include __DIR__ . '/../shared-components/header.php'; ?>

<main class="container">

    <h2>My Bookings</h2>

<<<<<<< HEAD
    <?php foreach ($bookings as $booking): ?>

        <div class="booking-card">

            <h3>
                <?= htmlspecialchars($booking["event_name"]) ?>
            </h3>

            <p>
                <strong>Date:</strong>
                <?= htmlspecialchars($booking["event_date"]) ?>
            </p>

            <p>
                <strong>Status:</strong>
                <?= htmlspecialchars($booking["status"]) ?>
            </p>

            <?php if ($booking["status"] === "Attended"): ?>

                <a
                    href="leave-review.php"
                    class="button"
                >
                    Leave Review
                </a>

            <?php elseif ($booking["status"] === "Cancelled"): ?>

                <p class="status-message">
                    This booking has been cancelled.
                </p>

            <?php endif; ?>

        </div>

    <?php endforeach; ?>

</main>

</body>
</html>
=======
    <p>
        Welcome,
        <?= escapeBooking($_SESSION['name'] ?? 'EventNest user'); ?>.
        Here you can view your event bookings and their current status.
    </p>

    <?php if ($error_message !== ''): ?>

        <p role="alert">
            <?= escapeBooking($error_message); ?>
        </p>

    <?php elseif (empty($bookings)): ?>

        <p>You have not made any event bookings yet.</p>

        <p>
            <a href="<?= escape_html(eventnest_url('index.php')) ?>">Return to EventNest</a>
            to explore events.
        </p>

    <?php else: ?>

        <p>
            Your bookings are listed below, with your most recent bookings first.
        </p>

        <div style="overflow-x: auto;">

            <table>
                <thead>
                    <tr>
                        <th>Booking Reference</th>
                        <th>Event</th>
                        <th>Event Date</th>
                        <th>Venue</th>
                        <th>Format</th>
                        <th>Ticket Type</th>
                        <th>Quantity</th>
                        <th>Total Amount</th>
                        <th>Booking Date</th>
                        <th>Booking Status</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($bookings as $booking): ?>

                        <tr>
                            <td>
                                <?= escapeBooking($booking['BookingReference']); ?>
                            </td>

                            <td>
                                <?= escapeBooking($booking['EventName']); ?>
                            </td>

                            <td>
                                <?= !empty($booking['EventDate'])
                                    ? escapeBooking(
                                        date(
                                            'd M Y, H:i',
                                            strtotime($booking['EventDate'])
                                        )
                                    )
                                    : 'Not available'; ?>
                            </td>

                            <td>
                                <?= escapeBooking(
                                    $booking['Venue'] ?: 'Not specified'
                                ); ?>
                            </td>

                            <td>
                                <?= escapeBooking(
                                    ucfirst($booking['Format'])
                                ); ?>
                            </td>

                            <td>
                                <?= escapeBooking($booking['TicketType']); ?>
                            </td>

                            <td>
                                <?= (int) $booking['Quantity']; ?>
                            </td>

                            <td>
                                R<?= number_format(
                                    (float) $booking['TotalAmount'],
                                    2
                                ); ?>
                            </td>

                            <td>
                                <?= !empty($booking['BookingDate'])
                                    ? escapeBooking(
                                        date(
                                            'd M Y, H:i',
                                            strtotime($booking['BookingDate'])
                                        )
                                    )
                                    : 'Not available'; ?>
                            </td>

                            <td>
                                <?= escapeBooking(
                                    formatBookingStatus(
                                        $booking['BookingStatus']
                                    )
                                ); ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                </tbody>
            </table>

        </div>

    <?php endif; ?>

    <p>
        <a href="<?= escape_html(eventnest_url('index.php')) ?>">Back to Home</a>
    </p>

</main>

<?php include __DIR__ . '/../shared-components/footer.php'; ?>
>>>>>>> origin/main
