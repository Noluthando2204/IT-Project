<?php
include("database-config/connection.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Registration logic will go here

}
?>

<?php include("shared-components/header.php"); ?>
<?php include("shared-components/navbar.php"); ?>

<h2>User Registration</h2>

<form method="POST">

    <input type="text" name="name" placeholder="Full Name" required>

    <input type="email" name="email" placeholder="Email Address" required>

    <input type="password" name="password" placeholder="Password" required>

    <button type="submit">Register</button>

</form>

<?php include("shared-components/footer.php"); ?>
