<?php
// admin-panel/dashboard.php
// Administrator's main dashboard: platform stats + links to manage-*.php pages.

session_start();
require_once __DIR__ . '/../config/db.php';

// Only admins may view this page.
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /login.php');
    exit;
}

// Lightweight COUNT() queries only — this page is loaded frequently,
// so we avoid expensive joins here (per the coding guide's notes).
$stats = [
    'total_users'         => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'total_events'        => (int) $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn(),
    'pending_verifications' => (int) $pdo->query(
        "SELECT COUNT(*) FROM verifications WHERE status = 'pending'"
    )->fetchColumn(),
    'total_bookings'      => (int) $pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn(),
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
        <div class="stat-card">
            <h2><?php echo htmlspecialchars((string) $stats['total_users']); ?></h2>
            <p>Total Users</p>
        </div>
        <div class="stat-card">
            <h2><?php echo htmlspecialchars((string) $stats['total_events']); ?></h2>
            <p>Total Events</p>
        </div>
        <div class="stat-card">
            <h2><?php echo htmlspecialchars((string) $stats['pending_verifications']); ?></h2>
            <p>Pending Verifications</p>
        </div>
        <div class="stat-card">
            <h2><?php echo htmlspecialchars((string) $stats['total_bookings']); ?></h2>
            <p>Total Bookings</p>
        </div>
    </section>

    <nav class="admin-links">
        <a href="manage-users.php">Manage Users</a>
        <a href="manage-events.php">Manage Events</a>
        <a href="manage-bookings.php">Manage Bookings</a>
        <a href="manage-businesses.php">Manage Businesses (Organisers)</a>
        <a href="manage-verifications.php">Manage Verifications</a>
    </nav>
</body>
</html>
