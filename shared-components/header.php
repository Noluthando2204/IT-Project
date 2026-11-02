<?php
require_once __DIR__ . '/functions.php';
eventnest_ensure_session();
$page_title = isset($page_title) ? $page_title : 'EventNest';
$flash = function_exists('auth_get_flash') ? auth_get_flash() : null;
$flashes = $flash ? [$flash] : [];

// Reservation timer: seconds left on the attendee's newest pending booking (4-minute hold).
// Calculated by the database so server/browser clocks and timezones cannot disagree.
$timer_remaining = 0;
$timer_booking_id = 0;
$timer_event_id = 0;
$timer_expires = 0;
if (($_SESSION['role'] ?? null) === 'attendee' && !empty($_SESSION['user_id'])) {
    try {
        eventnest_expire_pending(eventnest_db(), (int) $_SESSION['user_id']);
        $timer_stmt = eventnest_db()->prepare(
            "SELECT BookingID, EventID, UNIX_TIMESTAMP(DATE_ADD(BookingDate, INTERVAL 4 MINUTE)) AS ExpiresAt,
                    TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(BookingDate, INTERVAL 4 MINUTE)) AS SecondsLeft
             FROM BOOKINGS
             WHERE UserID = :user_id AND Status = 'pending'
             ORDER BY BookingDate DESC"
        );
        $timer_stmt->execute([':user_id' => (int) $_SESSION['user_id']]);
        while ($timer_row = $timer_stmt->fetch(PDO::FETCH_ASSOC)) {
          if ((int) $timer_row['SecondsLeft'] > 0 && !eventnest_checkout_started((int) $timer_row['BookingID'])) {
            $timer_remaining = min(240, (int) $timer_row['SecondsLeft']);
            $timer_booking_id = (int) $timer_row['BookingID'];
            $timer_event_id = (int) $timer_row['EventID'];
            $timer_expires = (int) $timer_row['ExpiresAt'];
            break;
          }
        }
    } catch (Throwable $exception) {
        $timer_remaining = 0;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="EventNest career-event discovery and booking platform">
    <title><?= e($page_title) ?> | EventNest</title>
    <link rel="stylesheet" href="<?= e(app_url('styles/main.css')) ?>">
    <link rel="stylesheet" href="<?= e(app_url('styles/responsive.css')) ?>">
    <link rel="stylesheet" href="<?= e(app_url('styles/reservation-timer.css')) ?>">

</head>
<body>
<?php if ($timer_remaining > 0): ?>
    <!-- Floating reservation timer, visible on every page while a booking is held. -->
    <aside id="reservation-timer" class="floating-timer" aria-label="Ticket reservation"
       data-booking-id="<?= $timer_booking_id ?>"
       data-expires="<?= $timer_expires * 1000 ?>"
       data-server-now="<?= time() * 1000 ?>"
       data-csrf="<?= e(eventnest_csrf_token()) ?>"
       data-expire-url="<?= e(app_url('reservation-expire.php')) ?>"
       data-expired-url="<?= e(app_url('event-detail.php?event_id=' . $timer_event_id)) ?>">
        <button type="button" class="timer-drag" aria-label="Drag reservation timer">Move</button>
        <a href="<?= e(app_url('payment.php?booking_id=' . $timer_booking_id)) ?>">
        <span id="timer-text">Ticket reserved for <?= sprintf('%02d:%02d', intdiv($timer_remaining, 60), $timer_remaining % 60) ?></span>
        </a>
    </aside>
<?php endif; ?>

<a class="skip-link" href="#main-content">Skip to content</a>
<header class="site-header">
    <div class="site-header-inner">
        <a class="brand" href="<?= e(app_url('index.php')) ?>" aria-label="EventNest home">
            <img src="<?= e(app_url('images/eventnest-logo.png')) ?>" alt="EventNest">
        </a>
        <?php require __DIR__ . '/navbar.php'; ?>
    </div>
</header>

<?php if ($flashes): ?>
<div class="flash-stack" aria-live="polite">
    <?php foreach ($flashes as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div id="main-content" class="page-shell">
