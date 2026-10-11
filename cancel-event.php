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

function cancel_event_id(mixed $value): int
{
    return is_string($value) && ctype_digit($value) && (int) $value > 0 ? (int) $value : 0;
}

function cancel_event_send_email(string $to, string $subject, string $body): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    if (function_exists('send_eventnest_email')) {
        try {
            return (bool) send_eventnest_email($to, $subject, $body);
        } catch (Throwable $exception) {
            error_log('EventNest cancellation email helper failed: ' . get_class($exception));
            return false;
        }
    }
    if (function_exists('sendEmail')) {
        try {
            return (bool) sendEmail($to, $subject, $body);
        } catch (Throwable $exception) {
            error_log('EventNest cancellation email helper failed: ' . get_class($exception));
            return false;
        }
    }

    $from = trim((string) getenv('EVENTNEST_SUPPORT_EMAIL'));
    if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        error_log('EventNest cancellation email skipped: no configured mail helper/support sender.');
        return false;
    }

    return @mail($to, $subject, $body, 'From: EventNest <' . $from . '>');
}

function cancel_event_sandbox_refund(array $payment): bool
{
    // EventNest is a student prototype. Its escrow/refund behaviour is represented by
    // sandbox transaction IDs and database states, not by holding real customer money.
    // The PayFast sandbox merchant portal is used to test the external refund action.
    // This function records that an eligible sandbox refund was initiated in EventNest;
    // no card/bank data or secret credential is handled here.
    if (($payment['PaymentStatus'] ?? '') !== 'success' || empty($payment['TransactionID'])) {
        return false;
    }

    error_log('EventNest sandbox refund initiated for PaymentID ' . (int) $payment['PaymentID']);
    return true;
}

$event_id = cancel_event_id($_POST['event_id'] ?? $_GET['event_id'] ?? '');
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

$impact_stmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT b.BookingID) AS BookingCount,
            COALESCE(SUM(CASE WHEN b.Status = 'paid' AND b.TotalAmount > 0 THEN b.TotalAmount ELSE 0 END), 0) AS PotentialRefundTotal
     FROM BOOKINGS b
     WHERE b.EventID = :event_id
       AND b.Status IN ('pending','paid','failed')"
);
$impact_stmt->execute([':event_id' => $event_id]);
$impact = $impact_stmt->fetch(PDO::FETCH_ASSOC) ?: ['BookingCount' => 0, 'PotentialRefundTotal' => 0];

$errors = [];
$result = null;
$cancellable = in_array($event['Status'], ['draft', 'published'], true);
$csrf_token = get_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') !== 'cancel_event') {
        $errors[] = 'The cancellation action was invalid.';
    }
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your form session expired. Please try again.';
    }

    if (!$errors) {
        $notification_rows = [];
        $refund_total = 0.0;
        $booking_changes = 0;
        $refund_changes = 0;
        $attention_count = 0;

        try {
            $pdo->beginTransaction();

            $lock_event = $pdo->prepare(
                'SELECT EventID, EventName, Status, OrganiserID FROM EVENTS WHERE EventID = :event_id AND OrganiserID = :organiser_id LIMIT 1 FOR UPDATE'
            );
            $lock_event->execute([':event_id' => $event_id, ':organiser_id' => $organiser_id]);
            $locked_event = $lock_event->fetch(PDO::FETCH_ASSOC);
            if (!$locked_event) {
                throw new DomainException('You do not own this event.');
            }
            if ($locked_event['Status'] === 'canceled') {
                throw new DomainException('This event has already been cancelled.');
            }
            if (!in_array($locked_event['Status'], ['draft', 'published'], true)) {
                throw new DomainException('This event can no longer be cancelled.');
            }

            $booking_stmt = $pdo->prepare(
                "SELECT b.BookingID, b.BookingReference, b.Status, b.Quantity, b.TotalAmount,
                        b.TicketID, u.Email, u.FirstName
                 FROM BOOKINGS b
                 INNER JOIN USERS u ON u.UserID = b.UserID
                 WHERE b.EventID = :event_id
                 ORDER BY b.BookingID ASC
                 FOR UPDATE"
            );
            $booking_stmt->execute([':event_id' => $event_id]);
            $bookings = $booking_stmt->fetchAll(PDO::FETCH_ASSOC);

            $event_update = $pdo->prepare("UPDATE EVENTS SET Status = 'canceled', UpdatedAt = NOW() WHERE EventID = :event_id AND OrganiserID = :organiser_id AND Status IN ('draft','published')");
            $event_update->execute([':event_id' => $event_id, ':organiser_id' => $organiser_id]);
            if ($event_update->rowCount() !== 1) {
                throw new DomainException('The event changed state before cancellation could be completed.');
            }

            foreach ($bookings as $booking) {
                $booking_id = (int) $booking['BookingID'];
                $old_status = (string) $booking['Status'];
                $new_status = null;
                $refund_message = 'No payment refund is required.';

                if ($old_status === 'pending' || $old_status === 'failed') {
                    $update_booking = $pdo->prepare("UPDATE BOOKINGS SET Status = 'canceled' WHERE BookingID = :booking_id AND Status = :old_status");
                    $update_booking->execute([':booking_id' => $booking_id, ':old_status' => $old_status]);
                    if ($update_booking->rowCount() === 1) {
                        $new_status = 'canceled';
                        $restore = $pdo->prepare('UPDATE TICKETS SET QuantityAvailable = LEAST(Capacity, QuantityAvailable + :quantity) WHERE TicketID = :ticket_id');
                        $restore->execute([':quantity' => (int) $booking['Quantity'], ':ticket_id' => (int) $booking['TicketID']]);
                        $booking_changes++;
                    }
                } elseif ($old_status === 'paid') {
                    $payments_stmt = $pdo->prepare(
                        "SELECT PaymentID, PaymentStatus, TransactionID, Escrow_status, Amount
                         FROM PAYMENTS
                         WHERE BookingID = :booking_id
                         ORDER BY PaymentID ASC
                         FOR UPDATE"
                    );
                    $payments_stmt->execute([':booking_id' => $booking_id]);
                    $payments = $payments_stmt->fetchAll(PDO::FETCH_ASSOC);

                    if ((float) $booking['TotalAmount'] > 0.0) {
                        $all_refundable = true;
                        $processed_any = false;
                        foreach ($payments as $payment) {
                            if ($payment['PaymentStatus'] !== 'success') {
                                continue;
                            }
                            if ($payment['Escrow_status'] === 'released') {
                                $all_refundable = false;
                                $attention_count++;
                                continue;
                            }
                            if ($payment['PaymentStatus'] === 'refunded' || $payment['Escrow_status'] === 'refunded') {
                                $processed_any = true;
                                continue;
                            }
                            if (!cancel_event_sandbox_refund($payment)) {
                                $all_refundable = false;
                                $attention_count++;
                                continue;
                            }

                            $refund_payment = $pdo->prepare(
                                "UPDATE PAYMENTS
                                 SET PaymentStatus = 'refunded', Escrow_status = 'refunded'
                                 WHERE PaymentID = :payment_id
                                   AND PaymentStatus = 'success'
                                   AND (Escrow_status = 'held' OR Escrow_status IS NULL)"
                            );
                            $refund_payment->execute([':payment_id' => (int) $payment['PaymentID']]);
                            if ($refund_payment->rowCount() === 1) {
                                $refund_total += (float) $payment['Amount'];
                                $refund_changes++;
                                $processed_any = true;
                            }
                        }

                        if ($all_refundable && $processed_any) {
                            $update_booking = $pdo->prepare("UPDATE BOOKINGS SET Status = 'refunded' WHERE BookingID = :booking_id AND Status = 'paid'");
                            $update_booking->execute([':booking_id' => $booking_id]);
                            if ($update_booking->rowCount() === 1) {
                                $new_status = 'refunded';
                                $restore = $pdo->prepare('UPDATE TICKETS SET QuantityAvailable = LEAST(Capacity, QuantityAvailable + :quantity) WHERE TicketID = :ticket_id');
                                $restore->execute([':quantity' => (int) $booking['Quantity'], ':ticket_id' => (int) $booking['TicketID']]);
                                $booking_changes++;
                                $refund_message = 'The paid booking was moved to the EventNest sandbox refund state.';
                            }
                        } else {
                            $refund_message = 'This paid booking needs administrator attention before its refund can be finalised.';
                        }
                    } else {
                        $update_booking = $pdo->prepare("UPDATE BOOKINGS SET Status = 'canceled' WHERE BookingID = :booking_id AND Status = 'paid'");
                        $update_booking->execute([':booking_id' => $booking_id]);
                        if ($update_booking->rowCount() === 1) {
                            $new_status = 'canceled';
                            $restore = $pdo->prepare('UPDATE TICKETS SET QuantityAvailable = LEAST(Capacity, QuantityAvailable + :quantity) WHERE TicketID = :ticket_id');
                            $restore->execute([':quantity' => (int) $booking['Quantity'], ':ticket_id' => (int) $booking['TicketID']]);
                            $booking_changes++;
                        }
                    }
                }

                if ($new_status !== null) {
                    $notification_rows[] = [
                        'email' => (string) $booking['Email'],
                        'first_name' => (string) $booking['FirstName'],
                        'reference' => (string) $booking['BookingReference'],
                        'status' => $new_status,
                        'refund_message' => $refund_message,
                    ];
                }
            }

            $pdo->commit();

            $email_failures = 0;
            foreach ($notification_rows as $notification) {
                $body = "Hello " . ($notification['first_name'] !== '' ? $notification['first_name'] : 'EventNest attendee') . ",\n\n"
                    . "The event \"" . $event['EventName'] . "\" has been cancelled by its organiser.\n"
                    . "Booking reference: " . $notification['reference'] . "\n"
                    . "Booking status: " . $notification['status'] . "\n"
                    . $notification['refund_message'] . "\n\n"
                    . "Open My Bookings in EventNest for the latest status.\n";
                if (!cancel_event_send_email($notification['email'], 'EventNest event cancellation: ' . $event['EventName'], $body)) {
                    $email_failures++;
                }
            }

            $event['Status'] = 'canceled';
            $cancellable = false;
            $result = [
                'booking_changes' => $booking_changes,
                'refund_changes' => $refund_changes,
                'refund_total' => $refund_total,
                'email_failures' => $email_failures,
                'attention_count' => $attention_count,
            ];
        } catch (DomainException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $exception->getMessage();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('EventNest event cancellation error: ' . get_class($exception));
            $errors[] = 'The event could not be cancelled safely. No partial database change was kept.';
        }
    }
}

$page_title = 'Cancel event';
require_once __DIR__ . '/shared-components/header.php';
?>
<main class="page-content cancel-event-page">
    <p><a class="btn btn-outline" href="manage-events.php">Back to manage events</a></p>

    <?php if ($result): ?>
        <section class="result-card">
            <div class="status status-error">Cancelled</div>
            <h1><?= escape_html($event['EventName']) ?> has been cancelled</h1>
            <p>The event record was kept for booking, payment and audit history.</p>
            <dl>
                <div><dt>Bookings changed</dt><dd><?= (int) $result['booking_changes'] ?></dd></div>
                <div><dt>Sandbox refunds recorded</dt><dd><?= (int) $result['refund_changes'] ?></dd></div>
                <div><dt>Refund total</dt><dd><?= escape_html(format_money($result['refund_total'])) ?></dd></div>
                <div><dt>Email delivery issues</dt><dd><?= (int) $result['email_failures'] ?></dd></div>
                <div><dt>Items needing admin attention</dt><dd><?= (int) $result['attention_count'] ?></dd></div>
            </dl>
            <?php if ((int) $result['attention_count'] > 0 || (int) $result['email_failures'] > 0): ?>
                <div class="alert alert-warning">The event cancellation is saved, but one or more refund/email items require follow-up. A notification failure does not reverse the valid database cancellation.</div>
            <?php endif; ?>
            <div class="button-row">
                <a class="btn btn-secondary" href="manage-events.php">Back to events</a>
                <a class="btn btn-outline" href="business-dashboard.php">Organiser dashboard</a>
            </div>
        </section>
    <?php else: ?>
        <h1>Cancel <?= escape_html($event['EventName']) ?>?</h1>

        <?php if ($errors): ?>
            <div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= escape_html($error) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <?php if (!$cancellable): ?>
            <div class="alert alert-warning">
                <p>This event cannot be cancelled from this page because its current status is <?= escape_html($event['Status']) ?>.</p>
                <a class="btn btn-outline" href="manage-events.php">Back to manage events</a>
            </div>
        <?php else: ?>
            <section class="summary-card">
                <h2>Impact before confirmation</h2>
                <dl>
                    <div><dt>Current event status</dt><dd><?= escape_html($event['Status']) ?></dd></div>
                    <div><dt>Affected active bookings</dt><dd><?= (int) $impact['BookingCount'] ?></dd></div>
                    <div><dt>Potential paid refund value</dt><dd><?= escape_html(format_money($impact['PotentialRefundTotal'])) ?></dd></div>
                </dl>
                <p>EventNest will hide the event from new bookings, update eligible booking/payment states, restore ticket quantities only with valid booking state changes, and notify affected attendees.</p>
                <p><strong>This action does not delete the event or its history.</strong></p>
            </section>

            <form method="post" action="cancel-event.php">
                <input type="hidden" name="event_id" value="<?= $event_id ?>">
                <input type="hidden" name="action" value="cancel_event">
                <input type="hidden" name="csrf_token" value="<?= escape_html($csrf_token) ?>">
                <div class="button-row">
                    <a class="btn btn-outline" href="manage-events.php">Keep event</a>
                    <button class="btn btn-danger" type="submit">Confirm cancellation</button>
                </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</main>
<?php require_once __DIR__ . '/shared-components/footer.php'; ?>

