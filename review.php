<?php
// review.php
// Saves a private review from an attendee for a completed booking.
// Called by leave-review.php.
//
// Contract:
//   booking_id, rating, review_tags[], comment, contact_permission,
//   contact_email, csrf_token
//
// UserID and EventID come from the booking, not from the form.

require_once __DIR__ . '/shared-components/auth.php';
require_once __DIR__ . '/database-config/connection.php';
require_once __DIR__ . '/shared-components/functions.php';

requireRole('attendee');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

// CSRF check. TODO: replace with Cynthia's requireValidCsrf() once ready.
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('Invalid request token.');
}

$userId    = (int)$_SESSION['user_id'];
$bookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
$rating    = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
$tags      = $_POST['review_tags'] ?? [];
$comment   = trim((string)($_POST['comment'] ?? ''));
$wants     = !empty($_POST['contact_permission']);
$contact   = trim((string)($_POST['contact_email'] ?? ''));

// Basic validation before touching the database.
if (!$bookingId || !$rating || $rating < 1 || $rating > 5) {
    header('Location: my-bookings.php?msg=review_invalid');
    exit;
}

// Load the booking and confirm it belongs to this attendee and is completed.
$stmt = $pdo->prepare(
    'SELECT b.BookingID, b.UserID, b.EventID, e.Status AS EventStatus
     FROM BOOKINGS b
     JOIN EVENTS e ON e.EventID = b.EventID
     WHERE b.BookingID = ?'
);
$stmt->execute([$bookingId]);
$booking = $stmt->fetch();

if (!$booking || (int)$booking['UserID'] !== $userId) {
    http_response_code(403);
    exit('Not allowed.');
}
if ($booking['EventStatus'] !== 'completed') {
    header('Location: my-bookings.php?msg=review_invalid');
    exit;
}

// One review per booking only.
$stmt = $pdo->prepare('SELECT 1 FROM REVIEWS WHERE BookingID = ?');
$stmt->execute([$bookingId]);
if ($stmt->fetchColumn()) {
    header('Location: my-bookings.php?msg=review_exists');
    exit;
}

// Rating 4-5: save selected compliment tags.
// Rating 1-3: save the written comment.
$tagsValue    = null;
$commentValue = null;

if ($rating >= 4) {
    if (is_array($tags) && !empty($tags)) {
        $clean = [];
        foreach ($tags as $tag) {
            $tag = trim((string)$tag);
            if ($tag !== '') {
                $clean[] = $tag;
            }
        }
        $clean     = array_slice($clean, 0, 10);
        $tagsValue = implode(',', $clean);
    }
} else {
    if ($comment !== '') {
        $commentValue = mb_substr($comment, 0, 2000);
    }
}

// Only store a contact email if the attendee asked for follow-up.
if ($wants) {
    if (!filter_var($contact, FILTER_VALIDATE_EMAIL)) {
        header('Location: my-bookings.php?msg=review_invalid');
        exit;
    }
} else {
    $contact = null;
}

$stmt = $pdo->prepare(
    'INSERT INTO REVIEWS
        (BookingID, UserID, EventID, Rating, Comment, Tags,
         WantsContact, ContactEmail, ReviewDate)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
);
$stmt->execute([
    $bookingId,
    $userId,
    (int)$booking['EventID'],
    $rating,
    $commentValue,
    $tagsValue,
    $wants ? 1 : 0,
    $contact,
]);

header('Location: my-bookings.php?msg=review_saved');
exit;


