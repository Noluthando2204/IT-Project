<?php
// admin-panel/dashboard.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

// Queries updated to match the 'eventnest' schema exactly.
$stats = [
    'total_users' => (int) $pdo->query(
        "SELECT COUNT(*) FROM users"
    )->fetchColumn(),
    
    'active_users' => (int) $pdo->query(
        "SELECT COUNT(*) FROM users WHERE account_status = 'active'"
    )->fetchColumn(),
    
    'total_events' => (int) $pdo->query(
        'SELECT COUNT(*) FROM events'
    )->fetchColumn(),
    
    // event_attendees acts as the registration/booking metric
    'total_attendees' => (int) $pdo->query(
        'SELECT COUNT(*) FROM event_attendees'
    )->fetchColumn(),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard — EventNest</title>
</head>
<body>
    <h1>Admin Dashboard</h1>
    
    <section class="stats-grid">
        <a href="manage-users.php" class="stat-card" style="display: block; text-decoration: none; color: inherit;">
            <h2><?php echo htmlspecialchars((string) $stats['total_users']); ?></h2>
            <p>Total Users (<?php echo htmlspecialchars((string) $stats['active_users']); ?> Active)</p>
        </a>
        
        <a href="manage-events.php" class="stat-card" style="display: block; text-decoration: none; color: inherit;">
            <h2><?php echo htmlspecialchars((string) $stats['total_events']); ?></h2>
            <p>Total Events</p>
        </a>
        
        <a href="manage-attendees.php" class="stat-card" style="display: block; text-decoration: none; color: inherit;">
            <h2><?php echo htmlspecialchars((string) $stats['total_attendees']); ?></h2>
            <p>Total Event Registrations</p>
        </a>
    </section>

    <nav class="admin-links">
        <a href="manage-users.php">Manage Users</a>
        <a href="manage-events.php">Manage Events</a>
    </nav>
</body>
</html>
