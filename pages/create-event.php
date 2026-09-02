<?php
/*
|--------------------------------------------------------------------------
| EventNest - Create Event
|--------------------------------------------------------------------------
| Organiser event-creation page.
| Frontend prototype: database INSERT will be connected during integration.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../shared-components/auth.php';

/*
|--------------------------------------------------------------------------
| Temporary prototype organiser data
|--------------------------------------------------------------------------
| Replace with session/database data during integration.
|--------------------------------------------------------------------------
*/
$organiserName = $_SESSION['business_name'] ?? 'Tino Organiser';

$eventTitle = '';
$careerField = '';
$eventDate = '';
$startTime = '';
$endTime = '';
$format = '';
$venue = '';
$ageRestriction = 'None';
$careerGateType = 'none';
$allowedCareerField = '';
$allowedEmailDomain = '';
$description = '';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eventTitle = trim($_POST['event_title'] ?? '');
    $careerField = trim($_POST['career_field'] ?? '');
    $eventDate = trim($_POST['event_date'] ?? '');
    $startTime = trim($_POST['start_time'] ?? '');
    $endTime = trim($_POST['end_time'] ?? '');
    $format = trim($_POST['format'] ?? '');
    $venue = trim($_POST['venue'] ?? '');
    $ageRestriction = trim($_POST['age_restriction'] ?? 'None');
    $careerGateType = trim($_POST['career_gate_type'] ?? 'none');
    $allowedCareerField = trim($_POST['allowed_career_field'] ?? '');
    $allowedEmailDomain = trim($_POST['allowed_email_domain'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($eventTitle === '') $errors[] = 'Event title is required.';
    if ($careerField === '') $errors[] = 'Career field is required.';
    if ($eventDate === '') $errors[] = 'Date is required.';
    if ($startTime === '' || $endTime === '') $errors[] = 'Start and end time are required.';
    if ($format === '') $errors[] = 'Format is required.';
    if ($venue === '') $errors[] = 'Venue or meeting link is required.';
    if ($description === '') $errors[] = 'Description is required.';

    if ($careerGateType === 'field' && $allowedCareerField === '') {
        $errors[] = 'Select an allowed career field.';
    }

    if ($careerGateType === 'email_domain' && $allowedEmailDomain === '') {
        $errors[] = 'Enter an allowed email domain.';
    }

    if (!$errors) {
        /*
         * Prototype only.
         * Replace this block with the approved backend/database handler
         * when integration is completed.
         */
        $success = true;
    }
}

function e($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function selected($current, $value) {
    return $current === $value ? 'selected' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Event | EventNest</title>

    <link rel="stylesheet" href="../styles/main.css">
    <link rel="stylesheet" href="../styles/responsive.css">
    <link rel="stylesheet" href="../styles/create-event.css">

    <!-- Use the project's shared validation script when available. -->
    <script src="../scripts/validation.js" defer></script>
</head>

<body>

<header class="topbar">
    <a href="business-dashboard.php" class="brand" aria-label="EventNest dashboard">
        <?php if (file_exists(__DIR__ . '/../images/eventnest-logo.png')): ?>
            <img src="../images/eventnest-logo.png" alt="EventNest logo">
        <?php else: ?>
            <span class="brand-fallback" aria-hidden="true">E</span>
        <?php endif; ?>
        <span>EventNest</span>
    </a>

    <div class="header-right">
        <button class="notification" type="button" aria-label="Notifications">
            &#128276;
        </button>

        <div class="user">
            <div class="avatar" aria-hidden="true">
                <?= e(strtoupper(substr($organiserName, 0, 1))) ?>
            </div>
            <span>Hi, <?= e($organiserName) ?></span>
        </div>
    </div>
</header>

<div class="layout">

    <aside class="sidebar">
        <div class="sidebar-title">Organiser</div>

        <nav class="nav" aria-label="Organiser navigation">
            <a href="business-dashboard.php">
                <span class="icon">&#9632;</span>
                Overview
            </a>

            <a href="create-event.php" class="active" aria-current="page">
                <span class="icon">+</span>
                Create event
            </a>

            <a href="manage-events.php">
                <span class="icon">&#9633;</span>
                Manage events
            </a>

            <a href="manage-bookings.php">
                <span class="icon">&#9776;</span>
                Bookings
            </a>

            <a href="posts.php">
                <span class="icon">&#9998;</span>
                Posts
            </a>

            <a href="private-reviews.php">
                <span class="icon">&#9829;</span>
                Private reviews
            </a>

            <a href="business-register.php">
                <span class="icon">&#9678;</span>
                Organisation
            </a>

            <a href="logout.php" class="logout">
                <span class="icon">&#8617;</span>
                Log out
            </a>
        </nav>
    </aside>

    <main class="main">

        <div class="heading">
            <div>
                <h1>Create event</h1>
                <p>Required fields marked <span class="required">*</span></p>
            </div>

            <a href="business-dashboard.php" class="back-link">
                &larr; Back to dashboard
            </a>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success" role="status">
                <strong>Event ready to publish.</strong>
                Your prototype event details passed validation.
                Database saving will be connected during integration.
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert alert-error" role="alert">
                <strong>Please correct the following:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="form-card" id="create-event-form">

            <div class="form-grid">

                <div class="form-group full">
                    <label for="event_title">
                        Event title <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        id="event_title"
                        name="event_title"
                        value="<?= e($eventTitle) ?>"
                        placeholder="Professional event title"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="career_field">
                        Career field <span class="required">*</span>
                    </label>

                    <select id="career_field" name="career_field" required>
                        <option value="">Select category</option>
                        <option value="Technology" <?= selected($careerField, 'Technology') ?>>Technology</option>
                        <option value="Cloud Computing" <?= selected($careerField, 'Cloud Computing') ?>>Cloud Computing</option>
                        <option value="Cybersecurity" <?= selected($careerField, 'Cybersecurity') ?>>Cybersecurity</option>
                        <option value="Finance" <?= selected($careerField, 'Finance') ?>>Finance</option>
                        <option value="Marketing" <?= selected($careerField, 'Marketing') ?>>Marketing</option>
                        <option value="Leadership" <?= selected($careerField, 'Leadership') ?>>Leadership</option>
                        <option value="Other" <?= selected($careerField, 'Other') ?>>Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="event_date">
                        Date <span class="required">*</span>
                    </label>

                    <input
                        type="date"
                        id="event_date"
                        name="event_date"
                        value="<?= e($eventDate) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="start_time">
                        Start and end time <span class="required">*</span>
                    </label>

                    <div class="time-row">
                        <input
                            type="time"
                            id="start_time"
                            name="start_time"
                            value="<?= e($startTime) ?>"
                            required
                            aria-label="Start time"
                        >

                        <input
                            type="time"
                            id="end_time"
                            name="end_time"
                            value="<?= e($endTime) ?>"
                            required
                            aria-label="End time"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="format">
                        Format <span class="required">*</span>
                    </label>

                    <select id="format" name="format" required>
                        <option value="">Select format</option>
                        <option value="In-person" <?= selected($format, 'In-person') ?>>In-person</option>
                        <option value="Online" <?= selected($format, 'Online') ?>>Online</option>
                        <option value="Hybrid" <?= selected($format, 'Hybrid') ?>>Hybrid</option>
                    </select>

                    <p class="hint">
                        Hybrid combines an online speaker with in-person attendance.
                    </p>
                </div>

                <div class="form-group">
                    <label for="venue">
                        Venue or meeting link <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        id="venue"
                        name="venue"
                        value="<?= e($venue) ?>"
                        placeholder="Location details or meeting link"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="age_restriction">Age restriction</label>

                    <select id="age_restriction" name="age_restriction">
                        <option value="None" <?= selected($ageRestriction, 'None') ?>>None</option>
                        <option value="18+" <?= selected($ageRestriction, '18+') ?>>18+</option>
                        <option value="21+" <?= selected($ageRestriction, '21+') ?>>21+</option>
                    </select>
                </div>

                <!-- Required career/email gating fields -->
                <div class="form-group">
                    <label for="career_gate_type">
                        Career access
                    </label>

                    <select id="career_gate_type" name="career_gate_type">
                        <option value="none" <?= selected($careerGateType, 'none') ?>>
                            No career restriction
                        </option>
                        <option value="field" <?= selected($careerGateType, 'field') ?>>
                            Career field
                        </option>
                        <option value="email_domain" <?= selected($careerGateType, 'email_domain') ?>>
                            Email domain
                        </option>
                    </select>
                </div>

                <div
                    class="form-group conditional-field"
                    id="allowed-career-field-wrapper"
                    <?= $careerGateType !== 'field' ? 'hidden' : '' ?>
                >
                    <label for="allowed_career_field">
                        Allowed career field
                    </label>

                    <select id="allowed_career_field" name="allowed_career_field">
                        <option value="">Select career field</option>
                        <option value="Technology" <?= selected($allowedCareerField, 'Technology') ?>>Technology</option>
                        <option value="Cloud Computing" <?= selected($allowedCareerField, 'Cloud Computing') ?>>Cloud Computing</option>
                        <option value="Cybersecurity" <?= selected($allowedCareerField, 'Cybersecurity') ?>>Cybersecurity</option>
                        <option value="Finance" <?= selected($allowedCareerField, 'Finance') ?>>Finance</option>
                        <option value="Marketing" <?= selected($allowedCareerField, 'Marketing') ?>>Marketing</option>
                        <option value="Leadership" <?= selected($allowedCareerField, 'Leadership') ?>>Leadership</option>
                        <option value="Other" <?= selected($allowedCareerField, 'Other') ?>>Other</option>
                    </select>
                </div>

                <div
                    class="form-group conditional-field"
                    id="allowed-email-domain-wrapper"
                    <?= $careerGateType !== 'email_domain' ? 'hidden' : '' ?>
                >
                    <label for="allowed_email_domain">
                        Allowed email domain
                    </label>

                    <input
                        type="text"
                        id="allowed_email_domain"
                        name="allowed_email_domain"
                        value="<?= e($allowedEmailDomain) ?>"
                        placeholder="example.ac.za"
                    >

                    <p class="hint">
                        Restrict attendance to users with a specific email domain.
                    </p>
                </div>

                <div class="form-group full">
                    <label for="description">
                        Description <span class="required">*</span>
                    </label>

                    <textarea
                        id="description"
                        name="description"
                        placeholder="Purpose, speakers, programme and accessibility information"
                        required
                    ><?= e($description) ?></textarea>
                </div>

            </div>

            <div class="actions">
                <button type="button" class="btn btn-preview" id="preview-event">
                    Preview
                </button>

                <button type="submit" class="btn btn-publish">
                    Publish event
                </button>
            </div>

        </form>

        <section class="preview-card" id="event-preview" hidden aria-live="polite">
            <div class="preview-header">
                <div>
                    <span class="preview-label">Event preview</span>
                    <h2 id="preview-title">Your event</h2>
                </div>

                <button
                    type="button"
                    class="preview-close"
                    id="close-preview"
                    aria-label="Close preview"
                >
                    &times;
                </button>
            </div>

            <div class="preview-content">
                <p id="preview-meta"></p>
                <p id="preview-description"></p>
            </div>
        </section>

    </main>
</div>

<script>
/*
 * Small page-specific interaction only.
 * General form validation remains in scripts/validation.js.
 */
document.addEventListener('DOMContentLoaded', function () {
    const gate = document.getElementById('career_gate_type');
    const careerWrapper = document.getElementById('allowed-career-field-wrapper');
    const emailWrapper = document.getElementById('allowed-email-domain-wrapper');

    const previewButton = document.getElementById('preview-event');
    const closePreview = document.getElementById('close-preview');
    const previewCard = document.getElementById('event-preview');

    function updateGateFields() {
        careerWrapper.hidden = gate.value !== 'field';
        emailWrapper.hidden = gate.value !== 'email_domain';
    }

    gate.addEventListener('change', updateGateFields);

    previewButton.addEventListener('click', function () {
        const title = document.getElementById('event_title').value.trim() || 'Your event';
        const date = document.getElementById('event_date').value || 'Date not selected';
        const start = document.getElementById('start_time').value;
        const end = document.getElementById('end_time').value;
        const venue = document.getElementById('venue').value.trim() || 'Venue not provided';
        const description =
            document.getElementById('description').value.trim() ||
            'No description provided yet.';

        document.getElementById('preview-title').textContent = title;

        let time = '';
        if (start && end) {
            time = ' | ' + start + ' - ' + end;
        }

        document.getElementById('preview-meta').textContent =
            date + time + ' | ' + venue;

        document.getElementById('preview-description').textContent = description;

        previewCard.hidden = false;
        previewCard.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    });

    closePreview.addEventListener('click', function () {
        previewCard.hidden = true;
    });

    updateGateFields();
});
</script>

</body>
</html>
