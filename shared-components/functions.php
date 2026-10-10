

<?php
/*
|--------------------------------------------------------------------------
| REUSABLE FUNCTIONS
|--------------------------------------------------------------------------
| Common helper functions used across the EventNest project.
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| OUTPUT ESCAPING
|--------------------------------------------------------------------------
| Safely escapes data when displaying it in HTML.
| Use this instead of sanitizing data before storing it.
|--------------------------------------------------------------------------
*/

function en_e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| FORMAT DATE
|--------------------------------------------------------------------------
| Formats a date for display.
| Example: 2026-09-16 -> 16 Sep 2026
|--------------------------------------------------------------------------
*/

function formatDate($date)
{
    if (empty($date)) {
        return '';
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return '';
    }

    return date('d M Y', $timestamp);
}

/*
|--------------------------------------------------------------------------
| CSRF TOKEN GENERATION
|--------------------------------------------------------------------------
| Creates a session CSRF token if one does not already exist.
|--------------------------------------------------------------------------
*/

function getCsrfToken()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new RuntimeException(
            'A session must be started before generating a CSRF token.'
        );
    }

    if (
        !isset($_SESSION['csrf_token']) ||
        !is_string($_SESSION['csrf_token']) ||
        $_SESSION['csrf_token'] === ''
    ) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/*
|--------------------------------------------------------------------------
| CSRF FORM FIELD
|--------------------------------------------------------------------------
| Outputs a hidden field containing the current CSRF token.
|--------------------------------------------------------------------------
*/

function csrfField()
{
    echo '<input type="hidden" name="csrf_token" value="'
        . en_e(getCsrfToken())
        . '">';
}

/*
|--------------------------------------------------------------------------
| CSRF TOKEN VERIFICATION
|--------------------------------------------------------------------------
| Rejects POST requests with a missing or invalid CSRF token.
| Call at the beginning of every relevant POST handler.
|--------------------------------------------------------------------------
*/

function verifyCsrfToken()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        http_response_code(403);
        exit('Forbidden');
    }

    $sessionToken = $_SESSION['csrf_token'] ?? null;
    $postedToken = $_POST['csrf_token'] ?? null;

    if (
        !is_string($sessionToken) ||
        $sessionToken === '' ||
        !is_string($postedToken) ||
        !hash_equals($sessionToken, $postedToken)
    ) {
        http_response_code(403);
        exit('Invalid or missing CSRF token.');
    }
}

/*
|--------------------------------------------------------------------------
| REDIRECT HELPER
|--------------------------------------------------------------------------
| Redirects to an internal EventNest URL and stops execution.
|--------------------------------------------------------------------------
*/

function redirect($path)
{
    if (
        !is_string($path) ||
        $path === '' ||
        preg_match('/[\r\n]/', $path) ||
        preg_match('/^[a-z][a-z0-9+.-]*:/i', $path) ||
        str_starts_with($path, '//')
    ) {
        throw new InvalidArgumentException(
            'Invalid internal redirect path.'
        );
    }

    if (!function_exists('en_url')) {
        throw new RuntimeException(
            'The en_url() helper must be loaded before redirect().'
        );
    }

    header('Location: ' . en_url($path));
    exit;
}

/*
|--------------------------------------------------------------------------
| GENERATE BOOKING REFERENCE
|--------------------------------------------------------------------------
| Generates an EN-prefixed booking reference.
| Check uniqueness against the database before inserting.
|--------------------------------------------------------------------------
*/

function generateBookingReference()
{
    return 'EN-' . strtoupper(bin2hex(random_bytes(4)));
}

/*
|--------------------------------------------------------------------------
| STATUS BADGE CLASS
|--------------------------------------------------------------------------
| Returns only approved status CSS classes.
| Unrecognised statuses fall back to status-draft.
|--------------------------------------------------------------------------
*/

function statusClass($status)
{
    $status = strtolower(trim((string) $status));

    $allowedStatuses = [
        'draft',
        'pending',
        'approved',
        'rejected',
        'published',
        'cancelled',
        'completed',
        'active',
        'inactive',
        'suspended',
        'banned',
        'verified',
        'unverified',
        'paid',
        'failed',
        'refunded',
        'confirmed',
        'open',
        'closed',
    ];

    if (!in_array($status, $allowedStatuses, true)) {
        $status = 'draft';
    }

    return 'status-badge status-' . $status;
}