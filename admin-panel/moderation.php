<?php
/**
 * admin-panel/moderation.php - NEW
 * Create inside admin-panel/ if absent.
 *
 * Purpose: Consolidate user blocks, suspensions and event/report
 * investigation actions.
 *
 * Required behaviour:
 *  - Require admin access.
 *  - Show recent BLOCKS records with organiser, blocked email, reason, date;
 *    group/count DISTINCT organisers per blocked email.
 *  - Display matching USERS suspension counters and calculated suspension end.
 *  - Provide investigation links to relevant users/bookings/events where the
 *    relationship can actually be verified.
 *  - Email-reported event concerns are NOT database rows — show explanatory
 *    text and links to event/payment management, not an invented "reports" query.
 *  - Do not edit review text or expose private information publicly.
 */

require_once __DIR__ . '/../shared-components/auth.php';
require_once __DIR__ . '/../shared-components/functions.php';
require_once __DIR__ . '/../database-config/connection.php';


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


local_require_role($pdo, ['admin'])['UserID'];

// Optional focus on one attendee, e.g. linked from manage-users.php.
$focus_user_id = filter_var($_GET['user_id'] ?? null, FILTER_VALIDATE_INT);
if ($focus_user_id === false || $focus_user_id <= 0) {
    $focus_user_id = null;
}

// Recent blocks, with the organiser's name for context.
$blocks_sql = "SELECT bl.BlockID, bl.BlockedEmail, bl.BlockDate, bl.Reason,
                      o.FirstName AS OrganizerFirstName, o.LastName AS OrganizerLastName
               FROM BLOCKS bl
               INNER JOIN USERS o ON o.UserID = bl.OrganizerID";
$blocks_params = [];

if ($focus_user_id !== null) {
    $blocks_sql .= " WHERE bl.BlockedEmail = (SELECT Email FROM USERS WHERE UserID = :user_id)";
    $blocks_params[':user_id'] = $focus_user_id;
}

$blocks_sql .= " ORDER BY bl.BlockDate DESC LIMIT 100";

$blocks_stmt = $pdo->prepare($blocks_sql);
$blocks_stmt->execute($blocks_params);
$recent_blocks = $blocks_stmt->fetchAll(PDO::FETCH_ASSOC);

// DISTINCT-organiser counts per blocked email, for the same set of emails
// currently shown (kept simple: aggregate over all BLOCKS, not just the
// capped recent list, so the count is always accurate).
$counts_stmt = $pdo->query(
    "SELECT BlockedEmail, COUNT(DISTINCT OrganizerID) AS distinct_blockers
     FROM BLOCKS GROUP BY BlockedEmail"
);
$distinct_blocker_counts = $counts_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Suspension state for users who have at least one block or an active/past suspension.
$suspensions_sql = "SELECT UserID, FirstName, LastName, Email, Blocks_count,
                            Suspension_count, Suspension_start_date
                     FROM USERS
                     WHERE Suspension_count > 0 OR Blocks_count > 0";
$suspensions_params = [];

if ($focus_user_id !== null) {
    $suspensions_sql .= " AND UserID = :user_id";
    $suspensions_params[':user_id'] = $focus_user_id;
}

$suspensions_sql .= " ORDER BY Suspension_start_date DESC LIMIT 100";

$suspensions_stmt = $pdo->prepare($suspensions_sql);
$suspensions_stmt->execute($suspensions_params);
$suspended_users = $suspensions_stmt->fetchAll(PDO::FETCH_ASSOC);

function suspension_label(array $user): string
{
    if ((int) $user['Suspension_count'] >= 2) {
        return 'Permanent ban';
    }
    if ((int) $user['Suspension_count'] >= 1 && $user['Suspension_start_date'] !== null) {
        $ends_at = strtotime($user['Suspension_start_date']) + (30 * 24 * 60 * 60);
        if ($ends_at > time()) {
            return 'Active suspension until ' . date('Y-m-d', $ends_at);
        }
        return 'Suspension period ended';
    }
    return 'Not currently suspended';
}

?><!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Moderation - Admin</title></head>
<body>
<h1>Moderation</h1>

<section>
    <h2>Recent blocks</h2>
    <table>
        <thead><tr><th>Blocked email</th><th>By organiser</th><th>Date</th><th>Reason</th><th>Distinct blockers</th></tr></thead>
        <tbody>
        <?php foreach ($recent_blocks as $block): ?>
            <tr>
                <td><?= htmlspecialchars($block['BlockedEmail']) ?></td>
                <td><?= htmlspecialchars($block['OrganizerFirstName'] . ' ' . $block['OrganizerLastName']) ?></td>
                <td><?= htmlspecialchars(local_format_datetime($block['BlockDate'])) ?></td>
                <td><?= htmlspecialchars($block['Reason'] ?? '—') ?></td>
                <td><?= (int) ($distinct_blocker_counts[$block['BlockedEmail']] ?? 0) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($recent_blocks)): ?>
            <tr><td colspan="5">No blocks recorded.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>

<section>
    <h2>Suspension state</h2>
    <table>
        <thead><tr><th>User</th><th>Blocks (current cycle)</th><th>Suspension count</th><th>State</th><th>Investigate</th></tr></thead>
        <tbody>
        <?php foreach ($suspended_users as $user): ?>
            <tr>
                <td><?= htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']) ?> (<?= htmlspecialchars($user['Email']) ?>)</td>
                <td><?= (int) $user['Blocks_count'] ?></td>
                <td><?= (int) $user['Suspension_count'] ?></td>
                <td><?= htmlspecialchars(suspension_label($user)) ?></td>
                <td>
                    <a href="manage-users.php?user_id=<?= (int) $user['UserID'] ?>">Account</a> ·
                    <a href="../manage-bookings.php?user_id=<?= (int) $user['UserID'] ?>">Bookings</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($suspended_users)): ?>
            <tr><td colspan="5">No accounts currently flagged.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>

<section>
    <h2>Reported event concerns</h2>
    <p>
        Event concerns reported by email are not stored as database rows in
        the current ERD. Investigate manually using the reporter's email and
        the links below, rather than expecting an automated "reports" list:
    </p>
    <ul>
        <li><a href="manage-events.php">Event management</a> — search by event name or organiser.</li>
        <li><a href="manage-bookings.php">Booking management</a> — search by booking reference.</li>
        <li><a href="escrow.php">Escrow / payments</a> — hold a payment while an investigation is open.</li>
    </ul>
</section>
</body>
</html>
