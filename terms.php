<?php

declare(strict_types=1);

require_once __DIR__ . '/shared-components/functions.php';

$page_title = 'Terms and Conditions';

require_once __DIR__ . '/shared-components/header.php';
?>

<main class="page-shell legal-page">
    <header class="page-header">
        <p class="eyebrow">Legal</p>

        <h1>EventNest Terms and Conditions</h1>

        <p>
            These terms explain the rules for using the EventNest prototype,
            including organiser verification, events, bookings, payments,
            cancellations, escrow, reviews and misuse.
        </p>
    </header>

    <div class="policy-layout"><nav class="policy-contents" aria-label="On this page"><h2>On this page</h2><a class="sidebar-link" href="#terms-accounts">Accounts and acceptable use</a><a class="sidebar-link" href="#terms-organisers">Organiser verification and annual coverage</a><a class="sidebar-link" href="#terms-events">Events and public content</a><a class="sidebar-link" href="#terms-bookings">Bookings and ticket availability</a><a class="sidebar-link" href="#terms-payments">Payments, pending bookings and timeout</a><a class="sidebar-link" href="#terms-cancellations">Attendee cancellations and refunds</a><a class="sidebar-link" href="#terms-escrow">Escrow and event completion</a><a class="sidebar-link" href="#terms-reviews">Reviews and reports</a><a class="sidebar-link" href="#terms-liability">Prototype availability</a><a class="sidebar-link" href="#terms-related">Related information</a></nav><div>    <section class="content-card" aria-labelledby="terms-accounts">
        <h2 id="terms-accounts">1. Accounts and acceptable use</h2>

        <p>
            Users must provide accurate account information and must not misuse
            EventNest, attempt to access another person's account, bypass access
            controls, interfere with the website, or use the platform for
            unlawful or abusive activity.
        </p>

        <p>
            EventNest may restrict access where the implemented moderation and
            suspension rules apply. Organisers may block an attendee in supported
            booking or review contexts, and repeated blocks from distinct
            organisers may lead to suspension under the platform rules.
        </p>
    </section>

    <section class="content-card" aria-labelledby="terms-organisers">
        <h2 id="terms-organisers">
            2. Organiser verification and annual coverage
        </h2>

        <p>
            Organiser accounts require verification before protected organiser
            management actions are available. Verification status is controlled
            through the EventNest verification and administrator review process.
        </p>

        <p>
            The organiser-verification coverage fee is R99 for 12 months from
            the verified payment date. Expiry of that fee coverage does not
            automatically change an organiser's verification status.
            Administrator review or another authorised status action remains
            separate from payment coverage.
        </p>

        <p>
            If annual verification coverage expires, the organiser's existing
            public events remain visible and existing bookings remain intact,
            but new bookings for that organiser's events are disabled until the
            required coverage is renewed.
        </p>
    </section>

    <section class="content-card" aria-labelledby="terms-events">
        <h2 id="terms-events">3. Events and public content</h2>

        <p>
            Organisers may create event-linked posts only for events that belong
            to them. EventNest supports written event updates, images and videos
            linked to an event. Public comments, public text replies, hashtag
            following and unrelated standalone posts are not part of the
            supported system.
        </p>

        <p>
            Draft and canceled events are not publicly listed. Public event and
            organiser-post pages show only content that is allowed by the
            event's current status.
        </p>

        <p>
            Organisers are responsible for the accuracy of the event information
            they provide. EventNest does not create a separate attendance or
            check-in record and does not independently prove that a person
            physically attended an event.
        </p>
    </section>

    <section class="content-card" aria-labelledby="terms-bookings">
        <h2 id="terms-bookings">4. Bookings and ticket availability</h2>

        <p>
            A booking is subject to the event's availability, ticket quantity
            and any applicable eligibility rules. EventNest may require
            career-field, email-domain, student or age-related checks before a
            booking can proceed.
        </p>

        <p>
            Ticket quantities shown by the system may change as bookings are
            made, canceled, paid or refunded. A booking is not treated as
            successfully paid merely because a user reaches a payment page.
        </p>
    </section>

    <section class="content-card" aria-labelledby="terms-payments">
        <h2 id="terms-payments">
            5. Payments, pending bookings and timeout
        </h2>

        <p>
            The EventNest prototype uses PayFast sandbox payment flows for
            testing. A paid booking is confirmed only after EventNest receives
            and verifies a successful payment result.
        </p>

        <p>
            An unpaid booking may remain pending for up to four minutes. If
            there is no verified payment success by the end of that period,
            the pending booking may be canceled by the payment-timeout process.
        </p>

        <p>
            If a user explicitly cancels a pending payment before verified
            success, EventNest changes the booking from pending to canceled and
            restores the reserved ticket quantity only when that status change
            succeeds in the same database transaction. Repeated cancellation
            attempts must not restore the same ticket quantity more than once.
        </p>

        <p>
            If verified payment success is received, that verified success takes
            priority over a late cancel or timeout attempt. A booking that has
            already been verified as successfully paid must not be canceled as
            an unpaid booking by the timeout process.
        </p>
    </section>

    <section class="content-card" aria-labelledby="terms-cancellations">
        <h2 id="terms-cancellations">
            6. Attendee cancellations and refunds
        </h2>

        <p>
            An attendee may cancel an eligible booking only when the event is
            more than 24 hours away. At or after the 24-hour cutoff, the
            EventNest backend rejects the attendee cancellation request.
        </p>

        <p>
            Refund handling depends on the booking and payment status. A
            canceled or refunded record is processed according to the
            implemented booking, payment and escrow rules rather than by
            changing values directly in the browser.
        </p>
    </section>

    <section class="content-card" aria-labelledby="terms-escrow">
        <h2 id="terms-escrow">7. Escrow and event completion</h2>

        <p>
            Where a verified successful payment is held in escrow, release or
            refund follows the implemented event, payment and administrative
            process. Organisers confirm event completion through EventNest, but
            that confirmation is not independent proof of physical attendance.
        </p>

        <p>
            EventNest administrators may investigate a report or dispute before
            held escrow is released. If an event or payment outcome requires a
            refund under the implemented rules, the booking, payment and escrow
            records must reflect that authorised outcome.
        </p>
    </section>

    <section class="content-card" aria-labelledby="terms-reviews">
        <h2 id="terms-reviews">8. Reviews and reports</h2>

        <p>
            Eligible attendees may submit the supported private review after an
            event. Reviews are not public comments and are not a public reply
            system.
        </p>

        <p>
            Reports about suspicious events or organisers are handled through
            the supported EventNest reporting/contact workflow. EventNest may
            investigate relevant information before taking an administrative
            or escrow action.
        </p>
    </section>

    <section class="content-card" aria-labelledby="terms-liability">
        <h2 id="terms-liability">9. Prototype availability</h2>

        <p>
            EventNest is being developed as an academic prototype. Features may
            be tested in local or system-integration environments, and test
            payment flows may use sandbox data. Users should not treat prototype
            test transactions as real commercial payments.
        </p>
    </section>

    <section class="content-card" aria-labelledby="terms-related">
        <h2 id="terms-related">10. Related information</h2>

        <p>
            Read the
            <a href="privacy-policy.php">Privacy Policy</a>
            and
            <a href="cookie-policy.php">Cookie Policy</a>
            for information about data and session cookies.
            For support or reporting guidance, use the
            <a href="contact.php">Contact page</a>.
        </p>
    </section>
</div></div></main>

<?php
require_once __DIR__ . '/shared-components/footer.php';
?>
