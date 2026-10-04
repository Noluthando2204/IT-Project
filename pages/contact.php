<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Contact Us - EventNest</title>

    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/responsive.css">
</head>

<body>

<header class="navbar">
    <h1>EventNest</h1>

    <nav>
        <a href="index.php">Home</a>
        <a href="events.php">Events</a>
        <a href="contact.php">Contact</a>
    </nav>
</header>

<main class="container">

    <section class="form-section">

        <h2>Contact Us</h2>

        <p>
            Have a question or need assistance?
            Send us a message and our team will get back to you.
        </p>

        <form id="contactForm" method="POST" action="">

            <div class="form-group">
                <label for="name">Name</label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    required
                >
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    required
                >
            </div>

            <div class="form-group">
                <label for="message">Message</label>

                <textarea
                    id="message"
                    name="message"
                    rows="6"
                    required
                ></textarea>
            </div>

            <button type="submit">
                Send Message
            </button>

        </form>

    </section>

</main>

<script src="scripts/validation.js"></script>

</body>
</html>
