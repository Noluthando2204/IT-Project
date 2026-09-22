<?php
/**
 * admin-panel/dashboard.php
 *
 * Purpose: Render the admin overview using efficient database totals and
 * recent activity.
 *
 * Receives from: login.php redirects admins here without user_id.
 *
 * Required behaviour:
 *  - Require admin access.
 *  - Show counts for users, organisers awaiting approval, events by status,
 *    bookings, held escrow, and recent relevant activity.
 *  - Use efficient aggregate queries and clear status labels; link each
 *    summary card to its management page.
 *  - No URL parameter may change dashboard scope.
 *  - Do not create admin-panel/index.php; this file uses Screen 25.
 *
 * Links to: admin-panel/manage-events.php, manage-bookings.php,
 * manage-businesses.php, manage-users.php, manage-verifications.php,
 * moderation.php, categories.php, escrow.php.
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
 
// Every figure below is a database aggregate — no GET/POST parameter is
// ever allowed to influence scope on this page.
 
$total_users = (int) $pdo->query("SELECT COUNT(*) FROM USERS WHERE Role = 'attendee'")->fetchColumn();
 
$organisers_awaiting = (int) $pdo->query(
    "SELECT COUNT(*) FROM USERS WHERE Role = 'organizer' AND RoleStatus = 'pending'"
)->fetchColumn();
 
$events_by_status = $pdo->query(
    "SELECT Status, COUNT(*) AS total FROM EVENTS GROUP BY Status"
)->fetchAll(PDO::FETCH_KEY_PAIR);
 
$total_bookings = (int) $pdo->query("SELECT COUNT(*) FROM BOOKINGS")->fetchColumn();
 
$held_escrow_count = (int) $pdo->query(
    "SELECT COUNT(*) FROM PAYMENTS WHERE Escrow_status = 'held'"
)->fetchColumn();
 
$held_escrow_total = (float) $pdo->query(
    "SELECT COALESCE(SUM(Amount), 0) FROM PAYMENTS WHERE Escrow_status = 'held'"
)->fetchColumn();
 
// Recent activity: a light, indexed, capped query — not a full audit log.
$recent_events = $pdo->query(
    "SELECT EventID, EventName, Status, Created_at FROM EVENTS ORDER BY Created_at DESC LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);
 
$recent_bookings = $pdo->query(
    "SELECT BookingID, Booking_reference, Status, BookingDate FROM BOOKINGS ORDER BY BookingDate DESC LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);
 
$status_order = ['draft', 'published', 'completed', 'cancelled'];
 
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - EventNest</title>
</head>
<body>
<h1>Admin Dashboard</h1>
 
<section>
    <h2>Overview</h2>
    <ul>
        <li><a href="manage-users.php">Attendee accounts</a>: <?= $total_users ?></li>
        <li><a href="manage-businesses.php">Organisers awaiting approval</a>: <?= $organisers_awaiting ?></li>
        <li><a href="manage-bookings.php">Total bookings</a>: <?= $total_bookings ?></li>
        <li><a href="escrow.php">Held escrow payments</a>: <?= $held_escrow_count ?>
            (<?= htmlspecialchars(local_format_money($held_escrow_total)) ?>)</li>
    </ul>
</section>
 
<section>
    <h2>Events by status</h2>
    <ul>
        <?php foreach ($status_order as $status): ?>
            <li>
                <a href="manage-events.php?status=<?= htmlspecialchars($status) ?>">
                    <?= htmlspecialchars(ucfirst($status)) ?>
                </a>: <?= (int) ($events_by_status[$status] ?? 0) ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
 
<section>
    <h2>Quick links</h2>
    <ul>
        <li><a href="manage-verifications.php">Organiser verification queue</a></li>
        <li><a href="moderation.php">Moderation &amp; suspensions</a></li>
        <li><a href="categories.php">Manage categories</a></li>
    </ul>
</section>
 
<section>
    <h2>Recent events</h2>
    <table>
        <thead><tr><th>Event</th><th>Status</th><th>Created</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($recent_events as $event): ?>
            <tr>
                <td><?= htmlspecialchars($event['EventName']) ?></td>
                <td><?= htmlspecialchars(ucfirst($event['Status'])) ?></td>
                <td><?= htmlspecialchars(local_format_datetime($event['Created_at'])) ?></td>
                <td><a href="manage-events.php?event_id=<?= (int) $event['EventID'] ?>">View</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
 
<section>
    <h2>Recent bookings</h2>
    <table>
        <thead><tr><th>Reference</th><th>Status</th><th>Booked</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($recent_bookings as $booking): ?>
            <tr>
                <td><?= htmlspecialchars($booking['Booking_reference']) ?></td>
                <td><?= htmlspecialchars(ucfirst($booking['Status'])) ?></td>
                <td><?= htmlspecialchars(local_format_datetime($booking['BookingDate'])) ?></td>
                <td><a href="manage-bookings.php?booking_id=<?= (int) $booking['BookingID'] ?>">View</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
</body>
</html>
 

 
