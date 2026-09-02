<?php
include "database-config/connection.php";

// Variable to show message to user
$message = "";
$message_type = ""; // 'success' or 'error'

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get data from the form
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Basic validation
    if (empty($name) || empty($email) || empty($password)) {

        $message = "All fields are required.";
        $message_type = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";
        $message_type = "error";

    } elseif (strlen($password) < 8) {

        $message = "Password must be at least 8 characters.";
        $message_type = "error";

    } else {

        // Check if email already exists in USERS table
        $check_sql = "SELECT user_id FROM users WHERE email = ?";
        $check_stmt = $conn->prepare($check_sql);

        if (!$check_stmt) {

            $message = "Database error. Please try again.";
            $message_type = "error";

        } else {

            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $check_stmt->store_result();

            if ($check_stmt->num_rows > 0) {

                $message = "This email is already registered. Please login.";
                $message_type = "error";

            } else {

                // Hash the password for security
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                // Insert new user
                $insert_sql = "INSERT INTO users (name, email, password_hash)
                               VALUES (?, ?, ?)";

                $insert_stmt = $conn->prepare($insert_sql);

                if (!$insert_stmt) {

                    $message = "Database error. Please try again.";
                    $message_type = "error";

                } else {

                    $insert_stmt->bind_param(
                        "sss",
                        $name,
                        $email,
                        $password_hash
                    );

                    if ($insert_stmt->execute()) {

                        $message = "Registration successful! You can now login.";
                        $message_type = "success";

                        // Redirect to login page after 2 seconds
                        header("refresh:2; url=login.php");

                    } else {

                        $message = "Something went wrong. Please try again.";
                        $message_type = "error";
                    }

                    $insert_stmt->close();
                }
            }

            $check_stmt->close();
        }
    }
}
?>

<?php include "shared-components/header.php"; ?>
<?php include "shared-components/navbar.php"; ?>

<div class="container">

    <h2>User Registration</h2>

    <!-- Show success or error message -->
    <?php if (!empty($message)) { ?>

        <p style="color: <?php echo ($message_type == 'success' ? 'green' : 'red'); ?>">
            <?php echo htmlspecialchars($message); ?>
        </p>

    <?php } ?>

    <form method="POST" action="register.php">

        <input
            type="text"
            name="name"
            placeholder="Full Name"
            required
        >
        <br><br>

        <input
            type="email"
            name="email"
            placeholder="Email Address"
            required
        >
        <br><br>

        <input
            type="password"
            name="password"
            placeholder="Password"
            required
        >
        <br><br>

        <button type="submit">Register</button>

    </form>

</div>

<?php include "shared-components/footer.php"; ?>
