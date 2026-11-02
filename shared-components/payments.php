<?php
declare(strict_types=1);
require_once __DIR__ . "/functions.php";

function payment_success_record_itn(PDO $pdo, array $post): void
{
    foreach ($post as $value) {
        if (!is_scalar($value)) {
            throw new RuntimeException('Invalid payment notification value.');
        }
    }
    $merchant_id = trim((string) getenv('PAYFAST_MERCHANT_ID'));
    $passphrase = (string) getenv('PAYFAST_PASSPHRASE');

    $received_signature = strtolower(trim((string) ($post['signature'] ?? '')));
    $reference = trim((string) ($post['m_payment_id'] ?? ''));
    $provider_status = strtoupper(trim((string) ($post['payment_status'] ?? '')));
    $transaction_id = trim((string) ($post['pf_payment_id'] ?? ''));
    $posted_merchant = trim((string) ($post['merchant_id'] ?? ''));
    $amount_gross = (string) ($post['amount_gross'] ?? '');

    if ($merchant_id === '' || $received_signature === '' || $reference === '' || strlen($reference) > 50
        || $transaction_id === '' || strlen($transaction_id) > 100 || !preg_match('/^\d+(?:\.\d{1,2})?$/D', $amount_gross)
        || !in_array($provider_status, ['COMPLETE', 'FAILED', 'CANCELLED'], true)) {
        throw new RuntimeException('Invalid payment notification shape.');
    }
    if ($posted_merchant === '' || !hash_equals($merchant_id, $posted_merchant)) {
        throw new RuntimeException('Payment merchant mismatch.');
    }

    $expected_signature = eventnest_payfast_itn_signature($post, $passphrase);
    if (!hash_equals($expected_signature, $received_signature)) {
        throw new RuntimeException('Payment notification signature failed.');
    }
    if (!payfast_validate_with_provider($post)) {
        throw new RuntimeException('Payment notification provider validation failed.');
    }

    $pdo->beginTransaction();
    try {
        $booking_stmt = $pdo->prepare(
            "SELECT b.BookingID, b.BookingReference, b.Status, b.Quantity, b.TotalAmount,
                    b.EventID, b.TicketID, t.Price, e.Status AS EventStatus
             FROM BOOKINGS b
             INNER JOIN TICKETS t ON t.TicketID = b.TicketID AND t.EventID = b.EventID
             INNER JOIN EVENTS e ON e.EventID = b.EventID
             WHERE b.BookingReference = :reference
             LIMIT 1 FOR UPDATE"
        );
        $booking_stmt->execute([':reference' => $reference]);
        $booking = $booking_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$booking) {
            throw new RuntimeException('Payment booking not found.');
        }

        $stored_amount = round((float) $booking['TotalAmount'], 2);
        $received_amount = round((float) $amount_gross, 2);
        if ($stored_amount <= 0.0 || abs($stored_amount - $received_amount) > 0.009
            || (isset($post['custom_int1']) && (string) $booking['BookingID'] !== (string) $post['custom_int1'])) {
            throw new RuntimeException('Payment amount mismatch.');
        }

        $existing_stmt = $pdo->prepare('SELECT PaymentID, BookingID, PaymentStatus, Escrow_status FROM PAYMENTS WHERE TransactionID = :transaction_id LIMIT 1 FOR UPDATE');
        $existing_stmt->execute([':transaction_id' => $transaction_id]);
        $existing_payment = $existing_stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_payment && (int) $existing_payment['BookingID'] !== (int) $booking['BookingID']) {
            throw new RuntimeException('Payment transaction belongs to another booking.');
        }
        // Retries must never reset released escrow or undo a verified refund.
        if ($existing_payment && in_array($existing_payment['PaymentStatus'], ['success', 'refunded'], true)) {
            $pdo->commit();
            return;
        }
        if ($booking['Status'] === 'refunded') {
            throw new RuntimeException('Payment notification requires review for a refunded booking.');
        }
        $successful = $pdo->prepare("SELECT PaymentID FROM PAYMENTS WHERE BookingID = ? AND PaymentStatus IN ('success','refunded') LIMIT 1 FOR UPDATE");
        $successful->execute([(int) $booking['BookingID']]);
        if ($provider_status !== 'COMPLETE' && $successful->fetchColumn()) { $pdo->commit(); return; }

        if ($provider_status === 'COMPLETE') {
            // Keep a late charge for a canceled event held for refund review, without reviving the booking.
            $inventory_available = ($booking['EventStatus'] ?? 'published') !== 'canceled';
            $other_success = $pdo->prepare("SELECT PaymentID FROM PAYMENTS WHERE BookingID = ? AND PaymentStatus IN ('success','refunded') AND (TransactionID IS NULL OR TransactionID <> ?) LIMIT 1 FOR UPDATE");
            $other_success->execute([(int) $booking['BookingID'], $transaction_id]);
            if ($other_success->fetchColumn()) {
                throw new RuntimeException('Another verified transaction already exists for this booking; provider review is required.');
            }
            // Verified provider success is authoritative over an earlier explicit cancel or timeout.
            // If that cancellation already restored stock, re-reserve the quantity where possible.
            if (in_array($booking['Status'], ['canceled', 'failed'], true) && $inventory_available) {
                $ticket_stmt = $pdo->prepare('SELECT QuantityAvailable FROM TICKETS WHERE TicketID = :ticket_id LIMIT 1 FOR UPDATE');
                $ticket_stmt->execute([':ticket_id' => (int) $booking['TicketID']]);
                $available = (int) ($ticket_stmt->fetchColumn() ?: 0);
                if ($available >= (int) $booking['Quantity']) {
                    $reserve_stmt = $pdo->prepare('UPDATE TICKETS SET QuantityAvailable = QuantityAvailable - :quantity WHERE TicketID = :ticket_id AND QuantityAvailable >= :minimum_quantity');
                    $reserve_stmt->execute([':quantity' => (int) $booking['Quantity'], ':minimum_quantity' => (int) $booking['Quantity'], ':ticket_id' => (int) $booking['TicketID']]);
                } else {
                    // Provider payment truth must not be discarded. The inventory mismatch is
                    // retained for admin review instead of inventing a new booking/payment state.
                    error_log('EventNest late verified payment inventory mismatch for booking ' . (int) $booking['BookingID']);
                    $inventory_available = false;
                }
            }

            if ($inventory_available && !in_array($booking['Status'], ['refunded'], true)) {
                $update_booking = $pdo->prepare("UPDATE BOOKINGS SET Status = 'paid' WHERE BookingID = :booking_id AND Status IN ('pending','canceled','failed','paid')");
                $update_booking->execute([':booking_id' => (int) $booking['BookingID']]);
            }

            if ($existing_payment) {
                $payment_stmt = $pdo->prepare(
                    "UPDATE PAYMENTS
                     SET PaymentDate = NOW(), Amount = :amount, PaymentMethod = 'PayFast',
                         PaymentStatus = 'success', Escrow_status = 'held', BookingID = :booking_id
                     WHERE PaymentID = :payment_id"
                );
                $payment_stmt->execute([
                    ':amount' => number_format($stored_amount, 2, '.', ''),
                    ':booking_id' => (int) $booking['BookingID'],
                    ':payment_id' => (int) $existing_payment['PaymentID'],
                ]);
            } else {
                $payment_stmt = $pdo->prepare(
                    "INSERT INTO PAYMENTS
                        (BookingID, PaymentDate, Amount, PaymentMethod, PaymentStatus, TransactionID, Escrow_status)
                     VALUES
                        (:booking_id, NOW(), :amount, 'PayFast', 'success', :transaction_id, 'held')"
                );
                $payment_stmt->execute([
                    ':booking_id' => (int) $booking['BookingID'],
                    ':amount' => number_format($stored_amount, 2, '.', ''),
                    ':transaction_id' => $transaction_id,
                ]);
            }
        } elseif (in_array($provider_status, ['FAILED', 'CANCELLED'], true)) {
            // Only a verified terminal provider result releases a dispatched checkout.
            $terminal = $provider_status === 'CANCELLED' ? 'canceled' : 'failed';
            $change = $pdo->prepare("UPDATE BOOKINGS SET Status = ? WHERE BookingID = ? AND Status = 'pending'");
            $change->execute([$terminal, (int) $booking['BookingID']]);
            if ($change->rowCount() === 1) {
                $restore = $pdo->prepare('UPDATE TICKETS SET QuantityAvailable = LEAST(Capacity, QuantityAvailable + ?) WHERE TicketID = ?');
                $restore->execute([(int) $booking['Quantity'], (int) $booking['TicketID']]);
            }
            if ($existing_payment) {
                $failed_stmt = $pdo->prepare(
                    "UPDATE PAYMENTS
                     SET PaymentDate = NOW(), Amount = :amount, PaymentMethod = 'PayFast',
                         PaymentStatus = 'failed', Escrow_status = NULL, BookingID = :booking_id
                     WHERE PaymentID = :payment_id AND PaymentStatus <> 'success'"
                );
                $failed_stmt->execute([
                    ':amount' => number_format($stored_amount, 2, '.', ''),
                    ':booking_id' => (int) $booking['BookingID'],
                    ':payment_id' => (int) $existing_payment['PaymentID'],
                ]);
            } else {
                $failed_stmt = $pdo->prepare(
                    "INSERT INTO PAYMENTS
                        (BookingID, PaymentDate, Amount, PaymentMethod, PaymentStatus, TransactionID, Escrow_status)
                     VALUES
                        (:booking_id, NOW(), :amount, 'PayFast', 'failed', :transaction_id, NULL)"
                );
                $failed_stmt->execute([
                    ':booking_id' => (int) $booking['BookingID'],
                    ':amount' => number_format($stored_amount, 2, '.', ''),
                    ':transaction_id' => $transaction_id,
                ]);
            }
        } else {
            throw new RuntimeException('Unsupported payment status.');
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

