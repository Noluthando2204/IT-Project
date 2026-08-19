<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Events - EventNest</title>
</head>

<body>

    <header>
        <h1>EventNest</h1>  

        <nav>
            <a href="index.php">Home</a>
            <a href="events.php">Events</a>
        </nav>
    </header>

    <main>

        <h2>Browse Events</h2>

        <p>
            Explore career, professional development and networking events
            available on EventNest.
        </p>

        <section class="event-filters">

    <h3>Find an Event</h3>

    <form action="search-results.php" method="GET">

        <label for="search">Search:</label>
        <input
            type="text"
            id="search"
            name="q"
            placeholder="Search for an event"
        >

        <label for="province">Province:</label>
        <select id="province" name="province">
            <option value="">All Provinces</option>
            <option value="Gauteng">Gauteng</option>
            <option value="Mpumalanga">Mpumalanga</option>
            <option value="Limpopo">Limpopo</option>
            <option value="KwaZulu-Natal">KwaZulu-Natal</option>
            <option value="Western Cape">Western Cape</option>
            <option value="Eastern Cape">Eastern Cape</option>
            <option value="Free State">Free State</option>
            <option value="North West">North West</option>
            <option value="Northern Cape">Northern Cape</option>
        </select>

        <label for="format">Event Format:</label>
        <select id="format" name="format">
            <option value="">All Formats</option>
            <option value="in-person">In-person</option>
            <option value="online">Online</option>
            <option value="hybrid">Hybrid</option>
        </select>

        <button type="submit">Search Events</button>

    </form>

</section>

        <section class="event-list">

    <h3>Available Events</h3>

    <div class="event-card">
        <h4>Event Title</h4>

        <p>
            <strong>Date:</strong> Event date
        </p>

        <p>
            <strong>Location:</strong> Event location
        </p>

        <p>
            <strong>Province:</strong> Event province
        </p>

        <p>
            <strong>Format:</strong> In-person / Online / Hybrid
        </p>

        <p>
            <strong>Ticket Price:</strong> R0.00
        </p>

        <a href="event-detail.php">View Event</a>
    </div>

</section>

    </main>
