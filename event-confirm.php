<?php

declare(strict_types=1);

require_once __DIR__ . '/shared-components/auth.php';

$db = isset($pdo) && $pdo instanceof PDO ? $pdo : ($conn ?? null);
if (!$db instanceof PDO) {
    http_response_code(503);
    exit('Service temporarily unavailable.');
}
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

function en_confirm_h(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function en_confirm_positive_id(mixed $value): int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id === false ? 0 : (int)$id;
}

/*
EventNest email delivery through PHPMailer when environment settings are present. 
Email failure never rolls back a valid event completion.
 */
function en_confirm_send_mail(string $to, string $subject, string $body): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        error_log('EventNest mail: Composer autoload not found; completion email was not sent.');
        return false;
    }

    require_once $autoload;

    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        error_log('EventNest mail: PHPMailer is not installed.');
        return false;
    }

    $host = getenv('EVENTNEST_SMTP_HOST') ?: 'smtp.gmail.com';
    $username = getenv('EVENTNEST_SMTP_USER') ?: '';
    $password = getenv('EVENTNEST_SMTP_PASS') ?: '';
    $from = getenv('EVENTNEST_FROM_EMAIL') ?: $username;
    $fromName = getenv('EVENTNEST_FROM_NAME') ?: 'EventNest';
    $port = (int)(getenv('EVENTNEST_SMTP_PORT') ?: 587);

    if ($username === '' || $password === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        error_log('EventNest mail: protected SMTP environment settings are incomplete.');
        return false;
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $port;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($from, $fromName);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->isHTML(false);
        return $mail->send();
    } catch (Throwable $e) {
        error_log('EventNest completion email error: ' . $e->getMessage());
        return false;
    }
}

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

$auth = $db->prepare(
    "SELECT UserID
       FROM USERS
      WHERE UserID = ?
        AND Role = 'organiser'
        AND UserStatus = 'active'
        AND VerificationStatus = 'verified'"
);
$auth->execute([$userId]);
if (!$auth->fetchColumn()) {
    http_response_code(403);
    exit('Only approved organisers can confirm their own events.');
}

$eventId = en_confirm_positive_id($_POST['event_id'] ?? $_GET['event_id'] ?? null);
if ($eventId < 1) {
    http_response_code(400);
    exit('Invalid event.');
}

$eventQuery = $db->prepare(
    'SELECT EventID, EventName, EventDate, Status
       FROM EVENTS
      WHERE EventID = ? AND OrganiserID = ?'
);
$eventQuery->execute([$eventId, $userId]);
$event = $eventQuery->fetch();
if (!$event) {
    http_response_code(404);
    exit('Event not found.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$notice = '';
$noticeType = 'info';
$justCompleted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim_input($_POST['action'] ?? '');
    $csrf = $_POST['csrf_token'] ?? null;

    if ($action !== 'confirm_event' || !eventnest_verify_csrf_token($csrf)) {
        http_response_code(403);
        exit('Invalid confirmation request.');
    }

    try {
        $db->beginTransaction();

        $lock = $db->prepare(
            'SELECT EventID, EventName, EventDate, Status
               FROM EVENTS
              WHERE EventID = ? AND OrganiserID = ?
              FOR UPDATE'
        );
        $lock->execute([$eventId, $userId]);
        $current = $lock->fetch();

        if (!$current) {
            throw new RuntimeException('Event was not found.');
        }

        if ($current['Status'] === 'completed') {
            $notice = 'This event was already confirmed. No duplicate changes were made.';
            $noticeType = 'info';
        } elseif ($current['Status'] !== 'published') {
            throw new RuntimeException('Only a published event can be confirmed as completed.');
        } elseif (strtotime((string)$current['EventDate']) > time()) {
            throw new RuntimeException('The event start time has not passed yet.');
        } else {
            $update = $db->prepare(
                "UPDATE EVENTS
                    SET Status = 'completed'
                  WHERE EventID = ?
                    AND OrganiserID = ?
                    AND Status = 'published'"
            );
            $update->execute([$eventId, $userId]);

            if ($update->rowCount() !== 1) {
                throw new RuntimeException('The event status changed while it was being confirmed.');
            }

            /*
             * EventNest's final stored BOOKING values do not include a separate "confirmed"
             * value. Paid bookings and zero-value/free bookings therefore remain Status='paid'.
             * Successful paid PAYMENTS remain Escrow_status='held' for later authorised release.
             */
            $justCompleted = true;
            $notice = 'Event marked as completed. Eligible bookings were preserved and held payments remain held for post-event review.';
            $noticeType = 'success';
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('EventNest event-confirm error: ' . $e->getMessage());
        http_response_code(409);
        $notice = 'The event could not be confirmed. Check that it is published, belongs to you and its start time has passed.';
        $noticeType = 'error';
    }

    if ($justCompleted) {
        $mailQuery = $db->prepare(
            "SELECT b.BookingID, b.BookingReference, u.Email
               FROM BOOKINGS b
               JOIN USERS u ON u.UserID = b.UserID
              WHERE b.EventID = ?
                AND b.Status = 'paid'
                AND u.UserStatus = 'active'"
        );
        $mailQuery->execute([$eventId]);

        $failedEmails = 0;
        foreach ($mailQuery->fetchAll() as $recipient) {
            $baseUrl = rtrim((string) getenv('EVENTNEST_BASE_URL'), '/');
            $reviewRoute = 'pages/leave-review.php?booking_id=' . (int)$recipient['BookingID'];
            $reviewLink = $baseUrl !== '' ? $baseUrl . '/' . $reviewRoute : eventnest_url($reviewRoute);
            $body =
                "Your EventNest event has been marked completed: {$event['EventName']}\n" .
                "Booking reference: {$recipient['BookingReference']}\n" .
                "Leave a private review after signing in: {$reviewLink}\n\n" .
                "This is the organiser's event-completion confirmation; it is not independent proof of attendance.";

            if (!en_confirm_send_mail(
                (string)$recipient['Email'],
                'EventNest event completed',
                $body
            )) {
                $failedEmails++;
            }
        }

        if ($failedEmails > 0) {
            error_log("EventNest event-confirm: {$failedEmails} completion email(s) failed for EventID {$eventId}.");
            $notice .= ' Some review-invitation emails could not be delivered; the completion itself was saved.';
        }
    }

    $eventQuery->execute([$eventId, $userId]);
    $event = $eventQuery->fetch();
}

$page_title = 'Confirm event';
require_once __DIR__ . '/shared-components/header.php';
?>
<main class="container page-section">
    <section class="card" aria-labelledby="confirm-event-heading">
        <h1 id="confirm-event-heading">Confirm event completion</h1>
        <h2><?= en_confirm_h($event['EventName']) ?></h2>

        <dl class="summary-list">
            <div>
                <dt>Scheduled</dt>
                <dd><?= en_confirm_h($event['EventDate']) ?></dd>
            </div>
            <div>
                <dt>Status</dt>
                <dd><?= en_confirm_h($event['Status']) ?></dd>
            </div>
        </dl>

        <?php if ($notice !== ''): ?>
            <p class="status-message status-<?= en_confirm_h($noticeType) ?>" role="status">
                <?= en_confirm_h($notice) ?>
            </p>
        <?php endif; ?>

        <p>
            Confirming records that the organiser says the event took place. It does not prove
            physical attendance and it does not release held payments. Eligible paid payments remain
            held until the approved post-event escrow process authorises release.
        </p>

        <?php if ($event['Status'] === 'published' && strtotime((string)$event['EventDate']) <= time()): ?>
            <form method="post" action="event-confirm.php">
                <input type="hidden" name="event_id" value="<?= (int)$eventId ?>">
                <input type="hidden" name="action" value="confirm_event">
                <input type="hidden" name="csrf_token" value="<?= en_confirm_h($_SESSION['csrf_token']) ?>">
                <button class="btn btn-confirm" type="submit">Confirm event took place</button>
            </form>
        <?php elseif ($event['Status'] !== 'completed'): ?>
            <p class="status-message status-info">This event is not currently eligible for completion confirmation.</p>
        <?php endif; ?>

        <p>
            <a class="btn btn-outline" href="event-detail.php?event_id=<?= (int) $eventId ?>">Back to event</a>
        </p>
    </section>
</main>
<?php require_once __DIR__ . '/shared-components/footer.php'; ?>
