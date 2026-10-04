<?php
// admin-panel/dashboard.php
// Administrator's main dashboard: platform stats + links to manage-*.php pages.
// Screen 25 — Admin Overview

require_once __DIR__ . '/auth.php'; // Require admin access (auth.php handles session + role check)

require_once __DIR__ . '/../config/db.php';

// ---------------------------------------------------------------------------
// Efficient aggregate queries — one query per card, no expensive joins.
// ---------------------------------------------------------------------------
$stats = [
    // Total users (excluding admins)
    'total_users' => (int) $pdo->query(
        "SELECT COUNT(*) FROM users WHERE role != 'admin'"
    )->fetchColumn(),

    // Organisers awaiting approval
    'pending_organizers' => (int) $pdo->query(
        "SELECT COUNT(*) FROM users WHERE role = 'organizer' AND verification_status = 'pending'"
    )->fetchColumn(),

    // Events by status (single query, grouped)
    'events_by_status' => (function () use ($pdo) {
        $rows = $pdo->query(
            "SELECT status, COUNT(*) AS cnt FROM events GROUP BY status"
        )->fetchAll(PDO::FETCH_KEY_PAIR);
        // Ensure all expected statuses exist
        $defaults = ['draft' => 0, 'published' => 0, 'cancelled' => 0, 'completed' => 0];
        return array_merge($defaults, $rows);
    })(),

    // Total events (sum of all statuses)
    'total_events' => 0, // calculated below

    // Total bookings
    'total_bookings' => (int) $pdo->query(
        'SELECT COUNT(*) FROM bookings'
    )->fetchColumn(),

    // Held escrow total
    'escrow_held' => (float) $pdo->query(
        "SELECT COALESCE(SUM(amount), 0) FROM escrow WHERE status = 'held'"
    )->fetchColumn(),
];

// Calculate total events from the status breakdown
$stats['total_events'] = array_sum($stats['events_by_status']);

// ---------------------------------------------------------------------------
// Recent relevant activity — last 5 users, last 5 events, last 5 bookings
// ---------------------------------------------------------------------------
$recentUsers = $pdo->query(
    "SELECT id, name, email, role, created_at
     FROM users
     WHERE role != 'admin'
     ORDER BY created_at DESC
     LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);

$recentEvents = $pdo->query(
    "SELECT e.id, e.title, e.status, e.created_at, u.name AS organizer_name
     FROM events e
     JOIN users u ON u.id = e.organizer_id
     ORDER BY e.created_at DESC
     LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);

$recentBookings = $pdo->query(
    "SELECT b.id, b.created_at, b.status, e.title AS event_title, u.name AS user_name
     FROM bookings b
     JOIN events e ON e.id = b.event_id
     JOIN users u ON u.id = b.user_id
     ORDER BY b.created_at DESC
     LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — EventNest</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f4f6f9;
            color: #1f2937;
            padding: 2rem;
            line-height: 1.5;
        }
        h1 { font-size: 1.75rem; margin-bottom: 1.5rem; color: #111827; }
        h2 { font-size: 1.5rem; margin-bottom: 0.25rem; }
        h3 { font-size: 1.1rem; margin: 1.5rem 0 0.75rem; color: #374151; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            display: block;
            background: #fff;
            border-radius: 10px;
            padding: 1.25rem 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            text-decoration: none;
            color: inherit;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            border-left: 4px solid #6366f1;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
        }
        .stat-card h2 { font-size: 1.75rem; color: #4f46e5; }
        .stat-card p { font-size: 0.875rem; color: #6b7280; margin-top: 0.25rem; }
        .stat-card.warn { border-left-color: #f59e0b; }
        .stat-card.warn h2 { color: #d97706; }
        .stat-card.escrow { border-left-color: #10b981; }
        .stat-card.escrow h2 { color: #059669; }

        .event-status-row {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 0.5rem;
        }
        .event-status-row .stat-card {
            flex: 1 1 120px;
            padding: 0.75rem 1rem;
            border-left-width: 3px;
        }
        .event-status-row .stat-card h2 { font-size: 1.25rem; }
        .event-status-row .stat-card p { font-size: 0.75rem; }

        .admin-links {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }
        .admin-links a {
            background: #fff;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            color: #4f46e5;
            font-weight: 500;
            font-size: 0.9rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.06);
            transition: background 0.15s;
        }
        .admin-links a:hover { background: #eef2ff; }

        .recent-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        .recent-section > div {
            background: #fff;
            border-radius: 10px;
            padding: 1.25rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        .recent-section h3 { margin-top: 0; }
        .recent-section ul { list-style: none; }
        .recent-section li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #f3f4f6;
            font-size: 0.875rem;
        }
        .recent-section li:last-child { border-bottom: none; }
        .recent-section .meta { color: #9ca3af; font-size: 0.75rem; }
        .badge {
            display: inline-block;
            padding: 0.1rem 0.5rem;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-published { background: #d1fae5; color: #065f46; }
        .badge-draft { background: #e5e7eb; color: #374151; }
        .badge-cancelled { background: #fee2e2; color: #991b1b; }
        .badge-completed { background: #dbeafe; color: #1e40af; }
    </style>
</head>
<body>

<h1>Admin Dashboard</h1>

<!-- ------------------------------------------------------------------ -->
<!-- Summary cards                                                       -->
<!-- ------------------------------------------------------------------ -->
<section class="stats-grid">
    <a href="manage-users.php" class="stat-card">
        <h2><?php echo htmlspecialchars((string) $stats['total_users']); ?></h2>
        <p>Total Users</p>
    </a>

    <a href="manage-businesses.php?filter=pending" class="stat-card warn">
        <h2><?php echo htmlspecialchars((string) $stats['pending_organizers']); ?></h2>
        <p>Organisers Awaiting Approval</p>
    </a>

    <a href="manage-events.php" class="stat-card">
        <h2><?php echo htmlspecialchars((string) $stats['total_events']); ?></h2>
        <p>Total Events</p>
    </a>

    <a href="manage-bookings.php" class="stat-card">
        <h2><?php echo htmlspecialchars((string) $stats['total_bookings']); ?></h2>
        <p>Total Bookings</p>
    </a>

    <a href="escrow.php" class="stat-card escrow">
        <h2>$<?php echo number_format($stats['escrow_held'], 2); ?></h2>
        <p>Held Escrow</p>
    </a>
</section>

<!-- ------------------------------------------------------------------ -->
<!-- Events by status                                                    -->
<!-- ------------------------------------------------------------------ -->
<h3>Events by Status</h3>
<section class="event-status-row">
    <?php
    $statusLabels = [
        'draft'     => 'Draft',
        'published' => 'Published',
        'cancelled' => 'Cancelled',
        'completed' => 'Completed',
    ];
    foreach ($statusLabels as $key => $label):
        $count = $stats['events_by_status'][$key] ?? 0;
        $badge = 'badge-' . $key;
    ?>
        <a href="manage-events.php?status=<?php echo urlencode($key); ?>" class="stat-card">
            <h2><?php echo htmlspecialchars((string) $count); ?></h2>
            <p><span class="badge <?php echo $badge; ?>"><?php echo htmlspecialchars($label); ?></span></p>
        </a>
    <?php endforeach; ?>
</section>

<!-- ------------------------------------------------------------------ -->
<!-- Quick links                                                         -->
<!-- ------------------------------------------------------------------ -->
<nav class="admin-links">
    <a href="manage-users.php">Manage Users</a>
    <a href="manage-events.php">Manage Events</a>
    <a href="manage-bookings.php">Manage Bookings</a>
    <a href="manage-businesses.php">Manage Businesses (Organisers)</a>
    <a href="manage-verifications.php">Manage Verifications</a>
    <a href="moderation.php">Moderation</a>
    <a href="categories.php">Categories</a>
    <a href="escrow.php">Escrow</a>
</nav>

<!-- ------------------------------------------------------------------ -->
<!-- Recent activity                                                     -->
<!-- ------------------------------------------------------------------ -->
<div class="recent-section">
    <!-- Recent Users -->
    <div>
        <h3>Recent Users</h3>
        <?php if (empty($recentUsers)): ?>
            <p class="meta">No users yet.</p>
        <?php else: ?>
            <ul>
                <?php foreach ($recentUsers as $u): ?>
                    <li>
                        <strong><?php echo htmlspecialchars($u['name']); ?></strong>
                        <span class="meta">(<?php echo htmlspecialchars($u['role']); ?>)</span><br>
                        <span class="meta"><?php echo htmlspecialchars($u['email']); ?> &middot;
                            <?php echo htmlspecialchars(date('M j, Y', strtotime($u['created_at']))); ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- Recent Events -->
    <div>
        <h3>Recent Events</h3>
        <?php if (empty($recentEvents)): ?>
            <p class="meta">No events yet.</p>
        <?php else: ?>
            <ul>
                <?php foreach ($recentEvents as $e): ?>
                    <li>
                        <strong><?php echo htmlspecialchars($e['title']); ?></strong>
                        <span class="badge badge-<?php echo htmlspecialchars($e['status']); ?>">
                            <?php echo htmlspecialchars($e['status']); ?>
                        </span><br>
                        <span class="meta">by <?php echo htmlspecialchars($e['organizer_name']); ?> &middot;
                            <?php echo htmlspecialchars(date('M j, Y', strtotime($e['created_at']))); ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- Recent Bookings -->
    <div>
        <h3>Recent Bookings</h3>
        <?php if (empty($recentBookings)): ?>
            <p class="meta">No bookings yet.</p>
        <?php else: ?>
            <ul>
                <?php foreach ($recentBookings as $b): ?>
                    <li>
                        <strong><?php echo htmlspecialchars($b['event_title']); ?></strong><br>
                        <span class="meta">by <?php echo htmlspecialchars($b['user_name']); ?> &middot;
                            <?php echo htmlspecialchars($b['status']); ?> &middot;
                            <?php echo htmlspecialchars(date('M j, Y', strtotime($b['created_at']))); ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
