<?php
include("database-config/connection.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Booking logic will go here

}
?>

<?php include("shared-components/header.php"); ?>
<?php include("shared-components/navbar.php"); ?>

<h2>Book Event</h2>

<form method="POST">

    <input type="text" name="event_name" placeholder="Event Name" required>

    <input type="date" name="booking_date" required>

    <button type="submit">Book Now</button>

</form>

<?php include("shared-components/footer.php"); ?>
