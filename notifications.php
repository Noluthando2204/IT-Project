<?php
declare(strict_types=1);

require_once __DIR__ . '/shared-components/auth.php';
require_once __DIR__ . '/shared-components/functions.php';

$pdo = eventnest_db();
$session_user = auth_current_user();

if ($session_user === null) {
    auth_set_flash('Log in to view your notifications.', 'info');
    auth_redirect('login.php');
}

$user_stmt = $pdo->prepare(
    'SELECT UserID, Email, Role, UserStatus, VerificationStatus FROM USERS WHERE UserID = :user_id LIMIT 1'
);
$user_stmt->execute([':user_id' => (int) $session_user['UserID']]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['UserStatus'] === 'deleted') {
    auth_redirect('logout.php');
}

$user_id = (int) $user['UserID'];
$role = (string) $user['Role'];
$notices = [];

/*
EventNest does not have a NOTIFICATIONS table in the current ERD.
This page therefore creates live notices from records that already exist instead of inventing new stored notification data.

*/
if ($role === 'attendee') {
    eventnest_expire_pending($pdo, $user_id);

    $stmt = $pdo->prepare(
        "SELECT b.BookingID, b.Status, b.BookingReference, e.EventID, e.EventName, e.EventDate
         FROM BOOKINGS b
         INNER JOIN EVENTS e ON e.EventID = b.EventID
         WHERE b.UserID = :user_id
         ORDER BY b.BookingDate DESC
         LIMIT 20"
    );
    $stmt->execute([':user_id' => $user_id]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $booking) {
        if ($booking['Status'] === 'pending') {
            $notices[] = [
                'title' => 'Payment still pending',
                'message' => 'Your booking for ' . $booking['EventName'] . ' is waiting for payment confirmation.',
                'url' => 'my-bookings.php',
            ];
        } elseif ($booking['Status'] === 'canceled' || $booking['Status'] === 'refunded') {
            $notices[] = [
                'title' => 'Booking update',
                'message' => $booking['EventName'] . ' has a booking status of ' . eventnest_status_label((string) $booking['Status']) . '.',
                'url' => 'my-bookings.php',
            ];
        } elseif (strtotime((string) $booking['EventDate']) > time() && strtotime((string) $booking['EventDate']) <= strtotime('+7 days')) {
            $notices[] = [
                'title' => 'Upcoming event',
                'message' => $booking['EventName'] . ' is coming up on ' . format_datetime((string) $booking['EventDate']) . '.',
                'url' => 'event-detail.php?event_id=' . (int) $booking['EventID'],
            ];
        }
    }
} elseif ($role === 'organiser') {
    if (($user['VerificationStatus'] ?? '') !== 'verified') {
        $notices[] = [
            'title' => 'Organiser verification',
            'message' => 'Your organiser account is ' . eventnest_status_label((string) ($user['VerificationStatus'] ?? 'pending')) . '. Complete verification before managing public events.',
            'url' => 'organiser-verification.php',
        ];
    }

    $stmt = $pdo->prepare(
        "SELECT e.EventID, e.EventName, e.Status, e.EventDate,
                (SELECT COUNT(*) FROM BOOKINGS b WHERE b.EventID = e.EventID AND b.Status IN ('paid','pending')) AS BookingCount
         FROM EVENTS e
         WHERE e.OrganiserID = :user_id
         ORDER BY e.EventDate ASC
         LIMIT 20"
    );
    $stmt->execute([':user_id' => $user_id]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $event) {
        if ($event['Status'] === 'draft') {
            $notices[] = [
                'title' => 'Draft event',
                'message' => $event['EventName'] . ' is still a draft and is not visible to attendees.',
                'url' => 'manage-events.php',
            ];
        } elseif ($event['Status'] === 'published' && strtotime((string) $event['EventDate']) > time()) {
            $notices[] = [
                'title' => 'Published event activity',
                'message' => $event['EventName'] . ' currently has ' . (int) $event['BookingCount'] . ' active or pending booking(s).',
                'url' => 'manage-bookings.php',
            ];
        }
    }
} elseif ($role === 'admin') {
    $pending_organisers = (int) $pdo->query("SELECT COUNT(*) FROM USERS WHERE Role = 'organiser' AND VerificationStatus = 'pending' AND UserStatus = 'active'")->fetchColumn();
    $draft_events = (int) $pdo->query("SELECT COUNT(*) FROM EVENTS WHERE Status = 'draft'")->fetchColumn();
    $suspended_users = (int) $pdo->query("SELECT COUNT(*) FROM USERS WHERE UserStatus = 'suspended'")->fetchColumn();

    if ($pending_organisers > 0) {
        $notices[] = ['title' => 'Pending organiser verifications', 'message' => $pending_organisers . ' organiser account(s) need verification review.', 'url' => 'admin-panel/manage-verifications.php'];
    }
    if ($draft_events > 0) {
        $notices[] = ['title' => 'Events awaiting review', 'message' => $draft_events . ' draft event(s) are currently in the system.', 'url' => 'admin-panel/manage-events.php'];
    }
    if ($suspended_users > 0) {
        $notices[] = ['title' => 'Suspended users', 'message' => $suspended_users . ' user account(s) are currently suspended.', 'url' => 'admin-panel/moderation.php'];
    }
}

$page_title = 'Notifications';
require_once __DIR__ . '/shared-components/header.php';
?>
<main class="page-content notifications-page">
    <header class="page-header">
        <h1>Notifications</h1>
        <p>These are current EventNest notices generated from your bookings, events and account status.</p>
    </header>

    <?php if (!$notices): ?>
        <div class="empty-state">
            <h2>No current notifications</h2>
            <p>There is nothing that needs your attention right now.</p>
        </div>
    <?php else: ?>
        <div class="notification-list">
            <?php foreach ($notices as $notice): ?>
                <article class="notification-card">
                    <h2><?= escape_html($notice['title']) ?></h2>
                    <p><?= escape_html($notice['message']) ?></p>
                    <a class="btn btn-outline" href="<?= escape_html(eventnest_url($notice['url'])) ?>">View details</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php require_once __DIR__ . '/shared-components/footer.php'; ?>
