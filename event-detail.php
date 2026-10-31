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

function event_detail_id(string $key): int
{
    $value = $_GET[$key] ?? '';
    return is_string($value) && ctype_digit($value) && (int) $value > 0 ? (int) $value : 0;
}

function event_detail_tags(?string $hashtags): array
{
    preg_match_all('/#[\p{L}\p{N}_]+/u', (string) $hashtags, $matches);
    return array_values(array_unique($matches[0] ?? []));
}

function event_detail_email_domain(string $email): string
{
    $parts = explode('@', strtolower(trim($email)));
    return count($parts) === 2 ? $parts[1] : '';
}

function event_detail_domain_matches(string $email, string $allowed): bool
{
    $domain = event_detail_email_domain($email);
    $allowed = strtolower(ltrim(trim($allowed), '@'));
    return $domain !== '' && $allowed !== '' && ($domain === $allowed || str_ends_with($domain, '.' . $allowed));
}

function event_detail_public_venue(array $event): string
{
    $venue = trim((string) ($event['Venue'] ?? ''));
    if ($venue === '') {
        return $event['Format'] === 'online' ? 'Online event' : 'Location to be confirmed';
    }
    if (filter_var($venue, FILTER_VALIDATE_URL)) {
        return $event['Format'] === 'online' ? 'Online event' : 'Venue details available through EventNest';
    }
    return $venue;
}

$event_id = event_detail_id('event_id');
if ($event_id <= 0) {
    http_response_code(404);
    $page_title = 'Event not found';
    require_once __DIR__ . '/shared-components/header.php';
    echo '<main class="page-content"><div class="empty-state"><h1>Event not found</h1><p>The event link is invalid.</p><a class="btn btn-outline" href="events.php">Back to events</a></div></main>';
    require_once __DIR__ . '/shared-components/footer.php';
    exit;
}

$stmt = $pdo->prepare(
    "SELECT e.*, c.CategoryName,
            u.FirstName AS OrganiserFirstName, u.LastName AS OrganiserLastName,
            u.OrganisationName AS OrganiserOrganisationName,
            u.Role AS OrganiserRole, u.VerificationStatus AS OrganiserVerificationStatus,
            u.UserStatus AS OrganiserUserStatus,
            u.VerificationFeePaidUntil, u.VerificationFeePaymentStatus
     FROM EVENTS e
     INNER JOIN CATEGORIES c ON c.CategoryID = e.CategoryID
     INNER JOIN USERS u ON u.UserID = e.OrganiserID
     WHERE e.EventID = :event_id
     LIMIT 1"
);
$stmt->execute([':event_id' => $event_id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    http_response_code(404);
    $page_title = 'Event not found';
    require_once __DIR__ . '/shared-components/header.php';
    echo '<main class="page-content"><div class="empty-state"><h1>Event not found</h1><p>This event does not exist.</p><a class="btn btn-outline" href="events.php">Back to events</a></div></main>';
    require_once __DIR__ . '/shared-components/footer.php';
    exit;
}

$current_user_id = isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$current_role = (string) ($_SESSION['role'] ?? '');
$is_owner_preview = $current_role === 'organiser' && $current_user_id === (int) $event['OrganiserID'];
$is_admin_preview = $current_role === 'admin';

// Published events are public. Completed events remain readable as public history
// because organiser public pages link to completed event records. Drafts require owner/admin preview.
$public_status = in_array($event['Status'], ['published', 'completed'], true);
if (!$public_status && !($event['Status'] === 'draft' && ($is_owner_preview || $is_admin_preview))) {
    http_response_code(404);
    $page_title = 'Event unavailable';
    require_once __DIR__ . '/shared-components/header.php';
    echo '<main class="page-content"><div class="empty-state"><h1>Event unavailable</h1><p>This event is not available for public viewing.</p><a class="btn btn-outline" href="events.php">Back to events</a></div></main>';
    require_once __DIR__ . '/shared-components/footer.php';
    exit;
}

eventnest_expire_pending($pdo);
$ticket_stmt = $pdo->prepare('SELECT TicketID, TicketType, Price, QuantityAvailable, Capacity, Description FROM TICKETS WHERE EventID = :event_id ORDER BY Price ASC, TicketID ASC');
$ticket_stmt->execute([':event_id' => $event_id]);
$tickets = $ticket_stmt->fetchAll(PDO::FETCH_ASSOC);

$attendee = null;
$is_blocked = false;
$gate_ok = true;
$gate_message = '';
$fee_ok = false;
$bookable_event = $event['Status'] === 'published' && strtotime((string) $event['EventDate']) > time();

$fee_ok = $event['OrganiserVerificationStatus'] === 'verified'
    && $event['OrganiserUserStatus'] === 'active'
    && $event['VerificationFeePaymentStatus'] === 'paid'
    && !empty($event['VerificationFeePaidUntil'])
    && strtotime((string) $event['VerificationFeePaidUntil']) > time();

if ($current_user_id > 0 && $current_role === 'attendee') {
    $user_stmt = $pdo->prepare('SELECT UserID, Email, CareerField, Is_email_verified, UserStatus FROM USERS WHERE UserID = :user_id AND Role = \'attendee\' LIMIT 1');
    $user_stmt->execute([':user_id' => $current_user_id]);
    $attendee = $user_stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($attendee) {
        $block_stmt = $pdo->prepare('SELECT 1 FROM BLOCKS WHERE OrganiserID = :organiser_id AND LOWER(BlockedEmail) = LOWER(:email) LIMIT 1');
        $block_stmt->execute([
            ':organiser_id' => (int) $event['OrganiserID'],
            ':email' => (string) $attendee['Email'],
        ]);
        $is_blocked = (bool) $block_stmt->fetchColumn();

        if ($event['CareerGoalType'] === 'field') {
            $career_stmt = $pdo->prepare('SELECT CategoryName FROM CATEGORIES WHERE CategoryID = :category_id LIMIT 1');
            $career_stmt->execute([':category_id' => (int) $event['AllowedCareerField']]);
            $allowed_career = (string) ($career_stmt->fetchColumn() ?: '');
            $gate_ok = $allowed_career !== '' && mb_strtolower(trim((string) $attendee['CareerField']), 'UTF-8') === mb_strtolower(trim($allowed_career), 'UTF-8');
            if (!$gate_ok) {
                $gate_message = 'This event is limited to the ' . $allowed_career . ' career field.';
            }
        } elseif ($event['CareerGoalType'] === 'email_domain') {
            $verified_email = (int) $attendee['Is_email_verified'] === 1;
            $gate_ok = $verified_email && event_detail_domain_matches((string) $attendee['Email'], (string) $event['AllowedEmailDomain']);
            if (!$gate_ok) {
                $gate_message = 'This event requires a verified email address from ' . ltrim((string) $event['AllowedEmailDomain'], '@') . '.';
            }
        }
    }
}

$can_start_booking = $bookable_event
    && $current_role === 'attendee'
    && $attendee !== null
    && !$is_blocked
    && $gate_ok
    && $fee_ok
    && count(array_filter($tickets, static fn(array $ticket): bool => (int) $ticket['QuantityAvailable'] > 0 || eventnest_ticket_hold($pdo, (int) $ticket['TicketID'])['held'] > 0)) > 0;

$organiser_name = eventnest_organiser_display_name([
    'OrganisationName' => $event['OrganiserOrganisationName'] ?? null,
    'FirstName' => $event['OrganiserFirstName'] ?? null,
    'LastName' => $event['OrganiserLastName'] ?? null,
]);
$image = resolve_event_image($pdo, $event_id);
$page_title = (string) $event['EventName'];
require_once __DIR__ . '/shared-components/header.php';
?>
<main class="page-content event-detail-page">
    <p><a class="btn btn-outline" href="events.php">Back to events</a></p>

    <?php if ($event['Status'] === 'draft'): ?>
        <div class="alert alert-warning" role="status">Preview only: this draft is not public or bookable.</div>
    <?php elseif ($event['Status'] === 'completed'): ?>
        <div class="alert alert-info" role="status">This event has been completed and is shown as part of the organiser's public history.</div>
    <?php endif; ?>

    <div class="event-detail-layout">
    <article class="event-detail-card">
        <img class="event-detail__image" src="<?= escape_html(eventnest_url($image)) ?>" alt="Image for <?= escape_html($event['EventName']) ?>">
        <div class="event-detail__content">
            <h1><?= escape_html($event['EventName']) ?></h1>
            <p><strong><?= escape_html(format_datetime($event['EventDate'])) ?></strong></p>
            <p><?= escape_html($event['CategoryName']) ?> · <?= escape_html(ucfirst($event['Format'])) ?><?= $event['AgeRestriction'] ? ' · ' . escape_html($event['AgeRestriction']) : '' ?></p>
            <p><?= nl2br(escape_html((string) $event['Description'])) ?></p>

            <p>
                Organised by
                <a href="organiser-profile.php?organiser_id=<?= (int) $event['OrganiserID'] ?>"><?= escape_html($organiser_name ?: 'Verified organiser') ?></a>
                · <a href="report-event.php?event_id=<?= $event_id ?>">Report this event</a>
            </p>

            <?php $tags = event_detail_tags($event['Hashtags']); ?>
            <?php if ($tags): ?>
                <div class="event-tags" aria-label="Event hashtags">
                    <?php foreach ($tags as $tag): ?>
                        <a href="tag.php?<?= http_build_query(['tag' => ltrim($tag, '#')], '', '&', PHP_QUERY_RFC3986) ?>"><?= escape_html($tag) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </article>

    <div class="event-detail-sidebar">
    <section class="ticket-options" aria-labelledby="ticket-options-heading">
        <h2 id="ticket-options-heading">Choose a ticket tier</h2>

        <?php if (!$tickets): ?>
            <div class="empty-state"><p>No ticket tiers are available for this event.</p></div>
        <?php else: ?>
            <div class="ticket-grid">
                <?php foreach ($tickets as $ticket): ?>
                    <article class="ticket-card">
                        <h3><?= escape_html($ticket['TicketType']) ?></h3>
                        <?php if ($ticket['Description']): ?><p><?= escape_html($ticket['Description']) ?></p><?php endif; ?>
                        <p><strong><?= (float) $ticket['Price'] <= 0 ? 'Free' : escape_html(format_money($ticket['Price'])) ?></strong></p>
                        <p><?= (int) $ticket['QuantityAvailable'] ?> remaining</p>
                        <?php if ((int) $ticket['QuantityAvailable'] <= 0): ?>
                            <?php if (eventnest_ticket_hold($pdo, (int) $ticket['TicketID'])['held'] > 0): ?>
                                <span class="status status-warning">Payment pending</span>
                                <p><small>Someone is paying for the last tickets. Try booking to join the waitlist.</small></p>
                            <?php else: ?>
                                <span class="status status-danger">Sold out</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="booking-guidance">
            <?php if (!$bookable_event): ?>
                <p class="alert alert-info">This event is not open for new bookings.</p>
            <?php elseif ($current_user_id <= 0): ?>
                <p class="alert alert-info">Log in as an attendee to book a ticket.</p>
                <a class="btn btn-secondary" href="login.php">Log in</a>
            <?php elseif ($current_role !== 'attendee'): ?>
                <p class="alert alert-info">Only attendee accounts can create bookings.</p>
            <?php elseif (!$fee_ok): ?>
                <p class="alert alert-warning">New bookings are temporarily unavailable while this organiser renews annual verification-fee coverage. Existing event information and bookings remain available.</p>
            <?php elseif ($is_blocked): ?>
                <p class="alert alert-warning">You cannot book events from this organiser. Contact EventNest support if you believe this is an error.</p>
            <?php elseif (!$gate_ok): ?>
                <p class="alert alert-warning"><?= escape_html($gate_message) ?></p>
            <?php elseif (!$can_start_booking): ?>
                <p class="alert alert-warning">No ticket tier is currently available to book.</p>
            <?php else: ?>
                <?php if ($event['AgeRestriction']): ?><p>You will need to confirm that you meet the age requirement before the booking is created.</p><?php endif; ?>
                <a class="btn btn-primary" href="booking.php?event_id=<?= $event_id ?>">Book now</a>
            <?php endif; ?>
        </div>
    </section>

    <section class="event-information" aria-labelledby="event-information-heading">
        <h2 id="event-information-heading">Event information</h2>
        <dl>
            <div><dt>Format</dt><dd><?= escape_html(ucfirst($event['Format'])) ?></dd></div>
            <div><dt>Venue / location</dt><dd><?= escape_html(event_detail_public_venue($event)) ?></dd></div>
            <?php if ($event['Province']): ?><div><dt>Province</dt><dd><?= escape_html($event['Province']) ?></dd></div><?php endif; ?>
            <?php if ($event['AgeRestriction']): ?><div><dt>Age requirement</dt><dd><?= escape_html($event['AgeRestriction']) ?></dd></div><?php endif; ?>
            <?php if ($event['CareerGoalType'] === 'field'): ?>
                <div><dt>Career restriction</dt><dd>This event is restricted to the selected career field. Your account career field is checked again during booking.</dd></div>
            <?php elseif ($event['CareerGoalType'] === 'email_domain'): ?>
                <div><dt>Email restriction</dt><dd>A verified account email ending in <?= escape_html(ltrim((string) $event['AllowedEmailDomain'], '@')) ?> is required.</dd></div>
            <?php endif; ?>
        </dl>
    </section>

    </div></div>
    <?php if ($current_role === 'attendee' && strtotime((string) $event['EventDate']) > time()): ?>
        <?php $more_than_24h = strtotime((string) $event['EventDate']) - time() > 86400; ?>
        <section class="event-cancellation-note">
            <h2>Cancellation timing</h2>
            <p><?= $more_than_24h ? 'Eligible attendee self-cancellations may still be requested from My Bookings, subject to the booking state.' : 'The normal attendee self-cancellation deadline has passed. Contact EventNest support if you need help.' ?></p>
        </section>
    <?php endif; ?>
</main>
<?php require_once __DIR__ . '/shared-components/footer.php'; ?>




