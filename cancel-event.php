<?php

declare(strict_types=1);

require_once __DIR__ . '/database-config/connection.php';
require_once __DIR__ . '/shared-components/auth.php';
require_once __DIR__ . '/shared-components/functions.php';


//Normalise databse connection

if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (isset($conn) && $conn instanceof PDO) {
        $pdo = $conn;
    } else {
        http_response_code(500);
        exit('EventNest is temporarily unavailable.');
    }
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);


//Require verified organiser

$auth_user = require_organiser_verified();

$organiser_id = (int) $auth_user['UserID'];


//Local helpers

function cancel_event_positive_id(mixed $value): int
{
    if (is_int($value)) {
        return $value > 0 ? $value : 0;
    }

    if (
        is_string($value)
        && ctype_digit($value)
        && (int) $value > 0
    ) {
        return (int) $value;
    }

    return 0;
}


//Cancellation email

function cancel_event_send_email(
    string $to,
    string $subject,
    string $body
): bool {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    //Email helper

    if (function_exists('send_eventnest_email')) {
        try {
            return (bool) send_eventnest_email(
                $to,
                $subject,
                $body
            );
        } catch (Throwable $exception) {
            error_log(
                'EventNest cancellation email helper failed: '
                . get_class($exception)
                . ' - '
                . $exception->getMessage()
            );

            return false;
        }
    }

    if (function_exists('sendEmail')) {
        try {
            return (bool) sendEmail(
                $to,
                $subject,
                $body
            );
        } catch (Throwable $exception) {
            error_log(
                'EventNest cancellation email helper failed: '
                . get_class($exception)
                . ' - '
                . $exception->getMessage()
            );

            return false;
        }
    }


    //PhpMailer falllback

    $autoload = __DIR__ . '/vendor/autoload.php';

    if (!is_file($autoload)) {
        error_log(
            'EventNest cancellation email skipped: '
            . 'Composer autoload.php was not found.'
        );

        return false;
    }

    require_once $autoload;

    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        error_log(
            'EventNest cancellation email skipped: '
            . 'PHPMailer is unavailable.'
        );

        return false;
    }

    $host = trim(
        (string) (getenv('EVENTNEST_SMTP_HOST') ?: 'smtp.gmail.com')
    );

    $username = trim(
        (string) getenv('EVENTNEST_SMTP_USER')
    );

    $password = (string) getenv('EVENTNEST_SMTP_PASS');

    $from = trim(
        (string) (
            getenv('EVENTNEST_FROM_EMAIL')
            ?: $username
        )
    );

    $from_name = trim(
        (string) (
            getenv('EVENTNEST_FROM_NAME')
            ?: 'EventNest'
        )
    );

    $port = (int) (
        getenv('EVENTNEST_SMTP_PORT')
        ?: 587
    );

    if (
        $username === ''
        || $password === ''
        || !filter_var($from, FILTER_VALIDATE_EMAIL)
    ) {
        error_log(
            'EventNest cancellation email skipped: '
            . 'SMTP environment settings are incomplete.'
        );

        return false;
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->SMTPSecure =
            \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $port;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom(
            $from,
            $from_name
        );

        $mail->addAddress($to);

        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->isHTML(false);

        return $mail->send();
    } catch (Throwable $exception) {
        error_log(
            'EventNest cancellation email error: '
            . $exception->getMessage()
        );

        return false;
    }
}


//Read event ID

$event_id = cancel_event_positive_id(
    $_POST['event_id']
        ?? $_GET['event_id']
        ?? ''
);

if ($event_id <= 0) {
    http_response_code(404);
    exit('Event not found.');
}


//Load owned event

$event_stmt = $pdo->prepare(
    "
    SELECT
        EventID,
        EventName,
        EventDate,
        Status,
        OrganiserID

    FROM EVENTS

    WHERE EventID = :event_id
      AND OrganiserID = :organiser_id

    LIMIT 1
    "
);

$event_stmt->execute([
    ':event_id' => $event_id,
    ':organiser_id' => $organiser_id,
]);

$event = $event_stmt->fetch();

if (!$event) {
    http_response_code(404);
    exit('Event not found.');
}


//Load confirmation summary

$impact_stmt = $pdo->prepare(
    "
    SELECT
        COUNT(*) AS BookingCount,

        COALESCE(
            SUM(
                CASE
                    WHEN b.Status IN ('pending', 'paid')
                     AND b.TotalAmount > 0
                     AND EXISTS (
                        SELECT 1

                        FROM PAYMENTS p

                        WHERE p.BookingID = b.BookingID
                          AND p.PaymentStatus = 'success'
                     )

                    THEN b.TotalAmount

                    ELSE 0
                END
            ),
            0
        ) AS PotentialRefundTotal

    FROM BOOKINGS b

    WHERE b.EventID = :event_id
      AND b.Status IN (
          'pending',
          'paid',
          'failed',
          'refunded',
          'canceled'
      )
    "
);

$impact_stmt->execute([
    ':event_id' => $event_id,
]);

$impact = $impact_stmt->fetch();

if (!$impact) {
    $impact = [
        'BookingCount' => 0,
        'PotentialRefundTotal' => 0,
    ];
}


//Page state

$errors = [];
$result = null;

$cancellable = in_array(
    $event['Status'],
    ['draft', 'published'],
    true
);

$reason = '';

$csrf_token = get_csrf_token();


//Confirm cancelation

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = is_string($_POST['action'] ?? null)
        ? trim($_POST['action'])
        : '';

    $reason = is_string($_POST['cancellation_reason'] ?? null)
        ? trim($_POST['cancellation_reason'])
        : '';


    //Validate form

    if ($action !== 'cancel_event') {
        $errors[] = 'The cancellation action was invalid.';
    }

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] =
            'Your form session expired. Please try again.';
    }

    $reason_length = mb_strlen($reason);

    if (
        $reason_length < 10
        || $reason_length > 500
    ) {
        $errors[] =
            'Please provide a cancellation reason between '
            . '10 and 500 characters.';
    }


    //Process database changes 

    if (!$errors) {
        $notifications = [];

        $bookings_changed = 0;
        $inventory_restored = 0;
        $refunds_required = 0;
        $refunds_already_recorded = 0;
        $attention_count = 0;

        try {
            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | LOCK EVENT
            |--------------------------------------------------------------------------
            */

            $lock_event = $pdo->prepare(
                "
                SELECT
                    EventID,
                    EventName,
                    Status,
                    OrganiserID

                FROM EVENTS

                WHERE EventID = :event_id
                  AND OrganiserID = :organiser_id

                LIMIT 1

                FOR UPDATE
                "
            );

            $lock_event->execute([
                ':event_id' => $event_id,
                ':organiser_id' => $organiser_id,
            ]);

            $locked_event = $lock_event->fetch();

            if (!$locked_event) {
                throw new DomainException(
                    'You do not own this event.'
                );
            }

            if ($locked_event['Status'] === 'canceled') {
                throw new DomainException(
                    'This event has already been cancelled.'
                );
            }

            if (
                !in_array(
                    $locked_event['Status'],
                    ['draft', 'published'],
                    true
                )
            ) {
                throw new DomainException(
                    'This event can no longer be cancelled.'
                );
            }


            //Lock affected bookings

            $booking_stmt = $pdo->prepare(
                "
                SELECT
                    b.BookingID,
                    b.BookingReference,
                    b.UserID,
                    b.TicketID,
                    b.Status,
                    b.Quantity,
                    b.TotalAmount,

                    u.Email,
                    u.FirstName

                FROM BOOKINGS b

                INNER JOIN USERS u
                    ON u.UserID = b.UserID

                WHERE b.EventID = :event_id

                ORDER BY b.BookingID ASC

                FOR UPDATE
                "
            );

            $booking_stmt->execute([
                ':event_id' => $event_id,
            ]);

            $bookings = $booking_stmt->fetchAll();


            //Prepared statements

            $payment_stmt = $pdo->prepare(
                "
                SELECT
                    PaymentID,
                    PaymentStatus,
                    TransactionID,
                    Escrow_status,
                    Amount

                FROM PAYMENTS

                WHERE BookingID = :booking_id

                ORDER BY PaymentID ASC

                FOR UPDATE
                "
            );


            $cancel_booking = $pdo->prepare(
                "
                UPDATE BOOKINGS

                SET Status = 'canceled'

                WHERE BookingID = :booking_id
                  AND Status = :expected_status
                "
            );


            $mark_booking_paid = $pdo->prepare(
                "
                UPDATE BOOKINGS

                SET Status = 'paid'

                WHERE BookingID = :booking_id
                  AND Status = 'pending'
                "
            );


            $mark_booking_refunded = $pdo->prepare(
                "
                UPDATE BOOKINGS

                SET Status = 'refunded'

                WHERE BookingID = :booking_id
                  AND Status IN ('paid', 'canceled')
                "
            );


            $restore_inventory = $pdo->prepare(
                "
                UPDATE TICKETS

                SET QuantityAvailable = LEAST(
                    Capacity,
                    QuantityAvailable + :quantity
                )

                WHERE TicketID = :ticket_id
                  AND EventID = :event_id
                "
            );


            //Cancel event

            $event_update = $pdo->prepare(
                "
                UPDATE EVENTS

                SET
                    Status = 'canceled',
                    UpdatedAt = NOW()

                WHERE EventID = :event_id
                  AND OrganiserID = :organiser_id
                  AND Status IN ('draft', 'published')
                "
            );

            $event_update->execute([
                ':event_id' => $event_id,
                ':organiser_id' => $organiser_id,
            ]);

            if ($event_update->rowCount() !== 1) {
                throw new DomainException(
                    'The event changed state before '
                    . 'cancellation could be completed.'
                );
            }


            // Process each booking

            foreach ($bookings as $booking) {
                $booking_id =
                    (int) $booking['BookingID'];

                $booking_status =
                    (string) $booking['Status'];

                $total_amount =
                    (float) $booking['TotalAmount'];

                $quantity =
                    (int) $booking['Quantity'];

                $ticket_id =
                    (int) $booking['TicketID'];


                //Load payment state

                $payment_stmt->execute([
                    ':booking_id' => $booking_id,
                ]);

                $payments =
                    $payment_stmt->fetchAll();


                $successful_payments = [];
                $has_refunded_payment = false;
                $has_released_payment = false;

                foreach ($payments as $payment) {
                    if (
                        $payment['PaymentStatus'] === 'success'
                    ) {
                        $successful_payments[] = $payment;

                        if (
                            $payment['Escrow_status']
                            === 'released'
                        ) {
                            $has_released_payment = true;
                        }
                    }

                    if (
                        $payment['PaymentStatus']
                            === 'refunded'
                        || $payment['Escrow_status']
                            === 'refunded'
                    ) {
                        $has_refunded_payment = true;
                    }
                }

                $has_success =
                    count($successful_payments) > 0;

                $new_status = null;

                $restore_ticket = false;

                $refund_message =
                    'No payment refund is required.';


                //Pending booking

                if ($booking_status === 'pending') {
                    //Verified payment

                    if ($has_success) {
                        $mark_booking_paid->execute([
                            ':booking_id' => $booking_id,
                        ]);

                        if (
                            $mark_booking_paid->rowCount()
                            !== 1
                        ) {
                            throw new RuntimeException(
                                'Booking state changed while '
                                . 'processing BookingID '
                                . $booking_id
                                . '.'
                            );
                        }

                        $bookings_changed++;

                        $new_status = 'paid';

                        $refunds_required++;

                        $refund_message =
                            'Payment was verified successfully. '
                            . 'Because the event is cancelled, '
                            . 'this booking now requires the '
                            . 'approved refund verification process.';

                        foreach (
                            $successful_payments as $payment
                        ) {
                            error_log(
                                'EventNest refund required after '
                                . 'EventID '
                                . $event_id
                                . ' cancellation: PaymentID '
                                . (int) $payment['PaymentID']
                            );
                        }
                    } else {
                        //No successful payment

                        $cancel_booking->execute([
                            ':booking_id' =>
                                $booking_id,

                            ':expected_status' =>
                                'pending',
                        ]);

                        if (
                            $cancel_booking->rowCount()
                            === 1
                        ) {
                            $bookings_changed++;

                            $new_status = 'canceled';

                            $restore_ticket = true;

                            $refund_message =
                                'No verified successful payment '
                                . 'exists, so no refund is required.';
                        }
                    }
                }


                //Paid booking

                elseif ($booking_status === 'paid') {
                    //Free booking

                    if ($total_amount <= 0.0) {
                        $cancel_booking->execute([
                            ':booking_id' =>
                                $booking_id,

                            ':expected_status' =>
                                'paid',
                        ]);

                        if (
                            $cancel_booking->rowCount()
                            === 1
                        ) {
                            $bookings_changed++;

                            $new_status = 'canceled';

                            $restore_ticket = true;

                            $refund_message =
                                'This was a free booking, '
                                . 'so no payment refund is required.';
                        }
                    }

                    // Provider refund was already recorded.
                

                    elseif ($has_refunded_payment) {
                        $mark_booking_refunded->execute([
                            ':booking_id' =>
                                $booking_id,
                        ]);

                        if (
                            $mark_booking_refunded->rowCount()
                            === 1
                        ) {
                            $bookings_changed++;

                            $new_status = 'refunded';

                            $restore_ticket = true;

                            $refunds_already_recorded++;

                            $refund_message =
                                'The payment record already shows '
                                . 'a verified refund.';
                        }
                    }

                    //Successful booking

                    elseif ($has_success) {
                        $new_status = 'paid';

                        $refunds_required++;

                        if ($has_released_payment) {
                            $attention_count++;

                            $refund_message =
                                'This payment was previously released '
                                . 'and requires administrator/payment-provider '
                                . 'refund follow-up.';
                        } else {
                            $refund_message =
                                'A verified successful payment exists. '
                                . 'Refund handling has been flagged, but '
                                . 'the payment will not be marked refunded '
                                . 'until the approved refund process confirms it.';
                        }

                        foreach (
                            $successful_payments as $payment
                        ) {
                            error_log(
                                'EventNest refund required after '
                                . 'EventID '
                                . $event_id
                                . ' cancellation: PaymentID '
                                . (int) $payment['PaymentID']
                            );
                        }
                    }

                    /*
                    | Paid booking but no success/refund payment record.
                    | Do not silently guess.
                    */

                    else {
                        throw new RuntimeException(
                            'Paid BookingID '
                            . $booking_id
                            . ' has no verified successful '
                            . 'or refunded payment record.'
                        );
                    }
                }


                //Failed booking

                elseif ($booking_status === 'failed') {
                    $cancel_booking->execute([
                        ':booking_id' =>
                            $booking_id,

                        ':expected_status' =>
                            'failed',
                    ]);

                    if (
                        $cancel_booking->rowCount()
                        === 1
                    ) {
                        $bookings_changed++;

                        $new_status = 'canceled';

                        $refund_message =
                            'The booking had failed payment status. '
                            . 'No refund is required.';
                    }
                }

                elseif (
                    $booking_status === 'refunded'
                ) {
                    $new_status = 'refunded';

                    $refund_message =
                        'The booking was already refunded.';
                } elseif (
                    $booking_status === 'canceled'
                ) {
                    $new_status = 'canceled';

                    $refund_message =
                        'The booking was already cancelled.';
                }

                //Restore ticket inventory

                if ($restore_ticket) {
                    $restore_inventory->execute([
                        ':quantity' =>
                            $quantity,

                        ':ticket_id' =>
                            $ticket_id,

                        ':event_id' =>
                            $event_id,
                    ]);

                    if (
                        $restore_inventory->rowCount()
                        !== 1
                    ) {
                        throw new RuntimeException(
                            'Ticket inventory could not '
                            . 'be restored safely for BookingID '
                            . $booking_id
                            . '.'
                        );
                    }

                    $inventory_restored += $quantity;
                }

                //Attendee notification

                $notifications[] = [
                    'email' =>
                        (string) $booking['Email'],

                    'first_name' =>
                        (string) $booking['FirstName'],

                    'reference' =>
                        (string) $booking['BookingReference'],

                    'status' =>
                        $new_status
                        ?? $booking_status,

                    'refund_message' =>
                        $refund_message,
                ];
            }


            //Comit database transaction

            $pdo->commit();

            //Send messages after comit. Email failure must not undo a valid database cancellation.


            $email_failures = 0;

            foreach ($notifications as $notification) {
                $first_name =
                    trim($notification['first_name']);

                if ($first_name === '') {
                    $first_name =
                        'EventNest attendee';
                }

                $body =
                    "Hello "
                    . $first_name
                    . ",\n\n"
                    . "The EventNest event \""
                    . $event['EventName']
                    . "\" has been cancelled by its organiser.\n\n"
                    . "Booking reference: "
                    . $notification['reference']
                    . "\n"
                    . "Booking status: "
                    . $notification['status']
                    . "\n"
                    . "Cancellation reason: "
                    . $reason
                    . "\n\n"
                    . $notification['refund_message']
                    . "\n\n"
                    . "Please open My Bookings in EventNest "
                    . "for the latest booking and refund status.\n";

                if (
                    !cancel_event_send_email(
                        $notification['email'],
                        'EventNest event cancellation: '
                            . $event['EventName'],
                        $body
                    )
                ) {
                    $email_failures++;
                }
            }

            //Result

            $event['Status'] = 'canceled';

            $cancellable = false;

            $result = [
                'bookings_changed' =>
                    $bookings_changed,

                'inventory_restored' =>
                    $inventory_restored,

                'refunds_required' =>
                    $refunds_required,

                'refunds_already_recorded' =>
                    $refunds_already_recorded,

                'attention_count' =>
                    $attention_count,

                'email_failures' =>
                    $email_failures,
            ];
        } catch (DomainException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] =
                $exception->getMessage();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'EventNest event cancellation error: '
                . get_class($exception)
                . ' - '
                . $exception->getMessage()
            );

            $errors[] =
                'The event could not be cancelled safely. '
                . 'No partial database change was kept.';
        }
    }


    //REFRESH EVENT/SUMMARY AFTER POST

    $event_stmt->execute([
        ':event_id' => $event_id,
        ':organiser_id' => $organiser_id,
    ]);

    $refreshed_event =
        $event_stmt->fetch();

    if ($refreshed_event) {
        $event = $refreshed_event;
    }

    $impact_stmt->execute([
        ':event_id' => $event_id,
    ]);

    $refreshed_impact =
        $impact_stmt->fetch();

    if ($refreshed_impact) {
        $impact = $refreshed_impact;
    }
}


/*
 PAGE OUTPUT
*/

$page_title = 'Cancel event';

require_once __DIR__ . '/shared-components/header.php';

?>

<main class="page-content cancel-event-page">

    <p>
        <a
            class="btn btn-outline"
            href="manage-events.php"
        >
            Back to manage events
        </a>
    </p>


    <?php if ($result): ?>

        <section
            class="result-card"
            aria-labelledby="cancel-result-heading"
        >

            <div class="status status-error">
                Cancelled
            </div>

            <h1 id="cancel-result-heading">
                <?= escape_html($event['EventName']) ?>
                has been cancelled
            </h1>

            <p>
                The event record has been preserved for
                booking, payment, refund and audit history.
            </p>


            <dl class="summary-list">

                <div>
                    <dt>Bookings changed</dt>

                    <dd>
                        <?= (int) $result['bookings_changed'] ?>
                    </dd>
                </div>

                <div>
                    <dt>Ticket quantity restored</dt>

                    <dd>
                        <?= (int) $result['inventory_restored'] ?>
                    </dd>
                </div>

                <div>
                    <dt>Paid refunds requiring processing</dt>

                    <dd>
                        <?= (int) $result['refunds_required'] ?>
                    </dd>
                </div>

                <div>
                    <dt>Refunds already recorded</dt>

                    <dd>
                        <?= (int) $result['refunds_already_recorded'] ?>
                    </dd>
                </div>

                <div>
                    <dt>Items needing admin attention</dt>

                    <dd>
                        <?= (int) $result['attention_count'] ?>
                    </dd>
                </div>

                <div>
                    <dt>Email delivery issues</dt>

                    <dd>
                        <?= (int) $result['email_failures'] ?>
                    </dd>
                </div>

            </dl>


            <?php if (
                (int) $result['refunds_required'] > 0
            ): ?>

                <div class="alert alert-warning">

                    <p>
                        One or more paid bookings require the
                        approved PayFast sandbox/provider refund
                        verification process.
                    </p>

                    <p>
                        EventNest has not falsely marked those
                        payments as refunded. Their payment and
                        booking refund states should be finalised
                        only after the refund is verified.
                    </p>

                </div>

            <?php endif; ?>


            <?php if (
                (int) $result['attention_count'] > 0
                || (int) $result['email_failures'] > 0
            ): ?>

                <div class="alert alert-warning">

                    The event cancellation has been saved, but one
                    or more refund or email items require follow-up.
                    A notification failure does not reverse the valid
                    database cancellation.

                </div>

            <?php endif; ?>


            <div class="button-row">

                <a
                    class="btn btn-secondary"
                    href="manage-events.php"
                >
                    Back to events
                </a>

                <a
                    class="btn btn-outline"
                    href="business-dashboard.php"
                >
                    Organiser dashboard
                </a>

            </div>

        </section>


    <?php else: ?>

        <h1>
            Cancel
            <?= escape_html($event['EventName']) ?>?
        </h1>


        <?php if ($errors): ?>

            <div
                class="alert alert-error"
                role="alert"
            >

                <ul>

                    <?php foreach ($errors as $error): ?>

                        <li>
                            <?= escape_html($error) ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <?php if (!$cancellable): ?>

            <div class="alert alert-warning">

                <p>
                    This event cannot be cancelled from this
                    page because its current status is
                    <strong>
                        <?= escape_html($event['Status']) ?>
                    </strong>.
                </p>

                <a
                    class="btn btn-outline"
                    href="manage-events.php"
                >
                    Back to manage events
                </a>

            </div>


        <?php else: ?>

            <section class="summary-card">

                <h2>
                    Impact before confirmation
                </h2>

                <dl class="summary-list">

                    <div>
                        <dt>Current event status</dt>

                        <dd>
                            <?= escape_html($event['Status']) ?>
                        </dd>
                    </div>

                    <div>
                        <dt>Bookings associated with event</dt>

                        <dd>
                            <?= (int) $impact['BookingCount'] ?>
                        </dd>
                    </div>

                    <div>
                        <dt>
                            Paid value requiring refund review
                        </dt>

                        <dd>
                            <?= escape_html(
                                format_money(
                                    (float) $impact[
                                        'PotentialRefundTotal'
                                    ]
                                )
                            ) ?>
                        </dd>
                    </div>

                </dl>

                <p>
                    EventNest will stop new bookings by changing
                    this event to cancelled, process eligible
                    booking state changes, identify paid bookings
                    requiring refunds, and notify affected
                    attendees.
                </p>

                <p>
                    <strong>
                        A successful payment is not marked refunded
                        until the approved refund process verifies
                        the refund.
                    </strong>
                </p>

                <p>
                    <strong>
                        This action does not delete the event,
                        bookings or payment history.
                    </strong>
                </p>

            </section>


            <form
                method="post"
                action="cancel-event.php"
                class="form-stack"
            >

                <input
                    type="hidden"
                    name="event_id"
                    value="<?= (int) $event_id ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="cancel_event"
                >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escape_html($csrf_token) ?>"
                >


                <div class="form-group">

                    <label for="cancellation_reason">
                        Cancellation reason
                    </label>

                    <textarea
                        id="cancellation_reason"
                        name="cancellation_reason"
                        rows="5"
                        minlength="10"
                        maxlength="500"
                        required
                    ><?= escape_html($reason) ?></textarea>

                    <p class="field-help">
                        Enter between 10 and 500 characters.
                        This explanation will be included in
                        attendee cancellation notifications.
                    </p>

                </div>


                <div class="button-row">

                    <a
                        class="btn btn-outline"
                        href="manage-events.php"
                    >
                        Keep event
                    </a>

                    <button
                        class="btn btn-danger"
                        type="submit"
                    >
                        Confirm cancellation
                    </button>

                </div>

            </form>

        <?php endif; ?>

    <?php endif; ?>

</main>

<?php

require_once __DIR__ . '/shared-components/footer.php';

?>