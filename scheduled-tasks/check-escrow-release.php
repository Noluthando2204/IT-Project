
<?php

/**
 * EventNest - Scheduled Escrow Eligibility Check
 *
 * Runs the existing escrow eligibility report.
 * It does NOT release payments or issue refunds automatically.
 */

// This task must be run from the command line.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden. Run this task from the command line.\n");
}

// Reuse the existing escrow processing and eligibility checks.
$escrowFile = __DIR__ . DIRECTORY_SEPARATOR . 'escrow.php';

if (!is_file($escrowFile)) {
    fwrite(STDERR, "Error: escrow.php could not be found.\n");
    exit(1);
}

require $escrowFile;
