<?php
/*
|--------------------------------------------------------------------------
| AUTHENTICATION CHECK
|--------------------------------------------------------------------------
| This file will later check whether a user is logged in
| and whether they have permission to access a page.
|--------------------------------------------------------------------------
*/

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}
?>
