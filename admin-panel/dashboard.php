<?php
// admin-panel/dashboard.php
// Admin overview page. Shows counters and the latest blocks.
// Only admins can open this page.

require_once __DIR__ . '/../shared-components/auth.php';
require_once __DIR__ . '/../database-config/connection.php';
require_once __DIR__ . '/../shared-components/functions.php';

requireRole('admin');

$page_title = 'Admin overview';

// Count active users (nobody with UserStatus = deleted).
$totalUsers = (int)$pdo->query(
    "SELECT COUNT(*) FROM USERS WHERE UserStatus <> 'deleted'"
)->fetchColumn();

// Count organisers.
$totalOrganisers = (int)$pdo->query(
    "SELECT COUNT(*) FROM USERS WHERE Role = 'organiser' AND UserStatus <> 'deleted'"
)->fetchColumn();

// Organisers waiting for document approval.
$pendingVerification = (int)$pdo->query(
    "SELECT COUNT(*) FROM USERS WHERE Role = 'organiser' AND VerificationStatus = 'pending'"
)->fetchColumn();

// Published events.
$publishedEvents = (int)$pdo->query(
    "SELECT COUNT(*) FROM EVENTS WHERE Status = 'published'"
)->fetchColumn();

// Bookings that are still active (pending or paid).
$activeBookings = (int)$pdo->query(
    "SELECT COUNT(*) FROM BOOKINGS WHERE Status IN ('pending','paid')"
)->fetchColumn();

// Total amount still sitting in escrow.
$heldEscrow = (float)$pdo->query(
    "SELECT COALESCE(SUM(Amount), 0) FROM PAYMENTS WHERE Escrow_status = 'held'"
)->fetchColumn();

// Last 5 blocks so admins can see recent moderation activity.
$recentBlocks = $pdo->query(
    "SELECT b.BlockDate, b.BlockedEmail, b.Reason,
            u.FirstName, u.LastName
     FROM BLOCKS b
     JOIN USERS u ON u.UserID = b.OrganiserID
     ORDER BY b.BlockDate DESC
     LIMIT 5"
)->fetchAll();

require_once __DIR__ . '/../shared-components/header.php';
?>

<div class="dashboard-shell">
    <?php en_render_sidebar(); ?>

    <main id="main-content" class="dashboard-main">
        <h1>Admin overview</h1>

        <section class="stats-grid">
            <article class="stat-card">
                <div class="stat-content">
                    <span class="stat-label">Active users</span>
                    <strong class="stat-value"><?php echo $totalUsers; ?></strong>
                </div>
            </article>
            <article class="stat-card">
                <div class="stat-content">
                    <span class="stat-label">Organisers</span>
                    <strong class="stat-value"><?php echo $totalOrganisers; ?></strong>
                </div>
            </article>
            <article class="stat-card">
                <div class="stat-content">
                    <span class="stat-label">Awaiting verification</span>
                    <strong class="stat-value"><?php echo $pendingVerification; ?></strong>
                </div>
            </article>
            <article class="stat-card">
                <div class="stat-content">
                    <span class="stat-label">Published events</span>
                    <strong class="stat-value"><?php echo $publishedEvents; ?></strong>
                </div>
            </article>
        </section>

        <section class="stats-grid">
            <article class="stat-card">
                <div class="stat-content">
                    <span class="stat-label">Active bookings</span>
                    <strong class="stat-value"><?php echo $activeBookings; ?></strong>
                </div>
            </article>
            <article class="stat-card">
                <div class="stat-content">
                    <span class="stat-label">Held escrow</span>
                    <strong class="stat-value">
                        R<?php echo number_format($heldEscrow, 2); ?>
                    </strong>
                </div>
            </article>
            <article class="stat-card">
                <div class="stat-content">
                    <span class="stat-label">Escrow</span>
                    <strong class="stat-value">
                        <a class="btn btn-outline btn-sm" href="escrow.php">View</a>
                    </strong>
                </div>
            </article>
            <article class="stat-card">
                <div class="stat-content">
                    <span class="stat-label">Moderation</span>
                    <strong class="stat-value">
                        <a class="btn btn-primary btn-sm" href="moderation.php">Open</a>
                    </strong>
                </div>
            </article>
        </section>

        <section class="dashboard-panel">
            <div class="panel-header">
                <h2>Recent moderation activity</h2>
                <a class="panel-link" href="moderation.php">View all</a>
            </div>

            <?php if (empty($recentBlocks)): ?>
                <p class="empty-state">No blocks have been recorded yet.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th scope="col">Date</th>
                                <th scope="col">Organiser</th>
                                <th scope="col">Blocked email</th>
                                <th scope="col">Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentBlocks as $b): ?>
                                <tr>
                                    <td><?php echo en_e(formatDate($b['BlockDate'])); ?></td>
                                    <td>
                                        <?php
                                        echo en_e(trim(
                                            ($b['FirstName'] ?? '') . ' ' . ($b['LastName'] ?? '')
                                        ));
                                        ?>
                                    </td>
                                    <td><?php echo en_e($b['BlockedEmail']); ?></td>
                                    <td><?php echo en_e($b['Reason'] ?? '—'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<?php require_once __DIR__ . '/../shared-components/footer.php'; ?>
