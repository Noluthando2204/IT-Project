<?php

declare(strict_types=1);

/*
EventNest - scheduled-tasks/escrow.php
Canonical backend escrow checker and processor.
There is intentionally no separate check-escrow-release.php. This file owns
both the read-only eligibility checks and the authorised release/refund
processing so the same business rules are used everywhere.
*/

$is_cli = PHP_SAPI === 'cli';

/*
Dependency order
The shared functions.php owns CSRF helpers. Web mode loads auth.php
before accessing protected actions. CLI mode loads the connection directly.
*/
if ($is_cli) {
    require_once dirname(__DIR__) . '/database-config/connection.php';
} else {
    require_once dirname(__DIR__) . '/shared-components/auth.php';
}

require_once dirname(__DIR__) . '/shared-components/functions.php';

if (!$is_cli) {
    $admin_user = auth_require_admin();
}

$pdo = eventnest_db();

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

function escrow_positive_id(mixed $value): int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    return $id === false ? 0 : (int) $id;
}

function escrow_load_payment(PDO $pdo, int $payment_id, bool $for_update = false): ?array
{
    $sql = "
        SELECT
            p.PaymentID,
            p.BookingID,
            p.PaymentDate,
            p.Amount,
            p.PaymentMethod,
            p.PaymentStatus,
            p.TransactionID,
            p.Escrow_status,
            b.BookingReference,
            b.Status AS BookingStatus,
            b.TotalAmount,
            e.EventID,
            e.EventName,
            e.Status AS EventStatus,
            e.EventDate,
            e.UpdatedAt
        FROM PAYMENTS p
        INNER JOIN BOOKINGS b ON b.BookingID = p.BookingID
        INNER JOIN EVENTS e ON e.EventID = b.EventID
        WHERE p.PaymentID = :payment_id
        LIMIT 1
    ";

    if ($for_update) {
        $sql .= ' FOR UPDATE';
    }

    $statement = $pdo->prepare($sql);
    $statement->execute([':payment_id' => $payment_id]);
    $payment = $statement->fetch();

    return $payment ?: null;
}

function escrow_completion_timestamp(array $payment): ?int
{
    $event_date = !empty($payment['EventDate'])
        ? strtotime((string) $payment['EventDate'])
        : false;

    $updated_at = !empty($payment['UpdatedAt'])
        ? strtotime((string) $payment['UpdatedAt'])
        : false;

    if ($event_date === false && $updated_at === false) {
        return null;
    }

    if ($event_date === false) {
        return $updated_at;
    }

    if ($updated_at === false) {
        return $event_date;
    }

    /*
The current schema has no CompletedAt column. Use UpdatedAt when the
event was marked completed, but never start the release clock before
EventDate itself.
*/
    return max($event_date, $updated_at);
}

function escrow_release_eligible_at(array $payment): ?int
{
    if (
        ($payment['PaymentStatus'] ?? null) !== 'success'
        || ($payment['Escrow_status'] ?? null) !== 'held'
        || ($payment['BookingStatus'] ?? null) !== 'paid'
        || ($payment['EventStatus'] ?? null) !== 'completed'
    ) {
        return null;
    }

    $completed_at = escrow_completion_timestamp($payment);

    if ($completed_at === null) {
        return null;
    }

    return $completed_at + (24 * 60 * 60);
}

function escrow_assess(array $payment, ?int $now = null): array
{
    $now = $now ?? time();

    $assessment = [
        'PaymentID' => (int) ($payment['PaymentID'] ?? 0),
        'BookingReference' => (string) ($payment['BookingReference'] ?? ''),
        'EventID' => (int) ($payment['EventID'] ?? 0),
        'EventName' => (string) ($payment['EventName'] ?? ''),
        'PaymentStatus' => (string) ($payment['PaymentStatus'] ?? ''),
        'BookingStatus' => (string) ($payment['BookingStatus'] ?? ''),
        'EventStatus' => (string) ($payment['EventStatus'] ?? ''),
        'EscrowStatus' => $payment['Escrow_status'] ?? null,
        'ReleaseEligible' => false,
        'EligibleAt' => null,
        'Result' => 'not_ready',
        'Message' => '',
    ];

    if (($payment['Escrow_status'] ?? null) === 'released') {
        $assessment['Result'] = 'already_released';
        $assessment['Message'] = 'This payment has already been released.';
        return $assessment;
    }

    if (
        ($payment['PaymentStatus'] ?? null) === 'refunded'
        || ($payment['Escrow_status'] ?? null) === 'refunded'
    ) {
        $assessment['Result'] = 'already_refunded';
        $assessment['Message'] = 'This payment has already been refunded.';
        return $assessment;
    }

    if (($payment['EventStatus'] ?? null) === 'canceled') {
        if (
            ($payment['PaymentStatus'] ?? null) === 'success'
            && ($payment['Escrow_status'] ?? null) === 'held'
        ) {
            $assessment['Result'] = 'refund_review_required';
            $assessment['Message'] =
                'The event is canceled. Keep the payment held until the approved refund is verified.';
        } else {
            $assessment['Result'] = 'canceled_event';
            $assessment['Message'] = 'A canceled event is not eligible for escrow release.';
        }

        return $assessment;
    }

    if (($payment['PaymentStatus'] ?? null) !== 'success') {
        $assessment['Result'] = 'payment_not_successful';
        $assessment['Message'] = 'Only a verified successful payment can be considered for release.';
        return $assessment;
    }

    if (($payment['Escrow_status'] ?? null) !== 'held') {
        $assessment['Result'] = 'escrow_not_held';
        $assessment['Message'] = 'Only held escrow can proceed to release review.';
        return $assessment;
    }

    if (($payment['BookingStatus'] ?? null) !== 'paid') {
        $assessment['Result'] = 'booking_not_paid';
        $assessment['Message'] = 'The related booking must be paid before release.';
        return $assessment;
    }

    if (($payment['EventStatus'] ?? null) !== 'completed') {
        $assessment['Result'] = 'event_not_completed';
        $assessment['Message'] = 'The related event must be completed before release.';
        return $assessment;
    }

    $completed_at = escrow_completion_timestamp($payment);

    if ($completed_at === null) {
        $assessment['Result'] = 'completion_time_unavailable';
        $assessment['Message'] = 'The event completion time could not be determined safely.';
        return $assessment;
    }

    $eligible_at = $completed_at + (24 * 60 * 60);
    $assessment['EligibleAt'] = date('Y-m-d H:i:s', $eligible_at);

    if ($now < $eligible_at) {
        $assessment['Result'] = 'waiting_period';
        $assessment['Message'] = 'The minimum 24-hour post-event waiting period has not passed.';
        return $assessment;
    }

    $assessment['ReleaseEligible'] = true;

    if ($now < ($completed_at + (48 * 60 * 60))) {
        $assessment['Result'] = 'release_review_required';
        $assessment['Message'] =
            'The payment is eligible for administrator release review. '
            . 'The administrator must still confirm that no unresolved emailed issue or investigation requires it to remain held.';
        return $assessment;
    }

    $assessment['Result'] = 'overdue_release_review';
    $assessment['Message'] =
        'The payment has been held beyond 48 hours after completion and should be reviewed. '
        . 'It must not be auto-released without administrator approval.';

    return $assessment;
}

function escrow_verified_refund_ready(array $payment): bool
{
    return
        ($payment['PaymentStatus'] ?? null) === 'success'
        && ($payment['Escrow_status'] ?? null) === 'held'
        && in_array(
            (string) ($payment['BookingStatus'] ?? ''),
            ['paid', 'canceled'],
            true
        )
        && ($payment['EventStatus'] ?? null) === 'canceled';
}

function escrow_log(string $action, int $payment_id, string $result): void
{
    error_log(
        'EventNest escrow action=' . $action
        . ' PaymentID=' . $payment_id
        . ' result=' . $result
    );
}

function escrow_record_release(PDO $pdo, array $payment, bool $dry_run = false): string
{
    $payment_id = (int) $payment['PaymentID'];

    if (($payment['Escrow_status'] ?? null) === 'released') {
        return 'already_released';
    }

    if (
        ($payment['PaymentStatus'] ?? null) === 'refunded'
        || ($payment['Escrow_status'] ?? null) === 'refunded'
    ) {
        throw new DomainException('A refunded payment cannot be released.');
    }

    if (($payment['EventStatus'] ?? null) === 'canceled') {
        throw new DomainException('A canceled event must follow the refund path.');
    }

    $assessment = escrow_assess($payment);

    if (!$assessment['ReleaseEligible']) {
        throw new DomainException('This payment has not passed all EventNest release checks.');
    }

    if ($dry_run) {
        return 'dry_run_release';
    }

    $statement = $pdo->prepare(
        "UPDATE PAYMENTS
        SET Escrow_status = 'released'
        WHERE PaymentID = :payment_id
          AND PaymentStatus = 'success'
          AND Escrow_status = 'held'"
    );

    $statement->execute([':payment_id' => $payment_id]);

    if ($statement->rowCount() !== 1) {
        throw new DomainException('The payment changed before release could be completed.');
    }

    return 'released';
}

function escrow_record_verified_refund(PDO $pdo, array $payment, bool $dry_run = false): string
{
    $payment_id = (int) $payment['PaymentID'];

    if (
        ($payment['PaymentStatus'] ?? null) === 'refunded'
        && ($payment['Escrow_status'] ?? null) === 'refunded'
        && ($payment['BookingStatus'] ?? null) === 'refunded'
    ) {
        return 'already_refunded';
    }

    if (($payment['Escrow_status'] ?? null) === 'released') {
        throw new DomainException(
            'Released escrow requires separate administrator/payment-provider recovery.'
        );
    }

    if (!escrow_verified_refund_ready($payment)) {
        throw new DomainException(
            'Only a successful held payment for a canceled event can record a verified refund.'
        );
    }

    if ($dry_run) {
        return 'dry_run_refund';
    }

    $payment_update = $pdo->prepare(
        "UPDATE PAYMENTS
        SET PaymentStatus = 'refunded',
            Escrow_status = 'refunded'
        WHERE PaymentID = :payment_id
          AND PaymentStatus = 'success'
          AND Escrow_status = 'held'"
    );

    $payment_update->execute([':payment_id' => $payment_id]);

    if ($payment_update->rowCount() !== 1) {
        throw new DomainException(
            'The payment changed before the verified refund could be recorded.'
        );
    }

    $booking_update = $pdo->prepare(
        "UPDATE BOOKINGS
        SET Status = 'refunded'
        WHERE BookingID = :booking_id
          AND Status IN ('paid', 'canceled')"
    );

    $booking_update->execute([
        ':booking_id' => (int) $payment['BookingID'],
    ]);

    if ($booking_update->rowCount() !== 1) {
        throw new DomainException(
            'The booking changed before the verified refund could be recorded.'
        );
    }

    return 'refunded';
}

function escrow_review_queue(PDO $pdo): array
{
    $statement = $pdo->query(
        "SELECT
            p.PaymentID,
            p.BookingID,
            p.PaymentDate,
            p.Amount,
            p.PaymentMethod,
            p.PaymentStatus,
            p.TransactionID,
            p.Escrow_status,
            b.BookingReference,
            b.Status AS BookingStatus,
            b.TotalAmount,
            e.EventID,
            e.EventName,
            e.Status AS EventStatus,
            e.EventDate,
            e.UpdatedAt
        FROM PAYMENTS p
        INNER JOIN BOOKINGS b ON b.BookingID = p.BookingID
        INNER JOIN EVENTS e ON e.EventID = b.EventID
        WHERE p.PaymentStatus = 'success'
          AND p.Escrow_status = 'held'
        ORDER BY e.EventDate ASC, p.PaymentID ASC"
    );

    return $statement->fetchAll();
}

/*
CLI / trusted scheduled context
php scheduled-tasks/escrow.php
php scheduled-tasks/escrow.php --payment-id=12
php scheduled-tasks/escrow.php --payment-id=12 --approve-release
php scheduled-tasks/escrow.php --payment-id=12 --record-verified-refund
    --gateway-refund-verified
*/
if ($is_cli) {
    $options = getopt('', [
        'payment-id:',
        'approve-release',
        'record-verified-refund',
        'gateway-refund-verified',
        'dry-run',
    ]);

    $payment_id = escrow_positive_id($options['payment-id'] ?? null);
    $approve_release = array_key_exists('approve-release', $options);
    $record_refund = array_key_exists('record-verified-refund', $options);
    $gateway_refund_verified = array_key_exists('gateway-refund-verified', $options);
    $dry_run = array_key_exists('dry-run', $options);

    if ($approve_release && $record_refund) {
        fwrite(STDERR, "Choose either --approve-release or --record-verified-refund.\n");
        exit(2);
    }

    if ($gateway_refund_verified && !$record_refund) {
        fwrite(
            STDERR,
            "--gateway-refund-verified is valid only with --record-verified-refund.\n"
        );
        exit(2);
    }

    if (($approve_release || $record_refund) && $payment_id <= 0) {
        fwrite(STDERR, "A positive --payment-id is required for a state-changing action.\n");
        exit(2);
    }

    /*
 Read-only check of one payment.
*/
    if ($payment_id > 0 && !$approve_release && !$record_refund) {
        try {
            $payment = escrow_load_payment($pdo, $payment_id);

            if ($payment === null) {
                fwrite(STDERR, "Payment not found.\n");
                exit(1);
            }

            $assessment = escrow_assess($payment);

            echo 'PaymentID: ' . $assessment['PaymentID'] . PHP_EOL;
            echo 'Booking: ' . $assessment['BookingReference'] . PHP_EOL;
            echo 'Event: ' . $assessment['EventName'] . PHP_EOL;
            echo 'Result: ' . $assessment['Result'] . PHP_EOL;
            echo 'Release eligible: '
                . ($assessment['ReleaseEligible'] ? 'yes' : 'no')
                . PHP_EOL;

            if ($assessment['EligibleAt'] !== null) {
                echo 'Eligible at: ' . $assessment['EligibleAt'] . PHP_EOL;
            }

            echo $assessment['Message'] . PHP_EOL;
            exit(0);
        } catch (Throwable $exception) {
            error_log(
                'EventNest escrow check error: '
                . get_class($exception)
                . ' - '
                . $exception->getMessage()
            );

            fwrite(STDERR, "EventNest could not complete the escrow check.\n");
            exit(1);
        }
    }

    /*
 Trusted targeted release/refund.
*/
    if ($approve_release || $record_refund) {
        try {
            $pdo->beginTransaction();

            $payment = escrow_load_payment($pdo, $payment_id, true);

            if ($payment === null) {
                throw new DomainException('Payment not found.');
            }

            if ($record_refund) {
                if (!$gateway_refund_verified) {
                    throw new DomainException(
                        'Verified payment-provider refund confirmation was not supplied.'
                    );
                }

                $result = escrow_record_verified_refund($pdo, $payment, $dry_run);
                $action = 'refund';
            } else {
                $result = escrow_record_release($pdo, $payment, $dry_run);
                $action = 'release';
            }

            if ($dry_run) {
                $pdo->rollBack();
            } else {
                $pdo->commit();
            }

            escrow_log($action, $payment_id, $result);
            echo 'PaymentID ' . $payment_id . ': ' . $result . '.' . PHP_EOL;
            exit(0);
        } catch (DomainException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            escrow_log(
                $record_refund ? 'refund' : 'release',
                $payment_id,
                'denied'
            );

            fwrite(STDERR, $exception->getMessage() . PHP_EOL);
            exit(1);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'EventNest escrow processing error: '
                . get_class($exception)
                . ' - '
                . $exception->getMessage()
            );

            fwrite(STDERR, "EventNest could not process this escrow action.\n");
            exit(1);
        }
    }

    /*
 Scheduled read-only eligibility report.
*/
    try {
        $payments = escrow_review_queue($pdo);
        $review_count = 0;

        echo "EventNest escrow review\n";
        echo "=======================\n";

        foreach ($payments as $payment) {
            $assessment = escrow_assess($payment);

            if (
                in_array(
                    $assessment['Result'],
                    [
                        'release_review_required',
                        'overdue_release_review',
                        'refund_review_required',
                    ],
                    true
                )
            ) {
                $review_count++;

                echo 'PaymentID ' . $assessment['PaymentID']
                    . ' | Booking ' . $assessment['BookingReference']
                    . ' | Event ' . $assessment['EventName']
                    . ' | ' . $assessment['Result']
                    . PHP_EOL;
            }
        }

        echo PHP_EOL
            . $review_count
            . " payment(s) require administrator escrow review.\n";

        exit(0);
    } catch (Throwable $exception) {
        error_log(
            'EventNest escrow scheduled review error: '
            . get_class($exception)
            . ' - '
            . $exception->getMessage()
        );

        fwrite(STDERR, "EventNest could not complete the escrow review.\n");
        exit(1);
    }
}

/*
Web / authorised administrator action
admin-panel/manage-bookings.php submits payment_id, action and csrf_token.
escrow_status is never trusted from the browser.
*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

$csrf_token = isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
    ? $_POST['csrf_token']
    : null;

if (!eventnest_verify_csrf_token($csrf_token)) {
    http_response_code(403);
    exit('The request could not be verified.');
}

$payment_id = escrow_positive_id($_POST['payment_id'] ?? null);
$action = trim_input($_POST['action'] ?? '');

if ($payment_id <= 0 || !in_array($action, ['release', 'refund'], true)) {
    http_response_code(400);
    exit('Invalid escrow action.');
}

if ($action === 'refund') {
    /*
A browser request may start refund handling, but it must not claim that
the payment provider already completed the refund.
*/
    escrow_log('refund', $payment_id, 'gateway_verification_required');
    auth_set_flash('Refunds require payment-provider verification before they can be recorded.', 'info');

    header(
        'Location: '
        . eventnest_url(
            'admin-panel/manage-bookings.php?result=' .
            rawurlencode('gateway_verification_required') .
            '&payment_id=' . $payment_id
        )
    );
    exit;
}

$result_code = 'error';

try {
    $pdo->beginTransaction();

    $payment = escrow_load_payment($pdo, $payment_id, true);

    if ($payment === null) {
        throw new DomainException('Payment not found.');
    }

    /*
The targeted POST is the admin authorisation. The release function then
re-checks payment, booking, event, escrow and waiting-period rules.
*/
    $result_code = escrow_record_release($pdo, $payment, false);

    $pdo->commit();
    escrow_log('release', $payment_id, $result_code);
} catch (DomainException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    escrow_log('release', $payment_id, 'denied');
    $result_code = 'denied';
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'EventNest escrow web processing error: '
        . get_class($exception)
        . ' - '
        . $exception->getMessage()
    );

    escrow_log('release', $payment_id, 'error');
    $result_code = 'error';
}

$result_messages = [
    'released' => 'Escrow release was recorded. This action does not transfer funds through a payment provider.',
    'already_released' => 'This escrow release was already recorded.',
    'denied' => 'Release was denied: payment, booking, event or waiting-period requirements were not met.',
    'error' => 'Escrow release could not be recorded. Please try again later.',
];
auth_set_flash($result_messages[$result_code] ?? 'Escrow action completed.', $result_code === 'released' ? 'success' : 'info');
header(
    'Location: '
    . eventnest_url(
        'admin-panel/manage-bookings.php?result=' .
        rawurlencode($result_code) .
        '&payment_id=' . $payment_id
    )
);
exit;
