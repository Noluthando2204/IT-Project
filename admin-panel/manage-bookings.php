
<?php
/**
 * EventNest - Admin Booking Management
 * Allows administrators to view and filter bookings.
 * Does not directly change payment or escrow records.
 */

require_once dirname(__DIR__) . '/shared-components/auth.php';

// Only logged-in administrators may access this page.
$admin = auth_require_admin();

// Use the existing database connection.
if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (isset($conn) && $conn instanceof PDO) {
        $pdo = $conn;
    } else {
        http_response_code(500);
        exit('Database connection is unavailable.');
    }
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// Escape values before displaying them in HTML.
function booking_admin_e($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Accept only statuses defined in the database schema.
$allowedStatuses = [
    'pending',
    'paid',
    'failed',
    'refunded',
    'canceled'
];

$status = trim((string) ($_GET['status'] ?? ''));
$search = trim((string) ($_GET['search'] ?? ''));

if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

// Prepare filter conditions.
$where = [];
$params = [];

if ($status !== '') {
    $where[] = 'b.Status = :status';
    $params[':status'] = $status;
}

if ($search !== '') {
    $where[] = "(
        b.BookingReference LIKE :search_reference
        OR u.FirstName LIKE :search_first
        OR u.LastName LIKE :search_last
        OR u.Email LIKE :search_email
        OR e.EventName LIKE :search_event
        OR CAST(b.BookingID AS CHAR) LIKE :search_id
    )";

    $searchTerm = '%' . $search . '%';

    $params[':search_reference'] = $searchTerm;
    $params[':search_first'] = $searchTerm;
    $params[':search_last'] = $searchTerm;
    $params[':search_email'] = $searchTerm;
    $params[':search_event'] = $searchTerm;
    $params[':search_id'] = $searchTerm;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$bookings = [];
$errorMessage = '';
$summary = [
    'total' => 0,
    'pending' => 0,
    'paid' => 0,
    'failed' => 0,
    'refunded' => 0,
    'canceled' => 0
];

try {
    // Overall booking totals.
    $summaryStatement = $pdo->query(
        "SELECT
            COUNT(*) AS total,
            COALESCE(SUM(Status = 'pending'), 0) AS pending,
            COALESCE(SUM(Status = 'paid'), 0) AS paid,
            COALESCE(SUM(Status = 'failed'), 0) AS failed,
            COALESCE(SUM(Status = 'refunded'), 0) AS refunded,
            COALESCE(SUM(Status = 'canceled'), 0) AS canceled
         FROM BOOKINGS"
    );

    $summary = array_merge(
        $summary,
        $summaryStatement->fetch() ?: []
    );

    // Show the latest payment record for each booking, if one exists.
    $sql = "
        SELECT
            b.BookingID,
            b.BookingReference,
            b.BookingDate,
            b.Quantity,
            b.TotalAmount,
            b.Status AS BookingStatus,

            u.FirstName,
            u.LastName,
            u.Email,

            e.EventName,
            e.EventDate,

            t.TicketType,

            p.PaymentID,
            p.PaymentDate,
            p.Amount AS PaymentAmount,
            p.PaymentMethod,
            p.PaymentStatus,
            p.TransactionID,
            p.Escrow_status

        FROM BOOKINGS b

        INNER JOIN USERS u
            ON u.UserID = b.UserID

        INNER JOIN EVENTS e
            ON e.EventID = b.EventID

        INNER JOIN TICKETS t
            ON t.TicketID = b.TicketID

        LEFT JOIN PAYMENTS p
            ON p.PaymentID = (
                SELECT MAX(p2.PaymentID)
                FROM PAYMENTS p2
                WHERE p2.BookingID = b.BookingID
            )

        $whereSql

        ORDER BY b.BookingDate DESC, b.BookingID DESC
        LIMIT 100
    ";

    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $bookings = $statement->fetchAll();

} catch (PDOException $exception) {
    error_log(
        'EventNest booking admin error: ' . $exception->getMessage()
    );

    $errorMessage =
        'Bookings could not be loaded. Please check the database configuration.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Bookings | EventNest Admin</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 24px;
            font-family: Arial, sans-serif;
            background: #f4f6fa;
            color: #243047;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            margin-bottom: 24px;
        }

        .page-header h1 {
            margin-bottom: 8px;
        }

        .page-header p {
            color: #667085;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }

        .summary-card,
        .panel {
            background: #fff;
            border-radius: 10px;
            padding: 18px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .summary-card p {
            margin: 0 0 8px;
            color: #667085;
        }

        .summary-card h2 {
            margin: 0;
            font-size: 27px;
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }

        input,
        select,
        button {
            padding: 11px 12px;
            border: 1px solid #d0d5dd;
            border-radius: 6px;
            font-size: 14px;
        }

        input {
            flex: 1;
            min-width: 200px;
        }

        button {
            background: #263c80;
            color: white;
            border: none;
            cursor: pointer;
        }

        button:hover {
            background: #1c2e65;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th,
        td {
            padding: 13px 12px;
            text-align: left;
            border-bottom: 1px solid #eaecf0;
            vertical-align: top;
        }

        th {
            background: #f8f9fc;
            white-space: nowrap;
        }

        .status {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            background: #eaecf0;
            font-size: 12px;
            white-space: nowrap;
        }

        .paid,
        .success,
        .released {
            background: #d1fadf;
            color: #05603a;
        }

        .pending,
        .held {
            background: #fef0c7;
            color: #93370d;
        }

        .failed,
        .canceled,
        .refunded {
            background: #fee4e2;
            color: #912018;
        }

        .message {
            padding: 14px;
            margin-bottom: 18px;
            border-radius: 6px;
            background: #fee4e2;
            color: #912018;
        }

        .empty {
            padding: 30px;
            text-align: center;
            color: #667085;
        }

        .small {
            display: block;
            margin-top: 5px;
            color: #667085;
        }

        @media (max-width: 600px) {
            body {
                padding: 12px;
            }
        }
    </style>
</head>

<body>
<div class="container">

    <header class="page-header">
        <h1>Manage Bookings</h1>
        <p>
            View attendee bookings, ticket details, payments and escrow status.
        </p>
    </header>

    <section class="summary">
        <div class="summary-card">
            <p>Total bookings</p>
            <h2><?= (int) $summary['total'] ?></h2>
        </div>

        <div class="summary-card">
            <p>Pending</p>
            <h2><?= (int) $summary['pending'] ?></h2>
        </div>

        <div class="summary-card">
            <p>Paid</p>
            <h2><?= (int) $summary['paid'] ?></h2>
        </div>

        <div class="summary-card">
            <p>Failed</p>
            <h2><?= (int) $summary['failed'] ?></h2>
        </div>

        <div class="summary-card">
            <p>Refunded</p>
            <h2><?= (int) $summary['refunded'] ?></h2>
        </div>

        <div class="summary-card">
            <p>Canceled</p>
            <h2><?= (int) $summary['canceled'] ?></h2>
        </div>
    </section>

    <section class="panel">
        <form method="GET" class="filters">
            <input
                type="text"
                name="search"
                placeholder="Search reference, attendee, email or event"
                value="<?= booking_admin_e($search) ?>"
            >

            <select name="status">
                <option value="">All booking statuses</option>

                <?php foreach ($allowedStatuses as $option): ?>
                    <option
                        value="<?= booking_admin_e($option) ?>"
                        <?= $status === $option ? 'selected' : '' ?>
                    >
                        <?= booking_admin_e(ucfirst($option)) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Filter bookings</button>

            <button
                type="button"
                onclick="window.location.href='manage-bookings.php'"
            >
                Clear filters
            </button>
        </form>

        <?php if ($errorMessage !== ''): ?>
            <div class="message">
                <?= booking_admin_e($errorMessage) ?>
            </div>

        <?php else: ?>

            <p>
                Showing <?= count($bookings) ?> booking(s).
                A maximum of 100 records is displayed per search.
            </p>

            <div class="table-wrapper">
                <table>
                    <thead>
                    <tr>
                        <th>Booking</th>
                        <th>Attendee</th>
                        <th>Event</th>
                        <th>Ticket</th>
                        <th>Quantity</th>
                        <th>Total</th>
                        <th>Booking status</th>
                        <th>Payment</th>
                        <th>Escrow</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr>
                            <td colspan="9" class="empty">
                                No bookings were found.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <?= booking_admin_e(
                                            $booking['BookingReference']
                                        ) ?>
                                    </strong>

                                    <span class="small">
                                        ID:
                                        <?= (int) $booking['BookingID'] ?>
                                    </span>

                                    <span class="small">
                                        <?= booking_admin_e(
                                            $booking['BookingDate']
                                        ) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= booking_admin_e(
                                        trim(
                                            ($booking['FirstName'] ?? '')
                                            . ' '
                                            . ($booking['LastName'] ?? '')
                                        )
                                    ) ?>

                                    <span class="small">
                                        <?= booking_admin_e(
                                            $booking['Email']
                                        ) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= booking_admin_e(
                                        $booking['EventName']
                                    ) ?>

                                    <span class="small">
                                        <?= booking_admin_e(
                                            $booking['EventDate']
                                        ) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= booking_admin_e(
                                        $booking['TicketType']
                                    ) ?>
                                </td>

                                <td>
                                    <?= (int) $booking['Quantity'] ?>
                                </td>

                                <td>
                                    R<?= number_format(
                                        (float) $booking['TotalAmount'],
                                        2
                                    ) ?>
                                </td>

                                <td>
                                    <?php
                                    $bookingStatus =
                                        (string) $booking['BookingStatus'];
                                    ?>

                                    <span class="status <?= booking_admin_e(
                                        $bookingStatus
                                    ) ?>">
                                        <?= booking_admin_e(
                                            ucfirst($bookingStatus)
                                        ) ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($booking['PaymentID'] !== null): ?>
                                        <?php
                                        $paymentStatus =
                                            (string) $booking['PaymentStatus'];
                                        ?>

                                        <span class="status <?= booking_admin_e(
                                            $paymentStatus
                                        ) ?>">
                                            <?= booking_admin_e(
                                                ucfirst($paymentStatus)
                                            ) ?>
                                        </span>

                                        <span class="small">
                                            R<?= number_format(
                                                (float) $booking['PaymentAmount'],
                                                2
                                            ) ?>
                                        </span>

                                        <span class="small">
                                            Transaction:
                                            <?= booking_admin_e(
                                                $booking['TransactionID']
                                                ?: 'Not provided'
                                            ) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="status">
                                            No payment
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if ($booking['PaymentID'] !== null): ?>
                                        <?php if ($booking['Escrow_status']): ?>
                                            <?php
                                            $escrowStatus =
                                                (string) $booking['Escrow_status'];
                                            ?>

                                            <span class="status <?= booking_admin_e(
                                                $escrowStatus
                                            ) ?>">
                                                <?= booking_admin_e(
                                                    ucfirst($escrowStatus)
                                                ) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="status">
                                                Not applicable
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="status">
                                            No payment
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </section>
</div>
</body>
</html>
