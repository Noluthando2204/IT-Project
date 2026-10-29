<?php
declare(strict_types=1);

require_once __DIR__ . '/shared-components/auth.php';
require_once __DIR__ . '/shared-components/functions.php';
require_once __DIR__ . '/shared-components/notifications.php';

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

if ((string) $user['Role'] === 'attendee') {
    eventnest_expire_pending($pdo, $user_id);
}

/*
EventNest does not have a NOTIFICATIONS table in the current ERD.
Notices are generated live from existing records (see shared-components/notifications.php).
The time of the last visit is remembered in a cookie so new notices can be highlighted.
*/
$notices = array_slice(eventnest_collect_notices($pdo, $user_id), 0, 50);
$last_seen = eventnest_notices_last_seen($user_id);
$new_count = 0;
foreach ($notices as $notice) {
    if ($notice['time'] > $last_seen) {
        $new_count++;
    }
}

// Opening the page marks everything as read. $last_seen above is kept so new items still show as "New" on this visit.
eventnest_notices_mark_seen($user_id);

$page_title = 'Notifications';
require_once __DIR__ . '/shared-components/header.php';
?>
<main class="page-content notifications-page">
    <header class="page-header">
        <h1>Notifications</h1>
        <p>
            <?php if ($new_count > 0): ?>
                You have <?= (int) $new_count ?> new notification<?= $new_count === 1 ? '' : 's' ?>.
            <?php else: ?>
                You&rsquo;re all caught up.
            <?php endif; ?>
            These notices come from your bookings, events, posts and account status.
        </p>
    </header>

    <?php if (!$notices): ?>
        <div class="empty-state">
            <h2>No current notifications</h2>
            <p>There is nothing that needs your attention right now.</p>
        </div>
    <?php else: ?>
        <div class="notification-list">
            <?php foreach ($notices as $notice):
                $is_new = $notice['time'] > $last_seen; ?>
                <article class="notification-card<?= $is_new ? ' is-new' : '' ?>">
                    <div class="notification-meta">
                        <span class="notification-type"><?= escape_html($notice['type']) ?></span>
                        <?php if ($is_new): ?><span class="notification-new">New</span><?php endif; ?>
                        <?php if ($notice['time'] > 0): ?>
                            <time class="notification-time" datetime="<?= escape_html(date('c', $notice['time'])) ?>"><?= escape_html(eventnest_time_ago($notice['time'])) ?></time>
                        <?php endif; ?>
                    </div>
                    <h2><?= escape_html($notice['title']) ?></h2>
                    <p><?= escape_html($notice['message']) ?></p>
                    <a class="btn btn-outline" href="<?= escape_html(eventnest_url($notice['url'])) ?>">View details</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php require_once __DIR__ . '/shared-components/footer.php'; ?>
