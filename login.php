

<?php
session_start();

include 'database-config/connection.php';

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {

        $message = "Please enter both email and password.";
        $message_type = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";
        $message_type = "error";

    } else {

        // Retrieve the user using the actual database column names.
        $sql = "SELECT
                    UserID,
                    FirstName,
                    LastName,
                    Email,
                    PasswordHash,
                    UserStatus,
                    Is_email_verified,
                    Suspension_count,
                    Suspension_start_date
                FROM USERS
                WHERE Email = ?";

        $stmt = $conn->prepare($sql);
        $stmt->execute([$email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Use a generic error for unknown emails and incorrect passwords.
        if (
            !$user ||
            empty($user['PasswordHash']) ||
            !password_verify($password, $user['PasswordHash'])
        ) {

            $message = "Invalid email or password.";
            $message_type = "error";

        } elseif ((int) $user['Is_email_verified'] !== 1) {

            // Users must verify their email before logging in.
            $message = "Please verify your email address before logging in.";
            $message_type = "error";

        } elseif ($user['UserStatus'] === 'deleted') {

            // Deleted and permanently banned accounts cannot log in.
            $message = "This account is no longer available.";
            $message_type = "error";

        } else {

            $account_status = $user['UserStatus'];

            // Check whether a suspension has expired.
            if ($account_status === 'suspended') {

                if (empty($user['Suspension_start_date'])) {

                    // Do not allow login if suspension timing is unknown.
                    $message = "Your account is suspended. Please contact support.";
                    $message_type = "error";

                } else {

                    $suspension_start = strtotime(
                        $user['Suspension_start_date']
                    );

                    if ($suspension_start === false) {

                        $message = "Your account is suspended. Please contact support.";
                        $message_type = "error";

                    } else {

                        // A suspension lasts 30 days from its start date.
                        $suspension_end = strtotime(
                            '+30 days',
                            $suspension_start
                        );

                        if (time() < $suspension_end) {

                            $days_remaining = (int) ceil(
                                ($suspension_end - time()) / 86400
                            );

                            $message = "Your account is suspended. "
                                     . "Please try again after your "
                                     . "30-day suspension period. "
                                     . "Approximately "
                                     . $days_remaining
                                     . " day(s) remaining.";

                            $message_type = "error";

                        } else {

                            // Reactivate an expired suspension.
                            $update = $conn->prepare(
                                "UPDATE USERS
                                 SET UserStatus = 'active',
                                     Suspension_start_date = NULL
                                 WHERE UserID = ?
                                   AND UserStatus = 'suspended'"
                            );

                            $update->execute([$user['UserID']]);

                            if ($update->rowCount() !== 1) {

                                $message = "Unable to verify account status. Please try again.";
                                $message_type = "error";

                            } else {

                                $account_status = 'active';
                            }
                        }
                    }
                }

            } elseif ($account_status !== 'active') {

                // Deny login for any unexpected account status.
                $message = "This account cannot log in.";
                $message_type = "error";
            }

            // Create a session only when all checks have passed.
            if (
                $account_status === 'active' &&
                $message_type !== 'error'
            ) {

                $update = $conn->prepare(
                    "UPDATE USERS
                     SET LastLoginAt = CURRENT_TIMESTAMP
                     WHERE UserID = ?
                       AND UserStatus = 'active'
                       AND Is_email_verified = TRUE"
                );

                $update->execute([$user['UserID']]);

                if ($update->rowCount() !== 1) {

                    $message = "Unable to complete login. Please try again.";
                    $message_type = "error";

                } else {

                    // Prevent session fixation after authentication.
                    session_regenerate_id(true);

                    $full_name = trim(
                        ($user['FirstName'] ?? '') . ' '
                        . ($user['LastName'] ?? '')
                    );

                    $_SESSION['user_id'] = $user['UserID'];
                    $_SESSION['name'] = $full_name;
                    $_SESSION['email'] = $user['Email'];
                    $_SESSION['loggedin'] = true;

                    $message = "Login successful! Redirecting...";
                    $message_type = "success";

                    header("Refresh: 2; URL=index.php");
                }
            }
        }
    }
}
?>

<?php include 'shared-components/header.php'; ?>

<div class="container">

    <h2>User Login</h2>

    <?php if (!empty($message)): ?>

        <p style="color: <?= $message_type === 'success' ? 'green' : 'red'; ?>">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
        </p>

    <?php endif; ?>

    <form method="POST" action="login.php">

        <input
            type="email"
            name="email"
            placeholder="Email Address"
            autocomplete="email"
            value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
            required
        >

        <br><br>

        <input
            type="password"
            name="password"
            placeholder="Password"
            autocomplete="current-password"
            required
        >

        <br><br>

        <button type="submit">Login</button>

    </form>

    <p>
        Don't have an account?
        <a href="register.php">Register here</a>
    </p>

</div>

<?php include 'shared-components/footer.php'; ?>
