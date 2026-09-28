<?php
/*
|--------------------------------------------------------------------------
| EventNest - Create Event (Organiser)
|--------------------------------------------------------------------------
| Temporary frontend version. Form does not yet submit to the database;
| POST handling will be added once the EVENTS table and CATEGORIES data
| are finalised.
|--------------------------------------------------------------------------
*/

require_once '../shared-components/auth.php';

$current_role = $_SESSION['role'] ?? 'attendee';

// Organiser-only page. Swap for requireRole('organiser') once
// Cynthia's auth.php exposes it — see shared-components/auth.php.
$is_organiser = ($current_role === 'organiser');

$career_fields = [
    'technology' => 'Technology',
    'finance'    => 'Finance',
    'marketing'  => 'Marketing',
    'healthcare' => 'Healthcare',
    'engineering'=> 'Engineering',
    'business'   => 'Business',
];

$current_page = 'create-event';
require_once '../shared-components/header.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventNest - Create Event</title>
    <link rel="stylesheet" href="../styles/main.css">
    <link rel="stylesheet" href="../styles/responsive.css">
</head>

<body>

    <div class="dashboard-shell">

        <?php require_once '../shared-components/navbar.php'; ?>

        <main class="dashboard-main">

            <header class="dashboard-page-title">
                <div>
                    <span class="eyebrow">EventNest</span>
                    <h1>Create Event</h1>
                </div>
                <span class="required-note">Required fields marked *</span>
            </header>

            <?php if (!$is_organiser): ?>

                <div class="form-notice form-notice-warning">
                    This page is intended for verified organisers. You can preview the
                    form, but submitting an event will be blocked until organiser
                    verification is confirmed.
                </div>

            <?php endif; ?>

            <section class="dashboard-panel">

                <form action="create-event.php" method="post" class="event-form" novalidate>

                    <div class="form-row">

                        <div class="form-group">
                            <label for="event_title">Event title *</label>
                            <input type="text" id="event_title" name="event_title"
                                   placeholder="Professional event title" required>
                        </div>

                        <div class="form-group">
                            <label for="career_field">Career field *</label>
                            <select id="career_field" name="career_field" required>
                                <option value="" disabled selected>Select category</option>
                                <?php foreach ($career_fields as $value => $label): ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>">
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    </div>

                    <div class="form-row">

                        <div class="form-group">
                            <label for="event_date">Date *</label>
                            <input type="date" id="event_date" name="event_date" required>
                        </div>

                        <div class="form-group">
                            <label for="start_time">Start and end time *</label>
                            <div class="time-range">
                                <input type="time" id="start_time" name="start_time" required>
                                <span class="time-separator">to</span>
                                <input type="time" id="end_time" name="end_time" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="event_format">Format *</label>
                            <select id="event_format" name="event_format" required>
                                <option value="" disabled selected>In-person / online / hybrid</option>
                                <option value="in_person">In-person</option>
                                <option value="online">Online</option>
                                <option value="hybrid">Hybrid</option>
                            </select>
                        </div>

                    </div>

                    <div class="form-row">

                        <div class="form-group">
                            <label for="event_location">Venue or meeting link *</label>
                            <input type="text" id="event_location" name="event_location"
                                   placeholder="Location details" required>
                        </div>

                        <div class="form-group">
                            <label for="age_restriction">Age restriction</label>
                            <select id="age_restriction" name="age_restriction">
                                <option value="none">None</option>
                                <option value="16">16+</option>
                                <option value="18">18+</option>
                            </select>
                        </div>

                    </div>

                    <div class="form-group">
                        <label for="event_description">Description *</label>
                        <textarea id="event_description" name="event_description" rows="4"
                                  placeholder="Purpose, speakers, programme and accessibility information"
                                  required></textarea>
                    </div>

                    <fieldset class="ticket-tier-group">

                        <legend>Ticket tiers</legend>

                        <div class="ticket-tier-grid">

                            <div class="ticket-tier-input">
                                <label for="tier_general">General</label>
                                <div class="currency-input">
                                    <span class="currency-prefix">R</span>
                                    <input type="number" id="tier_general" name="tier[general]"
                                           min="0" step="0.01" placeholder="0.00">
                                </div>
                            </div>

                            <div class="ticket-tier-input">
                                <label for="tier_vip">VIP</label>
                                <div class="currency-input">
                                    <span class="currency-prefix">R</span>
                                    <input type="number" id="tier_vip" name="tier[vip]"
                                           min="0" step="0.01" placeholder="0.00">
                                </div>
                            </div>

                            <div class="ticket-tier-input">
                                <label for="tier_early_bird">Early bird</label>
                                <div class="currency-input">
                                    <span class="currency-prefix">R</span>
                                    <input type="number" id="tier_early_bird" name="tier[early_bird]"
                                           min="0" step="0.01" placeholder="0.00">
                                </div>
                            </div>

                            <div class="ticket-tier-input">
                                <label for="tier_student">Student</label>
                                <div class="currency-input">
                                    <span class="currency-prefix">R</span>
                                    <input type="number" id="tier_student" name="tier[student]"
                                           min="0" step="0.01" placeholder="0.00">
                                </div>
                            </div>

                        </div>

                    </fieldset>

                    <div class="form-actions">
                        <button type="submit" name="action" value="preview" class="btn btn-outline">
                            Preview
                        </button>
                        <button type="submit" name="action" value="publish" class="btn btn-primary">
                            Publish event
                        </button>
                    </div>

                </form>

            </section>

        </main>

    </div>

    <footer class="dashboard-footer">
        <p>&copy; <?php echo date('Y'); ?> EventNest. Connecting People Through Events.</p>
    </footer>

</body>

</html>
