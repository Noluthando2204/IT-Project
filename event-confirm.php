<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/database-config/connection.php';

// Shared componenets code should be placed here

function confirm_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function confirm_valid_csrf(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/*
 PHPMailer/Gmail SMTP code will be inserted here and the mail will contain
 a url such as: leave-review.php?booking_id=[BookingID]
 Sensitive info will not be placed in message
 */
function send_review_request_email(
    string $recipient,
    string $event_name,
    int $booking_id,
    string $booking_reference
): bool {
    return false;
}

$user_id = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);

if (!$user_id) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Event confirmation must be submitted from Manage events.');
}

if (!confirm_valid_csrf($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Your session expired. Please return to Manage events and try again.');
}

$event_id = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);

if (!$event_id) {
    http_response_code(400);
    exit('A valid event is required.');
}

$organiser_statement = $pdo->prepare(
    "SELECT UserID
     FROM USERS
     WHERE UserID = :user_id
       AND Role = 'organizer'
       AND RoleStatus = 'approved'
     LIMIT 1"
);
$organiser_statement->execute(['user_id' => $user_id]);

if (!$organiser_statement->fetch()) {
    http_response_code(403);
    exit('Only approved organisers can confirm an event.');
}

$email_failures = [];

try {
    $pdo->beginTransaction();

    $event_statement = $pdo->prepare(
        'SELECT EventID, EventName, EventDate, Status, OrganizerID
         FROM EVENTS
         WHERE EventID = :event_id
           AND OrganizerID = :organizer_id
         FOR UPDATE'
    );
    $event_statement->execute([
        'event_id' => $event_id,
        'organizer_id' => $user_id
    ]);
    $event = $event_statement->fetch();

    if (!$event) {
        throw new RuntimeException('Event not found.');
    }

    if ($event['Status'] === 'completed') {
        // Repeated confirmation is safe and does not change payment state twice.
        $pdo->commit();
        header('Location: manage-events.php?confirmed=1');
        exit;
    }

    if ($event['Status'] !== 'published') {
        throw new RuntimeException('Only published events can be confirmed as completed.');
    }

    if (strtotime((string) $event['EventDate']) > time()) {
        throw new RuntimeException('The event cannot be confirmed before its event date.');
    }

    $bookings_statement = $pdo->prepare(
        "SELECT
            b.BookingID,
            b.Booking_reference,
            b.Status,
            u.Email
         FROM BOOKINGS b
         INNER JOIN USERS u ON u.UserID = b.UserID
         WHERE b.EventID = :event_id
           AND b.Status IN ('paid', 'free', 'confirmed')
         FOR UPDATE"
    );
    $bookings_statement->execute(['event_id' => $event_id]);
    $bookings = $bookings_statement->fetchAll();

    $update_event = $pdo->prepare(
        "UPDATE EVENTS
         SET Status = 'completed'
         WHERE EventID = :event_id
           AND OrganizerID = :organizer_id
           AND Status = 'published'"
    );
    $update_event->execute([
        'event_id' => $event_id,
        'organizer_id' => $user_id
    ]);

    if ($update_event->rowCount() !== 1) {
        throw new RuntimeException('The event status changed before confirmation.');
    }

    $update_bookings = $pdo->prepare(
        "UPDATE BOOKINGS
         SET Status = 'confirmed'
         WHERE EventID = :event_id
           AND Status IN ('paid', 'free')"
    );
    $update_bookings->execute(['event_id' => $event_id]);

    /*
    Payments should not yet be released but held. 
    Successful paid bookings remain Escrow_status = held.
    scheduled-taks/escrow.php will apply later on the release/refund rules.
     */

    $pdo->commit();

    foreach ($bookings as $booking) {
        if (!send_review_request_email(
            (string) $booking['Email'],
            (string) $event['EventName'],
            (int) $booking['BookingID'],
            (string) $booking['Booking_reference']
        )) {
            $email_failures[] = (string) $booking['Booking_reference'];
        }
    }
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Event confirmation failed: ' . $exception->getMessage());

    http_response_code(409);
    exit(confirm_escape($exception->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event confirmed | EventNest</title>

    <!-- INSERT THANDO'S SHARED HEADER/CSS COMPONENT LINKS HERE -->
</head>
<body>

<!-- INSERT SHARED header.php / navbar.php HERE -->

<main>
    <h1>Event marked as completed</h1>

    <p>
        EventNest has recorded the organiser's confirmation that the event took place.
        This does not prove physical attendance by each booked attendee.
    </p>

    <p>
        Eligible bookings were moved to the confirmed state. Paid payments remain held
        in escrow until the later release process is completed.
    </p>

    <?php if ($email_failures): ?>
        <h2>Email follow-up needed</h2>
        <p>Some review-request emails could not be sent and should be retried:</p>
        <ul>
            <?php foreach ($email_failures as $reference): ?>
                <li><?= confirm_escape($reference) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>Review-request emails were queued/sent through the configured mail service.</p>
    <?php endif; ?>

    <a href="manage-events.php">Back to manage events</a>
</main>

<!-- SHARED footer.php should be placed here -->

</body>
</html>

