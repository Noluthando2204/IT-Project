<?php
// admin-panel/manage-events.php
// Full oversight of every event; admins can remove/deactivate events.
// IMPORTANT: never let event deletion orphan bookings — we check the
// event -> booking relationship first and require deactivation instead
// of a hard delete whenever bookings exist.

session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /login.php');
    exit;
}

$message = '';

// Handle admin action (deactivate or remove an event).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['event_id'], $_POST['action'])) {
    $event_id = filter_var($_POST['event_id'], FILTER_VALIDATE_INT);
    $action = $_POST['action'] === 'remove' ? 'remove' : 'deactivate';
    $confirmed = isset($_POST['confirm']) && $_POST['confirm'] === '1';

    if ($event_id && $confirmed) {
        // Check for existing bookings tied to this event before deciding
        // how to remove it — we never want orphaned bookings.
        $check_stmt = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE event_id = :event_id');
        $check_stmt->execute(['event_id' => $event_id]);
        $booking_count = (int) $check_stmt->fetchColumn();

        if ($action === 'remove' && $booking_count === 0) {
            // Safe to hard-delete: no bookings reference this event.
            $del_stmt = $pdo->prepare('DELETE FROM events WHERE event_id = :event_id');
            $del_stmt->execute(['event_id' => $event_id]);
            $message = 'Event removed.';
        } else {
            // Either bookings exist, or the admin chose deactivate:
            // soft-delete via status so bookings stay intact.
            $deact_stmt = $pdo->prepare(
                "UPDATE events SET status = 'deactivated' WHERE event_id = :event_id"
            );
            $deact_stmt->execute(['event_id' => $event_id]);
            $message = $booking_count > 0
                ? 'Event has existing bookings — it was deactivated instead of deleted.'
                : 'Event deactivated.';
        }
    }
}

$events_stmt = $pdo->query(
    'SELECT event_id, title, organiser_id, status, event_date, format
     FROM events
     ORDER BY event_date DESC'
);
$events = $events_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Events — Admin</title>
</head>
<body>
    <h1>Manage Events</h1>

    <?php if ($message): ?>
        <p class="flash-message"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>ID</th><th>Title</th><th>Organiser</th><th>Status</th><th>Date</th><th>Format</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($events as $event): ?>
            <tr>
                <td><?php echo (int) $event['event_id']; ?></td>
                <td><?php echo htmlspecialchars($event['title']); ?></td>
                <td><?php echo (int) $event['organiser_id']; ?></td>
                <td><?php echo htmlspecialchars($event['status']); ?></td>
                <td><?php echo htmlspecialchars($event['event_date']); ?></td>
                <td><?php echo htmlspecialchars($event['format']); ?></td>
                <td>
                    <!-- Confirmation prompt handled client-side via confirm() before submit -->
                    <form method="post" onsubmit="return confirm('Deactivate this event?');" style="display:inline;">
                        <input type="hidden" name="event_id" value="<?php echo (int) $event['event_id']; ?>">
                        <input type="hidden" name="action" value="deactivate">
                        <input type="hidden" name="confirm" value="1">
                        <button type="submit">Deactivate</button>
                    </form>
                    <form method="post" onsubmit="return confirm('Permanently remove this event? This cannot be undone.');" style="display:inline;">
                        <input type="hidden" name="event_id" value="<?php echo (int) $event['event_id']; ?>">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="confirm" value="1">
                        <button type="submit">Remove</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
