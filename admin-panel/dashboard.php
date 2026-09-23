<?php
// admin-panel/dashboard.php
// Administrator's main dashboard: platform stats + links to manage-*.php pages.

session_start();

// Only admins may view this page. Checked BEFORE opening a DB connection.
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

// Lightweight COUNT() queries to avoid expensive joins.
$stats = [
    // total_users excludes admin accounts
    'total_users' => (int) $pdo->query(
        "SELECT COUNT(*) FROM users WHERE role != 'admin'"
    )->fetchColumn(),
    
    'total_events' => (int) $pdo->query(
        'SELECT COUNT(*) FROM events'
    )->fetchColumn(),
    
    // Updated to use the expected schema verification_status
    'pending_verifications' => (int) $pdo->query(
        "SELECT COUNT(*) FROM users WHERE role = 'organizer' AND verification_status = 'pending'"
    )->fetchColumn(),
    
    'total_bookings' => (int) $pdo->query(
        'SELECT COUNT(*) FROM bookings'
    )->fetchColumn(),

    // Added held escrow requirement
    'escrow_held' => (float) $pdo->query(
        "SELECT COALESCE(SUM(amount), 0) FROM escrow WHERE status = 'held'"
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
        <!-- Cards made clickable per the coding guide -->
        <a href="manage-users.php" class="stat-card" style="display: block; text-decoration: none; color: inherit;">
            <h2><?php echo htmlspecialchars((string) $stats['total_users']); ?></h2>
            <p>Total Users</p>
        </a>
        
        <a href="manage-businesses.php?filter=pending" class="stat-card" style="display: block; text-decoration: none; color: inherit;">
            <h2><?php echo htmlspecialchars((string) $stats['pending_verifications']); ?></h2>
            <p>Organisers Awaiting Approval</p>
        </a>

        <a href="manage-events.php" class="stat-card" style="display: block; text-decoration: none; color: inherit;">
            <h2><?php echo htmlspecialchars((string) $stats['total_events']); ?></h2>
            <p>Total Events</p>
        </a>
        
        <a href="manage-bookings.php" class="stat-card" style="display: block; text-decoration: none; color: inherit;">
            <h2><?php echo htmlspecialchars((string) $stats['total_bookings']); ?></h2>
            <p>Total Bookings</p>
        </a>

        <a href="manage-escrow.php" class="stat-card" style="display: block; text-decoration: none; color: inherit;">
            <h2>$<?php echo number_format($stats['escrow_held'], 2); ?></h2>
            <p>Held Escrow</p>
        </a>
    </section>

    <nav class="admin-links">
        <a href="manage-users.php">Manage Users</a>
        <a href="manage-events.php">Manage Events</a>
        <a href="manage-bookings.php">Manage Bookings</a>
        <a href="manage-businesses.php">Manage Businesses (Organisers)</a>
    </nav>
</body>
</html>
