<?php
// EventNest privacy policy. Keep this page aligned with implemented data flows.
$page_title = 'Privacy Policy';
require_once __DIR__ . '/shared-components/header.php';
?>
<main id="main-content" class="container policy-page">
  <h1>EventNest Privacy Policy</h1>
  <p><strong>Last updated: 21 October 2026</strong></p>
  <p>EventNest is a South African career-event discovery and booking platform. This policy explains how information is handled when visitors browse events and when attendees, organisers and administrators use the service.</p>
  <h2>Information we use</h2>
  <p>Depending on the feature used, EventNest may process account details such as name, email address, phone number, career field and account status; event and organiser information; bookings, ticket selections and payment status; organiser verification submissions; post reactions; private reviews; and moderation records. Passwords must be stored as password hashes, not readable text.</p>
  <h2>How information is used</h2>
  <p>Information is used to create and protect accounts, verify organisers, approve and display events, validate ticket eligibility and availability, process bookings and sandbox payments, send transactional notifications, administer the platform, enforce moderation rules and provide requested features.</p>
  <h2>Bookings and payments</h2>
  <p>Free events bypass the paid-payment flow. Paid bookings remain pending until payment success is verified server-side. An unpaid pending booking may be cancelled following explicit PayFast cancellation or the approved four-minute timeout, only if verified payment success has not occurred. Ticket capacity is restored once as part of the successful cancellation transaction. Reaching a browser return page alone does not prove payment success or failure.</p>
  <p>Attendees may self-cancel a booking only while more than 24 hours remain before the event starts. At or after the cutoff, the cancellation action is unavailable and support guidance is shown. Refund and simulated escrow handling follow the implemented database state and applicable event policy. The student-project escrow feature is a simulation and is not represented as a licensed real-world escrow service.</p>
  <h2>Organiser verification</h2>
  <p>Organiser verification may require ID or CIPC documentation according to the applicable verification process. The annual verification fee is R99, with coverage beginning on the successfully verified payment date and lasting 12 months. Fee coverage is separate from verification status. Expiry does not automatically change that status; an administrator reviews and acts where required. Existing published events and bookings are preserved, but new bookings are disabled until coverage is renewed.</p>
  <h2>Personalised recommendations and AI</h2>
  <p>Personalised recommendations are optional and intended for authenticated attendees who opt in and have sufficient profile information. Permitted inputs may include career field, account status, major or job information, selected optional interests and public event attributes such as title, description, category, format, location, date and public hashtags. Names, email addresses, passwords, session information, bookings, payments, transaction identifiers, private reviews, moderation records, verification documents and secret keys must not be sent to the AI provider.</p>
  <p>Recommendation results may be cached for 30 minutes, with earlier invalidation when relevant profile or event eligibility changes. Turning recommendations off immediately stops personalised cache reads and display; existing cache rows may expire naturally. Ordinary search, filters and career-field browsing remain available when recommendations are off or unavailable.</p>
  <h2>Sharing and service providers</h2>
  <p>EventNest uses external services only where required by the implemented features, including PayFast sandbox for payment testing, email delivery through PHPMailer/Gmail SMTP, and the OpenAI Embeddings API for optional recommendations. The project should transmit only the minimum data needed for each service and must not expose credentials in source control or user-facing errors.</p>
  <h2>Retention and security</h2>
  <p>Access is restricted by role and server-side authorisation. We use validation, prepared database statements, output encoding and other safeguards appropriate to the application. Historical booking, payment, review and audit information may need to be retained or anonymised rather than deleted when an account is removed. No online system can guarantee absolute security.</p>
  <h2>Your choices and contact</h2>
  <p>Use the account settings available in EventNest to manage the recommendation preference and other supported account choices. For privacy questions or requests, contact the EventNest team through the contact details published on the website.</p>
  <p>This policy must be reviewed whenever implemented data flows or approved business rules change. It must not promise controls or practices that the deployed system does not actually implement.</p>
</main>
<?php require_once __DIR__ . '/shared-components/footer.php'; ?>
