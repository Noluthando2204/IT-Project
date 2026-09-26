<?php
// review.php
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

if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('CSRF token validation failed.');
}

$user_id = (int) $_SESSION['user_id'];
$event_id = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
$rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
$review_text = trim(strip_tags($_POST['review_text'] ?? ''));

$errors = [];
if (!$event_id) {
    $errors[] = 'Invalid event reference.';
}
if (!$rating || $rating < 1 || $rating > 5) {
    $errors[] = 'Rating must be between 1 and 5.';
}

if (!empty($errors)) {
    $_SESSION['review_errors'] = $errors;
    header('Location: /leave-review.php?event_id=' . urlencode((string) $event_id));
    exit;
}

// Confirm the user actually attended/registered for the event
$attendance_check = $pdo->prepare(
    'SELECT 1 FROM event_attendees WHERE event_id = :event_id AND user_id = :user_id'
);
$attendance_check->execute(['event_id' => $event_id, 'user_id' => $user_id]);

if (!$attendance_check->fetchColumn()) {
    http_response_code(403);
    exit('You may only review events you have registered for.');
}

// Enforce unique review per user per event
$duplicate_check = $pdo->prepare('SELECT 1 FROM event_reviews WHERE event_id = :event_id AND user_id = :user_id');
$duplicate_check->execute(['event_id' => $event_id, 'user_id' => $user_id]);
if ($duplicate_check->fetchColumn()) {
    http_response_code(409);
    exit('You have already reviewed this event.');
}

$insert_stmt = $pdo->prepare(
    'INSERT INTO event_reviews
        (event_id, user_id, rating, review_text, created_at)
     VALUES
        (:event_id, :user_id, :rating, :review_text, NOW())'
);

$insert_stmt->execute([
    'event_id'    => $event_id,
    'user_id'     => $user_id,
    'rating'      => $rating,
    'review_text' => $review_text ?: null
]);

header('Location: /leave-review.php?success=1');
exit;
