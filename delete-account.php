
<?php

session_start();

require_once __DIR__ . '/database-config/connection.php';

/*
 * Make sure the user is logged in.
 */
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

/*
 * Account deletion must only happen through POST.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

try {

    /*
     * Start a database transaction.
     */
    $conn->beginTransaction();

    /*
     * Check that the logged-in user exists.
     */
    $checkUser = $conn->prepare(
        "SELECT user_id
         FROM users
         WHERE user_id = ?
         LIMIT 1"
    );

    $checkUser->execute([$userId]);

    if (!$checkUser->fetch(PDO::FETCH_ASSOC)) {

        $conn->rollBack();

        session_unset();
        session_destroy();

        header('Location: login.php');
        exit;
    }

    /*
     * Delete the user's account.
     *
     * Related records are removed automatically where
     * ON DELETE CASCADE has been defined in the schema.
     */
    $deleteUser = $conn->prepare(
        "DELETE FROM users
         WHERE user_id = ?"
    );

    $deleteUser->execute([$userId]);

    /*
     * Make sure the account was actually deleted.
     */
    if ($deleteUser->rowCount() !== 1) {
        throw new Exception('Account deletion failed.');
    }

    /*
     * Complete the transaction.
     */
    $conn->commit();

    /*
     * Clear the user's session.
     */
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();

    /*
     * Return the user to the login page.
     */
    header('Location: login.php?deleted=1');
    exit;

} catch (Throwable $e) {

    /*
     * Undo the transaction if something went wrong.
     */
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    http_response_code(500);

    echo "Unable to delete your account. Please try again later.";
    exit;
}
?>