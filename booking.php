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

$auth_user = auth_require_attendee();
$user_id = (int) $auth_user['UserID'];
eventnest_expire_pending($pdo); // Free every expired hold, not just this user's, so stock is accurate.

/** Thrown when the last tickets are held by someone who is still paying. */
class BookingHoldException extends DomainException
{
    public int $secondsLeft = 0;
}

function booking_positive_int(mixed $value): int
{
    if (is_int($value)) {
        return $value > 0 ? $value : 0;
    }
    if (!is_string($value) || !ctype_digit($value)) {
        return 0;
    }
    $number = (int) $value;
    return $number > 0 ? $number : 0;
}

function booking_email_domain(string $email): string
{
    $parts = explode('@', strtolower(trim($email)));
    return count($parts) === 2 ? $parts[1] : '';
}

function booking_domain_matches(string $email, string $allowed): bool
{
    $domain = booking_email_domain($email);
    $allowed = strtolower(ltrim(trim($allowed), '@'));
    return $domain !== '' && $allowed !== '' && ($domain === $allowed || str_ends_with($domain, '.' . $allowed));
}

function booking_valid_student_email(string $email): bool
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $domain = booking_email_domain($email);
    return str_ends_with($domain, '.ac.za') || $domain === 'tuks.co.za' || str_ends_with($domain, '.tuks.co.za');
}

function booking_requires_age_confirmation(?string $restriction): bool
{
    $value = strtolower(trim((string) $restriction));
    if ($value === '' || in_array($value, ['none', 'no restriction', 'all ages', 'n/a'], true)) {
        return false;
    }
    return true;
}

function booking_load_event(PDO $pdo, int $event_id, bool $for_update = false): ?array
{
    $sql =
        "SELECT e.EventID, e.OrganiserID, e.CategoryID, e.EventName, e.EventDate,
                e.AgeRestriction, e.Status, e.Format, e.CareerGoalType,
                e.AllowedCareerField, e.AllowedEmailDomain,
                c.CategoryName,
                u.Role AS OrganiserRole, u.UserStatus AS OrganiserUserStatus,
                u.VerificationStatus AS OrganiserVerificationStatus,
                u.VerificationFeePaidUntil, u.VerificationFeePaymentStatus
         FROM EVENTS e
         INNER JOIN CATEGORIES c ON c.CategoryID = e.CategoryID
         INNER JOIN USERS u ON u.UserID = e.OrganiserID
         WHERE e.EventID = :event_id
         LIMIT 1";
    if ($for_update) {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':event_id' => $event_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function booking_event_gate_error(PDO $pdo, array $event, array $attendee): string
{
    if ($event['CareerGoalType'] === 'field') {
        if (empty($event['AllowedCareerField'])) {
            return 'This event has an invalid career-field restriction. Please contact EventNest support.';
        }
        $stmt = $pdo->prepare('SELECT CategoryName FROM CATEGORIES WHERE CategoryID = :category_id LIMIT 1');
        $stmt->execute([':category_id' => (int) $event['AllowedCareerField']]);
        $allowed = trim((string) ($stmt->fetchColumn() ?: ''));
        if ($allowed === '' || mb_strtolower(trim((string) $attendee['CareerField']), 'UTF-8') !== mb_strtolower($allowed, 'UTF-8')) {
            return $allowed !== ''
                ? 'This event is limited to the ' . $allowed . ' career field.'
                : 'You do not meet this event\'s career-field restriction.';
        }
    }

    if ($event['CareerGoalType'] === 'email_domain') {
        if ((int) $attendee['Is_email_verified'] !== 1 || !booking_domain_matches((string) $attendee['Email'], (string) $event['AllowedEmailDomain'])) {
            $domain = ltrim((string) $event['AllowedEmailDomain'], '@');
            return $domain !== ''
                ? 'This event requires a verified account email from ' . $domain . '.'
                : 'You do not meet this event\'s verified email restriction.';
        }
    }

    return '';
}

$event_id = booking_positive_int($_POST['event_id'] ?? $_GET['event_id'] ?? '');
if ($event_id <= 0) {
    http_response_code(404);
    $page_title = 'Booking unavailable';
    require_once __DIR__ . '/shared-components/header.php';
    echo '<main class="page-content"><div class="empty-state"><h1>Booking unavailable</h1><p>The event link is invalid.</p><a class="btn btn-outline" href="events.php">Browse events</a></div></main>';
    require_once __DIR__ . '/shared-components/footer.php';
    exit;
}

$user_stmt = $pdo->prepare(
    "SELECT UserID, Email, CareerField, Is_email_verified, UserStatus
     FROM USERS WHERE UserID = :user_id AND Role = 'attendee' LIMIT 1"
);
$user_stmt->execute([':user_id' => $user_id]);
$attendee = $user_stmt->fetch(PDO::FETCH_ASSOC);
if (!$attendee || $attendee['UserStatus'] !== 'active') {
    http_response_code(403);
    exit('Your account cannot create a booking.');
}

$event = booking_load_event($pdo, $event_id);
if (!$event) {
    http_response_code(404);
    exit('Event not found.');
}

$errors = [];
$hold_notice = null;
$blocking_error = '';

if ($event['Status'] !== 'published' || strtotime((string) $event['EventDate']) <= time()) {
    $blocking_error = 'This event is not currently open for new bookings.';
} elseif ($event['OrganiserRole'] !== 'organiser' || $event['OrganiserVerificationStatus'] !== 'verified' || $event['OrganiserUserStatus'] !== 'active') {
    $blocking_error = 'New bookings are temporarily unavailable for this organiser.';
} elseif ($event['VerificationFeePaymentStatus'] !== 'paid' || empty($event['VerificationFeePaidUntil']) || strtotime((string) $event['VerificationFeePaidUntil']) <= time()) {
    $blocking_error = 'New bookings are temporarily disabled while the organiser renews EventNest verification coverage.';
}

$block_stmt = $pdo->prepare('SELECT 1 FROM BLOCKS WHERE OrganiserID = :organiser_id AND LOWER(BlockedEmail) = LOWER(:email) LIMIT 1');
$block_stmt->execute([':organiser_id' => (int) $event['OrganiserID'], ':email' => (string) $attendee['Email']]);
if ($block_stmt->fetchColumn()) {
    $blocking_error = 'You cannot book events from this organiser. Contact EventNest support if you think this is a mistake.';
}

$gate_error = booking_event_gate_error($pdo, $event, $attendee);
if ($gate_error !== '') {
    $blocking_error = $gate_error;
}

$ticket_stmt = $pdo->prepare(
    'SELECT TicketID, TicketType, Price, QuantityAvailable, Capacity, Description FROM TICKETS WHERE EventID = :event_id ORDER BY Price ASC, TicketID ASC'
);
$ticket_stmt->execute([':event_id' => $event_id]);
$tickets = $ticket_stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$tickets && $blocking_error === '') {
    $blocking_error = 'No ticket tiers are available for this event.';
}

$tier_holds = [];
$waitlisted = [];
foreach ($tickets as $tier) {
    if ((int) $tier['QuantityAvailable'] <= 0) {
        $tier_holds[(int) $tier['TicketID']] = eventnest_ticket_hold($pdo, (int) $tier['TicketID'], $user_id)['held'] > 0;
    }
}
try {
    $wait_stmt = $pdo->prepare('SELECT TicketID, NotifiedAt FROM TICKET_WAITLIST WHERE UserID = ?');
    $wait_stmt->execute([$user_id]);
    foreach ($wait_stmt->fetchAll(PDO::FETCH_ASSOC) as $wait_row) {
        $waitlisted[(int) $wait_row['TicketID']] = $wait_row['NotifiedAt'];
    }
} catch (Throwable $exception) {
    $waitlisted = [];
}

$selected_ticket_id = booking_positive_int($_POST['ticket_id'] ?? $_GET['ticket_id'] ?? '');
$quantity = booking_positive_int($_POST['quantity'] ?? ($_SERVER['REQUEST_METHOD'] === 'POST' ? '' : '1'));
$student_email = is_string($_POST['student_email'] ?? null) ? trim($_POST['student_email']) : '';
$age_confirm = ($_POST['age_confirm'] ?? '') === '1';
$csrf_token = eventnest_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $blocking_error === '') {
    if (!eventnest_verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your form session expired. Please try again.';
    }
    if ($selected_ticket_id <= 0) {
        $errors[] = 'Choose a ticket tier.';
    }
    if ($quantity <= 0) {
        $errors[] = 'Quantity must be at least 1.';
    }
    if (booking_requires_age_confirmation($event['AgeRestriction']) && !$age_confirm) {
        $errors[] = 'Confirm that you meet the event age requirement.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $locked_event = booking_load_event($pdo, $event_id, true);
            if (!$locked_event || $locked_event['Status'] !== 'published' || strtotime((string) $locked_event['EventDate']) <= time()) {
                throw new DomainException('This event is no longer open for booking.');
            }
            if ($locked_event['OrganiserVerificationStatus'] !== 'verified' || $locked_event['OrganiserUserStatus'] !== 'active') {
                throw new DomainException('New bookings are temporarily unavailable for this organiser.');
            }
            if ($locked_event['VerificationFeePaymentStatus'] !== 'paid' || empty($locked_event['VerificationFeePaidUntil']) || strtotime((string) $locked_event['VerificationFeePaidUntil']) <= time()) {
                throw new DomainException('New bookings are temporarily disabled while organiser verification coverage is renewed.');
            }

            $locked_block = $pdo->prepare('SELECT 1 FROM BLOCKS WHERE OrganiserID = :organiser_id AND LOWER(BlockedEmail) = LOWER(:email) LIMIT 1');
            $locked_block->execute([':organiser_id' => (int) $locked_event['OrganiserID'], ':email' => (string) $attendee['Email']]);
            if ($locked_block->fetchColumn()) {
                throw new DomainException('You cannot book events from this organiser.');
            }

            $locked_gate_error = booking_event_gate_error($pdo, $locked_event, $attendee);
            if ($locked_gate_error !== '') {
                throw new DomainException($locked_gate_error);
            }

            // Reuse an unpaid reservation for the same ticket and quantity within its four-minute window.
            $retry = $pdo->prepare("SELECT BookingID FROM BOOKINGS WHERE UserID=? AND EventID=? AND TicketID=? AND Quantity=? AND Status='pending' AND BookingDate>DATE_SUB(NOW(), INTERVAL 4 MINUTE) ORDER BY BookingID DESC LIMIT 1 FOR UPDATE");
            $retry->execute([$user_id,$event_id,$selected_ticket_id,$quantity]);
            $retry_id = $retry->fetchColumn();
            if ($retry_id) {
                $pdo->commit(); auth_redirect('payment.php?booking_id='.(int)$retry_id);
            }

            $ticket_lock = $pdo->prepare(
                'SELECT TicketID, EventID, TicketType, Price, QuantityAvailable, Capacity, Description FROM TICKETS WHERE TicketID = :ticket_id AND EventID = :event_id LIMIT 1 FOR UPDATE'
            );
            $ticket_lock->execute([':ticket_id' => $selected_ticket_id, ':event_id' => $event_id]);
            $ticket = $ticket_lock->fetch(PDO::FETCH_ASSOC);
            if (!$ticket) {
                throw new DomainException('The selected ticket tier is invalid for this event.');
            }
            if ($quantity > (int) $ticket['QuantityAvailable']) {
                $hold = eventnest_ticket_hold($pdo, $selected_ticket_id, $user_id);
                if ($hold['held'] > 0) {
                    $hold_exception = new BookingHoldException('Payment pending');
                    $hold_exception->secondsLeft = $hold['seconds_left'];
                    throw $hold_exception;
                }
                throw new DomainException('There are not enough tickets remaining for that quantity.');
            }
            if ($ticket['TicketType'] === 'Student' && !booking_valid_student_email($student_email)) {
                throw new DomainException('Enter a valid student email ending in ac.za or tuks.co.za for the Student ticket.');
            }
            if (booking_requires_age_confirmation($locked_event['AgeRestriction']) && !$age_confirm) {
                throw new DomainException('Confirm that you meet the event age requirement.');
            }

            $reserve_stmt = $pdo->prepare(
                'UPDATE TICKETS SET QuantityAvailable = QuantityAvailable - :quantity WHERE TicketID = :ticket_id AND QuantityAvailable >= :minimum_quantity'
            );
            $reserve_stmt->execute([':quantity' => $quantity, ':minimum_quantity' => $quantity, ':ticket_id' => $selected_ticket_id]);
            if ($reserve_stmt->rowCount() !== 1) {
                throw new DomainException('Those tickets were just taken by another booking. Please choose an available quantity.');
            }

            $total_amount = round((float) $ticket['Price'] * $quantity, 2);
            $booking_reference = generate_booking_reference($pdo);

            // The current ERD has no separate "free" booking status. A valid zero-value
            // booking is complete immediately and therefore uses the active paid state with R0.00.
            $booking_status = $total_amount <= 0.0 ? 'paid' : 'pending';

            $insert = $pdo->prepare(
                "INSERT INTO BOOKINGS
                    (EventID, UserID, TicketID, BookingDate, Quantity, TotalAmount, Status, BookingReference)
                 VALUES
                    (:event_id, :user_id, :ticket_id, NOW(), :quantity, :total_amount, :status, :booking_reference)"
            );
            $insert->execute([
                ':event_id' => $event_id,
                ':user_id' => $user_id,
                ':ticket_id' => $selected_ticket_id,
                ':quantity' => $quantity,
                ':total_amount' => number_format($total_amount, 2, '.', ''),
                ':status' => $booking_status,
                ':booking_reference' => $booking_reference,
            ]);
            $booking_id = (int) $pdo->lastInsertId();
            eventnest_waitlist_leave($pdo, $user_id, $selected_ticket_id);

            $pdo->commit();

            if ($total_amount <= 0.0) {
                // A repeated free-booking submission must not reserve another set of tickets.
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                header('Location: ' . eventnest_url('payment-success.php?booking_id=' . $booking_id . '&free=1'));
            } else {
                header('Location: ' . eventnest_url('payment.php?booking_id=' . $booking_id));
            }
            exit;
        } catch (BookingHoldException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Joined after the rollback so the waitlist row is kept.
            eventnest_waitlist_join($pdo, $user_id, $selected_ticket_id, $quantity);
            $hold_notice = [
                'seconds' => $exception->secondsLeft,
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
            error_log('EventNest booking error: ' . get_class($exception));
            $errors[] = 'The booking could not be completed. Please try again.';
        }
    }
}

$page_title = 'Book ' . (string) $event['EventName'];
require_once __DIR__ . '/shared-components/header.php';
?>
<main class="page-content booking-page">
    <p><a class="btn btn-outline" href="event-detail.php?event_id=<?= $event_id ?>">Back to event</a></p>
    <h1>Book <?= escape_html($event['EventName']) ?></h1>
    <p>Step 1 of 3 · Select tickets</p>

    <?php if ($blocking_error !== ''): ?>
        <div class="alert alert-warning" role="alert">
            <p><?= escape_html($blocking_error) ?></p>
            <a class="btn btn-outline" href="event-detail.php?event_id=<?= $event_id ?>">View event</a>
        </div>
    <?php else: ?>
        <?php if ($hold_notice !== null): ?>
            <div class="alert alert-warning payment-pending-notice" role="status" aria-live="polite">
                <h2>Payment pending</h2>
                <p>
                    Another attendee has just booked the last of these tickets and is completing payment.
                    <?php if ($hold_notice['seconds'] > 0): ?>
                        Their hold expires in <strong data-countdown="<?= (int) $hold_notice['seconds'] ?>"><?= (int) $hold_notice['seconds'] ?> seconds</strong>.
                    <?php endif; ?>
                </p>
                <p>You have been added to the waitlist. If they do not pay in time, we will notify you straight away so you can book.</p>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert alert-error" role="alert">
                <p>Please correct the following:</p>
                <ul><?php foreach ($errors as $error): ?><li><?= escape_html($error) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="post" action="booking.php" data-validate="true" novalidate>
            <input type="hidden" name="event_id" value="<?= $event_id ?>">
            <input type="hidden" name="csrf_token" value="<?= escape_html($csrf_token) ?>">

            <div class="booking-layout"><div>
            <fieldset>
                <legend>Ticket tier</legend>
                <div class="ticket-tier-list">
                    <?php foreach ($tickets as $ticket): ?>
                        <?php
                            $tid = (int) $ticket['TicketID'];
                            $out_of_stock = (int) $ticket['QuantityAvailable'] <= 0;
                            $payment_pending = $out_of_stock && !empty($tier_holds[$tid]);
                            $sold_out = $out_of_stock && !$payment_pending;
                        ?>
                        <label class="ticket-tier<?= $sold_out ? ' is-disabled' : '' ?>">
                            <input
                                type="radio"
                                name="ticket_id"
                                value="<?= (int) $ticket['TicketID'] ?>"
                                data-ticket-type="<?= escape_html($ticket['TicketType']) ?>"
                                data-ticket-price="<?= escape_html((string) $ticket['Price']) ?>"
                                <?= $selected_ticket_id === (int) $ticket['TicketID'] ? 'checked' : '' ?>
                                <?= $sold_out ? 'disabled' : '' ?>
                                required
                            >
                            <span><strong><?= escape_html($ticket['TicketType']) ?></strong></span>
                            <?php if ($ticket['Description']): ?><span><?= escape_html($ticket['Description']) ?></span><?php endif; ?>
                            <span><?= (float) $ticket['Price'] <= 0 ? 'Free' : escape_html(format_money($ticket['Price'])) ?></span>
                            <span>
                                <?php if ($payment_pending): ?>
                                    <span class="status status-warning">Payment pending</span>
                                    <?= array_key_exists($tid, $waitlisted) ? 'You are on the waitlist' : 'Another attendee is paying. Select to join the waitlist.' ?>
                                <?php elseif ($sold_out): ?>
                                    Sold out
                                <?php else: ?>
                                    <?= (int) $ticket['QuantityAvailable'] ?> remaining
                                    <?php if (!empty($waitlisted[$tid])): ?><span class="status status-success">Available now</span><?php endif; ?>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            </div><aside class="summary-card">
            <h2>Booking summary</h2><p><?= escape_html($event['EventName']) ?></p><p><?= escape_html(format_datetime($event['EventDate'])) ?></p>
            <div class="form-field">
                <label for="quantity">Quantity</label>
                <input id="quantity" name="quantity" type="number" min="1" step="1" value="<?= $quantity ?>" required data-validate-number="positive">
            </div>

            <div class="form-field" data-student-email-field hidden>
                <label for="student_email">Academic email address</label>
                <input id="student_email" name="student_email" type="email" maxlength="100" value="<?= escape_html($student_email) ?>" autocomplete="email">
                <small>Student tickets require an email ending in ac.za or tuks.co.za. This temporary value is checked for eligibility and is not stored.</small>
            </div>

            <?php if (booking_requires_age_confirmation($event['AgeRestriction'])): ?>
                <div class="form-field">
                    <label>
                        <input type="checkbox" name="age_confirm" value="1" <?= $age_confirm ? 'checked' : '' ?> data-age-confirm required>
                        I confirm that I meet the event age requirement: <?= escape_html((string) $event['AgeRestriction']) ?>
                    </label>
                </div>
            <?php endif; ?>

            <?php if ($event['CareerGoalType'] === 'field'): ?>
                <p class="alert alert-info">Your saved career field is checked again when the booking is submitted.</p>
            <?php elseif ($event['CareerGoalType'] === 'email_domain'): ?>
                <p class="alert alert-info">Your verified EventNest account email must meet this event's domain restriction.</p>
            <?php endif; ?>

            <p>Total estimate: <strong data-booking-total>Select a ticket</strong></p>
            <button class="btn btn-primary" type="submit">Continue</button>
            </aside></div>
        </form>
    <?php endif; ?>
</main>
<script src="<?= escape_html(eventnest_url('scripts/validation.js')) ?>" defer></script>
<script src="<?= escape_html(eventnest_url('scripts/student-email-check.js')) ?>" defer></script>
<?php require_once __DIR__ . '/shared-components/footer.php'; ?>
