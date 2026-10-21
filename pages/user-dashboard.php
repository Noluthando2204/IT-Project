<?php
// Shared signed-in user dashboard. Role-specific actions remain protected by their destination pages.
require_once __DIR__ . '/shared-components/auth.php';
require_once __DIR__ . '/database-config/connection.php';
require_once __DIR__ . '/shared-components/functions.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (empty($_SESSION['UserID'])) { header('Location: login.php'); exit; }
$user_id = (int) $_SESSION['UserID'];
$role = strtolower((string)($_SESSION['Role'] ?? 'attendee'));
$stmt = $pdo->prepare('SELECT UserID, FirstName, LastName, Email, Role, UserStatus, CareerField, RecommendationOptIn, VerificationStatus, VerificationFeePaidUntil FROM USERS WHERE UserID = :id LIMIT 1');
$stmt->execute(['id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user || $user['UserStatus'] !== 'active') { $_SESSION = []; session_destroy(); header('Location: login.php'); exit; }
$bookings = [];
if ($user['Role'] === 'attendee') {
  $q = $pdo->prepare("SELECT b.BookingID, b.Status, e.EventName, e.EventDate, t.TicketType FROM BOOKINGS b JOIN EVENTS e ON e.EventID=b.EventID LEFT JOIN TICKETS t ON t.TicketID=b.TicketID WHERE b.UserID=:uid ORDER BY b.BookingID DESC LIMIT 8");
  $q->execute(['uid'=>$user_id]); $bookings = $q->fetchAll(PDO::FETCH_ASSOC);
}
$events = [];
if ($user['Role'] === 'organiser') {
  $q = $pdo->prepare('SELECT EventID, EventName, EventDate, Status FROM EVENTS WHERE OrganiserID=:uid ORDER BY Created_at DESC LIMIT 8');
  $q->execute(['uid'=>$user_id]); $events = $q->fetchAll(PDO::FETCH_ASSOC);
}
$page_title = 'Dashboard';
require_once __DIR__ . '/shared-components/header.php';
?>
<div class="dashboard-shell">
<?php if (function_exists('en_render_sidebar')) { en_render_sidebar(); } ?>
<main id="main-content" class="dashboard-main container">
  <h1>Welcome, <?= en_e(trim(($user['FirstName'] ?? '') . ' ' . ($user['LastName'] ?? ''))) ?: 'to EventNest' ?></h1>
  <p>Manage your EventNest activity and account.</p>
  <section class="stats-grid">
    <article class="stat-card"><span class="stat-label">Account role</span><strong class="stat-value"><?= en_e(ucfirst($user['Role'])) ?></strong></article>
    <article class="stat-card"><span class="stat-label">Account status</span><strong class="stat-value"><?= en_e(ucfirst($user['UserStatus'])) ?></strong></article>
    <article class="stat-card"><span class="stat-label">Career field</span><strong class="stat-value"><?= en_e($user['CareerField'] ?: 'Not set') ?></strong></article>
  </section>
  <nav class="dashboard-actions" aria-label="Account actions">
    <a class="btn btn-primary" href="profile.php">Profile</a>
    <a class="btn btn-outline" href="setting.php">Settings</a>
    <?php if ($user['Role'] === 'attendee'): ?><a class="btn btn-outline" href="my-bookings.php">My bookings</a><?php endif; ?>
    <?php if ($user['Role'] === 'organiser'): ?><a class="btn btn-primary" href="create-event.php">Create event</a><a class="btn btn-outline" href="organiser-account.php">Organiser account</a><?php endif; ?>
    <?php if ($user['Role'] === 'admin'): ?><a class="btn btn-primary" href="admin-panel/dashboard.php">Admin portal</a><?php endif; ?>
  </nav>
  <?php if ($user['Role'] === 'attendee'): ?>
  <section class="dashboard-panel"><div class="panel-header"><h2>Recent bookings</h2><a href="my-bookings.php">View all</a></div>
    <?php if (!$bookings): ?><p class="empty-state">You do not have bookings to display yet. <a href="pages/events.php">Explore events</a>.</p><?php else: ?>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Event</th><th>Date</th><th>Ticket</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($bookings as $b): ?><tr><td><?= en_e($b['EventName']) ?></td><td><?= en_e($b['EventDate']) ?></td><td><?= en_e($b['TicketType'] ?? '—') ?></td><td><?= en_e(ucfirst($b['Status'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </section>
  <?php elseif ($user['Role'] === 'organiser'): ?>
  <section class="dashboard-panel"><div class="panel-header"><h2>Your events</h2><a href="create-event.php">Create event</a></div>
    <?php if (!$events): ?><p class="empty-state">You have not created any events yet.</p><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Event</th><th>Date</th><th>Status</th><th>Action</th></tr></thead><tbody>
    <?php foreach ($events as $e): ?><tr><td><?= en_e($e['EventName']) ?></td><td><?= en_e($e['EventDate']) ?></td><td><?= en_e(ucfirst($e['Status'])) ?></td><td><a href="edit-event.php?id=<?= (int)$e['EventID'] ?>">Edit</a></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </section>
  <?php endif; ?>
</main></div>
<?php require_once __DIR__ . '/shared-components/footer.php'; ?>
