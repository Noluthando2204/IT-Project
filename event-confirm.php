<?php

declare(strict_types=1);

require_once __DIR__ . '/shared-components/auth.php';
require_once __DIR__ . '/shared-components/functions.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (isset($conn) && $conn instanceof PDO) {
        $pdo = $conn;
    } else {
        http_response_code(500);
        exit('EventNest is temporarily unavailable.');
    }
}

$auth_user = require_organiser_verified();
$organiser_id = (int) $auth_user['UserID'];

function event_confirm_id(mixed $value): int
{
    return is_string($value) && ctype_digit($value) && (int) $value > 0 ? (int) $value : 0;
}

function event_confirm_send_email(string $to, string $subject, string $body): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    if (function_exists('send_eventnest_email')) {
        try {
            return (bool) send_eventnest_email($to, $subject, $body);
        } catch (Throwable $exception) {
            error_log('EventNest review invitation helper failed: ' . get_class($exception));
            return false;
        }
    }
    if (function_exists('sendEmail')) {
        try {
            return (bool) sendEmail($to, $subject, $body);
        } catch (Throwable $exception) {
            error_log('EventNest review invitation helper failed: ' . get_class($exception));
            return false;
        }
    }

    $from = trim((string) getenv('EVENTNEST_SUPPORT_EMAIL'));
    if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        error_log('EventNest review invitation skipped: no configured mail helper/support sender.');
        return false;
    }
    return @mail($to, $subject, $body, 'From: EventNest <' . $from . '>');
}

function event_confirm_absolute_url(string $path): string
{
    $base = trim((string) getenv('EVENTNEST_BASE_URL'));
    if ($base !== '') {
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return ($https ? 'https' : 'http') . '://' . $host . eventnest_url($path);
}

$event_id = event_confirm_id($_POST['event_id'] ?? $_GET['event_id'] ?? '');
if ($event_id <= 0) {
    http_response_code(404);
    exit('Event not found.');
}

$event_stmt = $pdo->prepare(
    'SELECT EventID, EventName, EventDate, Status, OrganiserID FROM EVENTS WHERE EventID = :event_id AND OrganiserID = :organiser_id LIMIT 1'
);
$event_stmt->execute([':event_id' => $event_id, ':organiser_id' => $organiser_id]);
$event = $event_stmt->fetch(PDO::FETCH_ASSOC);
if (!$event) {
    http_response_code(404);
    exit('Event not found.');
}

$errors = [];
$result = null;
$csrf_token = get_csrf_token();
$event_has_passed = strtotime((string) $event['EventDate']) <= time();
$can_confirm = $event['Status'] === 'published' && $event_has_passed;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') !== 'confirm_event') {
        $errors[] = 'The confirmation action was invalid.';
    }
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your form session expired. Please try again.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $lock = $pdo->prepare(
                'SELECT EventID, EventName, EventDate, Status, OrganiserID FROM EVENTS WHERE EventID = :event_id AND OrganiserID = :organiser_id LIMIT 1 FOR UPDATE'
            );
            $lock->execute([':event_id' => $event_id, ':organiser_id' => $organiser_id]);
            $locked_event = $lock->fetch(PDO::FETCH_ASSOC);
            if (!$locked_event) {
                throw new DomainException('You do not own this event.');
            }

            if ($locked_event['Status'] === 'completed') {
                // Repeated submissions are safe: no second status transition or escrow release is attempted.
                $pdo->commit();
                $result = ['already_completed' => true, 'emails_sent' => 0, 'email_failures' => 0, 'eligible_bookings' => 0];
            } else {
                if ($locked_event['Status'] !== 'published') {
                    throw new DomainException('Only a published event can be confirmed as completed.');
                }
                if (strtotime((string) $locked_event['EventDate']) > time()) {
                    throw new DomainException('The event date has not passed yet.');
                }

                $update = $pdo->prepare(
                    "UPDATE EVENTS
                     SET Status = 'completed', UpdatedAt = NOW()
                     WHERE EventID = :event_id AND OrganiserID = :organiser_id AND Status = 'published'"
                );
                $update->execute([':event_id' => $event_id, ':organiser_id' => $organiser_id]);
                if ($update->rowCount() !== 1) {
                    throw new DomainException('The event changed state before confirmation could be completed.');
                }

                // The approved current schema has no separate BOOKING "confirmed" or "free" status.
                // Valid paid and R0.00 bookings therefore remain Status='paid'; Event.Status='completed'
                // is the authoritative post-event condition used by the review workflow.
                $booking_stmt = $pdo->prepare(
                    "SELECT b.BookingID, b.BookingReference, b.TotalAmount, b.Status,
                            u.Email, u.FirstName
                     FROM BOOKINGS b
                     INNER JOIN USERS u ON u.UserID = b.UserID
                     WHERE b.EventID = :event_id AND b.Status = 'paid'
                     ORDER BY b.BookingID ASC"
                );
                $booking_stmt->execute([':event_id' => $event_id]);
                $eligible_bookings = $booking_stmt->fetchAll(PDO::FETCH_ASSOC);

                // Successful paid PAYMENTS deliberately remain Escrow_status='held'. Release is
                // controlled later by scheduled-taks/escrow.php after the waiting/admin checks.
                $pdo->commit();

                $emails_sent = 0;
                $email_failures = 0;
                foreach ($eligible_bookings as $booking) {
                    $review_url = event_confirm_absolute_url('leave-review.php?booking_id=' . (int) $booking['BookingID']);
                    $body = "Hello " . ((string) $booking['FirstName'] !== '' ? $booking['FirstName'] : 'EventNest attendee') . ",\n\n"
                        . "The organiser has confirmed that \"" . $locked_event['EventName'] . "\" took place.\n"
                        . "Booking reference: " . $booking['BookingReference'] . "\n"
                        . "You can leave your private EventNest review here: " . $review_url . "\n\n"
                        . "This organiser confirmation opens the feedback process; it is not independent proof of physical attendance.\n";

                    if (event_confirm_send_email((string) $booking['Email'], 'EventNest review invitation: ' . $locked_event['EventName'], $body)) {
                        $emails_sent++;
                    } else {
                        $email_failures++;
                    }
                }

                $event['Status'] = 'completed';
                $can_confirm = false;
                $result = [
                    'already_completed' => false,
                    'emails_sent' => $emails_sent,
                    'email_failures' => $email_failures,
                    'eligible_bookings' => count($eligible_bookings),
                ];
            }
        } catch (DomainException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $exception->getMessage();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('EventNest event confirmation error: ' . get_class($exception));
            $errors[] = 'The event could not be confirmed safely. Please try again.';
        }
    }
}

$page_title = 'Confirm event completion';
require_once __DIR__ . '/shared-components/header.php';
?>
<main class="page-content event-confirm-page">
    <p><a class="btn btn-outline" href="manage-events.php">Back to manage events</a></p>

    <?php if ($result): ?>
        <section class="result-card">
            <div class="status status-success">Completed</div>
            <h1><?= escape_html($event['EventName']) ?> is marked completed</h1>
            <?php if ($result['already_completed']): ?>
                <p>This event was already completed. Repeating the confirmation did not change bookings or escrow again.</p>
            <?php else: ?>
                <p>Organiser confirmation has been recorded. This does not independently prove physical attendance.</p>
                <dl>
                    <div><dt>Eligible attendee bookings</dt><dd><?= (int) $result['eligible_bookings'] ?></dd></div>
                    <div><dt>Review invitations sent</dt><dd><?= (int) $result['emails_sent'] ?></dd></div>
                    <div><dt>Email delivery issues</dt><dd><?= (int) $result['email_failures'] ?></dd></div>
                    <div><dt>Paid escrow</dt><dd>Held during the post-event waiting/review period</dd></div>
                </dl>
                <?php if ((int) $result['email_failures'] > 0): ?>
                    <div class="alert alert-warning">The completion status is valid even though one or more review emails could not be delivered. Those delivery failures should be followed up separately.</div>
                <?php endif; ?>
            <?php endif; ?>
            <a class="btn btn-secondary" href="manage-events.php">Back to manage events</a>
        </section>
    <?php else: ?>
        <h1>Confirm <?= escape_html($event['EventName']) ?> took place?</h1>
        <p>Event date: <?= escape_html(format_datetime($event['EventDate'])) ?></p>

        <?php if ($errors): ?>
            <div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= escape_html($error) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <?php if ($event['Status'] === 'completed'): ?>
            <div class="alert alert-info">This event is already completed. No further confirmation is needed.</div>
        <?php elseif (!$event_has_passed): ?>
            <div class="alert alert-warning">The event cannot be confirmed before its scheduled date and time.</div>
        <?php elseif ($event['Status'] !== 'published'): ?>
            <div class="alert alert-warning">Only a published event can move to completed status.</div>
        <?php else: ?>
            <section class="summary-card">
                <p>Confirming completion will mark the event as completed, make eligible private reviews available and keep successful paid payments held for the post-event escrow review period.</p>
                <p>No payment is released directly from this page.</p>
            </section>
            <form method="post" action="event-confirm.php">
                <input type="hidden" name="event_id" value="<?= $event_id ?>">
                <input type="hidden" name="action" value="confirm_event">
                <input type="hidden" name="csrf_token" value="<?= escape_html($csrf_token) ?>">
                <button class="btn btn-success" type="submit">Confirm event</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</main>
<?php require_once __DIR__ . '/shared-components/footer.php'; ?>
