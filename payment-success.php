<?php
session_start();

require_once __DIR__ . '/database-config/connection.php';

/*
Only an approved organiser can confirm that their event happened.
*/
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'organiser' ||
    ($_SESSION['organiser_status'] ?? '') !== 'approved'
) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Invalid request.');
}

/*
This checks the form token to stop another website from submitting
the confirmation form for the organiser.
*/
if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    exit('Invalid form request.');
}

$eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);

if (!$eventId) {
    exit('Invalid event ID.');
}

$organiserId = (int) $_SESSION['user_id'];

/*
The event must belong to the organiser, be published, and have already happened.
*/
$eventStatement = $pdo->prepare(
    "SELECT event_id, title, event_date, status
     FROM events
     WHERE event_id = :event_id
       AND organiser_id = :organiser_id
     LIMIT 1"
);

$eventStatement->execute([
    ':event_id' => $eventId,
    ':organiser_id' => $organiserId
]);

$event = $eventStatement->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    exit('Event not found or you do not own this event.');
}

/*
If the event was already completed, nothing else needs to happen.
This makes repeated clicks safe.
*/
if ($event['status'] === 'completed') {
    header('Location: admin-panel/manage-events.php?message=already_completed');
    exit;
}

if ($event['status'] !== 'published') {
    exit('Only published events can be confirmed.');
}

if (strtotime($event['event_date']) > time()) {
    exit('This event has not happened yet.');
}

$pdo->beginTransaction();

try {
    /*
    The event is now marked as completed.
    This records organiser confirmation only. It does not prove attendance.
    */
    $eventUpdate = $pdo->prepare(
        "UPDATE events
         SET status = 'completed'
         WHERE event_id = :event_id"
    );

    $eventUpdate->execute([
        ':event_id' => $eventId
    ]);

    /*
    Paid bookings and free bookings can now be marked as confirmed.
    Payment records stay held for the scheduled escrow process.
    */
    $bookingUpdate = $pdo->prepare(
        "UPDATE bookings b
         LEFT JOIN tickets t ON b.ticket_id = t.ticket_id
         LEFT JOIN payments p ON p.booking_id = b.booking_id
         SET b.status = 'confirmed'
         WHERE b.event_id = :event_id
           AND (
                p.payment_status = 'success'
                OR t.price = 0
           )"
    );

    $bookingUpdate->execute([
        ':event_id' => $eventId
    ]);

    $escrowUpdate = $pdo->prepare(
        "UPDATE payments p
         INNER JOIN bookings b ON p.booking_id = b.booking_id
         SET p.escrow_status = 'held'
         WHERE b.event_id = :event_id
           AND p.payment_status = 'success'"
    );

    $escrowUpdate->execute([
        ':event_id' => $eventId
    ]);

    $pdo->commit();

} catch (PDOException $error) {
    $pdo->rollBack();
    exit('Unable to complete the event confirmation.');
}

/*
The attendee email part happens after the database updates.
If one email fails, it should not undo the completed event status.
*/
$attendeeStatement = $pdo->prepare(
    "SELECT u.email, b.booking_id, b.booking_reference
     FROM bookings b
     INNER JOIN users u ON b.user_id = u.user_id
     WHERE b.event_id = :event_id
       AND b.status = 'confirmed'"
);

$attendeeStatement->execute([
    ':event_id' => $eventId
]);

$attendees = $attendeeStatement->fetchAll(PDO::FETCH_ASSOC);

foreach ($attendees as $attendee) {
    $reviewLink = 'http://localhost/IT-Project/pages/leave-review.php?booking_id='
        . urlencode($attendee['booking_id']);

    $subject = 'Please review ' . $event['title'];

    $message = "Thank you for attending.\n\n"
        . "You can leave a review here:\n"
        . $reviewLink . "\n\n"
        . "Booking reference: " . $attendee['booking_reference'];

    @mail($attendee['email'], $subject, $message);
}

header('Location: admin-panel/manage-events.php?message=event_completed');
exit;
