<?php
<<<<<<< HEAD
include "database-config/connection.php";

$message = "";
$message_type = "";
=======
>>>>>>> origin/main

require_once __DIR__ . '/config/db.php';

<<<<<<< HEAD
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Check that all fields are filled in
    if (empty($name) || empty($email) || empty($password)) {

        $message = "All fields are required.";
        $message_type = "error";

    // Check that the email is valid
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";
        $message_type = "error";

    // Check password length
    } elseif (strlen($password) < 8) {

        $message = "Password must be at least 8 characters.";
        $message_type = "error";

    } else {

        // Check if email already exists
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

                // Hash the password before storing it
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                // Insert the new user
                $insert_sql = "INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);

                if (!$insert_stmt) {

                    $message = "Database error. Please try again.";
                    $message_type = "error";

                } else {

                    $insert_stmt->bind_param("sss", $name, $email, $password_hash);

                    if ($insert_stmt->execute()) {

                        $message = "Registration successful! You can login.";
                        $message_type = "success";

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
=======
$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        "INSERT INTO users (name, email, password)
         VALUES (?, ?, ?)"
    );

    $stmt->execute([
        $name,
        $email,
        $hashedPassword
    ]);

    $message = "Registration successful!";
>>>>>>> origin/main
}

$conn->close();
?>

<<<<<<< HEAD
<?php include 'shared-components/header.php'; ?>

<div class="container">
=======
<h1>Create EventNest Account</h1>
>>>>>>> origin/main

    <h2>User Registration</h2>

<<<<<<< HEAD
    <?php if (!empty($message)): ?>

        <p style="color: <?= $message_type === 'success' ? 'green' : 'red'; ?>">
            <?= htmlspecialchars($message); ?>
        </p>

    <?php endif; ?>
=======
    <label>Name</label>
    <input type="text" name="name" required>

    <label>Email</label>
    <input type="email" name="email" required>

    <label>Password</label>
    <input type="password" name="password" required>
>>>>>>> origin/main

    <form method="POST" action="register.php">

        <input
            type="text"
            name="name"
            placeholder="Full Name"
            required
        >

<<<<<<< HEAD
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

    <p>
        Already have an account?
        <a href="login.php">Login here</a>
    </p>

</div>

<?php include 'shared-components/footer.php'; ?>
=======
<p><?= htmlspecialchars($message) ?></p>
>>>>>>> origin/main
