<?php

declare(strict_types=1);

$is_cli = PHP_SAPI === 'cli';

require_once dirname(__DIR__) . '/database-config/connection.php';
require_once dirname(__DIR__) . '/shared-components/functions.php';

if (!$is_cli) {
    require_once dirname(__DIR__) . '/shared-components/auth.php';
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (isset($conn) && $conn instanceof PDO) {
        $pdo = $conn;
    } else {
        if ($is_cli) {
            fwrite(STDERR, "EventNest database connection is unavailable.\n");
            exit(1);
        }

        http_response_code(500);
        exit('EventNest is temporarily unavailable.');
    }
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

function escrow_positive_id(mixed $value): int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id === false ? 0 : (int) $id;
}

function escrow_load_payment(PDO $pdo, int $payment_id, bool $for_update = false): ?array
{
    $sql = "SELECT
                p.PaymentID,
                p.BookingID,
                p.PaymentDate,
                p.Amount,
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
            LIMIT 1";

    if ($for_update) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':payment_id' => $payment_id]);
    $row = $stmt->fetch();

    return $row ?: null;
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

    /*
     * The approved schema has no CompletedAt column. event-confirm.php changes the event
     * to completed and therefore updates EVENTS.UpdatedAt. EventDate is a safe fallback for
     * older rows where UpdatedAt is unavailable.
     */
    $completed_at = !empty($payment['UpdatedAt'])
        ? strtotime((string) $payment['UpdatedAt'])
        : false;

    if ($completed_at === false) {
        $completed_at = strtotime((string) ($payment['EventDate'] ?? ''));
    }

    if ($completed_at === false) {
        return null;
    }

    // Admin review may begin after the lower edge of the agreed 24-48 hour window.
    return $completed_at + (24 * 60 * 60);
}

function escrow_release_ready(array $payment): bool
{
    $eligible_at = escrow_release_eligible_at($payment);
    return $eligible_at !== null && time() >= $eligible_at;
}

function escrow_verified_refund_ready(array $payment): bool
{
    if (($payment['PaymentStatus'] ?? null) !== 'success') {
        return false;
    }

    if (($payment['Escrow_status'] ?? null) !== 'held') {
        return false;
    }

    if (!in_array((string) ($payment['BookingStatus'] ?? ''), ['paid', 'canceled'], true)) {
        return false;
    }

    return ($payment['EventStatus'] ?? null) === 'canceled';
}

function escrow_log(string $action, int $payment_id, string $result): void
{
    error_log(
        'EventNest escrow action=' . $action
        . ' PaymentID=' . $payment_id
        . ' result=' . $result
    );
}

function escrow_record_verified_refund(PDO $pdo, array $payment, bool $dry_run = false): string
{
    $payment_id = (int) $payment['PaymentID'];

    if (
        $payment['PaymentStatus'] === 'refunded'
        && $payment['Escrow_status'] === 'refunded'
        && $payment['BookingStatus'] === 'refunded'
    ) {
        return 'already_refunded';
    }

    if ($payment['Escrow_status'] === 'released') {
        throw new DomainException(
            'Released escrow requires separate administrator/payment-provider recovery and cannot be rewritten automatically.'
        );
    }

    if (!escrow_verified_refund_ready($payment)) {
        throw new DomainException(
            'Only a successful held payment for a canceled event and paid/canceled booking can record a verified refund.'
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
        throw new DomainException('The payment changed before the verified refund could be recorded.');
    }

    $booking_update = $pdo->prepare(
        "UPDATE BOOKINGS
            SET Status = 'refunded'
          WHERE BookingID = :booking_id
            AND Status IN ('paid','canceled')"
    );
    $booking_update->execute([':booking_id' => (int) $payment['BookingID']]);

    if ($booking_update->rowCount() !== 1) {
        throw new DomainException('The booking changed before the verified refund could be recorded.');
    }

    return 'refunded';
}

function escrow_record_release(PDO $pdo, array $payment, bool $dry_run = false): string
{
    $payment_id = (int) $payment['PaymentID'];

    if ($payment['Escrow_status'] === 'released') {
        return 'already_released';
    }

    if ($payment['PaymentStatus'] === 'refunded' || $payment['Escrow_status'] === 'refunded') {
        throw new DomainException('A refunded payment cannot be released.');
    }

    if ($payment['EventStatus'] === 'canceled') {
        throw new DomainException('A canceled event must follow the refund path, not escrow release.');
    }

    if (!escrow_release_ready($payment)) {
        throw new DomainException('This payment has not passed all EventNest release checks yet.');
    }

    if ($dry_run) {
        return 'dry_run_release';
    }

    $update = $pdo->prepare(
        "UPDATE PAYMENTS
            SET Escrow_status = 'released'
          WHERE PaymentID = :payment_id
            AND PaymentStatus = 'success'
            AND Escrow_status = 'held'"
    );
    $update->execute([':payment_id' => $payment_id]);

    if ($update->rowCount() !== 1) {
        throw new DomainException('The payment changed before release could be completed.');
    }

    return 'released';
}

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
        fwrite(STDERR, "Choose either --approve-release or --record-verified-refund, not both.\n");
        exit(2);
    }

    if ($gateway_refund_verified && !$record_refund) {
        fwrite(STDERR, "--gateway-refund-verified is valid only with --record-verified-refund.\n");
        exit(2);
    }

    if (($approve_release || $record_refund) && $payment_id <= 0) {
        fwrite(STDERR, "A positive --payment-id is required for a release or verified refund action.\n");
        exit(2);
    }

    if ($payment_id > 0) {
        try {
            $pdo->beginTransaction();

            $payment = escrow_load_payment($pdo, $payment_id, true);
            if (!$payment) {
                throw new DomainException('Payment not found.');
            }

            if ($record_refund) {
                if (!$gateway_refund_verified) {
                    throw new DomainException(
                        'Verified PayFast/payment-provider refund confirmation was not supplied.'
                    );
                }

                $result = escrow_record_verified_refund($pdo, $payment, $dry_run);
                $action = 'refund';
            } elseif ($approve_release) {
                $result = escrow_record_release($pdo, $payment, $dry_run);
                $action = 'release';
            } else {
                $eligible_at = escrow_release_eligible_at($payment);

                if ($payment['EventStatus'] === 'canceled' && $payment['Escrow_status'] === 'held') {
                    $result = 'refund_review_required';
                    echo "PaymentID {$payment_id}: canceled event; keep held until the provider refund is verified.\n";
                } elseif ($eligible_at !== null && time() >= $eligible_at) {
                    $result = 'release_review_required';
                    echo "PaymentID {$payment_id}: eligible for admin release review; no state was changed.\n";
                } else {
                    $result = 'not_ready';
                    echo "PaymentID {$payment_id}: not currently eligible for release/refund recording.\n";
                }

                $pdo->commit();
                escrow_log('check', $payment_id, $result);
                exit(0);
            }

            if ($dry_run) {
                $pdo->rollBack();
            } else {
                $pdo->commit();
            }

            escrow_log($action, $payment_id, $result);
            echo "PaymentID {$payment_id}: {$result}.\n";
            exit(0);
        } catch (DomainException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            escrow_log(
                $record_refund ? 'refund' : ($approve_release ? 'release' : 'check'),
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

    // Scheduled eligibility report. It never auto-releases or auto-refunds.
    try {
        $stmt = $pdo->query(
            "SELECT
                p.PaymentID,
                b.BookingReference,
                e.EventID,
                e.EventName,
                e.EventDate,
                e.UpdatedAt
             FROM PAYMENTS p
             INNER JOIN BOOKINGS b ON b.BookingID = p.BookingID
             INNER JOIN EVENTS e ON e.EventID = b.EventID
             WHERE p.PaymentStatus = 'success'
               AND p.Escrow_status = 'held'
               AND b.Status = 'paid'
               AND e.Status = 'completed'
               AND COALESCE(e.UpdatedAt, e.EventDate) <= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             ORDER BY COALESCE(e.UpdatedAt, e.EventDate) ASC, p.PaymentID ASC"
        );

        $eligible = $stmt->fetchAll();
        echo 'EventNest escrow review: ' . count($eligible) . " payment(s) eligible for admin release review.\n";

        foreach ($eligible as $payment) {
            echo 'PaymentID ' . (int) $payment['PaymentID']
                . ' | Booking ' . $payment['BookingReference']
                . ' | EventID ' . (int) $payment['EventID']
                . ' | ' . $payment['EventName']
                . PHP_EOL;
        }

        exit(0);
    } catch (Throwable $exception) {
        error_log(
            'EventNest escrow eligibility error: '
            . get_class($exception)
            . ' - '
            . $exception->getMessage()
        );
        fwrite(STDERR, "EventNest could not complete the escrow eligibility check.\n");
        exit(1);
    }
}

// --------------------------- WEB / ADMIN MODE ---------------------------
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('The request could not be verified.');
}

$payment_id = escrow_positive_id($_POST['payment_id'] ?? null);
$action = is_string($_POST['action'] ?? null) ? trim($_POST['action']) : '';

if ($payment_id <= 0 || !in_array($action, ['release', 'refund'], true)) {
    http_response_code(400);
    exit('Invalid escrow action.');
}

if ($action === 'refund') {
    // The browser may start the refund workflow, but it must not declare provider success.
    escrow_log('refund', $payment_id, 'gateway_verification_required');
    header(
        'Location: '
        . eventnest_url('admin-panel/escrow.php?result=gateway_verification_required')
    );
    exit;
}

$result_code = 'error';

try {
    $pdo->beginTransaction();

    $payment = escrow_load_payment($pdo, $payment_id, true);
    if (!$payment) {
        throw new DomainException('Payment not found.');
    }

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
        'EventNest escrow processing error: '
        . get_class($exception)
        . ' - '
        . $exception->getMessage()
    );
    escrow_log('release', $payment_id, 'error');
    $result_code = 'error';
}

header(
    'Location: '
    . eventnest_url(
        'admin-panel/escrow.php?result=' . rawurlencode($result_code)
    )
);
exit;
