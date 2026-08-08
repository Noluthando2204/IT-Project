<?php

 // AUTHENTICATION AND AUTHORISATION


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


  //Require the user to be logged in.
 
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


  //Require the logged-in user to have a specific role.
 
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


  //Require the logged-in user to be a verified organiser.

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
