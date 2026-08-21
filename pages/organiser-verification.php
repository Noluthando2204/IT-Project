<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Organiser Verification - EventNest</title>

    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/responsive.css">

</head>

<body>

<header class="navbar">

    <h1>EventNest</h1>

</header>

<main class="container">

    <section class="form-section">

        <h2>Organiser Verification</h2>

        <p>
            Upload the required verification document
            for administrator approval.
        </p>

        <form
            method="POST"
            action=""
            enctype="multipart/form-data"
        >

            <div class="form-group">

                <label for="verification_type">
                    Verification Type
                </label>

                <select
                    id="verification_type"
                    name="verification_type"
                    required
                >

                    <option value="">
                        Select document type
                    </option>

                    <option value="id">
                        Identity Document
                    </option>

                    <option value="cipc">
                        CIPC Registration Document
                    </option>

                </select>

            </div>


            <div class="form-group">

                <label for="verification_doc">
                    Verification Document
                </label>

                <input
                    type="file"
                    id="verification_doc"
                    name="verification_doc"
                    required
                >

            </div>


            <button type="submit">
                Upload Document
            </button>

        </form>

    </section>

</main>

</body>
</html>
