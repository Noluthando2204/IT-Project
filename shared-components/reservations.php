<?php
declare(strict_types=1);

// Durable checkout handoff markers are shared by HTTP requests and the CLI cleanup.
// They contain no credentials or personal data. Multiple app servers need shared storage.
function eventnest_checkout_file(int $booking_id): string
{
    if ($booking_id < 1) { throw new InvalidArgumentException('Invalid booking.'); }
    $directory = getenv('EVENTNEST_STATE_DIR') ?: dirname(__DIR__) . '/database-config/runtime';
    return rtrim($directory, '/\\') . '/checkout-' . $booking_id;
}

function eventnest_checkout_started(int $booking_id): bool
{
    return is_file(eventnest_checkout_file($booking_id));
}

// Caller holds the booking row lock, also used by expiration, cancellation and ITN.
function eventnest_mark_checkout(int $booking_id): void
{
    $file = eventnest_checkout_file($booking_id);
    if (!is_dir(dirname($file)) && !mkdir(dirname($file), 0700, true) && !is_dir(dirname($file))) {
        throw new RuntimeException('Checkout state storage unavailable.');
    }
    if (file_put_contents($file, 'awaiting-provider', LOCK_EX) === false) {
        throw new RuntimeException('Checkout state could not be saved.');
    }
    @chmod($file, 0600);
}
