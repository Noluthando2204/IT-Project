<?php
// business-dashboard.php
// The organiser's private dashboard. Shows counts and quick links.

require_once __DIR__ . '/shared-components/auth.php';
require_once __DIR__ . '/database-config/connection.php';
require_once __DIR__ . '/shared-components/functions.php';

requireOrganiserVerified();

$userId = (int)$_SESSION['user_id'];
$page_title = 'Organiser dashboard';

// Load the organiser row fresh from the database.
$stmt = $pdo->prepare(
    "SELECT FirstName, LastName, Email, CareerField,
            VerificationStatus, VerificationFeePaidUntil
     FROM USERS
     WHERE UserID = ? AND Role = 'organiser'"
);
$stmt->execute([$userId]);
$me = $stmt->fetch();

if (!$me) {
    http_response_code(403);
    exit('Access denied.');
}

// Count this organiser's events.
$stmt = $pdo->prepare('SELECT COUNT(*) FROM EVENTS WHERE OrganiserID = ?');
$stmt->execute([$userId]);
$eventCount = (int)$stmt->fetchColumn();

// Count bookings on this organiser's events.
$stmt = $pdo->prepare(
    'SELECT COUNT(*) FROM BOOKINGS b
     JOIN EVENTS e ON e.EventID = b.EventID
     WHERE e.OrganiserID = ?'
);
$stmt->execute([$userId]);
$bookingCount = (int)$stmt->fetchColumn();

// Five most recent events.
$stmt = $pdo->prepare(
    'SELECT EventID, EventName, EventDate, Status
     FROM EVENTS
     WHERE OrganiserID = ?
     ORDER BY EventDate DESC
     LIMIT 5'
);
$stmt->execute([$userId]);
$recentEvents = $stmt->fetchAll();

require_once __DIR__ . '/shared-components/header.php';
?>

<div class="dashboard-shell">
    <?php en_render_sidebar(); ?>

    <main id="main-content" class="dashboard-main">
        <h1>Hi, <?php echo en_e($me['FirstName']); ?></h1>

        <?php if ($me['VerificationStatus'] !== 'verified'): ?>
            <div class="form-notice form-notice-warning" role="status">
                <p>
                    Your organiser account is
                    <strong><?php echo en_e(ucfirst($me['VerificationStatus'])); ?></strong>.
                    You cannot publish events or posts until an admin approves your document.
                </p>
                <p>
                    <a class="btn btn-primary" href="organiser-verification.php">
                        Upload verification document
                    </a>
                </p>
            </div>
        <?php endif; ?>

        <section class="stats-grid">
            <article class="stat-card">
                <div class="stat-content">
                    <span class="stat-label">Events</span>
                    <strong class="stat-value"><?php echo $eventCount; ?></strong>
                </div>
            </article>
            <article class="stat-card">
                <div class="stat-content">
                    <span class="stat-label">Bookings</span>
                    <strong class="stat-value"><?php echo $bookingCount; ?></strong>
                </div>
            </article>
            <article class="stat-card">
                <div class="stat-content">
                    <span class="stat-label">Verification</span>
                    <strong class="stat-value"><?php echo en_e(ucfirst($me['VerificationStatus'])); ?></strong>
                </div>
            </article>
            <article class="stat-card">
                <div class="stat-content">
                    <span class="stat-label">Coverage until</span>
                    <strong class="stat-value">
                        <?php
                        echo !empty($me['VerificationFeePaidUntil'])
                            ? en_e(formatDate($me['VerificationFeePaidUntil']))
                            : '—';
                        ?>
                    </strong>
                </div>
            </article>
        </section>

        <div class="form-actions">
            <a class="btn btn-primary" href="create-event.php">Create event</a>
            <a class="btn btn-secondary" href="create-post.php">Create post</a>
            <a class="btn btn-outline" href="manage-reviews.php">Private reviews</a>
            <a class="btn btn-outline" href="organiser-account.php">Account</a>
        </div>

        <section class="dashboard-panel">
            <div class="panel-header">
                <h2>Recent events</h2>
            </div>

            <?php if (empty($recentEvents)): ?>
                <p class="empty-state">
                    You have not created any events yet.
                    <a href="create-event.php">Create your first event</a>.
                </p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th scope="col">Event</th>
                                <th scope="col">Date</th>
                                <th scope="col">Status</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentEvents as $ev): ?>
                                <tr>
                                    <td><?php echo en_e($ev['EventName']); ?></td>
                                    <td><?php echo en_e(formatDate($ev['EventDate'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo en_e($ev['Status']); ?>">
                                            <?php echo en_e($ev['Status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a class="btn btn-outline btn-sm"
                                           href="event-detail.php?event_id=<?php echo (int)$ev['EventID']; ?>">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<?php require_once __DIR__ . '/shared-components/footer.php'; ?>
