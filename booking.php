<?php
include("database-config/connection.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get submitted booking data
    $ticket_tier = $_POST["ticket_tier"] ?? "";
    $age_confirm = $_POST["age_confirm"] ?? "";

    // Validate ticket tier
    if ($ticket_tier !== "general") {
        exit("Invalid ticket tier.");
    }

    // Validate age confirmation
    if (!isset($_POST["age_confirm"])) {
        exit("You must confirm your age before booking.");
    }

    /*
    Creates a booking reference and will be stored inside a variable
    It will be saved in the database after the ERD is completed.
    */
    $booking_reference = "EN-" . strtoupper(bin2hex(random_bytes(5)));

    /* 
    Database booking logic will be added
    once the ERD confirms the exact booking fields
    and booking status field/value.
    */
}

?>

<?php include("shared-components/header.php"); ?>
<?php include("shared-components/navbar.php"); ?>

<h2>Book Event</h2>

<form method="POST">

    <input type="text" name="event_name" placeholder="Event Name" required>

    <input type="date" name="booking_date" required>

    <input type="hidden" name="ticket_tier" value="general">

    <label>
        <input type="checkbox" name="age_confirm" value="1" required>
        I confirm that I meet the required age for this event.
    </label>

    <button type="submit">Book Now</button>

</form>

<?php include("shared-components/footer.php"); ?>
