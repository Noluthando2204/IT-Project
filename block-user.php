<?php
/**
 * block-user.php
 * Root-level standalone backend handler.
 *
 * Purpose: Let an organiser block a user while preventing one organiser from
 * manufacturing repeated suspensions.
 *
 * Receives from: organiser booking/review context sends blocked_user_email
 * and optional block_reason.
 *
 * Required behaviour:
 *  - Require an approved organiser.
 *  - Verify the blocked attendee has a legitimate relationship/context with
 *    the organiser (event booking, post reaction, etc.) before allowing the action.
 *  - Store OrganizerID, BlockedEmail, BlockDate, optional Reason in BLOCKS.
 *  - Reject a duplicate block by the same OrganizerID + BlockedEmail (unique constraint).
 *  - Recalculate DISTINCT organisers who have blocked that email; update USERS.Blocks_count.
 *  - At 3 distinct organisers: increment Suspension_count, set Suspension_start_date,
 *    reset Blocks_count, notify the user.
 *  - At Suspension_count >= 2: permanent ban is enforced by auth.php/login.php (not here).
 *  - While blocked by a particular organiser, that user cannot book that organiser's
 *    events or react to their posts (enforced in booking.php / reaction handling,
 *    not in this file — this file only records the block).
 *
 * Links to / returns to: manage-bookings.php or manage-reviews.php with the result.
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

$organizer_id = local_require_role(\$pdo, ['organizer'], true)['UserID'];

if (!local_verify_csrf($_POST['csrf_token'] ?? '')) {
    header('Location: manage-bookings.php?block=error');
    exit;
}

$raw_email = trim((string) ($_POST['blocked_user_email'] ?? ''));
$block_reason = trim((string) ($_POST['block_reason'] ?? ''));
$block_reason = $block_reason !== '' ? mb_substr($block_reason, 0, 255) : null;

if ($raw_email === '' || !filter_var($raw_email, FILTER_VALIDATE_EMAIL)) {
    header('Location: manage-bookings.php?block=error');
    exit;
}

// Normalise the email for consistent, case-insensitive comparison/storage.
$blocked_email = mb_strtolower($raw_email);

// The blocked user must actually exist as a USERS row (we store the email,
// but we need the row to check legitimacy of the relationship and later
// to update Blocks_count / Suspension fields).
$user_stmt = $pdo->prepare("SELECT UserID, Email, Blocks_count, Suspension_count FROM USERS WHERE LOWER(Email) = :email");
$user_stmt->execute([':email' => $blocked_email]);
$blocked_user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$blocked_user) {
    header('Location: manage-bookings.php?block=error');
    exit;
}

// Verify a legitimate relationship: the user must have a booking on one of
// this organiser's events, OR a reaction on one of this organiser's posts.
$relationship_stmt = $pdo->prepare(
    "SELECT 1
     FROM BOOKINGS b
     INNER JOIN EVENTS e ON e.EventID = b.EventID
     WHERE e.OrganizerID = :organizer_id AND b.UserID = :blocked_user_id
     UNION
     SELECT 1
     FROM REACTIONS r
     INNER JOIN POSTS p ON p.PostID = r.PostID
     WHERE p.OrganizerID = :organizer_id AND r.UserID = :blocked_user_id
     LIMIT 1"
);
$relationship_stmt->execute([
    ':organizer_id' => $organizer_id,
    ':blocked_user_id' => $blocked_user['UserID'],
]);

if (!$relationship_stmt->fetch()) {
    // No legitimate context — refuse the block.
    header('Location: manage-bookings.php?block=error');
    exit;
}

// Reject a duplicate block by the same organiser against the same email.
$dupe_stmt = $pdo->prepare(
    "SELECT BlockID FROM BLOCKS WHERE OrganizerID = :organizer_id AND BlockedEmail = :email"
);
$dupe_stmt->execute([':organizer_id' => $organizer_id, ':email' => $blocked_email]);

if ($dupe_stmt->fetch()) {
    header('Location: manage-bookings.php?block=duplicate');
    exit;
}

$pdo->beginTransaction();

try {
    $insert = $pdo->prepare(
        "INSERT INTO BLOCKS (OrganizerID, BlockedEmail, BlockDate, Reason)
         VALUES (:organizer_id, :email, NOW(), :reason)"
    );
    $insert->execute([
        ':organizer_id' => $organizer_id,
        ':email' => $blocked_email,
        ':reason' => $block_reason,
    ]);

    // Recalculate DISTINCT organisers who have blocked this email — never
    // trust any submitted count, always derive it fresh from BLOCKS.
    $count_stmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT OrganizerID) AS distinct_blockers
         FROM BLOCKS WHERE BlockedEmail = :email"
    );
    $count_stmt->execute([':email' => $blocked_email]);
    $distinct_blockers = (int) $count_stmt->fetch(PDO::FETCH_ASSOC)['distinct_blockers'];

    if ($distinct_blockers >= 3) {
        // Trigger a suspension cycle: increment Suspension_count, start the
        // 30-day clock, and reset Blocks_count for the next cycle.
        $update = $pdo->prepare(
            "UPDATE USERS
             SET Suspension_count = Suspension_count + 1,
                 Suspension_start_date = NOW(),
                 Blocks_count = 0
             WHERE UserID = :user_id"
        );
        $update->execute([':user_id' => $blocked_user['UserID']]);

        // TODO: send the user a suspension notification email here via the
        // project's mail helper (not specified in the guide for this file).
    } else {
        // Not yet at the suspension threshold — just record the fresh count.
        $update = $pdo->prepare("UPDATE USERS SET Blocks_count = :count WHERE UserID = :user_id");
        $update->execute([
            ':count' => $distinct_blockers,
            ':user_id' => $blocked_user['UserID'],
        ]);
    }

    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('block-user.php failed: ' . $e->getMessage());
    header('Location: manage-bookings.php?block=error');
    exit;
}

header('Location: manage-bookings.php?block=success');
exit;
