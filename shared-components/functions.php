<?php

 //REUSABLE FUNCTIONS

//Common helper functions used across the EventNest project.



 // Clean user input before displaying or processing it.

function sanitizeInput($data)
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}


 //Format a date for display.
 
function formatDate($date)
{
    if (empty($date)) {
        return '';
    }

    return date('d M Y', strtotime($date));
}


 //Generate a unique booking reference.
 
function generateBookingReference()
{
    return 'BOOK-' . strtoupper(bin2hex(random_bytes(4)));
}

?>