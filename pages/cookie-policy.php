<?php

declare(strict_types=1);

require_once __DIR__ . '/../shared-components/functions.php';

$page_title = 'Cookie Policy';

require_once __DIR__ . '/../shared-components/header.php';
?>

<main class="page-shell legal-page">
    <header class="page-header">
        <p class="eyebrow">Legal</p>
        <h1>Cookie Policy</h1>
        <p>
            This policy explains the limited browser cookies and session
            storage used by the EventNest prototype.
        </p>
    </header>

    <section class="content-card" aria-labelledby="cookies-what">
        <h2 id="cookies-what">1. What EventNest uses</h2>

        <p>
            EventNest uses essential PHP session cookies so the website can
            keep a user signed in, recognise the current session and protect
            supported form actions such as CSRF-protected requests.
        </p>

        <p>
            These cookies are required for core account and security behaviour.
            They are not used to create a public profile, follow hashtags or
            search for people.
        </p>
    </section>

    <section class="content-card" aria-labelledby="cookies-essential">
        <h2 id="cookies-essential">2. Essential session cookies</h2>

        <p>
            The PHP session cookie stores a session identifier in the browser.
            The actual account role, user identifier and other protected session
            values are managed by the server-side session rather than being
            trusted from values submitted by the browser.
        </p>

        <p>
            Session cookies may also support security controls such as CSRF
            tokens. EventNest uses these controls to help ensure that a form
            submission belongs to the active session.
        </p>
    </section>

    <section class="content-card" aria-labelledby="cookies-preferences">
        <h2 id="cookies-preferences">3. Preferences and analytics</h2>

        <p>
            The current EventNest prototype does not claim a separate
            advertising-cookie or analytics-cookie system unless such a feature
            has actually been added to the deployed version.
        </p>

        <p>
            There is no misleading “Accept all advertising cookies” control in
            the current prototype. If non-essential cookies are introduced
            later, this policy and the user interface must be updated to
            describe them accurately before they are used.
        </p>
    </section>

    <section class="content-card" aria-labelledby="cookies-control">
        <h2 id="cookies-control">4. Browser controls</h2>

        <p>
            You can use your browser settings to clear or block cookies.
            Blocking the essential EventNest session cookie may prevent login,
            protected account features and security-protected forms from
            working correctly.
        </p>
    </section>

    <section class="content-card" aria-labelledby="cookies-related">
        <h2 id="cookies-related">5. Related information</h2>

        <p>
            For more information about how EventNest handles personal and
            system data, read the
            <a href="privacy-policy.php">Privacy Policy</a>.
            The platform conditions are available in the
            <a href="terms.php">Terms and Conditions</a>.
        </p>
    </section>
</main>

<?php
require_once __DIR__ . '/../shared-components/footer.php';
?>