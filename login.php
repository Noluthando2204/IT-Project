<?php
session_start();

include("database-config/connection.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Login validation logic will go here

}
?>

<?php include("shared-components/header.php"); ?>
<?php include("shared-components/navbar.php"); ?>

<h2>User Login</h2>

<form method="POST">

    <input type="email" name="email" placeholder="Email Address" required>

    <input type="password" name="password" placeholder="Password" required>

    <button type="submit">Login</button>

</form>

<?php include("shared-components/footer.php"); ?>
