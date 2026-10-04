<?php

require_once __DIR__ . '/config/db.php';

include __DIR__ . '/includes/header.php';

?>

<main class="container">

    <section class="hero">

        <h2>Discover Events That Match Your Career</h2>

        <p>
            EventNest helps students, graduates and professionals
            discover career-related events in their area.
        </p>

        <a href="events.php" class="button">
            Explore Events
        </a>

    </section>

    <section>

        <h2>Why EventNest?</h2>

        <div class="feature-grid">

            <div class="feature-card">
                <h3>Career-Based</h3>
                <p>
                    Discover events related to your career.
                </p>
            </div>

            <div class="feature-card">
                <h3>Location-Based</h3>
                <p>
                    Find relevant events in your area.
                </p>
            </div>

            <div class="feature-card">
                <h3>Easy Booking</h3>
                <p>
                    Book tickets online through EventNest.
                </p>
            </div>

        </div>

    </section>

</main>

<?php include __DIR__ . '/includes/footer.php'; ?>