<?php

/*
|--------------------------------------------------------------------------
| EventNest - shared-components/auth.php
|--------------------------------------------------------------------------
| Central authentication and authorisation helpers.
|
| Responsibilities:
| - Start the session safely.
| - Confirm the logged-in account still exists.
| - Refresh security-related user state from USERS.
| - Deny deleted, suspended and permanently denied accounts.
| - Enforce attendee, organiser and administrator roles.
| - Enforce organiser verification where required.
|
| IMPORTANT:
| Authentication confirms identity and general access only.
| Individual feature files must still verify ownership of records such as
| events, bookings, posts and reviews where required.
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| LOAD DATABASE CONNECTION
|--------------------------------------------------------------------------
| EventNest connection.php exposes the PDO instance as $pdo.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../database-config/connection.php';


/*
|--------------------------------------------------------------------------
| START SESSION
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| BUILD EVENTNEST URL
|--------------------------------------------------------------------------
| Creates a project-relative URL so the application can work whether it is
| installed directly in the web root or in a folder such as /EventNest/.
|--------------------------------------------------------------------------
*/

function eventnest_url(string $path = ''): string
{
    $project_root = realpath(dirname(__DIR__));

    $document_root = isset($_SERVER['DOCUMENT_ROOT'])
        ? realpath($_SERVER['DOCUMENT_ROOT'])
        : false;

    $base_path = '';

    if ($project_root !== false && $document_root !== false) {
        $project_root = str_replace('\\', '/', $project_root);
        $document_root = rtrim(
            str_replace('\\', '/', $document_root),
            '/'
        );

        if (
            $project_root === $document_root ||
            strpos($project_root, $document_root . '/') === 0
        ) {
            $base_path = substr(
                $project_root,
                strlen($document_root)
            );
        }
    }

    $base_path = '/' . trim($base_path, '/');

    if ($base_path === '/') {
        $base_path = '';
    }

    return $base_path . '/' . ltrim($path, '/');
}


/*
|--------------------------------------------------------------------------
| REDIRECT
|--------------------------------------------------------------------------
*/

function auth_redirect(string $path): void
{
    header('Location: ' . eventnest_url($path));
    exit();
}


/*
|--------------------------------------------------------------------------
| CLEAR EVENTNEST SESSION
|--------------------------------------------------------------------------
*/

function clear_eventnest_session(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $cookie_parameters = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $cookie_parameters['path'],
            $cookie_parameters['domain'],
            $cookie_parameters['secure'],
            $cookie_parameters['httponly']
        );
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}


/*
|--------------------------------------------------------------------------
| LOAD CURRENT USER SECURITY STATE
|--------------------------------------------------------------------------
| Security-sensitive account state is refreshed from USERS instead of
| trusting an old session copy.
|--------------------------------------------------------------------------
*/

function get_current_auth_user(): ?array
{
    global $pdo;

    if (
        !isset($_SESSION['user_id']) ||
        !is_numeric($_SESSION['user_id'])
    ) {
        return null;
    }

    $user_id = (int) $_SESSION['user_id'];

    if ($user_id <= 0) {
        return null;
    }

    try {
        $statement = $pdo->prepare(
            'SELECT
                UserID,
                Role,
                UserStatus,
                VerificationStatus,
                Suspension_count,
                Suspension_start_date,
                VerificationFeePaidUntil,
                VerificationFeePaymentStatus
             FROM USERS
             WHERE UserID = :user_id
             LIMIT 1'
        );

        $statement->execute([
            ':user_id' => $user_id
        ]);

        $user = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return null;
        }

        return $user;

    } catch (PDOException $exception) {
        error_log(
            'EventNest authentication database error: ' .
            $exception->getMessage()
        );

        return null;
    }
}


/*
|--------------------------------------------------------------------------
| CHECK ACTIVE 30-DAY SUSPENSION
|--------------------------------------------------------------------------
| EventNest rule:
| Three qualifying blocks create one 30-day suspension.
|--------------------------------------------------------------------------
*/

function has_active_suspension(array $user): bool
{
    if (empty($user['Suspension_start_date'])) {
        return false;
    }

    try {
        $suspension_start = new DateTimeImmutable(
            $user['Suspension_start_date']
        );

        $suspension_end = $suspension_start->modify('+30 days');
        $now = new DateTimeImmutable();

        return (
            $now >= $suspension_start &&
            $now < $suspension_end
        );

    } catch (Exception $exception) {
        error_log(
            'EventNest suspension date error: ' .
            $exception->getMessage()
        );

        /*
         * Fail closed if the stored suspension date is invalid.
         */
        return true;
    }
}


/*
|--------------------------------------------------------------------------
| SYNC SESSION SECURITY VALUES
|--------------------------------------------------------------------------
*/

function sync_auth_session(array $user): void
{
    $_SESSION['user_id'] =
        (int) $user['UserID'];

    $_SESSION['role'] =
        $user['Role'];

    $_SESSION['user_status'] =
        $user['UserStatus'];

    $_SESSION['verification_status'] =
        $user['VerificationStatus'];
}


/*
|--------------------------------------------------------------------------
| REQUIRE LOGIN
|--------------------------------------------------------------------------
| Protected access requires:
| - valid session UserID
| - existing USERS row
| - UserStatus = active
| - no active 30-day suspension
| - Suspension_count below 2
|--------------------------------------------------------------------------
*/

function require_login(): array
{
    if (!isset($_SESSION['user_id'])) {
        auth_redirect('login.php');
    }

    $user = get_current_auth_user();

    if ($user === null) {
        clear_eventnest_session();
        auth_redirect('login.php');
    }

    sync_auth_session($user);


    /*
    |--------------------------------------------------------------------------
    | DELETED ACCOUNT
    |--------------------------------------------------------------------------
    */

    if ($user['UserStatus'] === 'deleted') {
        clear_eventnest_session();
        auth_redirect('login.php');
    }


    /*
    |--------------------------------------------------------------------------
    | PERMANENT ACCESS DENIAL
    |--------------------------------------------------------------------------
    | Two suspensions result in permanent denial of normal access.
    |--------------------------------------------------------------------------
    */

    if ((int) $user['Suspension_count'] >= 2) {
        clear_eventnest_session();
        auth_redirect('login.php');
    }


    /*
    |--------------------------------------------------------------------------
    | ACTIVE SUSPENSION
    |--------------------------------------------------------------------------
    */

    if (has_active_suspension($user)) {
        clear_eventnest_session();
        auth_redirect('login.php');
    }


    /*
    |--------------------------------------------------------------------------
    | ACCOUNT MUST BE ACTIVE
    |--------------------------------------------------------------------------
    */

    if ($user['UserStatus'] !== 'active') {
        clear_eventnest_session();
        auth_redirect('login.php');
    }

    return $user;
}


/*
|--------------------------------------------------------------------------
| REQUIRE ROLE
|--------------------------------------------------------------------------
| Examples:
|
| require_role('admin');
| require_role(['admin', 'organiser']);
|--------------------------------------------------------------------------
*/

function require_role($allowed_roles): array
{
    $user = require_login();

    if (!is_array($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }

    $valid_roles = [
        'attendee',
        'organiser',
        'admin'
    ];

    foreach ($allowed_roles as $role) {
        if (!in_array($role, $valid_roles, true)) {
            error_log(
                'EventNest auth.php received an invalid required role.'
            );

            auth_redirect('index.php');
        }
    }

    if (!in_array($user['Role'], $allowed_roles, true)) {
        auth_redirect('index.php');
    }

    return $user;
}


/*
|--------------------------------------------------------------------------
| REQUIRE ATTENDEE
|--------------------------------------------------------------------------
*/

function require_attendee(): array
{
    return require_role('attendee');
}


/*
|--------------------------------------------------------------------------
| REQUIRE ORGANISER
|--------------------------------------------------------------------------
| Use where an organiser may access their account/status page even if
| verification is still unverified, pending or rejected.
|--------------------------------------------------------------------------
*/

function require_organiser(): array
{
    return require_role('organiser');
}


/*
|--------------------------------------------------------------------------
| REQUIRE VERIFIED ORGANISER
|--------------------------------------------------------------------------
| Protected organiser features require:
|
| Role = organiser
| AND
| VerificationStatus = verified
|--------------------------------------------------------------------------
*/

function require_organiser_verified(): array
{
    $user = require_role('organiser');

    if ($user['VerificationStatus'] !== 'verified') {
        auth_redirect('organiser-verification.php');
    }

    return $user;
}


/*
|--------------------------------------------------------------------------
| REQUIRE ADMIN
|--------------------------------------------------------------------------
*/

function require_admin(): array
{
    return require_role('admin');
}


/*
|--------------------------------------------------------------------------
| ORGANISER FEE COVERAGE
|--------------------------------------------------------------------------
| Verification status and annual R99 fee coverage are separate controls.
|
| Fee expiry does not automatically:
| - change VerificationStatus;
| - remove published events;
| - remove existing bookings.
|
| Use this helper only where valid fee coverage is specifically required,
| such as deciding whether new bookings may be accepted.
|--------------------------------------------------------------------------
*/

function has_valid_organiser_fee_coverage(
    ?array $user = null
): bool {
    if ($user === null) {
        $user = get_current_auth_user();
    }

    if (
        $user === null ||
        empty($user['VerificationFeePaidUntil']) ||
        empty($user['VerificationFeePaymentStatus'])
    ) {
        return false;
    }

    if (
        $user['VerificationFeePaymentStatus'] !== 'paid'
    ) {
        return false;
    }

    try {
        $paid_until = new DateTimeImmutable(
            $user['VerificationFeePaidUntil']
        );

        $now = new DateTimeImmutable();

        return $now < $paid_until;

    } catch (Exception $exception) {
        error_log(
            'EventNest verification fee date error: ' .
            $exception->getMessage()
        );

        return false;
    }
}


/*
|--------------------------------------------------------------------------
| BACKWARD-COMPATIBILITY WRAPPERS
|--------------------------------------------------------------------------
| These wrappers allow older EventNest files using camelCase helper names
| to continue working while the project moves to snake_case.
|--------------------------------------------------------------------------
*/

function requireLogin(): array
{
    return require_login();
}

function requireRole($allowed_roles): array
{
    return require_role($allowed_roles);
}

function requireAttendee(): array
{
    return require_attendee();
}

function requireOrganiser(): array
{
    return require_organiser();
}

function requireOrganiserVerified(): array
{
    return require_organiser_verified();
}

function requireAdmin(): array
{
    return require_admin();
}