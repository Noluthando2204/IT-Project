<?php
/*
|--------------------------------------------------------------------------
| REUSABLE FUNCTIONS
|--------------------------------------------------------------------------
| Common helper functions used across the project.
|--------------------------------------------------------------------------
*/

function sanitizeInput($data)
{
    return htmlspecialchars(trim($data));
}

function generateBookingReference()
{
    return "BOOK-" . rand(1000, 9999);
}
?>
