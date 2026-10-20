<?php

declare(strict_types=1);


if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    $is_https =
        isset($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== ''
        && strtolower((string) $_SERVER['HTTPS']) !== 'off';

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $is_https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

//DATABASE CONNECTION
require_once __DIR__ . '/../database-config/connection.php';


/*
 Return the shared EventNest PDO connection.
 */
function auth_db(): PDO
{
    global $conn, $pdo;

    if (isset($conn) && $conn instanceof PDO) {
        return $conn;
    }

    if (isset($pdo) && $pdo instanceof PDO) {
        return $pdo;
    }

    error_log('EventNest auth: PDO connection was not available.');

    http_response_code(500);
    exit('A system error occurred. Please try again later.');
}

//PROJECT URL HELPERS
function auth_project_base_url(): string
{
    $script_name = str_replace(
        '\\',
        '/',
        (string) ($_SERVER['SCRIPT_NAME'] ?? '')
    );

    $directory = rtrim(
        str_replace('\\', '/', dirname($script_name)),
        '/'
    );

    if ($directory === '.' || $directory === '/') {
        return '';
    }

    if (substr($directory, -12) === '/admin-panel') {
        $directory = str_replace(
            '\\',
            '/',
            dirname($directory)
        );

        if ($directory === '/' || $directory === '.') {
            return '';
        }
    }

    return rtrim($directory, '/');
}


/**
 * Safely redirect to a known EventNest project route.
 */
function auth_redirect(string $relative_url): void
{
    $relative_url = ltrim($relative_url, '/');

    /*
     * Prevent this helper from ever becoming an open redirect.
     */
    if (
        stripos($relative_url, 'http://') === 0
        || stripos($relative_url, 'https://') === 0
        || strpos($relative_url, '//') === 0
    ) {
        $relative_url = 'index.php';
    }

    $base_url = auth_project_base_url();

    header('Location: ' . $base_url . '/' . $relative_url);
    exit;
}

//FLASH MESSAGE HELPERS
function auth_set_flash(
    string $message,
    string $type = 'error'
): void {
    $_SESSION['auth_flash'] = [
        'message' => $message,
        'type' => $type,
    ];
}


/**
 * Read and remove the current authentication flash message.
 */
function auth_get_flash(): ?array
{
    if (
        !isset($_SESSION['auth_flash'])
        || !is_array($_SESSION['auth_flash'])
    ) {
        return null;
    }

    $flash = $_SESSION['auth_flash'];

    unset($_SESSION['auth_flash']);

    return $flash;
}


/**
 * Create/reuse secure CSRF token.
 */
function eventnest_csrf_token(): string
{
    if (
        empty($_SESSION['csrf_token'])
        || !is_string($_SESSION['csrf_token'])
    ) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}


/**
 * Verify a submitted CSRF token.
 */
function eventnest_verify_csrf_token(?string $token): bool
{
    if (
        $token === null
        || $token === ''
        || empty($_SESSION['csrf_token'])
        || !is_string($_SESSION['csrf_token'])
    ) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

//SESSION TERMINATION
function auth_destroy_session(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/*
 Load the currently authenticated user from USERS.
 Session Role/UserStatus/
 VerificationStatus values are refreshed from the database.
 */
function auth_current_user(): ?array
{
    if (
        !isset($_SESSION['user_id'])
        || !is_numeric($_SESSION['user_id'])
        || (int) $_SESSION['user_id'] <= 0
    ) {
        return null;
    }

    $user_id = (int) $_SESSION['user_id'];

    try {
        $stmt = auth_db()->prepare(
            '
            SELECT
                UserID,
                FirstName,
                LastName,
                Email,
                UserStatus,
                Role,
                VerificationStatus,
                Verification_docs_path,
                Blocks_count,
                Suspension_count,
                Suspension_start_date,
                Is_email_verified,
                VerificationFeePaidAt,
                VerificationFeePaidUntil,
                VerificationFeePaymentStatus,
                VerificationFeeGatewayReference
            FROM USERS
            WHERE UserID = :user_id
            LIMIT 1
            '
        );

        $stmt->execute([
            ':user_id' => $user_id,
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log(
            'EventNest auth user lookup failed: ' . $e->getMessage()
        );

        http_response_code(500);
        exit('A system error occurred. Please try again later.');
    }

    if (!$user) {
        auth_destroy_session();
        auth_redirect('login.php?auth=session_invalid');
    }

    //PERMANENT BAN. Two suspensions means permanent denial of normal access.
    if ((int) $user['Suspension_count'] >= 2) {
        auth_destroy_session();

        auth_redirect('login.php?auth=account_unavailable');
    }

    //ACTIVE 30-DAY SUSPENSION
    if (!empty($user['Suspension_start_date'])) {
        try {
            $suspension_start = new DateTimeImmutable(
                (string) $user['Suspension_start_date']
            );

            $suspension_end = $suspension_start->modify('+30 days');
            $now = new DateTimeImmutable();

            if ($now < $suspension_end) {
                auth_destroy_session();

                auth_redirect(
                    'login.php?auth=account_suspended'
                );
            }
        } catch (Exception $e) {
            /*
            Fail closed if a malformed suspension date is stored.
             */
            error_log(
                'EventNest auth: invalid Suspension_start_date for UserID '
                . $user_id
            );

            auth_destroy_session();

            auth_redirect(
                'login.php?auth=account_unavailable'
            );
        }
    }

    //ACCOUNT STATUS
    if ((string) $user['UserStatus'] !== 'active') {
        auth_destroy_session();

        auth_redirect('login.php?auth=account_unavailable');
    }

    //REFRESH SHARED SESSION VALUES FROM DATABASE
    $_SESSION['user_id'] = (int) $user['UserID'];
    $_SESSION['role'] = (string) $user['Role'];
    $_SESSION['user_status'] = (string) $user['UserStatus'];

    /*
     * Compatibility session key only.
     * USERS.VerificationStatus remains the real source of truth.
     */
    $_SESSION['verification_status'] =
        (string) $user['VerificationStatus'];

    return $user;
}

//REQUIRE LOGIN
function auth_require_login(): array
{
    $user = auth_current_user();

    if ($user === null) {
        auth_redirect('login.php?auth=login_required');
    }

    return $user;
}

//Role Checks
function auth_require_attendee(): array
{
    $user = auth_require_login();

    if ((string) $user['Role'] !== 'attendee') {
        auth_set_flash(
            'You do not have permission to access that page.'
        );

        auth_redirect('index.php');
    }

    return $user;
}


/*
 Require organiser login, regardless of verification state. 
 Use this for pages such as organiser-verification.php and the
 organiser dashboard where pending/rejected organisers need to
 see their current verification state.
 */
function auth_require_organiser(): array
{
    $user = auth_require_login();

    if ((string) $user['Role'] !== 'organiser') {
        auth_set_flash(
            'You do not have permission to access that page.'
        );

        auth_redirect('index.php');
    }

    return $user;
}


/*
 Require an organiser whose database VerificationStatus is verified.
 Use this for event creation, organiser posts, private organiser
 management and other verification-gated functionality.
 */
function auth_require_verified_organiser(): array
{
    $user = auth_require_organiser();

    if ((string) $user['VerificationStatus'] !== 'verified') {
        auth_set_flash(
            'Your organiser account must be verified before you can use that feature.'
        );

        auth_redirect('organiser-verification.php');
    }

    return $user;
}

//Require administrator access.
function auth_require_admin(): array
{
    $user = auth_require_login();

    if ((string) $user['Role'] !== 'admin') {
        auth_set_flash(
            'Administrator access is required.'
        );

        auth_redirect('index.php');
    }

    return $user;
}

//ORGANISER VERIFICATION-FEE COVERAGE. Return true only when the organiser currently has valid paid coverage.
function auth_has_current_verification_fee(
    array $user
): bool {
    if (
        (string) ($user['Role'] ?? '') !== 'organiser'
        || (string) ($user['VerificationFeePaymentStatus'] ?? '') !== 'paid'
        || empty($user['VerificationFeePaidUntil'])
    ) {
        return false;
    }

    try {
        $paid_until = new DateTimeImmutable(
            (string) $user['VerificationFeePaidUntil']
        );

        return $paid_until > new DateTimeImmutable();
    } catch (Exception $e) {
        error_log(
            'EventNest auth: invalid VerificationFeePaidUntil for UserID '
            . (int) ($user['UserID'] ?? 0)
        );

        return false;
    }
}
