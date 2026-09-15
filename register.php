<?php

require_once __DIR__ . '/config/db.php';

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
}
?>

<h1>Create EventNest Account</h1>

<form method="POST">

    <label>Name</label>
    <input type="text" name="name" required>

    <label>Email</label>
    <input type="email" name="email" required>

    <label>Password</label>
    <input type="password" name="password" required>

    <button type="submit">Register</button>

</form>

<p><?= htmlspecialchars($message) ?></p>
