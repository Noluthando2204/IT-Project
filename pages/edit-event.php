<?php
/*
|--------------------------------------------------------------------------
| EventNest - Edit Event (Organiser)
|--------------------------------------------------------------------------
| Temporary frontend version. Reuses the create-event form, pre-filled
| with the event's existing values, with an "Update event" action
| instead of "Publish event". Reached from manage-events.php.
|--------------------------------------------------------------------------
*/

require_once '../shared-components/auth.php';

$current_role = $_SESSION['role'] ?? 'attendee';
$is_organiser = ($current_role === 'organiser');

$event_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$career_fields = [
    'technology' => 'Technology',
    'finance'    => 'Finance',
    'marketing'  => 'Marketing',
    'healthcare' => 'Healthcare',
    'engineering'=> 'Engineering',
    'business'   => 'Business',
];

/*
|--------------------------------------------------------------------------
| Placeholder event data
|--------------------------------------------------------------------------
| Stands in for a lookup by $event_id until EVENTS queries are wired up.
| Falls back to blank values if no matching placeholder is found, so the
| form still renders for any id during frontend development.
|--------------------------------------------------------------------------
*/

$placeholder_events = [
    1 => [
        'event_title'       => 'Cloud Careers',
        'career_field'      => 'technology',
        'event_date'        => '2026-09-18',
        'start_time'        => '08:00',
        'end_time'          => '15:00',
        'event_format'      => 'online',
        'event_location'    => 'https://meet.eventnest.example/cloud-careers',
        'age_restriction'   => 'none',
        'event_description' => 'A practical professional event covering cloud identity, threat prevention and entry-level career pathways.',
        'tier'              => ['general' => '180.00', 'vip' => '350.00', 'early_bird' => '130.00', 'student' => '90.00'],
    ],
];

$event = $placeholder_events[$event_id] ?? [
    'event_title'       => '',
    'career_field'      => '',
    'event_date'        => '',
    'start_time'        => '',
    'end_time'          => '',
    'event_format'      => '',
    'event_location'    => '',
    'age_restriction'   => 'none',
    'event_description' => '',
    'tier'              => ['general' => '', 'vip' => '', 'early_bird' => '', 'student' => ''],
];

$current_page = 'manage-events';
require_once '../shared-components/header.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventNest - Edit Event</title>
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
                    <h1>Edit Event</h1>
                </div>
                <span class="required-note">Required fields marked *</span>
            </header>

            <?php if (!$is_organiser): ?>

                <div class="form-notice form-notice-warning">
                    This page is intended for verified organisers. You can preview the
                    form, but updating an event will be blocked until organiser
                    verification is confirmed.
                </div>

            <?php endif; ?>

            <section class="dashboard-panel">

                <form action="edit-event.php?id=<?php echo (int) $event_id; ?>" method="post"
                      class="event-form" novalidate>

                    <div class="form-row">

                        <div class="form-group">
                            <label for="event_title">Event title *</label>
                            <input type="text" id="event_title" name="event_title"
                                   value="<?php echo htmlspecialchars($event['event_title']); ?>"
                                   placeholder="Professional event title" required>
                        </div>

                        <div class="form-group">
                            <label for="career_field">Career field *</label>
                            <select id="career_field" name="career_field" required>
                                <option value="" disabled<?php echo $event['career_field'] === '' ? ' selected' : ''; ?>>
                                    Select category
                                </option>
                                <?php foreach ($career_fields as $value => $label): ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>"
                                        <?php echo ($event['career_field'] === $value) ? ' selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    </div>

                    <div class="form-row">

                        <div class="form-group">
                            <label for="event_date">Date *</label>
                            <input type="date" id="event_date" name="event_date"
                                   value="<?php echo htmlspecialchars($event['event_date']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="start_time">Start and end time *</label>
                            <div class="time-range">
                                <input type="time" id="start_time" name="start_time"
                                       value="<?php echo htmlspecialchars($event['start_time']); ?>" required>
                                <span class="time-separator">to</span>
                                <input type="time" id="end_time" name="end_time"
                                       value="<?php echo htmlspecialchars($event['end_time']); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="event_format">Format *</label>
                            <select id="event_format" name="event_format" required>
                                <option value="" disabled<?php echo $event['event_format'] === '' ? ' selected' : ''; ?>>
                                    In-person / online / hybrid
                                </option>
                                <option value="in_person"<?php echo $event['event_format'] === 'in_person' ? ' selected' : ''; ?>>In-person</option>
                                <option value="online"<?php echo $event['event_format'] === 'online' ? ' selected' : ''; ?>>Online</option>
                                <option value="hybrid"<?php echo $event['event_format'] === 'hybrid' ? ' selected' : ''; ?>>Hybrid</option>
                            </select>
                        </div>

                    </div>

                    <div class="form-row">

                        <div class="form-group">
                            <label for="event_location">Venue or meeting link *</label>
                            <input type="text" id="event_location" name="event_location"
                                   value="<?php echo htmlspecialchars($event['event_location']); ?>"
                                   placeholder="Location details" required>
                        </div>

                        <div class="form-group">
                            <label for="age_restriction">Age restriction</label>
                            <select id="age_restriction" name="age_restriction">
                                <option value="none"<?php echo $event['age_restriction'] === 'none' ? ' selected' : ''; ?>>None</option>
                                <option value="16"<?php echo $event['age_restriction'] === '16' ? ' selected' : ''; ?>>16+</option>
                                <option value="18"<?php echo $event['age_restriction'] === '18' ? ' selected' : ''; ?>>18+</option>
                            </select>
                        </div>

                    </div>

                    <div class="form-group">
                        <label for="event_description">Description *</label>
                        <textarea id="event_description" name="event_description" rows="4"
                                  placeholder="Purpose, speakers, programme and accessibility information"
                                  required><?php echo htmlspecialchars($event['event_description']); ?></textarea>
                    </div>

                    <fieldset class="ticket-tier-group">

                        <legend>Ticket tiers</legend>

                        <div class="ticket-tier-grid">

                            <div class="ticket-tier-input">
                                <label for="tier_general">General</label>
                                <div class="currency-input">
                                    <span class="currency-prefix">R</span>
                                    <input type="number" id="tier_general" name="tier[general]"
                                           value="<?php echo htmlspecialchars($event['tier']['general']); ?>"
                                           min="0" step="0.01" placeholder="0.00">
                                </div>
                            </div>

                            <div class="ticket-tier-input">
                                <label for="tier_vip">VIP</label>
                                <div class="currency-input">
                                    <span class="currency-prefix">R</span>
                                    <input type="number" id="tier_vip" name="tier[vip]"
                                           value="<?php echo htmlspecialchars($event['tier']['vip']); ?>"
                                           min="0" step="0.01" placeholder="0.00">
                                </div>
                            </div>

                            <div class="ticket-tier-input">
                                <label for="tier_early_bird">Early bird</label>
                                <div class="currency-input">
                                    <span class="currency-prefix">R</span>
                                    <input type="number" id="tier_early_bird" name="tier[early_bird]"
                                           value="<?php echo htmlspecialchars($event['tier']['early_bird']); ?>"
                                           min="0" step="0.01" placeholder="0.00">
                                </div>
                            </div>

                            <div class="ticket-tier-input">
                                <label for="tier_student">Student</label>
                                <div class="currency-input">
                                    <span class="currency-prefix">R</span>
                                    <input type="number" id="tier_student" name="tier[student]"
                                           value="<?php echo htmlspecialchars($event['tier']['student']); ?>"
                                           min="0" step="0.01" placeholder="0.00">
                                </div>
                            </div>

                        </div>

                    </fieldset>

                    <div class="form-actions">
                        <a href="manage-events.php" class="btn btn-outline">Cancel</a>
                        <button type="submit" name="action" value="update" class="btn btn-primary">
                            Update event
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