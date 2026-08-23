<?php

/*
|--------------------------------------------------------------------------
| REUSABLE FUNCTIONS
|--------------------------------------------------------------------------
| Common helper functions used across the EventNest project.
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Sanitize Input
|--------------------------------------------------------------------------
| Used for safely displaying user-provided text in HTML.
| Database queries must still use prepared statements.
|--------------------------------------------------------------------------
*/

function sanitizeInput($data)
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}


/*
|--------------------------------------------------------------------------
| Format Date
|--------------------------------------------------------------------------
| Formats a date for display.
|--------------------------------------------------------------------------
*/

function formatDate($date)
{
    if (empty($date)) {
        return '';
    }

    return date('d M Y', strtotime($date));
}


/*
|--------------------------------------------------------------------------
| Generate Booking Reference
|--------------------------------------------------------------------------
| Generates a readable and sufficiently unique booking reference.
|--------------------------------------------------------------------------
*/

function generateBookingReference()
{
    return 'BOOK-' . strtoupper(bin2hex(random_bytes(4)));
}