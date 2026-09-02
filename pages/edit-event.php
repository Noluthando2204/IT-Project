<?php
/*
|--------------------------------------------------------------------------
| EventNest - Edit Event
|--------------------------------------------------------------------------
| Organiser event-editing page.
| Frontend prototype: database UPDATE will be connected during integration.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../shared-components/auth.php';

/*
|--------------------------------------------------------------------------
| Temporary prototype event
|--------------------------------------------------------------------------
| In the integrated version, load this event using the event ID and a
| prepared PDO statement. The organiser must only be able to edit their
| own event.
|--------------------------------------------------------------------------
*/
$event_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 101;

$event = [
    'event_id' => $event_id,
    'organiser_id' => $_SESSION['user_id'] ?? 1,
    'event_title' => 'Cloud Security Essentials',
    'career_field' => 'Cybersecurity',
    'event_date' => '2026-09-17',
    'start_time' => '09:00',
    'end_time' => '15:00',
    'format' => 'Hybrid',
    'venue' => 'Sandton Convention Centre + livestream',
    'age_restriction' => '18+',
    'career_gate_type' => 'field',
    'allowed_career_field' => 'Cybersecurity',
    'allowed_email_domain' => '',
    'description' => 'A practical professional event covering cloud identity, threat prevention and entry-level career pathways.',
    'status' => 'published'
];

$organiser_name = $_SESSION['business_name'] ?? 'Tino Organiser';

$errors = [];
$success = false;

/*
|--------------------------------------------------------------------------
| Editing restriction
|--------------------------------------------------------------------------
| Completed and cancelled events must not be edited.
|--------------------------------------------------------------------------
*/
$editing_locked = in_array(
    strtolower($event['status']),
    ['completed', 'cancelled'],
    true
);

/*
|--------------------------------------------------------------------------
| Pre-populate form values from the existing event
|--------------------------------------------------------------------------
*/
$event_title = $event['event_title'];
$career_field = $event['career_field'];
$event_date = $event['event_date'];
$start_time = $event['start_time'];
$end_time = $event['end_time'];
$format = $event['format'];
$venue = $event['venue'];
$age_restriction = $event['age_restriction'];
$career_gate_type = $event['career_gate_type'];
$allowed_career_field = $event['allowed_career_field'];
$allowed_email_domain = $event['allowed_email_domain'];
$description = $event['description'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$editing_locked) {

    $event_title = trim($_POST['event_title'] ?? '');
    $career_field = trim($_POST['career_field'] ?? '');
    $event_date = trim($_POST['event_date'] ?? '');
    $start_time = trim($_POST['start_time'] ?? '');
    $end_time = trim($_POST['end_time'] ?? '');
    $format = trim($_POST['format'] ?? '');
    $venue = trim($_POST['venue'] ?? '');
    $age_restriction = trim($_POST['age_restriction'] ?? 'None');
    $career_gate_type = trim($_POST['career_gate_type'] ?? 'none');
    $allowed_career_field = trim($_POST['allowed_career_field'] ?? '');
    $allowed_email_domain = trim($_POST['allowed_email_domain'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($event_title === '') {
        $errors[] = 'Event title is required.';
    }

    if ($career_field === '') {
        $errors[] = 'Career field is required.';
    }

    if ($event_date === '') {
        $errors[] = 'Date is required.';
    }

    if ($start_time === '' || $end_time === '') {
        $errors[] = 'Start and end time are required.';
    } elseif ($start_time >= $end_time) {
        $errors[] = 'End time must be later than the start time.';
    }

    if ($format === '') {
        $errors[] = 'Format is required.';
    }

    if ($venue === '') {
        $errors[] = 'Venue or meeting link is required.';
    }

    if ($description === '') {
        $errors[] = 'Description is required.';
    }

    if (!in_array($age_restriction, ['None', '18+', '21+'], true)) {
        $errors[] = 'Please select a valid age restriction.';
    }

    if (!in_array($format, ['In-person', 'Online', 'Hybrid'], true)) {
        $errors[] = 'Please select a valid event format.';
    }

    if (!in_array($career_gate_type, ['none', 'field', 'email_domain'], true)) {
        $errors[] = 'Please select a valid career access option.';
    }

    if ($career_gate_type === 'field' && $allowed_career_field === '') {
        $errors[] = 'Select an allowed career field.';
    }

    if ($career_gate_type === 'email_domain' && $allowed_email_domain === '') {
        $errors[] = 'Enter an allowed email domain.';
    }

    if (!$errors) {
        /*
         * Prototype only.
         * Replace with the approved backend UPDATE handler during
         * database integration. Use a PDO prepared statement and
         * verify the organiser owns this event before updating it.
         */
        $success = true;

        // Keep the prototype display data in sync after a successful update.
        $event['event_title'] = $event_title;
        $event['career_field'] = $career_field;
        $event['event_date'] = $event_date;
        $event['start_time'] = $start_time;
        $event['end_time'] = $end_time;
        $event['format'] = $format;
        $event['venue'] = $venue;
        $event['age_restriction'] = $age_restriction;
        $event['career_gate_type'] = $career_gate_type;
        $event['allowed_career_field'] = $allowed_career_field;
        $event['allowed_email_domain'] = $allowed_email_domain;
        $event['description'] = $description;
    }
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function selected($current, $value): string
{
    return $current === $value ? 'selected' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Event | EventNest</title>

    <link rel="stylesheet" href="../styles/main.css">
    <link rel="stylesheet" href="../styles/responsive.css">
    <link rel="stylesheet" href="../styles/create-event.css">
</head>

<body>

<header class="topbar">
    <div class="topbar-left">
        <a href="business-dashboard.php" class="logo" aria-label="EventNest dashboard">
            <?php if (file_exists(__DIR__ . '/../images/eventnest-logo.png')): ?>
                <img
                    src="../images/eventnest-logo.png"
                    alt="EventNest logo"
                    class="eventnest-logo"
                >
            <?php else: ?>
                <span class="logo-icon">EN</span>
            <?php endif; ?>

            <span class="logo-text">EventNest</span>
        </a>

        <span class="role-label">Organiser</span>
    </div>

    <div class="topbar-right">
        <a href="notifications.php" class="notifications">Notifications</a>

        <span class="user-greeting">
            Hi, <?= e($organiser_name) ?>
        </span>
    </div>
</header>

<div class="dashboard-layout">

    <aside class="sidebar">
        <div class="sidebar-title">Organiser</div>

        <nav class="sidebar-nav" aria-label="Organiser navigation">
            <a href="business-dashboard.php">Overview</a>

            <a href="create-event.php">Create event</a>

            <a href="manage-events.php" class="active" aria-current="page">
                Manage events
            </a>

            <a href="manage-bookings.php">Bookings</a>

            <a href="posts.php">Posts</a>

            <a href="private-reviews.php">Private reviews</a>

            <a href="business-register.php">Organisation</a>

            <a href="logout.php">Log out</a>
        </nav>
    </aside>

    <main class="main-content">

        <div class="page-heading">
            <div>
                <h1>Edit event</h1>
                <p class="required-note">
                    Update your event details. Required fields marked *
                </p>
            </div>

            <a href="manage-events.php" class="back-link">
                &larr; Back to events
            </a>
        </div>

        <div class="event-status-banner">
            <span class="status-label">Current status:</span>
            <span class="status-badge status-<?= e(strtolower($event['status'])) ?>">
                <?= e(ucfirst($event['status'])) ?>
            </span>
        </div>

        <?php if ($editing_locked): ?>

            <div class="alert alert-error" role="alert">
                <strong>Editing unavailable.</strong>
                This event has been marked as
                <strong><?= e(strtolower($event['status'])) ?></strong>
                and can no longer be edited.
            </div>

            <div class="locked-card">
                <h2><?= e($event_title) ?></h2>
                <p>
                    Completed or cancelled events are read-only.
                    You can return to Manage Events to view the event.
                </p>

                <a href="manage-events.php" class="btn btn-secondary">
                    Back to Manage Events
                </a>
            </div>

        <?php else: ?>

            <?php if ($success): ?>
                <div class="alert alert-success" role="status">
                    <strong>Event updated successfully.</strong>
                    Your changes have passed prototype validation.
                    Database saving will be connected during integration.
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error" role="alert">
                    <strong>Please correct the following:</strong>

                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form
                action="edit-event.php?id=<?= (int)$event_id ?>"
                method="POST"
                class="event-form"
                id="edit-event-form"
            >

                <div class="form-group full-width">
                    <label for="event_title">
                        Event title <span>*</span>
                    </label>

                    <input
                        type="text"
                        id="event_title"
                        name="event_title"
                        value="<?= e($event_title) ?>"
                        placeholder="Professional event title"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="career_field">
                        Career field <span>*</span>
                    </label>

                    <select id="career_field" name="career_field" required>
                        <option value="">Select category</option>
                        <option value="Cloud Computing" <?= selected($career_field, 'Cloud Computing') ?>>Cloud Computing</option>
                        <option value="Cybersecurity" <?= selected($career_field, 'Cybersecurity') ?>>Cybersecurity</option>
                        <option value="Finance" <?= selected($career_field, 'Finance') ?>>Finance</option>
                        <option value="Marketing" <?= selected($career_field, 'Marketing') ?>>Marketing</option>
                        <option value="Leadership" <?= selected($career_field, 'Leadership') ?>>Leadership</option>
                        <option value="Technology" <?= selected($career_field, 'Technology') ?>>Technology</option>
                        <option value="Other" <?= selected($career_field, 'Other') ?>>Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="event_date">
                        Date <span>*</span>
                    </label>

                    <input
                        type="date"
                        id="event_date"
                        name="event_date"
                        value="<?= e($event_date) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>
                        Start and end time <span>*</span>
                    </label>

                    <div class="time-row">
                        <input
                            type="time"
                            id="start_time"
                            name="start_time"
                            value="<?= e($start_time) ?>"
                            required
                            aria-label="Start time"
                        >

                        <input
                            type="time"
                            id="end_time"
                            name="end_time"
                            value="<?= e($end_time) ?>"
                            required
                            aria-label="End time"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="format">
                        Format <span>*</span>
                    </label>

                    <select id="format" name="format" required>
                        <option value="">Select format</option>
                        <option value="In-person" <?= selected($format, 'In-person') ?>>In-person</option>
                        <option value="Online" <?= selected($format, 'Online') ?>>Online</option>
                        <option value="Hybrid" <?= selected($format, 'Hybrid') ?>>Hybrid</option>
                    </select>

                    <p class="hint">
                        Hybrid combines an online speaker with in-person attendance requirements.
                    </p>
                </div>

                <div class="form-group">
                    <label for="venue">
                        Venue or meeting link <span>*</span>
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
                        <option value="None" <?= selected($age_restriction, 'None') ?>>None</option>
                        <option value="18+" <?= selected($age_restriction, '18+') ?>>18+</option>
                        <option value="21+" <?= selected($age_restriction, '21+') ?>>21+</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="career_gate_type">
                        Career access
                    </label>

                    <select id="career_gate_type" name="career_gate_type">
                        <option value="none" <?= selected($career_gate_type, 'none') ?>>
                            No career restriction
                        </option>
                        <option value="field" <?= selected($career_gate_type, 'field') ?>>
                            Career field
                        </option>
                        <option value="email_domain" <?= selected($career_gate_type, 'email_domain') ?>>
                            Email domain
                        </option>
                    </select>
                </div>

                <div
                    class="form-group"
                    id="allowed-career-field-wrapper"
                    <?= $career_gate_type !== 'field' ? 'hidden' : '' ?>
                >
                    <label for="allowed_career_field">
                        Allowed career field
                    </label>

                    <select id="allowed_career_field" name="allowed_career_field">
                        <option value="">Select career field</option>
                        <option value="Cloud Computing" <?= selected($allowed_career_field, 'Cloud Computing') ?>>Cloud Computing</option>
                        <option value="Cybersecurity" <?= selected($allowed_career_field, 'Cybersecurity') ?>>Cybersecurity</option>
                        <option value="Finance" <?= selected($allowed_career_field, 'Finance') ?>>Finance</option>
                        <option value="Marketing" <?= selected($allowed_career_field, 'Marketing') ?>>Marketing</option>
                        <option value="Leadership" <?= selected($allowed_career_field, 'Leadership') ?>>Leadership</option>
                        <option value="Technology" <?= selected($allowed_career_field, 'Technology') ?>>Technology</option>
                        <option value="Other" <?= selected($allowed_career_field, 'Other') ?>>Other</option>
                    </select>
                </div>

                <div
                    class="form-group"
                    id="allowed-email-domain-wrapper"
                    <?= $career_gate_type !== 'email_domain' ? 'hidden' : '' ?>
                >
                    <label for="allowed_email_domain">
                        Allowed email domain
                    </label>

                    <input
                        type="text"
                        id="allowed_email_domain"
                        name="allowed_email_domain"
                        value="<?= e($allowed_email_domain) ?>"
                        placeholder="example.ac.za"
                    >

                    <p class="hint">
                        Restrict attendance to users with a specific email domain.
                    </p>
                </div>

                <div class="form-group full-width">
                    <label for="description">
                        Description <span>*</span>
                    </label>

                    <textarea
                        id="description"
                        name="description"
                        placeholder="Purpose, speakers, programme and accessibility information"
                        required
                    ><?= e($description) ?></textarea>
                </div>

                <div class="form-actions full-width">
                    <a href="manage-events.php" class="btn btn-secondary">
                        Cancel
                    </a>

                    <button type="button" class="btn btn-preview" id="previewButton">
                        Preview changes
                    </button>

                    <button type="submit" class="btn btn-publish">
                        Update event
                    </button>
                </div>

            </form>

            <div class="preview-modal" id="previewModal" aria-hidden="true">
                <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="previewTitle">

                    <button
                        type="button"
                        class="modal-close"
                        id="closePreview"
                        aria-label="Close preview"
                    >
                        &times;
                    </button>

                    <p class="preview-label">Event preview</p>

                    <h2 id="previewTitle"><?= e($event_title) ?></h2>

                    <div class="preview-meta">
                        <span id="previewCareer"><?= e($career_field) ?></span>
                        <span id="previewDate">
                            <?= e($event_date) ?> |
                            <?= e($start_time) ?> - <?= e($end_time) ?>
                        </span>
                        <span id="previewFormat"><?= e($format) ?></span>
                    </div>

                    <p id="previewVenue"><?= e($venue) ?></p>

                    <p id="previewDescription"><?= e($description) ?></p>

                    <div class="preview-restrictions">
                        <strong>Access information</strong>
                        <p id="previewAge">
                            Age restriction: <?= e($age_restriction) ?>
                        </p>
                        <p id="previewGate">
                            Career access:
                            <?= $career_gate_type === 'none'
                                ? 'No restriction'
                                : e($career_gate_type === 'field'
                                    ? $allowed_career_field
                                    : $allowed_email_domain) ?>
                        </p>
                    </div>

                </div>
            </div>

        <?php endif; ?>

    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const gate = document.getElementById('career_gate_type');
    const careerWrapper = document.getElementById('allowed-career-field-wrapper');
    const emailWrapper = document.getElementById('allowed-email-domain-wrapper');

    const previewButton = document.getElementById('previewButton');
    const previewModal = document.getElementById('previewModal');
    const closePreview = document.getElementById('closePreview');

    function updateGateFields() {
        if (!gate) return;

        careerWrapper.hidden = gate.value !== 'field';
        emailWrapper.hidden = gate.value !== 'email_domain';
    }

    if (gate) {
        gate.addEventListener('change', updateGateFields);
        updateGateFields();
    }

    if (previewButton) {
        previewButton.addEventListener('click', function () {
            const value = function (id) {
                const element = document.getElementById(id);
                return element ? element.value.trim() : '';
            };

            const formatDate = function (dateValue) {
                if (!dateValue) return 'Date';

                const date = new Date(dateValue + 'T00:00:00');

                return date.toLocaleDateString('en-ZA', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric'
                });
            };

            document.getElementById('previewTitle').textContent =
                value('event_title') || 'Your event title';

            document.getElementById('previewCareer').textContent =
                value('career_field') || 'Career field';

            document.getElementById('previewDate').textContent =
                (value('event_date')
                    ? formatDate(value('event_date'))
                    : 'Date') +
                ' | ' +
                (value('start_time') || '--:--') +
                ' - ' +
                (value('end_time') || '--:--');

            document.getElementById('previewFormat').textContent =
                value('format') || 'Format';

            document.getElementById('previewVenue').textContent =
                value('venue') || 'Venue or meeting link';

            document.getElementById('previewDescription').textContent =
                value('description') || 'Event description';

            const age = value('age_restriction') || 'None';
            document.getElementById('previewAge').textContent =
                'Age restriction: ' + age;

            let gateText = 'No restriction';

            if (gate.value === 'field') {
                gateText = value('allowed_career_field') || 'Career field';
            } else if (gate.value === 'email_domain') {
                gateText = value('allowed_email_domain') || 'Email domain';
            }

            document.getElementById('previewGate').textContent =
                'Career access: ' + gateText;

            previewModal.classList.add('show');
            previewModal.setAttribute('aria-hidden', 'false');
        });
    }

    if (closePreview) {
        closePreview.addEventListener('click', function () {
            previewModal.classList.remove('show');
            previewModal.setAttribute('aria-hidden', 'true');
        });
    }

    if (previewModal) {
        previewModal.addEventListener('click', function (event) {
            if (event.target === previewModal) {
                previewModal.classList.remove('show');
                previewModal.setAttribute('aria-hidden', 'true');
            }
        });
    }
});
</script>

</body>
</html>
