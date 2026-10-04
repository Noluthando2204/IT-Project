<?php
// review.php
// Saves review, rating, and complaint info submitted via leave-review.php.
// Built against the finalised Tickets/reviews table structure.

session_start();
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /leave-review.php');
    exit;
}

// CSRF protection required by reviewer
if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    exit('CSRF token validation failed.');
}

$user_id = (int) $_SESSION['user_id'];
$booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
$rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);

// Basic validation.
$errors = [];
if (!$booking_id) {
    $errors[] = 'Invalid booking reference.';
}
if (!$rating || $rating < 1 || $rating > 5) {
    $errors[] = 'Rating must be between 1 and 5.';
}

// Compliment tags strictly whitelisted per coding guide
$allowed_compliments = ['well_organized', 'great_atmosphere', 'helpful_staff', 'good_value', 'started_on_time'];
$compliment_tags = [];
if ($rating >= 4 && isset($_POST['compliment_tags']) && is_array($_POST['compliment_tags'])) {
    foreach ($_POST['compliment_tags'] as $tag) {
        if (in_array($tag, $allowed_compliments, true)) {
            $compliment_tags[] = $tag;
        }
    }
}

// complaint_text only applies to under-4-star reviews and is private
$complaint_text = null;
if ($rating < 4) {
    $complaint_text = trim(strip_tags($_POST['complaint_text'] ?? ''));
}

// Strict check for wants_contact exact value
$wants_contact = (isset($_POST['wants_contact']) && $_POST['wants_contact'] === '1') ? 1 : 0;
$contact_email = null;

if ($wants_contact) {
    $submitted_email = filter_input(INPUT_POST, 'contact_email', FILTER_VALIDATE_EMAIL);
    if ($submitted_email) {
        $contact_email = $submitted_email;
    } else {
        $errors[] = 'Please provide a valid contact email, or uncheck "wants to be contacted".';
    }
}

if (!empty($errors)) {
    $_SESSION['review_errors'] = $errors;
    header('Location: /leave-review.php?booking_id=' . urlencode((string) $booking_id));
    exit;
}

// Confirm booking ownership and completed status
$owner_check = $pdo->prepare(
    'SELECT b.user_id, b.status AS booking_status, e.event_occurred
     FROM bookings b
     JOIN events e ON e.event_id = b.event_id
     WHERE b.booking_id = :booking_id'
);
$owner_check->execute(['booking_id' => $booking_id]);
$booking = $owner_check->fetch();

if (!$booking || (int) $booking['user_id'] !== $user_id) {
    http_response_code(403);
    exit('You may only review your own bookings.');
}

if ($booking['booking_status'] === 'cancelled') {
    http_response_code(400);
    exit('Cancelled bookings cannot be reviewed.');
}

if (empty($booking['event_occurred'])) {
    http_response_code(400);
    exit('This event has not been confirmed as having happened yet.');
}

// Enforce unique review per booking
$duplicate_check = $pdo->prepare('SELECT 1 FROM reviews WHERE booking_id = :booking_id');
$duplicate_check->execute(['booking_id' => $booking_id]);
if ($duplicate_check->fetchColumn()) {
    http_response_code(409);
    exit('You have already reviewed this booking.');
}

$insert_stmt = $pdo->prepare(
    'INSERT INTO reviews
        (booking_id, user_id, rating, compliment_tags, complaint_text, wants_contact, contact_email, created_at)
     VALUES
        (:booking_id, :user_id, :rating, :compliment_tags, :complaint_text, :wants_contact, :contact_email, NOW())'
);

$insert_stmt->execute([
    'booking_id'       => $booking_id,
    'user_id'          => $user_id,
    'rating'           => $rating,
    'compliment_tags'  => !empty($compliment_tags) ? json_encode($compliment_tags) : null,
    'complaint_text'   => $complaint_text,
    'wants_contact'    => $wants_contact,
    'contact_email'    => $contact_email,
]);

header('Location: /leave-review.php?success=1');
exit;
