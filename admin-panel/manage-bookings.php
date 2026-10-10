<?php
// admin-panel/manage-bookings.php
// Full visibility of booking activity for support/dispute resolution.
// Relies on the booking reference generator in shared-components/functions.php —
// booking_reference format/field names must stay in sync with Cynthia's code.

session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /login.php');
    exit;
}

// Build filter conditions dynamically but safely (prepared statements only).
$conditions = [];
$params = [];

$event_filter = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);
if ($event_filter) {
    $conditions[] = 'b.event_id = :event_id';
    $params['event_id'] = $event_filter;
}

$user_filter = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
if ($user_filter) {
    $conditions[] = 'b.user_id = :user_id';
    $params['user_id'] = $user_filter;
}

$status_filter = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_SPECIAL_CHARS);
$allowed_statuses = ['pending', 'approved', 'rejected', 'cancelled'];
if ($status_filter && in_array($status_filter, $allowed_statuses, true)) {
    $conditions[] = 'b.status = :status';
    $params['status'] = $status_filter;
}

// Booking reference search (exact or partial match).
$reference_search = trim((string) filter_input(INPUT_GET, 'booking_reference', FILTER_SANITIZE_SPECIAL_CHARS));
if ($reference_search !== '') {
    $conditions[] = 'b.booking_reference LIKE :reference';
    $params['reference'] = '%' . $reference_search . '%';
}

$where_clause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$sql = "SELECT b.booking_id, b.booking_reference, b.event_id, b.user_id,
               b.status, b.escrow_status, b.created_at
        FROM bookings b
        {$where_clause}
        ORDER BY b.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Bookings — Admin</title>
</head>
<body>
    <h1>Manage Bookings</h1>

    <form method="get">
        <input type="number" name="event_id" placeholder="Event ID" value="<?php echo htmlspecialchars((string) $event_filter); ?>">
        <input type="number" name="user_id" placeholder="User ID" value="<?php echo htmlspecialchars((string) $user_filter); ?>">
        <select name="status">
            <option value="">All statuses</option>
            <?php foreach ($allowed_statuses as $status_option): ?>
                <option value="<?php echo $status_option; ?>" <?php echo $status_filter === $status_option ? 'selected' : ''; ?>>
                    <?php echo ucfirst($status_option); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="booking_reference" placeholder="Booking reference" value="<?php echo htmlspecialchars($reference_search); ?>">
        <button type="submit">Filter</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>Reference</th><th>Event</th><th>User</th><th>Status</th><th>Escrow</th><th>Created</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($bookings as $booking): ?>
            <tr>
                <td><?php echo htmlspecialchars($booking['booking_reference']); ?></td>
                <td><?php echo (int) $booking['event_id']; ?></td>
                <td><?php echo (int) $booking['user_id']; ?></td>
                <td><?php echo htmlspecialchars($booking['status']); ?></td>
                <td><?php echo htmlspecialchars($booking['escrow_status']); ?></td>
                <td><?php echo htmlspecialchars($booking['created_at']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
