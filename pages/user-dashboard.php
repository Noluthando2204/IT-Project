<?php
/*
|--------------------------------------------------------------------------
| EventNest - User Dashboard
|--------------------------------------------------------------------------
| Temporary frontend version.
| Database integration will be added once the EventNest database is created.
|--------------------------------------------------------------------------
*/

require_once '../shared-components/auth.php';

/*
|--------------------------------------------------------------------------
| Temporary user information
|--------------------------------------------------------------------------
| This allows us to preview the dashboard before the database is connected.
|--------------------------------------------------------------------------
*/

$user = [
    'first_name' => 'Thando',
    'last_name'  => 'User',
    'email'      => 'user@example.com',
    'created_at' => '2026-08-01'
];

/*
|--------------------------------------------------------------------------
| Temporary booking data
|--------------------------------------------------------------------------
| These are placeholder records for UI development only.
|--------------------------------------------------------------------------
*/

$upcoming_bookings = [
    [
        'event_id'       => 1,
        'event_name'     => 'Tech & Innovation Networking',
        'event_date'     => '2026-08-20',
        'event_time'     => '10:00 AM',
        'venue'          => 'Richfield Bryanston',
        'booking_status' => 'confirmed'
    ],
    [
        'event_id'       => 2,
        'event_name'     => 'Career Development Expo',
        'event_date'     => '2026-09-05',
        'event_time'     => '09:00 AM',
        'venue'          => 'Sandton Convention Centre',
        'booking_status' => 'pending'
    ]
];

/*
|--------------------------------------------------------------------------
| Temporary liked events
|--------------------------------------------------------------------------
*/

$liked_events_available = true;

$liked_events = [
    [
        'event_id'   => 3,
        'event_name' => 'Digital Skills Workshop',
        'event_date' => '2026-09-12',
        'venue'      => 'Johannesburg'
    ]
];

/*
|--------------------------------------------------------------------------
| Temporary date formatting function
|--------------------------------------------------------------------------
*/

function formatDate($date)
{
    return date('d M Y', strtotime($date));
}

?>

<?php
/*
|--------------------------------------------------------------------------
| EventNest - User Dashboard
|--------------------------------------------------------------------------
| Temporary frontend version.
| Database integration will be added once the EventNest database is created.
|--------------------------------------------------------------------------
*/

require_once '../shared-components/auth.php';

/*
|--------------------------------------------------------------------------
| Temporary user information
|--------------------------------------------------------------------------
| This allows us to preview the dashboard before the database is connected.
|--------------------------------------------------------------------------
*/

$user = [
    'first_name' => 'Thando',
    'last_name'  => 'User',
    'email'      => 'user@example.com',
    'created_at' => '2026-08-01'
];

/*
|--------------------------------------------------------------------------
| Temporary booking data
|--------------------------------------------------------------------------
| These are placeholder records for UI development only.
|--------------------------------------------------------------------------
*/

$upcoming_bookings = [
    [
        'event_id'       => 1,
        'event_name'     => 'Tech & Innovation Networking',
        'event_date'     => '2026-08-20',
        'event_time'     => '10:00 AM',
        'venue'          => 'Richfield Bryanston',
        'booking_status' => 'confirmed'
    ],
    [
        'event_id'       => 2,
        'event_name'     => 'Career Development Expo',
        'event_date'     => '2026-09-05',
        'event_time'     => '09:00 AM',
        'venue'          => 'Sandton Convention Centre',
        'booking_status' => 'pending'
    ]
];

/*
|--------------------------------------------------------------------------
| Temporary date formatting function
|--------------------------------------------------------------------------
*/

function formatDate($date)
{
    return date('d M Y', strtotime($date));
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>EventNest - User Dashboard</title>

    <link rel="stylesheet" href="../styles/main.css">
    <link rel="stylesheet" href="../styles/responsive.css">

</head>

<body>
    <h1>User Dashboard</h1>
</body>
</html>
