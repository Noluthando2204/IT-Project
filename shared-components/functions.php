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
| SANITIZE INPUT
|--------------------------------------------------------------------------
| Cleans user input before displaying or processing it.
|
| Note: This function does not replace prepared statements.
| Prepared statements must still be used for all database queries.
|--------------------------------------------------------------------------
*/

function sanitizeInput($data)
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}


/*
|--------------------------------------------------------------------------
| FORMAT DATE
|--------------------------------------------------------------------------
| Formats a date for display.
|
| Example:
| 2026-09-16 → 16 Sep 2026
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
| GENERATE BOOKING REFERENCE
|--------------------------------------------------------------------------
| Generates a readable booking reference using random bytes.
|
| Example:
| BOOK-A3F91C7D
|--------------------------------------------------------------------------
*/

function generateBookingReference()
{
    return 'BOOK-' . strtoupper(bin2hex(random_bytes(4)));
}