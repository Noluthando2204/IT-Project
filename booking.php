<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/database-config/connection.php';

// ===== INSERT SHARED COMPONENTS CODE HERE =====
// After Cynthia's files are merged, include the approved shared auth.php
// and functions.php here. The local checks below are kept so this file can
// still be tested in isolation without guessing Cynthia's function names.
//
// require_once __DIR__ . '/shared-components/auth.php';
// require_once __DIR__ . '/shared-components/functions.php';
// ===============================================


/*
 * Escapes text before displaying it on the page.
 */
function booking_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}


/*
 * Redirects the user to another page.
 */
function booking_redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}


/*
 * Creates a CSRF token for the booking form.
 */
function booking_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}


/*
 * Checks that the submitted CSRF token matches the session token.
 */
function booking_valid_csrf(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}


/*
 * Temporary local fallback.
 *
 * Replace this with the approved booking-reference helper in functions.php
 * after the shared component is merged.
 */
function booking_reference(PDO $pdo): string
{
    do {
        $reference = 'EN-' . strtoupper(bin2hex(random_bytes(5)));

        $check = $pdo->prepare(
            'SELECT BookingID
             FROM BOOKINGS
             WHERE Booking_reference = :reference
             LIMIT 1'
        );

        $check->execute([
            'reference' => $reference
        ]);

    } while ($check->fetch());

    return $reference;
}


/*
 * Checks whether the temporary student email uses one of the
 * agreed institutional domains.
 *
 * The email is only used for validation and is not stored.
 */
function student_email_allowed(string $email): bool
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $at_position = strrpos($email, '@');

    if ($at_position === false) {
        return false;
    }

    $domain = strtolower(substr($email, $at_position + 1));

    /*
     * INSERT THE AGREED STUDENT-DOMAIN RULE HERE if the final shared helper
     * stores it centrally. These values reflect the currently agreed
     * EventNest student-ticket rule.
     */
    return str_ends_with($domain, '.ac.za')
        || $domain === 'tuks.co.za';
}


/*
 * Check that the user is logged in.
 */
$user_id = filter_var(
    $_SESSION['user_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$user_id || $user_id < 1) {
    booking_redirect('login.php');
}


/*
 * Load the logged-in user's information from the database.
 *
 * The database is used instead of trusting role or career information
 * sent by the browser.
 */
$user_statement = $pdo->prepare(
    'SELECT
        UserID,
        Email,
        CareerField,
        Role,
        Is_email_verified
     FROM USERS
     WHERE UserID = :user_id
     LIMIT 1'
);

$user_statement->execute([
    'user_id' => $user_id
]);

$current_user = $user_statement->fetch();


/*
 * Only attendees are allowed to make bookings.
 */
if (!$current_user || $current_user['Role'] !== 'attendee') {
    http_response_code(403);
    exit('Only attendee accounts can make bookings.');
}


/*
 * Get the EventID.
 *
 * event-detail.php sends event_id using GET.
 * The booking form sends it back using POST.
 */
$event_id = filter_input(
    INPUT_GET,
    'event_id',
    FILTER_VALIDATE_INT
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_id = filter_input(
        INPUT_POST,
        'event_id',
        FILTER_VALIDATE_INT
    );
}


if (!$event_id || $event_id < 1) {
    http_response_code(400);
    exit('A valid event is required.');
}


/*
 * Load the event.
 *
 * The category name is also loaded because AllowedCareerField identifies
 * the category required by a field-gated event.
 */
$event_statement = $pdo->prepare(
    'SELECT
        e.EventID,
        e.EventName,
        e.EventDate,
        e.AgeRestriction,
        e.Status,
        e.CareerGateType,
        e.AllowedCareerField,
        e.AllowedEmailDomain,
        e.OrganizerID,
        gate_category.CategoryName AS AllowedCareerFieldName
     FROM EVENTS e
     LEFT JOIN CATEGORIES gate_category
        ON gate_category.CategoryID = e.AllowedCareerField
     WHERE e.EventID = :event_id
     LIMIT 1'
);

$event_statement->execute([
    'event_id' => $event_id
]);

$event = $event_statement->fetch();


/*
 * Only published events can be booked.
 */
if (!$event || $event['Status'] !== 'published') {
    http_response_code(404);
    exit('This event is not available for booking.');
}


/*
 * Check whether this attendee has been blocked by the organiser.
 *
 * This uses the email stored in the user's account rather than
 * accepting an email from the booking form.
 */
$block_statement = $pdo->prepare(
    'SELECT BlockID
     FROM BLOCKS
     WHERE OrganizerID = :organizer_id
       AND LOWER(BlockedEmail) = LOWER(:email)
     LIMIT 1'
);

$block_statement->execute([
    'organizer_id' => $event['OrganizerID'],
    'email' => $current_user['Email']
]);


if ($block_statement->fetch()) {
    http_response_code(403);
    exit('You cannot book events from this organiser.');
}


$errors = [];


/*
 * FIELD CAREER GATE
 *
 * Compare the attendee CareerField with the category identified
 * by the event AllowedCareerField.
 */
if ($event['CareerGateType'] === 'field') {

    $required_field = trim(
        (string) $event['AllowedCareerFieldName']
    );

    $user_field = trim(
        (string) $current_user['CareerField']
    );

    if (
        $required_field === ''
        || strcasecmp($required_field, $user_field) !== 0
    ) {
        $errors[] =
            'Your career field does not meet this event requirement.';
    }
}


/*
 * EMAIL DOMAIN CAREER GATE
 *
 * The attendee must have a verified account email using
 * the required domain.
 */
if ($event['CareerGateType'] === 'email_domain') {

    $allowed_domain = strtolower(
        ltrim(
            trim((string) $event['AllowedEmailDomain']),
            '@'
        )
    );

    $email = (string) $current_user['Email'];
    $at_position = strrpos($email, '@');

    $email_domain = '';

    if ($at_position !== false) {
        $email_domain = strtolower(
            substr($email, $at_position + 1)
        );
    }


    if (
        (int) $current_user['Is_email_verified'] !== 1
        || $allowed_domain === ''
        || $email_domain !== $allowed_domain
    ) {
        $errors[] =
            'A verified email from the required domain is needed for this event.';
    }
}


/*
 * Work out whether this event requires age confirmation.
 */
$age_restriction = trim(
    (string) $event['AgeRestriction']
);

$requires_age_confirmation =
    $age_restriction !== ''
    && $age_restriction !== '0';


/*
 * Load the tickets for this event.
 *
 * These are only for displaying the available options.
 * The selected ticket is loaded again and locked during POST.
 */
$ticket_statement = $pdo->prepare(
    'SELECT
        TicketID,
        TicketType,
        Price,
        QuantityAvailable,
        Capacity,
        Description
     FROM TICKETS
     WHERE EventID = :event_id
     ORDER BY Price ASC, TicketType ASC'
);

$ticket_statement->execute([
    'event_id' => $event_id
]);

$tickets = $ticket_statement->fetchAll();


/*
 * Process the submitted booking form.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
     * Check CSRF token.
     */
    if (!booking_valid_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] =
            'Your session expired. Please refresh the page and try again.';
    }


    /*
     * Validate the ticket and quantity IDs.
     */
    $ticket_id = filter_input(
        INPUT_POST,
        'ticket_id',
        FILTER_VALIDATE_INT
    );

    $quantity = filter_input(
        INPUT_POST,
        'quantity',
        FILTER_VALIDATE_INT
    );


    if (!$ticket_id || $ticket_id < 1) {
        $errors[] = 'Please choose a valid ticket.';
    }


    if (!$quantity || $quantity < 1) {
        $errors[] =
            'Quantity must be at least 1.';
    }


    /*
     * Only require age confirmation when the event has
     * an age restriction.
     */
    if ($requires_age_confirmation) {

        if (
            !isset($_POST['age_confirm'])
            || $_POST['age_confirm'] !== '1'
        ) {
            $errors[] =
                'You must confirm that you meet the event age requirement.';
        }
    }


    /*
     * Only continue when the earlier checks passed.
     */
    if (!$errors) {

        try {

            /*
             * Start the transaction so that the selected ticket can
             * be locked while checking and changing availability.
             */
            $pdo->beginTransaction();


            /*
             * Reload and lock the selected ticket.
             *
             * Price and ticket type are taken from the database.
             * They are never accepted from the booking form.
             */
            $locked_ticket_statement = $pdo->prepare(
                'SELECT
                    TicketID,
                    EventID,
                    TicketType,
                    Price,
                    QuantityAvailable,
                    Capacity
                 FROM TICKETS
                 WHERE TicketID = :ticket_id
                 FOR UPDATE'
            );

            $locked_ticket_statement->execute([
                'ticket_id' => $ticket_id
            ]);

            $ticket = $locked_ticket_statement->fetch();


            /*
             * Make sure the selected ticket really belongs
             * to this event.
             */
            if (
                !$ticket
                || (int) $ticket['EventID'] !== (int) $event_id
            ) {
                throw new RuntimeException(
                    'The selected ticket does not belong to this event.'
                );
            }


            /*
             * Check ticket capacity and remaining availability.
             */
            $available_quantity =
                (int) $ticket['QuantityAvailable'];

            $ticket_capacity =
                (int) $ticket['Capacity'];


            if (
                $ticket_capacity < 1
                || $quantity > $ticket_capacity
                || $available_quantity < $quantity
            ) {
                throw new RuntimeException(
                    'There are not enough tickets left for that quantity.'
                );
            }


            /*
             * Student tickets require an institutional email.
             *
             * The temporary email is checked but is not inserted
             * into BOOKINGS.
             */
            if (
                strcasecmp(
                    (string) $ticket['TicketType'],
                    'Student'
                ) === 0
            ) {

                $student_email = trim(
                    (string) ($_POST['student_email'] ?? '')
                );


                if (!student_email_allowed($student_email)) {
                    throw new RuntimeException(
                        'Please enter a valid institutional email for the Student ticket.'
                    );
                }
            }


            /*
             * Calculate the total on the server using the ticket
             * price stored in the database.
             */
            $ticket_price =
                (float) $ticket['Price'];

            $total_amount = round(
                $ticket_price * $quantity,
                2
            );


            /*
             * Free bookings are immediately marked free.
             * Paid bookings remain pending until payment is completed.
             */
            if ($total_amount == 0) {
                $status = 'free';
            } else {
                $status = 'pending';
            }


            /*
             * Temporary booking reference helper.
             *
             * Replace this call with the shared functions.php helper
             * after Cynthia's file has been merged.
             */
            $reference = booking_reference($pdo);


            /*
             * Create the booking.
             *
             * EventID, UserID and TicketID come from validated
             * server/database data.
             */
            $insert_booking = $pdo->prepare(
                'INSERT INTO BOOKINGS
                    (
                        BookingDate,
                        Quantity,
                        TotalAmount,
                        Status,
                        Booking_reference,
                        EventID,
                        UserID,
                        TicketID
                    )
                 VALUES
                    (
                        NOW(),
                        :quantity,
                        :total_amount,
                        :status,
                        :reference,
                        :event_id,
                        :user_id,
                        :ticket_id
                    )'
            );


            $insert_booking->execute([
                'quantity' => $quantity,
                'total_amount' => number_format(
                    $total_amount,
                    2,
                    '.',
                    ''
                ),
                'status' => $status,
                'reference' => $reference,
                'event_id' => $event_id,
                'user_id' => $user_id,
                'ticket_id' => $ticket_id
            ]);


            /*
             * This assumes BookingID is AUTO_INCREMENT.
             *
             * If the final database schema uses another strategy,
             * replace this line with the agreed method.
             */
            $booking_id =
                (int) $pdo->lastInsertId();


            /*
             * Reduce the ticket availability.
             *
             * The WHERE condition gives an extra check in case the
             * availability changed before the update.
             */
            $update_ticket = $pdo->prepare(
                'UPDATE TICKETS
                 SET QuantityAvailable =
                     QuantityAvailable - :quantity
                 WHERE TicketID = :ticket_id
                   AND QuantityAvailable >= :quantity
                   AND Capacity >= :quantity'
            );


            $update_ticket->execute([
                'quantity' => $quantity,
                'ticket_id' => $ticket_id
            ]);


            if ($update_ticket->rowCount() !== 1) {
                throw new RuntimeException(
                    'The ticket availability changed. Please try again.'
                );
            }


            /*
             * Save both database changes.
             */
            $pdo->commit();


            /*
             * Free bookings do not need payment.
             */
            if ($status === 'free') {
                booking_redirect(
                    'payment-success.php?booking_id='
                    . $booking_id
                    . '&free=1'
                );
            }


            /*
             * Paid bookings continue to PayFast/payment handling.
             */
            booking_redirect(
                'payment.php?booking_id=' . $booking_id
            );


        } catch (Throwable $exception) {

            /*
             * Undo the booking and ticket quantity change if
             * something failed.
             */
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }


            error_log(
                'Booking failed: '
                . $exception->getMessage()
            );


            /*
             * Only display errors that are safe for the user to see.
             */
            $safe_messages = [
                'The selected ticket does not belong to this event.',
                'There are not enough tickets left for that quantity.',
                'Please enter a valid institutional email for the Student ticket.',
                'The ticket availability changed. Please try again.'
            ];


            if (
                in_array(
                    $exception->getMessage(),
                    $safe_messages,
                    true
                )
            ) {
                $errors[] = $exception->getMessage();

            } else {

                $errors[] =
                    'The booking could not be completed. Please try again.';
            }
        }
    }
}


/*
 * Generate the token after processing the booking request.
 */
$csrf_token = booking_csrf_token();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Book <?= booking_escape((string) $event['EventName']) ?>
        | EventNest
    </title>

    <!-- INSERT THANDO'S SHARED HEADER/CSS COMPONENT LINKS HERE -->

</head>


<body>

<!-- INSERT SHARED header.php / navbar.php HERE -->


<main>

    <h1>
        Book <?= booking_escape((string) $event['EventName']) ?>
    </h1>


    <?php if ($errors): ?>

        <div class="form-errors" role="alert">

            <ul>

                <?php foreach ($errors as $error): ?>

                    <li>
                        <?= booking_escape($error) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <?php if (!$tickets): ?>

        <p>
            No tickets are currently available for this event.
        </p>

        <p>
            <a
                href="event-detail.php?event_id=<?= (int) $event_id ?>"
            >
                Back to event
            </a>
        </p>


    <?php else: ?>

        <form
            method="post"
            action="booking.php?event_id=<?= (int) $event_id ?>"
            novalidate
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= booking_escape($csrf_token) ?>"
            >

            <input
                type="hidden"
                name="event_id"
                value="<?= (int) $event_id ?>"
            >


            <label for="ticket_id">
                Ticket
            </label>

            <select
                name="ticket_id"
                id="ticket_id"
                required
            >

                <option value="">
                    Select a ticket
                </option>


                <?php foreach ($tickets as $ticket): ?>

                    <?php
                    $selected_ticket =
                        (string) ($_POST['ticket_id'] ?? '')
                        === (string) $ticket['TicketID'];
                    ?>

                    <option
                        value="<?= (int) $ticket['TicketID'] ?>"

                        data-ticket-type="<?=
                            booking_escape(
                                (string) $ticket['TicketType']
                            )
                        ?>"

                        <?=
                            (int) $ticket['QuantityAvailable'] < 1
                                ? 'disabled'
                                : ''
                        ?>

                        <?= $selected_ticket ? 'selected' : '' ?>
                    >

                        <?= booking_escape(
                            (string) $ticket['TicketType']
                        ) ?>

                        -
                        R<?= number_format(
                            (float) $ticket['Price'],
                            2
                        ) ?>

                        (<?= (int) $ticket['QuantityAvailable'] ?> left)

                    </option>

                <?php endforeach; ?>

            </select>


            <label for="quantity">
                Quantity
            </label>

            <input
                type="number"
                id="quantity"
                name="quantity"
                min="1"
                value="<?= booking_escape(
                    (string) ($_POST['quantity'] ?? '1')
                ) ?>"
                required
            >


            <div id="student-email-area">

                <label for="student_email">
                    Student email
                    (Student tickets only)
                </label>

                <input
                    type="email"
                    id="student_email"
                    name="student_email"
                    value="<?= booking_escape(
                        (string) ($_POST['student_email'] ?? '')
                    ) ?>"
                >

            </div>


            <?php if ($requires_age_confirmation): ?>

                <label>

                    <input
                        type="checkbox"
                        name="age_confirm"
                        value="1"
                        <?=
                            isset($_POST['age_confirm'])
                                ? 'checked'
                                : ''
                        ?>
                    >

                    I confirm that I meet the event age requirement:
                    <?= booking_escape($age_restriction) ?>

                </label>

            <?php endif; ?>


            <button type="submit">
                Continue
            </button>


            <a
                href="event-detail.php?event_id=<?= (int) $event_id ?>"
            >
                Back to event
            </a>

        </form>

    <?php endif; ?>


    <p>
        <a href="my-bookings.php">
            My Bookings
        </a>
    </p>

</main>


<!-- INSERT SHARED footer.php HERE -->


<!-- INSERT scripts/validation.js HERE -->
<!-- INSERT scripts/student-email-check.js HERE -->


</body>
</html>