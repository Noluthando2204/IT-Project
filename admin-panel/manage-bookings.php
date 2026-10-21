<?php

declare(strict_types=1);

require_once __DIR__ . '/../shared-components/auth.php';
require_once __DIR__ . '/../shared-components/functions.php';

$admin_user = auth_require_admin();
$pdo = eventnest_db();

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$allowed_booking_statuses = ['pending', 'paid', 'failed', 'refunded', 'canceled'];
$allowed_payment_statuses = ['success', 'failed', 'refunded'];

$q = trim_input($_GET['q'] ?? '');
$booking_status = trim_input($_GET['booking_status'] ?? '');
$payment_status = trim_input($_GET['payment_status'] ?? '');
$action = trim_input($_GET['action'] ?? '');

if (!in_array($booking_status, $allowed_booking_statuses, true)) {
    $booking_status = '';
}

if (!in_array($payment_status, $allowed_payment_statuses, true)) {
    $payment_status = '';
}

if ($action !== '' && $action !== 'view') {
    $action = '';
}

$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$page = is_int($page) ? $page : 1;

$booking_id = filter_input(INPUT_GET, 'booking_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$booking_id = is_int($booking_id) ? $booking_id : null;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit('Method not allowed.');
}

$where = [];
$params = [];

if ($q !== '') {
    $where[] = "(
        b.BookingReference LIKE :q_reference
        OR e.EventName LIKE :q_event
        OR u.FirstName LIKE :q_first_name
        OR u.LastName LIKE :q_last_name
        OR u.Email LIKE :q_email
    )";

    $search_term = '%' . $q . '%';
    $params[':q_reference'] = $search_term;
    $params[':q_event'] = $search_term;
    $params[':q_first_name'] = $search_term;
    $params[':q_last_name'] = $search_term;
    $params[':q_email'] = $search_term;
}

if ($booking_status !== '') {
    $where[] = 'b.Status = :booking_status';
    $params[':booking_status'] = $booking_status;
}

if ($payment_status !== '') {
    $where[] = 'p.PaymentStatus = :payment_status';
    $params[':payment_status'] = $payment_status;
}

if ($booking_id !== null && $action === 'view') {
    $where[] = 'b.BookingID = :booking_id';
    $params[':booking_id'] = $booking_id;
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$summary = [
    'total' => 0,
    'pending' => 0,
    'paid' => 0,
    'failed' => 0,
    'refunded' => 0,
    'canceled' => 0,
];

$bookings = [];
$error_message = '';
$total_records = 0;
$total_pages = 1;
$per_page = 25;

try {
    $summary_statement = $pdo->query(
        "SELECT
            COUNT(*) AS total,
            COALESCE(SUM(Status = 'pending'), 0) AS pending,
            COALESCE(SUM(Status = 'paid'), 0) AS paid,
            COALESCE(SUM(Status = 'failed'), 0) AS failed,
            COALESCE(SUM(Status = 'refunded'), 0) AS refunded,
            COALESCE(SUM(Status = 'canceled'), 0) AS canceled
        FROM BOOKINGS"
    );

    $summary_row = $summary_statement->fetch();
    if (is_array($summary_row)) {
        $summary = array_merge($summary, $summary_row);
    }

    $from_sql = "
        FROM BOOKINGS b
        INNER JOIN USERS u ON u.UserID = b.UserID
        INNER JOIN EVENTS e ON e.EventID = b.EventID
        INNER JOIN TICKETS t
            ON t.TicketID = b.TicketID
            AND t.EventID = b.EventID
        LEFT JOIN PAYMENTS p
            ON p.PaymentID = (
                SELECT p2.PaymentID
                FROM PAYMENTS p2
                WHERE p2.BookingID = b.BookingID
                ORDER BY p2.PaymentID DESC
                LIMIT 1
            )
        {$where_sql}
    ";

    $count_statement = $pdo->prepare('SELECT COUNT(*) ' . $from_sql);
    $count_statement->execute($params);
    $total_records = (int) $count_statement->fetchColumn();

    $total_pages = max(1, (int) ceil($total_records / $per_page));
    if ($page > $total_pages) {
        $page = $total_pages;
    }

    $offset = ($page - 1) * $per_page;

    $sql = "
        SELECT
            b.BookingID,
            b.BookingReference,
            b.BookingDate,
            b.Quantity,
            b.TotalAmount,
            b.Status AS BookingStatus,
            u.UserID,
            u.FirstName,
            u.LastName,
            u.Email,
            e.EventID,
            e.EventName,
            e.EventDate,
            t.TicketID,
            t.TicketType,
            p.PaymentID,
            p.PaymentDate,
            p.Amount AS PaymentAmount,
            p.PaymentStatus,
            p.Escrow_status
        {$from_sql}
        ORDER BY b.BookingDate DESC, b.BookingID DESC
        LIMIT {$per_page} OFFSET {$offset}
    ";

    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $bookings = $statement->fetchAll();
} catch (PDOException $exception) {
    error_log('EventNest admin manage-bookings error: ' . $exception->getMessage());
    $error_message = 'Bookings could not be loaded. Please try again later.';
}

function admin_booking_page_url(
    int $target_page,
    string $q,
    string $booking_status,
    string $payment_status
): string {
    $query = [
        'q' => $q,
        'booking_status' => $booking_status,
        'payment_status' => $payment_status,
        'page' => max(1, $target_page),
    ];

    $query = array_filter(
        $query,
        static fn($value): bool => $value !== '' && $value !== null
    );

    return 'manage-bookings.php?' . http_build_query($query);
}

$page_title = 'Manage bookings';
require_once __DIR__ . '/../shared-components/header.php';
?>

<main class="container">
    <header class="page-header">
        <p>Administrator</p>
        <h1>Manage bookings</h1>
        <p>View booking, attendee, event, ticket and payment context for support, refunds and investigations.</p>
        <p><a class="btn btn--outline" href="dashboard.php">Back to admin overview</a></p>
    </header>

    <?php if ($error_message !== ''): ?>
        <div class="message message--error" role="alert"><?= escape_html($error_message) ?></div>
    <?php endif; ?>

    <section aria-labelledby="booking-summary-heading">
        <h2 id="booking-summary-heading">Booking summary</h2>
        <div class="summary">
            <?php foreach ($summary as $status_name => $count): ?>
                <article class="summary-card">
                    <p><?= escape_html(eventnest_status_label((string) $status_name)) ?></p>
                    <strong><?= (int) $count ?></strong>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel" aria-labelledby="booking-filter-heading">
        <h2 id="booking-filter-heading">Filter bookings</h2>
        <form method="get" action="manage-bookings.php">
            <div class="form-group">
                <label for="q">Booking reference, attendee, email or event</label>
                <input id="q" name="q" type="search" value="<?= escape_html($q) ?>" maxlength="120" autocomplete="off">
            </div>

            <div class="form-group">
                <label for="booking_status">Booking status</label>
                <select id="booking_status" name="booking_status">
                    <option value="">All booking statuses</option>
                    <?php foreach ($allowed_booking_statuses as $status): ?>
                        <option value="<?= escape_html($status) ?>" <?= $booking_status === $status ? 'selected' : '' ?>>
                            <?= escape_html(eventnest_status_label($status)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="payment_status">Payment status</label>
                <select id="payment_status" name="payment_status">
                    <option value="">All payment statuses</option>
                    <?php foreach ($allowed_payment_statuses as $status): ?>
                        <option value="<?= escape_html($status) ?>" <?= $payment_status === $status ? 'selected' : '' ?>>
                            <?= escape_html(eventnest_status_label($status)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="button-group">
                <button class="btn btn--primary" type="submit">Apply filters</button>
                <a class="btn btn--outline" href="manage-bookings.php">Clear filters</a>
            </div>
        </form>
    </section>

    <section class="panel" aria-labelledby="booking-results-heading">
        <h2 id="booking-results-heading">Bookings</h2>
        <p><?= $total_records ?> matching <?= $total_records === 1 ? 'record' : 'records' ?>. Page <?= $page ?> of <?= $total_pages ?>.</p>

        <?php if ($bookings === []): ?>
            <div class="empty-state">
                <h3>No bookings found</h3>
                <p>Try changing the search or filter values.</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Reference</th>
                            <th scope="col">Attendee</th>
                            <th scope="col">Event</th>
                            <th scope="col">Ticket</th>
                            <th scope="col">Qty</th>
                            <th scope="col">Total</th>
                            <th scope="col">Date</th>
                            <th scope="col">Booking</th>
                            <th scope="col">Payment</th>
                            <th scope="col">Escrow</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($bookings as $booking): ?>
                        <?php
                        $attendee_name = trim(
                            (string) ($booking['FirstName'] ?? '') . ' ' .
                            (string) ($booking['LastName'] ?? '')
                        );
                        $booking_status_value = (string) ($booking['BookingStatus'] ?? '');
                        $payment_status_value = (string) ($booking['PaymentStatus'] ?? '');
                        $escrow_status_value = (string) ($booking['Escrow_status'] ?? '');
                        ?>
                        <tr>
                            <td>
                                <strong><?= escape_html((string) $booking['BookingReference']) ?></strong><br>
                                <small>Booking #<?= (int) $booking['BookingID'] ?></small>
                            </td>
                            <td>
                                <?= escape_html($attendee_name !== '' ? $attendee_name : 'Unnamed attendee') ?><br>
                                <small><?= escape_html((string) $booking['Email']) ?></small>
                            </td>
                            <td>
                                <?= escape_html((string) $booking['EventName']) ?><br>
                                <small><?= escape_html(format_datetime((string) $booking['EventDate'])) ?></small>
                            </td>
                            <td><?= escape_html((string) $booking['TicketType']) ?></td>
                            <td><?= (int) $booking['Quantity'] ?></td>
                            <td><?= escape_html(format_money($booking['TotalAmount'])) ?></td>
                            <td><?= escape_html(format_datetime((string) $booking['BookingDate'])) ?></td>
                            <td>
                                <span class="<?= escape_html(eventnest_status_class($booking_status_value)) ?>">
                                    <?= escape_html(eventnest_status_label($booking_status_value)) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($payment_status_value !== ''): ?>
                                    <span class="<?= escape_html(eventnest_status_class($payment_status_value)) ?>">
                                        <?= escape_html(eventnest_status_label($payment_status_value)) ?>
                                    </span>
                                <?php else: ?>
                                    No payment record
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($escrow_status_value !== ''): ?>
                                    <span class="<?= escape_html(eventnest_status_class($escrow_status_value)) ?>">
                                        <?= escape_html(eventnest_status_label($escrow_status_value)) ?>
                                    </span>
                                <?php else: ?>
                                    Not applicable
                                <?php endif; ?>
                            </td>
                            <td>
                                <a class="btn btn--small btn--outline" href="<?= escape_html(eventnest_url('event-detail.php?event_id=' . (int) $booking['EventID'])) ?>">View event</a>
                                <a class="btn btn--small btn--outline" href="<?= escape_html(eventnest_url('admin-panel/manage-users.php?user_id=' . (int) $booking['UserID'])) ?>">View user</a>
                                <?php if (!empty($booking['PaymentID'])): ?>
                                    <a class="btn btn--small btn--outline" href="<?= escape_html(eventnest_url('admin-panel/escrow.php?payment_id=' . (int) $booking['PaymentID'])) ?>">Payment / escrow</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <nav class="pagination" aria-label="Booking results pages">
                    <?php if ($page > 1): ?>
                        <a class="btn btn--outline" href="<?= escape_html(admin_booking_page_url($page - 1, $q, $booking_status, $payment_status)) ?>">Previous</a>
                    <?php endif; ?>
                    <span>Page <?= $page ?> of <?= $total_pages ?></span>
                    <?php if ($page < $total_pages): ?>
                        <a class="btn btn--outline" href="<?= escape_html(admin_booking_page_url($page + 1, $q, $booking_status, $payment_status)) ?>">Next</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>

<?php
require_once __DIR__ . '/../shared-components/footer.php';
