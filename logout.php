

<?php
// Start the session so it can be cleared.
session_start();

// Clear all session variables.
$_SESSION = [];

// Remove the session cookie from the browser.
if (ini_get('session.use_cookies')) {

    $cookie_params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $cookie_params['path'],
        $cookie_params['domain'],
        $cookie_params['secure'],
        $cookie_params['httponly']
    );
}

// Destroy the session on the server.
session_destroy();

// Prevent the browser from displaying a cached authenticated page.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Redirect the user to the login page.
header('Location: login.php');
exit;
