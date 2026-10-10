<?php
// block-user.php
// An approved organiser blocks an attendee's email address.
// Called from manage-bookings.php.
//
// Accepts:
//   blocked_user_email, block_reason, csrf_token

require_once __DIR__ . '/shared-components/auth.php';
require_once __DIR__ . '/database-config/connection.php';
require_once __DIR__ . '/shared-components/functions.php';

requireOrganiserVerified();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

// CSRF check. TODO: replace with Cynthia's requireValidCsrf() once ready.
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('Invalid request token.');
}

$organiserId = (int)$_SESSION['user_id'];
$email       = trim((string)($_POST['blocked_user_email'] ?? ''));
$reason      = trim((string)($_POST['block_reason'] ?? ''));

// Validate email.
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: manage-bookings.php?msg=block_invalid');
    exit;
}
$email  = mb_strtolower($email);
$reason = $reason !== '' ? mb_substr($reason, 0, 255) : null;

// Confirm the attendee actually interacted with this organiser's events
// before the block is allowed.
$stmt = $pdo->prepare(
    'SELECT 1
     FROM BOOKINGS b
     JOIN EVENTS e ON e.EventID = b.EventID
     JOIN USERS  u ON u.UserID  = b.UserID
     WHERE e.OrganiserID = ? AND u.Email = ?
     LIMIT 1'
);
$stmt->execute([$organiserId, $email]);
$related = $stmt->fetchColumn();

if (!$related) {
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM REVIEWS r
         JOIN EVENTS e ON e.EventID = r.EventID
         JOIN USERS  u ON u.UserID  = r.UserID
         WHERE e.OrganiserID = ? AND u.Email = ?
         LIMIT 1'
    );
    $stmt->execute([$organiserId, $email]);
    $related = $stmt->fetchColumn();
}

if (!$related) {
    http_response_code(403);
    exit('No legitimate relationship with this attendee.');
}

$pdo->beginTransaction();
try {
    // Reject a duplicate block from the same organiser.
    $stmt = $pdo->prepare(
        'SELECT 1 FROM BLOCKS WHERE OrganiserID = ? AND BlockedEmail = ?'
    );
    $stmt->execute([$organiserId, $email]);
    if ($stmt->fetchColumn()) {
        $pdo->rollBack();
        header('Location: manage-bookings.php?msg=block_exists');
        exit;
    }

    // Insert the new block.
    $pdo->prepare(
        'INSERT INTO BLOCKS (OrganiserID, BlockedEmail, Reason, BlockDate)
         VALUES (?, ?, ?, NOW())'
    )->execute([$organiserId, $email, $reason]);

    // Recount the distinct organisers for this email.
    $stmt = $pdo->prepare(
        'SELECT COUNT(DISTINCT OrganiserID) FROM BLOCKS WHERE BlockedEmail = ?'
    );
    $stmt->execute([$email]);
    $distinct = (int)$stmt->fetchColumn();

    // Find the user behind this email (if the account exists).
    $stmt = $pdo->prepare('SELECT UserID FROM USERS WHERE Email = ?');
    $stmt->execute([$email]);
    $targetUserId = (int)$stmt->fetchColumn();

    if ($targetUserId) {
        // Keep Blocks_count in step with the distinct organiser count.
        $pdo->prepare(
            'UPDATE USERS SET Blocks_count = ? WHERE UserID = ?'
        )->execute([$distinct, $targetUserId]);

        // Three different organisers triggers a suspension.
        if ($distinct >= 3) {
            $pdo->prepare(
                'UPDATE USERS
                 SET Suspension_count = Suspension_count + 1,
                     Suspension_start_date = NOW(),
                     Blocks_count = 0
                 WHERE UserID = ?'
            )->execute([$targetUserId]);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('block-user.php failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Could not process block.');
}

header('Location: manage-bookings.php?msg=block_saved');
exit;
