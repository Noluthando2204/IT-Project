<?php
session_start();

include 'database-config/connection.php';

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {

        $message = "Please enter both email and password.";
        $message_type = "error";

    } else {

        $sql = "SELECT user_id, name, email, password_hash
                FROM users
                WHERE email = ?";

        $stmt = $conn->prepare($sql);
        $stmt->execute([$email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {

            if (password_verify($password, $user['password_hash'])) {

                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['loggedin'] = true;

                $message = "Login successful! Redirecting...";
                $message_type = "success";

                header("Refresh: 2; URL=index.php");

            } else {

                $message = "Invalid email or password.";
                $message_type = "error";
            }

        } else {

            $message = "Invalid email or password.";
            $message_type = "error";
        }
    }
}
?>

<?php include 'shared-components/header.php'; ?>

<div class="container">

    <h2>User Login</h2>

    <?php if (!empty($message)): ?>

        <p style="color: <?= $message_type === 'success' ? 'green' : 'red'; ?>">
            <?= htmlspecialchars($message); ?>
        </p>

    <?php endif; ?>

    <form method="POST" action="login.php">

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

        <button type="submit">Login</button>

    </form>

    <p>
        Don't have an account?
        <a href="register.php">Register here</a>
    </p>

</div>

<?php include 'shared-components/footer.php'; ?>
