<?php

/*
|--------------------------------------------------------------------------
| AUTHENTICATION AND AUTHORISATION
|--------------------------------------------------------------------------
| Reusable functions for checking whether users are logged in,
| authorised, verified, suspended, or banned.
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| Require Login
|--------------------------------------------------------------------------
*/

function requireLogin()
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: /login.php");
        exit();
    }

    if (isset($_SESSION['user_status'])) {
        if (
            $_SESSION['user_status'] === 'suspended' ||
            $_SESSION['user_status'] === 'banned'
        ) {
            session_destroy();
            header("Location: /login.php");
            exit();
        }
    }
}


/*
|--------------------------------------------------------------------------
| Require Role
|--------------------------------------------------------------------------
*/

function requireRole($allowedRoles)
{
    requireLogin();

    if (!isset($_SESSION['role'])) {
        header("Location: /index.php");
        exit();
    }

    if (!is_array($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }

    if (!in_array($_SESSION['role'], $allowedRoles, true)) {
        header("Location: /index.php");
        exit();
    }
}


/*
|--------------------------------------------------------------------------
| Require Verified Organiser
|--------------------------------------------------------------------------
*/

function requireOrganiserVerified()
{
    requireRole('organiser');

    if (
        !isset($_SESSION['verification_status']) ||
        $_SESSION['verification_status'] !== 'approved'
    ) {
        header("Location: /pages/organiser-verification.php");
        exit();
    }
}

?>