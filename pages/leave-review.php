<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Leave a Review - EventNest</title>

    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/responsive.css">

</head>

<body>

<header class="navbar">

    <h1>EventNest</h1>

</header>

<main class="container">

    <section class="form-section">

        <h2>Leave a Review</h2>

        <form method="POST" action="">

            <!-- Rating -->

            <div class="form-group">

                <label for="rating">
                    Rating
                </label>

                <select
                    id="rating"
                    name="rating"
                    required
                >

                    <option value="">
                        Select rating
                    </option>

                    <option value="1">1 Star</option>
                    <option value="2">2 Stars</option>
                    <option value="3">3 Stars</option>
                    <option value="4">4 Stars</option>
                    <option value="5">5 Stars</option>

                </select>

            </div>


            <!-- Compliment Tags -->

            <div
                id="complimentSection"
                class="form-group"
                style="display: none;"
            >

                <label>
                    What did you like?
                </label>

                <label>
                    <input
                        type="checkbox"
                        name="compliment_tags[]"
                        value="Great atmosphere"
                    >
                    Great atmosphere
                </label>

                <label>
                    <input
                        type="checkbox"
                        name="compliment_tags[]"
                        value="Well organised"
                    >
                    Well organised
                </label>

                <label>
                    <input
                        type="checkbox"
                        name="compliment_tags[]"
                        value="Friendly staff"
                    >
                    Friendly staff
                </label>

                <label>
                    <input
                        type="checkbox"
                        name="compliment_tags[]"
                        value="Great experience"
                    >
                    Great experience
                </label>

            </div>


            <!-- Complaint -->

            <div
                id="complaintSection"
                class="form-group"
                style="display: none;"
            >

                <label for="complaint_text">
                    Private Complaint
                </label>

                <textarea
                    id="complaint_text"
                    name="complaint_text"
                    rows="5"
                ></textarea>

                <small>
                    Your complaint is private and will not
                    be displayed publicly.
                </small>

            </div>


            <!-- Contact Preference -->

            <div class="form-group">

                <label>

                    <input
                        type="checkbox"
                        id="wants_contact"
                        name="wants_contact"
                        value="1"
                    >

                    I would like EventNest to contact me.

                </label>

            </div>


            <!-- Contact Email -->

            <div
                id="contactEmailSection"
                class="form-group"
                style="display: none;"
            >

                <label for="contact_email">
                    Contact Email
                </label>

                <input
                    type="email"
                    id="contact_email"
                    name="contact_email"
                >

            </div>


            <button type="submit">
                Submit Review
            </button>

        </form>

    </section>

</main>


<script>

const rating = document.getElementById("rating");

const complimentSection =
    document.getElementById("complimentSection");

const complaintSection =
    document.getElementById("complaintSection");

const wantsContact =
    document.getElementById("wants_contact");

const contactEmailSection =
    document.getElementById("contactEmailSection");


rating.addEventListener("change", function () {

    const selectedRating = Number(this.value);

    if (selectedRating >= 4) {

        complimentSection.style.display = "block";
        complaintSection.style.display = "none";

    } else if (selectedRating >= 1) {

        complimentSection.style.display = "none";
        complaintSection.style.display = "block";

    } else {

        complimentSection.style.display = "none";
        complaintSection.style.display = "none";

    }

});


wantsContact.addEventListener("change", function () {

    if (this.checked) {

        contactEmailSection.style.display = "block";

    } else {

        contactEmailSection.style.display = "none";

    }

});

</script>

</body>
</html>
