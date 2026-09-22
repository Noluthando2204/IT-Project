<?php
/**
 * review.php
 * Root-level standalone backend handler for leave-review.php submissions.
 *
 * Receives from: leave-review.php sends booking_id, rating, and conditional
 * tags/comment/contact fields.
 *
 * Required behaviour:
 *  - Require attendee login.
 *  - Load the booking and verify: its UserID matches the session user, the
 *    linked event is completed, and no existing review exists for this BookingID.
 *  - Validate rating 1-5.
 *  - Ratings 4-5  -> compliment_tags[]  saved to REVIEWS.Tags.
 *  - Ratings <4   -> complaint_text     saved to REVIEWS.Comment.
 *  - Save WantsContact, validate ContactEmail only when requested.
 *  - Derive UserID/EventID from the booking/session — never from the form.
 *  - Links to / returns to my-bookings.php with a safe success/error result.
 */
 
require_once __DIR__ . '/shared-components/auth.php';
require_once __DIR__ . '/shared-components/functions.php';
require_once __DIR__ . '/database-config/connection.php';
 
 
/*
|--------------------------------------------------------------------------
| TEMPORARY LOCAL HELPERS
|--------------------------------------------------------------------------
| shared-components/auth.php currently only checks that $_SESSION['user_id']
| is set (its own comment says role/permission checks are "for later").
| shared-components/functions.php currently only has sanitizeInput() and
| generateBookingReference(). Neither file yet provides role checks, CSRF,
| or money/date/hashtag formatting helpers.
|
| Until the team adds those to the real shared files (per the guide's rule
| that shared-file changes need team agreement), this file does its own
| role check and formatting locally. If auth.php/functions.php gain proper
| versions of these later, delete this block and call the shared ones.
|--------------------------------------------------------------------------
*/
function local_require_role(PDO $pdo, array $allowed_roles, bool $require_approved = false): array
{
    $user_id = (int) ($_SESSION['user_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT UserID, Role, RoleStatus FROM USERS WHERE UserID = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
 
    if (!$user || !in_array($user['Role'], $allowed_roles, true)) {
        header('Location: /login.php');
        exit;
    }
    if ($require_approved && $user['RoleStatus'] !== 'approved') {
        header('Location: /login.php');
        exit;
    }
    return $user;
}
 
function local_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
 
function local_verify_csrf(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
 
function local_format_money($amount): string
{
    return 'R' . number_format((float) $amount, 2);
}
 
function local_format_datetime($dt): string
{
    return $dt ? date('d M Y, H:i', strtotime($dt)) : '';
}
 
function local_status_badge(string $status): array
{
    $map = [
        'draft' => 'badge-grey', 'pending' => 'badge-grey',
        'published' => 'badge-green', 'paid' => 'badge-green', 'confirmed' => 'badge-green', 'success' => 'badge-green', 'released' => 'badge-green',
        'completed' => 'badge-blue', 'free' => 'badge-blue',
        'cancelled' => 'badge-red', 'failed' => 'badge-red',
        'refunded' => 'badge-orange', 'held' => 'badge-orange', 'rejected' => 'badge-red', 'approved' => 'badge-green',
    ];
    return ['label' => ucfirst($status), 'class' => $map[$status] ?? 'badge-grey'];
}
 
 
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
 
$user_id = local_require_role($pdo, ['attendee'])['UserID'];
 
if (!local_verify_csrf($_POST['csrf_token'] ?? '')) {
    header('Location: my-bookings.php?review=error');
    exit;
}
 
$booking_id = filter_var($_POST['booking_id'] ?? null, FILTER_VALIDATE_INT);
$rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT);
 
if ($booking_id === false || $booking_id <= 0 || $rating === false || $rating < 1 || $rating > 5) {
    header('Location: my-bookings.php?review=error');
    exit;
}
 
// Load the booking together with its event, and confirm ownership + completion,
// deriving UserID/EventID from the database, never trusting the form for these.
$stmt = $pdo->prepare(
    "SELECT b.BookingID, b.UserID, b.EventID, e.Status AS event_status
     FROM BOOKINGS b
     INNER JOIN EVENTS e ON e.EventID = b.EventID
     WHERE b.BookingID = :booking_id"
);
$stmt->execute([':booking_id' => $booking_id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);
 
if (!$booking || (int) $booking['UserID'] !== (int) $user_id) {
    header('Location: my-bookings.php?review=error');
    exit;
}
 
if ($booking['event_status'] !== 'completed') {
    header('Location: my-bookings.php?review=error');
    exit;
}
 
// Reject a duplicate review for this booking (REVIEWS.BookingID is unique).
$dupe_stmt = $pdo->prepare("SELECT ReviewID FROM REVIEWS WHERE BookingID = :booking_id");
$dupe_stmt->execute([':booking_id' => $booking_id]);
if ($dupe_stmt->fetch()) {
    header('Location: my-bookings.php?review=exists');
    exit;
}
 
// Map rating band to the correct field, per the required behaviour.
$tags = null;
$comment = null;
 
if ($rating >= 4) {
    $compliment_tags = $_POST['compliment_tags'] ?? [];
    if (!is_array($compliment_tags)) {
        $compliment_tags = [];
    }
    // Whitelist-safe: trim, strip empties, cap length, comma-join for storage.
    $clean_tags = array_filter(array_map(function ($tag) {
        $tag = trim((string) $tag);
        return $tag !== '' ? mb_substr($tag, 0, 50) : null;
    }, $compliment_tags));
    $tags = $clean_tags ? implode(',', array_slice($clean_tags, 0, 10)) : null;
} else {
    $complaint_text = trim((string) ($_POST['complaint_text'] ?? ''));
    $comment = $complaint_text !== '' ? mb_substr($complaint_text, 0, 2000) : null;
}
 
$wants_contact = !empty($_POST['wants_contact']) ? 1 : 0;
$contact_email = null;
 
if ($wants_contact) {
    $submitted_email = trim((string) ($_POST['contact_email'] ?? ''));
    if ($submitted_email === '' || !filter_var($submitted_email, FILTER_VALIDATE_EMAIL)) {
        header('Location: my-bookings.php?review=error');
        exit;
    }
    $contact_email = mb_substr($submitted_email, 0, 100);
}
 
try {
    $insert = $pdo->prepare(
        "INSERT INTO REVIEWS (BookingID, Rating, Comment, Tags, WantsContact, ContactEmail, ReviewDate, UserID, EventID)
         VALUES (:booking_id, :rating, :comment, :tags, :wants_contact, :contact_email, NOW(), :user_id, :event_id)"
    );
    $insert->execute([
        ':booking_id' => $booking_id,
        ':rating' => $rating,
        ':comment' => $comment,
        ':tags' => $tags,
        ':wants_contact' => $wants_contact,
        ':contact_email' => $contact_email,
        ':user_id' => $user_id,
        ':event_id' => $booking['EventID'],
    ]);
} catch (PDOException $e) {
    // Duplicate BookingID (race condition) or other constraint failure.
    error_log('review.php insert failed: ' . $e->getMessage());
    header('Location: my-bookings.php?review=error');
    exit;
}
 
header('Location: my-bookings.php?review=success');
exit;
 
