<?php
/*
|--------------------------------------------------------------------------
| EventNest - Business Dashboard
|--------------------------------------------------------------------------
| Business/organiser dashboard frontend prototype.
|
| Authentication:
| The shared auth.php file ensures that only logged-in users
| can access this dashboard.
|
| The data below is temporary prototype data.
| It can later be replaced with database queries.
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| AUTHENTICATION CHECK
|--------------------------------------------------------------------------
|
| business-dashboard.php is in the project root, while auth.php is
| inside shared-components.
|
*/

require_once __DIR__ . '/shared-components/auth.php';


/*
|--------------------------------------------------------------------------
| TEMPORARY BUSINESS DATA
|--------------------------------------------------------------------------
|
| This information is currently hard-coded because the EventNest
| database has not been created yet.
|
*/

$business = [
    'name' => 'TechConnect SA',
    'email' => 'techconnect@example.com',
    'initial' => 'T',
    'verification_status' => 'Verified'
];


/*
|--------------------------------------------------------------------------
| TEMPORARY DASHBOARD STATISTICS
|--------------------------------------------------------------------------
*/

$dashboard_stats = [
    'total_events' => 25,
    'upcoming_events' => 4,
    'total_bookings' => 320,
    'pending_actions' => 6
];


/*
|--------------------------------------------------------------------------
| TEMPORARY UPCOMING EVENTS
|--------------------------------------------------------------------------
*/

$upcoming_events = [

    [
        'name' => 'AI & Data Conference',
        'date' => '20 May 2026',
        'venue' => 'Johannesburg',
        'bookings' => 84,
        'status' => 'Published'
    ],

    [
        'name' => 'Web Development Bootcamp',
        'date' => '05 June 2026',
        'venue' => 'Cape Town',
        'bookings' => 62,
        'status' => 'Published'
    ],

    [
        'name' => 'Cybersecurity Summit',
        'date' => '12 July 2026',
        'venue' => 'Durban',
        'bookings' => 95,
        'status' => 'Pending'
    ],

    [
        'name' => 'Cloud Computing Day',
        'date' => '18 August 2026',
        'venue' => 'Pretoria',
        'bookings' => 79,
        'status' => 'Published'
    ]

];


/*
|--------------------------------------------------------------------------
| TEMPORARY RECENT BOOKINGS
|--------------------------------------------------------------------------
*/

$recent_bookings = [

    [
        'attendee' => 'Lerato D.',
        'event' => 'AI & Data Conference',
        'date' => '20 May',
        'tickets' => 1,
        'status' => 'Confirmed'
    ],

    [
        'attendee' => 'Sipho K.',
        'event' => 'Web Development Bootcamp',
        'date' => '05 June',
        'tickets' => 2,
        'status' => 'Confirmed'
    ],

    [
        'attendee' => 'Thandi P.',
        'event' => 'Cybersecurity Summit',
        'date' => '12 July',
        'tickets' => 1,
        'status' => 'Pending'
    ],

    [
        'attendee' => 'Marco R.',
        'event' => 'Cloud Computing Day',
        'date' => '18 August',
        'tickets' => 1,
        'status' => 'Confirmed'
    ]

];

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        EventNest - Business Dashboard
    </title>

    <!-- Business Dashboard CSS -->
    <link
        rel="stylesheet"
        href="styles/business-dashboard.css"
    >

</head>


<body>


<!-- =========================================================
     EVENTNEST HEADER
========================================================= -->

<header class="dashboard-header">


    <!-- EventNest Brand -->

    <div class="brand">

        <img
            src="images/eventnest-logo.png"
            alt="EventNest"
            class="brand-logo"
        >

        <span class="brand-name">
            EventNest
        </span>

    </div>


    <!-- Header Right -->

    <div class="header-right">


        <!-- Notifications -->

        <button
            class="notification-btn"
            type="button"
            aria-label="Notifications"
        >
            ♧

            <span class="notification-dot"></span>

        </button>


        <!-- Business Account -->

        <div class="business-user">

            <div class="business-avatar">

                <?php
                echo htmlspecialchars($business['initial']);
                ?>

            </div>

            <span class="business-name">

                <?php
                echo htmlspecialchars($business['name']);
                ?>

            </span>

            <span class="dropdown-arrow">
                ˅
            </span>

        </div>

    </div>

</header>



<!-- =========================================================
     DASHBOARD SHELL
========================================================= -->

<div class="dashboard-shell">


    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

    <aside class="dashboard-sidebar">


        <nav class="sidebar-nav">


            <a
                href="business-dashboard.php"
                class="sidebar-link active"
            >

                <span class="sidebar-icon">
                    ▣
                </span>

                Dashboard

            </a>


            <a
                href="#"
                class="sidebar-link"
            >

                <span class="sidebar-icon">
                    ▦
                </span>

                My Events

            </a>


            <a
                href="#"
                class="sidebar-link"
            >

                <span class="sidebar-icon">
                    ＋
                </span>

                Create Event

            </a>


            <a
                href="#"
                class="sidebar-link"
            >

                <span class="sidebar-icon">
                    ▤
                </span>

                Bookings

            </a>


            <a
                href="#"
                class="sidebar-link"
            >

                <span class="sidebar-icon">
                    ♥
                </span>

                Reviews

            </a>


            <a
                href="#"
                class="sidebar-link"
            >

                <span class="sidebar-icon">
                    ◎
                </span>

                Business Profile

            </a>


            <a
                href="#"
                class="sidebar-link"
            >

                <span class="sidebar-icon">
                    ⚙
                </span>

                Settings

            </a>


            <a
                href="logout.php"
                class="sidebar-link sidebar-link-logout"
            >

                <span class="sidebar-icon">
                    ↪
                </span>

                Logout

            </a>


        </nav>

    </aside>



    <!-- =====================================================
         MAIN DASHBOARD
    ====================================================== -->

    <main class="dashboard-main">


        <!-- PAGE HEADING -->

        <div class="page-heading">

            <div>

                <h1>
                    Dashboard Overview
                </h1>

                <p>
                    Manage your events, bookings and business activity.
                </p>

            </div>

        </div>



        <!-- =================================================
             WELCOME CARD
        ================================================== -->

        <section class="welcome-card">


            <div class="welcome-content">

                <h2>

                    Welcome back,
                    <?php
                    echo htmlspecialchars($business['name']);
                    ?>

                </h2>

                <p>
                    Here's an overview of what's happening with your events.
                </p>

            </div>


            <a
                href="#"
                class="primary-button"
            >
                + Create Event
            </a>


        </section>



        <!-- =================================================
             STATISTICS
        ================================================== -->

        <section class="stats-grid">


            <!-- Total Events -->

            <div class="stat-card">

                <div class="stat-icon">
                    ◇
                </div>

                <div class="stat-content">

                    <span class="stat-label">
                        Total Events
                    </span>

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $dashboard_stats['total_events']
                        );
                        ?>
                    </strong>

                </div>

            </div>



            <!-- Upcoming Events -->

            <div class="stat-card">

                <div class="stat-icon">
                    ◷
                </div>

                <div class="stat-content">

                    <span class="stat-label">
                        Upcoming Events
                    </span>

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $dashboard_stats['upcoming_events']
                        );
                        ?>
                    </strong>

                </div>

            </div>



            <!-- Total Bookings -->

            <div class="stat-card">

                <div class="stat-icon">
                    ♟
                </div>

                <div class="stat-content">

                    <span class="stat-label">
                        Total Bookings
                    </span>

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $dashboard_stats['total_bookings']
                        );
                        ?>
                    </strong>

                </div>

            </div>



            <!-- Pending Actions -->

            <div class="stat-card">

                <div class="stat-icon">
                    !
                </div>

                <div class="stat-content">

                    <span class="stat-label">
                        Pending Actions
                    </span>

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $dashboard_stats['pending_actions']
                        );
                        ?>
                    </strong>

                </div>

            </div>


        </section>



        <!-- =================================================
             MY / UPCOMING EVENTS
        ================================================== -->

        <section class="dashboard-panel events-panel">


            <div class="panel-header">

                <h2>
                    My / Upcoming Events
                </h2>

                <a
                    href="#"
                    class="view-all"
                >
                    View all →
                </a>

            </div>



            <div class="event-list">


                <?php foreach ($upcoming_events as $event): ?>

                    <div class="event-row">


                        <!-- Event -->

                        <div class="event-info event-name">

                            <span class="mobile-label">
                                Event
                            </span>

                            <strong>

                                <?php
                                echo htmlspecialchars(
                                    $event['name']
                                );
                                ?>

                            </strong>

                        </div>



                        <!-- Date -->

                        <div class="event-info">

                            <span class="mobile-label">
                                Date
                            </span>

                            <strong>

                                <?php
                                echo htmlspecialchars(
                                    $event['date']
                                );
                                ?>

                            </strong>

                        </div>



                        <!-- Venue -->

                        <div class="event-info">

                            <span class="mobile-label">
                                Venue
                            </span>

                            <strong>

                                <?php
                                echo htmlspecialchars(
                                    $event['venue']
                                );
                                ?>

                            </strong>

                        </div>



                        <!-- Bookings -->

                        <div class="event-info">

                            <span class="mobile-label">
                                Bookings
                            </span>

                            <strong>

                                <?php
                                echo htmlspecialchars(
                                    $event['bookings']
                                );
                                ?>

                            </strong>

                        </div>



                        <!-- Status -->

                        <div class="event-info">

                            <span class="mobile-label">
                                Status
                            </span>


                            <?php

                            $status_class =
                                strtolower(
                                    $event['status']
                                );

                            ?>

                            <span
                                class="status-badge
                                status-<?php
                                echo htmlspecialchars(
                                    $status_class
                                );
                                ?>"
                            >

                                <?php
                                echo htmlspecialchars(
                                    $event['status']
                                );
                                ?>

                            </span>

                        </div>



                        <!-- Action -->

                        <div class="event-action">

                            <a
                                href="#"
                                class="outline-button"
                            >
                                View / Edit
                            </a>

                        </div>


                    </div>

                <?php endforeach; ?>


            </div>

        </section>



        <!-- =================================================
             LOWER DASHBOARD
        ================================================== -->

        <div class="dashboard-columns">


            <!-- =================================================
                 RECENT BOOKINGS
            ================================================== -->

            <section class="dashboard-panel">


                <div class="panel-header">

                    <h2>
                        Recent Bookings
                    </h2>

                    <a
                        href="#"
                        class="view-all"
                    >
                        View all →
                    </a>

                </div>



                <div class="booking-list">


                    <?php foreach ($recent_bookings as $booking): ?>

                        <div class="booking-row">


                            <div>

                                <strong>

                                    <?php
                                    echo htmlspecialchars(
                                        $booking['attendee']
                                    );
                                    ?>

                                </strong>

                                <span>

                                    <?php
                                    echo htmlspecialchars(
                                        $booking['event']
                                    );
                                    ?>

                                </span>

                            </div>



                            <div>

                                <strong>

                                    <?php
                                    echo htmlspecialchars(
                                        $booking['date']
                                    );
                                    ?>

                                </strong>

                                <span>

                                    <?php
                                    echo htmlspecialchars(
                                        $booking['tickets']
                                    );
                                    ?>

                                    ticket(s)

                                </span>

                            </div>



                            <?php

                            $booking_status_class =
                                strtolower(
                                    $booking['status']
                                );

                            ?>

                            <span
                                class="status-badge
                                status-<?php
                                echo htmlspecialchars(
                                    $booking_status_class
                                );
                                ?>"
                            >

                                <?php
                                echo htmlspecialchars(
                                    $booking['status']
                                );
                                ?>

                            </span>


                        </div>

                    <?php endforeach; ?>


                </div>

            </section>



            <!-- =================================================
                 QUICK ACTIONS
            ================================================== -->

            <section class="dashboard-panel">


                <div class="panel-header">

                    <h2>
                        Quick Actions
                    </h2>

                </div>



                <div class="quick-actions">


                    <a
                        href="#"
                        class="quick-action"
                    >

                        <span>
                            ＋
                        </span>

                        Create Event

                        <strong>
                            →
                        </strong>

                    </a>



                    <a
                        href="#"
                        class="quick-action"
                    >

                        <span>
                            ▦
                        </span>

                        Manage Events

                        <strong>
                            →
                        </strong>

                    </a>



                    <a
                        href="#"
                        class="quick-action"
                    >

                        <span>
                            ▤
                        </span>

                        View Bookings

                        <strong>
                            →
                        </strong>

                    </a>



                    <a
                        href="#"
                        class="quick-action"
                    >

                        <span>
                            ◎
                        </span>

                        Edit Business Profile

                        <strong>
                            →
                        </strong>

                    </a>


                </div>

            </section>


        </div>



        <!-- =================================================
             BUSINESS PROFILE
        ================================================== -->

        <section class="dashboard-panel business-profile-panel">


            <div class="panel-header">

                <h2>
                    Business Profile
                </h2>

                <a
                    href="#"
                    class="view-all"
                >
                    Edit Profile →
                </a>

            </div>



            <div class="business-profile-content">


                <div class="profile-avatar-large">

                    <?php
                    echo htmlspecialchars(
                        $business['initial']
                    );
                    ?>

                </div>



                <div class="business-profile-details">

                    <h3>

                        <?php
                        echo htmlspecialchars(
                            $business['name']
                        );
                        ?>

                    </h3>

                    <p>

                        <?php
                        echo htmlspecialchars(
                            $business['email']
                        );
                        ?>

                    </p>

                </div>



                <div class="verification">

                    <span class="verification-label">
                        Verification Status
                    </span>


                    <span class="status-badge status-verified">

                        ✓

                        <?php
                        echo htmlspecialchars(
                            $business['verification_status']
                        );
                        ?>

                    </span>

                </div>



                <a
                    href="#"
                    class="outline-button"
                >
                    Edit Profile
                </a>


            </div>

        </section>


    </main>

</div>


</body>

</html>