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

    <!-- EventNest Header -->
    <header class="site-header">
        <a href="../index.php" class="brand">
            <img src="../images/eventnest-logo.png" alt="EventNest" class="brand-logo">
        </a>

        <div class="site-header-actions">
            <button class="icon-btn" aria-label="Notifications" type="button">🔔</button>
            <span class="topbar-greeting">
                Hi, <?php echo htmlspecialchars($user['first_name']); ?>
            </span>
        </div>
    </header>

    <div class="dashboard-shell">

        <!-- ==============================================================
             SIDEBAR
        ============================================================== -->

        <aside class="dashboard-sidebar">

            <nav class="sidebar-nav">

                <a href="user-dashboard.php"
                   class="sidebar-link active">
                    Dashboard
                </a>

                <a href="my-bookings.php"
                   class="sidebar-link">
                    My Bookings
                </a>

                <?php if ($liked_events_available): ?>

                    <a href="#liked-events" class="sidebar-link">
                        <span class="sidebar-icon heart-icon" aria-hidden="true">♥</span>
                        Liked Events
                    </a>

                <?php endif; ?>

                <a href="#profile"
                   class="sidebar-link">
                    Profile
                </a>

                <a href="#settings"
                   class="sidebar-link">
                    Settings
                </a>

                <a href="../logout.php"
                   class="sidebar-link sidebar-link-logout">
                    Logout
                </a>

            </nav>

        </aside>


        <!-- ==============================================================
             MAIN DASHBOARD
        ============================================================== -->

        <main class="dashboard-main">


            <!-- ==========================================================
                 TOP BAR
            =========================================================== -->

            <header class="dashboard-page-title">
                <div>
                    <span class="eyebrow">EventNest</span>
                    <h1>Dashboard</h1>
                </div>
            </header>


            <!-- ==========================================================
                 WELCOME SECTION
            =========================================================== -->

            <section class="dashboard-welcome">

                <h1>

                    Welcome back,
                    <?php
                    echo htmlspecialchars(
                        $user['first_name']
                    );
                    ?>

                </h1>

                <p>
                    This is your personal EventNest dashboard.
                    Track your bookings and manage your account here.
                </p>

            </section>


            <!-- ==========================================================
                 BOOKINGS + LIKED EVENTS
            =========================================================== -->

            <div class="dashboard-columns">


                <!-- ======================================================
                     MY BOOKINGS
                ======================================================= -->

                <section class="dashboard-panel">

                    <div class="panel-header">

                        <h2>
                            My Bookings
                        </h2>

                        <a
                            href="my-bookings.php"
                            class="panel-link">

                            View all

                        </a>

                    </div>


                    <?php if (empty($upcoming_bookings)): ?>

                        <p class="empty-state">

                            No upcoming bookings yet.
                            When you book an event,
                            it will show up here.

                        </p>

                    <?php else: ?>

                        <div class="booking-list">

                            <?php foreach ($upcoming_bookings as $booking): ?>

                                <div class="booking-card">

                                    <div class="booking-card-info">

                                        <h3>

                                            <?php
                                            echo htmlspecialchars(
                                                $booking['event_name']
                                            );
                                            ?>

                                        </h3>

                                        <p>

                                            <?php
                                            echo htmlspecialchars(
                                                formatDate(
                                                    $booking['event_date']
                                                )
                                            );
                                            ?>

                                            &middot;

                                            <?php
                                            echo htmlspecialchars(
                                                $booking['event_time']
                                            );
                                            ?>

                                            &middot;

                                            <?php
                                            echo htmlspecialchars(
                                                $booking['venue']
                                            );
                                            ?>

                                        </p>


                                        <span
                                            class="status-badge status-<?php
                                                echo htmlspecialchars(
                                                    strtolower(
                                                        $booking['booking_status']
                                                    )
                                                );
                                            ?>">

                                            <?php
                                            echo htmlspecialchars(
                                                ucfirst(
                                                    $booking['booking_status']
                                                )
                                            );
                                            ?>

                                        </span>

                                    </div>


                                    <a
                                        href="event-detail.php?id=<?php
                                            echo (int)
                                                $booking['event_id'];
                                        ?>"
                                        class="btn btn-outline">

                                        View Details

                                    </a>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                </section>



                <!-- ======================================================
                     LIKED EVENTS
                ======================================================= -->

                <?php if ($liked_events_available): ?>

                    <section
                        class="dashboard-panel"
                        id="liked-events">

                        <div class="panel-header">

                            <h2>
                                <span class="section-heart" aria-hidden="true">♥</span>
                                Liked Events
                            </h2>

                            <a
                                href="#liked-events"
                                class="panel-link">

                                View all

                            </a>

                        </div>


                        <?php if (empty($liked_events)): ?>

                            <p class="empty-state">

                                Events you like will be saved here.

                            </p>

                        <?php else: ?>

                            <div class="booking-list">

                                <?php foreach ($liked_events as $event): ?>

                                    <div class="booking-card liked-event-card">

                                        <div class="booking-card-info">

                                            <h3>
                                                <span class="card-heart" aria-hidden="true">♥</span>
                                                <?php
                                                echo htmlspecialchars(
                                                    $event['event_name']
                                                );
                                                ?>
                                            </h3>

                                            <p>

                                                <?php
                                                echo htmlspecialchars(
                                                    formatDate(
                                                        $event['event_date']
                                                    )
                                                );
                                                ?>

                                                &middot;

                                                <?php
                                                echo htmlspecialchars(
                                                    $event['venue']
                                                );
                                                ?>

                                            </p>

                                        </div>


                                        <a
                                            href="event-detail.php?id=<?php
                                                echo (int)
                                                    $event['event_id'];
                                            ?>"
                                            class="btn btn-outline">

                                            View Event

                                        </a>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        <?php endif; ?>

                    </section>

                <?php endif; ?>

            </div>



            <!-- ==========================================================
                 PROFILE + QUICK LINKS
            =========================================================== -->

            <div class="dashboard-columns dashboard-columns-secondary">


                <!-- ======================================================
                     PROFILE SUMMARY
                ======================================================= -->

                <section
                    class="dashboard-panel profile-summary"
                    id="profile">

                    <h2>
                        Profile Summary
                    </h2>


                    <div class="profile-summary-body">

                        <div
                            class="profile-avatar"
                            aria-hidden="true">

                            <?php

                            echo htmlspecialchars(
                                strtoupper(
                                    substr(
                                        $user['first_name'],
                                        0,
                                        1
                                    )
                                )
                            );

                            ?>

                        </div>


                        <div class="profile-details">

                            <p class="profile-name">

                                <?php

                                echo htmlspecialchars(
                                    $user['first_name']
                                    . ' '
                                    . $user['last_name']
                                );

                                ?>

                            </p>


                            <p class="profile-email">

                                <?php

                                echo htmlspecialchars(
                                    $user['email']
                                );

                                ?>

                            </p>


                            <p class="profile-since">

                                Member since

                                <?php

                                echo htmlspecialchars(
                                    formatDate(
                                        $user['created_at']
                                    )
                                );

                                ?>

                            </p>

                        </div>

                    </div>


                    <a
                        href="#profile"
                        class="btn btn-secondary">

                        Edit Profile

                    </a>

                </section>



                <!-- ======================================================
                     QUICK LINKS
                ======================================================= -->

                <section
                    class="dashboard-panel quick-links"
                    id="settings">

                    <h2>
                        Quick Links
                    </h2>


                    <ul class="quick-links-list">

                        <li>
                            <a href="events.php">
                                Browse Events
                            </a>
                        </li>

                        <li>
                            <a href="my-bookings.php">
                                My Bookings
                            </a>
                        </li>

                        <li>
                            <a href="#profile">
                                Update Profile
                            </a>
                        </li>

                        <li>
                            <a href="#settings">
                                Change Password
                            </a>
                        </li>

                    </ul>

                </section>

            </div>

        </main>

    </div>

    <footer class="dashboard-footer">
        <p>&copy; <?php echo date('Y'); ?> EventNest. Connecting People Through Events.</p>
    </footer>

</body>

</html>