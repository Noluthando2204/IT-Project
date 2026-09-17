<?php

/*
|--------------------------------------------------------------------------
| AUTHENTICATION AND AUTHORISATION
|--------------------------------------------------------------------------
| This file contains reusable functions for:
| - Checking whether a user is logged in
| - Checking whether a user has a specific role
| - Checking whether an organiser has been verified
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| START SESSION
|--------------------------------------------------------------------------
| Start the session only if a session has not already been started.
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| REQUIRE LOGIN
|--------------------------------------------------------------------------
| Requires the user to be logged in.
| If the user is not logged in, they are redirected to the login page.
|--------------------------------------------------------------------------
*/

function requireLogin()
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: /login.php");
        exit();
    }


    /*
    |--------------------------------------------------------------------------
    | CHECK ACCOUNT STATUS
    |--------------------------------------------------------------------------
    | Suspended or banned users are not allowed to continue using
    | protected parts of the system.
    |--------------------------------------------------------------------------
    */

    if (
        isset($_SESSION['user_status']) &&
        (
            $_SESSION['user_status'] === 'suspended' ||
            $_SESSION['user_status'] === 'banned'
        )
    ) {
        $_SESSION = [];
        session_destroy();

        header("Location: /login.php");
        exit();
    }
}


/*
|--------------------------------------------------------------------------
| REQUIRE ROLE
|--------------------------------------------------------------------------
| Requires the logged-in user to have one of the specified roles.
|
| Example:
| requireRole('admin');
|
| Or:
| requireRole(['admin', 'organiser']);
|--------------------------------------------------------------------------
*/

function requireRole($allowedRoles)
{
    requireLogin();


    /*
    |--------------------------------------------------------------------------
    | CHECK WHETHER ROLE EXISTS
    |--------------------------------------------------------------------------
    */

    if (!isset($_SESSION['role'])) {
        header("Location: /index.php");
        exit();
    }


    /*
    |--------------------------------------------------------------------------
    | ALLOW SINGLE ROLE OR MULTIPLE ROLES
    |--------------------------------------------------------------------------
    | Convert a single role into an array so that both single and
    | multiple allowed roles can be handled consistently.
    |--------------------------------------------------------------------------
    */

    if (!is_array($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }


    /*
    |--------------------------------------------------------------------------
    | CHECK USER ROLE
    |--------------------------------------------------------------------------
    */

    if (!in_array($_SESSION['role'], $allowedRoles, true)) {
        header("Location: /index.php");
        exit();
    }
}


/*
|--------------------------------------------------------------------------
| REQUIRE VERIFIED ORGANISER
|--------------------------------------------------------------------------
| Requires the user to:
| 1. Be logged in
| 2. Have the organiser role
| 3. Have an approved verification status
|--------------------------------------------------------------------------
*/

function requireOrganiserVerified()
{
    requireRole('organiser');


    /*
    |--------------------------------------------------------------------------
    | CHECK ORGANISER VERIFICATION STATUS
    |--------------------------------------------------------------------------
    */

    if (
        !isset($_SESSION['verification_status']) ||
        $_SESSION['verification_status'] !== 'approved'
    ) {
        header("Location: /pages/organiser-verification.php");
        exit();
    }
}