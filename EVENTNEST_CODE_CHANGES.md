# EventNest Code Changes

Date: 23 October 2026. Branch: `tino-backend`. Work is local and uncommitted; no push, merge, PR or deployment was performed.

## 1. Summary

Read the entire uploaded audit Markdown before making repository changes. Audited the current application, Coding Guide, schema and seed. The user confirmed the local Downloads Coding Guide and updated wireframe references. Inspected all 54 screen images in the guide's visual appendix. Used the local 2 October project explanation DOCX to resolve the annual R99 fee, 12-month coverage and 5% commission rules.

Main defects included empty role-management pages, misplaced/missing root routes, broken shared-layout and JavaScript contracts, unsafe status-only account deletion, missing pending-booking expiry and incomplete post/review/account/verification-fee workflows. Completed these within the existing PHP/PDO/shared-component architecture.

The working tree was already dirty: nine JS/layout/CSS files had changes, root `.htaccess` was deleted, and the backup plus AI service were untracked. Existing contributions were reviewed and integrated rather than reset. The original backup remains local and ignored. This inventory accounts for the complete final Git worktree, including inherited changes. The AI service was completed from the existing untracked file.

The guide's older `organizer`, `RoleStatus`, demographic UserStatus values, `cancelled`, `free/confirmed`, `Booking_reference` and `Payfast_transaction_id` conflict with the approved repository schema. Following the user's explicit read-only-schema instruction, code uses `organiser`, `VerificationStatus`, `active/suspended/deleted`, `canceled`, `BookingReference` and `TransactionID`. Zero-value bookings use `paid` with a zero total; completed bookings retain `paid` because no free/confirmed enum exists. The actual existing job folder is `scheduled-tasks/`; no second `scheduled-taks/` folder was created.

Code and isolated checks are substantially complete. Release status remains partial because rendered browser/responsive checks, genuine external integrations, Docker and Render SIT could not be verified here.

## 2. Files Modified

Each entry records the actual change and its reason.

### `.gitignore`

Changes made: excluded environment variants, Composer vendor and backups; retained credential/upload protections.

Reason: prevent local configuration and uploaded/dependency files entering Git.

### `.htaccess`

Changes made: reinstated root protection, disabled directory listing, denied internal source/config paths and retained extensionless PHP routing.

Reason: the pre-existing deletion removed required server protection.

### `Dockerfile`

Changes made: added Composer installation, unzip, Apache overrides and writable private/public upload directories; retained required PHP extensions.

Reason: support the application's actual dependencies and protection rules on Render.

### `admin-panel/dashboard.php`

Changes made: completed database totals, pending verification/event summaries and admin navigation.

Reason: replace the empty admin landing page and correct role destination.

### `admin-panel/manage-bookings.php`

Changes made: added shared admin navigation and student-style block comments; retained existing prepared booking/payment queries and guarded escrow actions.

Reason: integrate the existing support view without replacing working backend logic.

### `admin-panel/manage-businesses.php`

Changes made: implemented organiser listing/search, verification/fee state, event counts and management links.

Reason: complete the empty organiser-account administration view using supported fields.

### `admin-panel/manage-events.php`

Changes made: implemented validated filters, locked publication, admin-only featured toggling and shared cancellation links.

Reason: provide event approval without giving organisers publication privileges.

### `admin-panel/manage-users.php`

Changes made: implemented escaped account listings, search, calculated suspension/permanent-ban state and investigation links.

Reason: replace the placeholder without exposing hashes or inventing a banned enum.

### `admin-panel/manage-verifications.php`

Changes made: added shared navigation, compared the locked document path before a decision, retained protected MIME-checked retrieval, connected decision email and removed implementation notes from the product screen.

Reason: prevent stale/private-document approval errors and communicate rejection guidance without adding a column.

### `block-user.php`

Changes made: implemented owned booking context, duplicate prevention, distinct-organiser counting, suspension cycles and optional notification.

Reason: apply approved moderation rules without one organiser manufacturing repeated suspensions.

### `booking.php`

Changes made: connected four-minute cleanup, locked pending-reservation reuse, free-submission token rotation and ticket/summary layout.

Reason: prevent duplicate reservations and stale stock while retaining existing server gates/price/quantity checks.

### `cancel-event.php`

Changes made: corrected canonical links, added role navigation and permitted authenticated admins to use the existing cancellation flow with database-derived ownership.

Reason: reuse transactional cancellation/refund-preservation logic rather than create a duplicate route.

### `database-config/connection-template.php`

Changes made: added optional DB_SSL_CA, required environment checks and South African database time zone; retained native PDO prepares and generic failures.

Reason: support secure Render configuration and consistent booking cutoffs.

### `delete-account.php`

Changes made: added password verification, locked obligation checks, anonymisation, cache/document removal and logout; blocked pending fees and financial/moderation obligations.

Reason: preserve historical relationships and prevent unsafe status-only deletion.

### `event-confirm.php`

Changes made: corrected canonical links, added organiser navigation and shared confirmation styling, and normalised comments.

Reason: connect completion/review invitations while retaining schema-supported booking states.

### `event-detail.php`

Changes made: arranged event content beside ticket controls and event information.

Reason: match Screen 07's responsive columns without replacing backend eligibility or adding an image field.

### `includes/auth.php`

Changes made: delegated the old empty include to canonical shared authentication.

Reason: avoid inconsistent legacy authentication.

### `includes/footer.php`

Changes made: delegated to the canonical shared footer.

Reason: avoid duplicate layout behavior.

### `includes/header.php`

Changes made: delegated to the canonical shared header.

Reason: avoid duplicate layout/helper contracts.

### `index.php`

Changes made: adapted service reason/fallback metadata, made opted-out discovery neutral, removed a duplicate ID and added the approved logo hero fallback.

Reason: connect consent, recommendations and empty-state branding correctly.

### `login.php`

Changes made: corrected role redirects, retained generic account errors, labelled/styled inputs and allowed login without an unavailable email-verification workflow.

Reason: make new accounts usable while keeping verified-email event gates enforced separately.

### `logout.php`

Changes made: replaced immediate GET logout with CSRF-protected confirmation POST and session/cookie cleanup.

Reason: follow the approved logout confirmation flow.

### `organiser-posts.php`

Changes made: loaded canonical auth for current navigation roles and safely handled non-scalar post filters.

Reason: avoid stale session display and malformed-input warnings.

### `payment-cancel.php`

Changes made: corrected My bookings links to the root route.

Reason: retain the existing ownership/CSRF/idempotent cancellation handler after migration.

### `payment-success.php`

Changes made: centralised PayFast helpers, rejected a second successful transaction for one booking and preserved late charges for canceled/sold-out bookings without overselling; corrected links.

Reason: protect payment truth, inventory and released/refunded escrow from replay or late callbacks.

### `payment.php`

Changes made: ran expiry cleanup before checkout, required trusted HTTPS callback configuration and used common signature/URL helpers.

Reason: prevent expired checkout and untrusted Host-derived callbacks.

### `post.php`

Changes made: implemented event ownership, text/media validation, MIME/extension/size/generated-path checks and transactional dependency-safe deletion.

Reason: complete event-linked posts using the approved tables only.

### `register.php`

Changes made: centralised attendee/organiser registration, supported profile fields, validated unique email/passwords, controlled role/status server-side and captured optional consent.

Reason: complete registration and separate organiser account creation from document submission.

### `review.php`

Changes made: implemented ownership/completion/duplicate checks, rating-specific tags/comment validation and consent-limited contact fields.

Reason: satisfy the existing private-review constraints.

### `scheduled-tasks/check-suspension-reset.php`

Changes made: moved the existing root implementation here; kept CLI-only access and reset elapsed dates/block cycles without reducing suspension history.

Reason: use the single existing job folder and remove duplicate responsibility.

### `scheduled-tasks/escrow.php`

Changes made: normalised explanatory comments; retained its existing locked status/waiting-period/admin-or-CLI release/refund rules.

Reason: keep one authoritative processor for the completed admin view and avoid fabricated provider operations.

### `scripts/main.js`

Changes made: repaired search selectors/JSON, stale requests and keyboard navigation; connected confirmations, gate/review controls, media preview and booking estimates.

Reason: match actual page contracts while leaving PHP authoritative.

### `scripts/reactions.js`

Changes made: repaired widget/button selectors and add/change/remove response handling, updated server-returned totals and ARIA state, and guarded in-flight requests.

Reason: fix incompatible frontend/backend reaction contracts.

### `scripts/review-form.js`

Changes made: implemented immediate positive-rating tag-group validation and focus feedback.

Reason: complete conditional review guidance.

### `scripts/student-email-check.js`

Changes made: matched the real wrapper, toggled visibility/required state, reset validity and checked institutional domain boundaries.

Reason: make Student selection usable and reject suffix lookalikes consistently.

### `scripts/tag-follow.js`

Changes made: documented that following is unsupported; no follow behavior was added.

Reason: retain the file without introducing prohibited functionality.

### `scripts/validation.js`

Changes made: corrected required radio groups, hidden/disabled inputs, integer quantities/capacities and student-domain boundaries in the inherited validator.

Reason: repair client feedback without substituting it for server validation.

### `search-results.php`

Changes made: normalised block comments; retained prepared search/filter/suggestion behavior.

Reason: preserve the correct backend while main.js was fixed to consume its actual contract.

### `shared-components/auth.php`

Changes made: added supported profile/consent lookup fields, used trusted HTTPS configuration for secure cookies, rejected redirect newlines and corrected verification links.

Reason: support private account pages and Render proxy sessions while retaining database authorisation.

### `shared-components/footer.php`

Changes made: matched the shell wrapper, retained shared JavaScript loading and linked About to the real page.

Reason: close layout correctly and connect public destinations.

### `shared-components/functions.php`

Changes made: added shared layout/ID/POST/CSRF/sidebar/mail/eligibility/expiry helpers, South African time, common PayFast verification, trusted URLs and annual R99 state transitions.

Reason: centralise required integration without inventing database structure or duplicate endpoints.

### `shared-components/header.php`

Changes made: replaced undefined session/flash calls, connected existing escape/URL helpers and used a layout div instead of nested main elements.

Reason: fix shared-shell runtime failures and semantic structure.

### `shared-components/navbar.php`

Changes made: removed the filter-array variable collision, isolated navbar variables and added active-page/logout navigation.

Reason: fix hashtag-page runtime errors and maintain role destinations.

### `styles/main.css`

Changes made: styled actual existing classes, navy sidebars, forms/tables/statuses, event/checkout/support columns, media fallback and policy navigation; retained the approved palette.

Reason: apply the wireframe system to actual markup rather than leave pages unstyled.

### `styles/responsive.css`

Changes made: added tablet/mobile rules for event/account/checkout/policy/support structures, reachable navigation, proportional media and container table scrolling.

Reason: preserve required actions and readable layouts on smaller screens.

### `uploaded-content/.htaccess`

Changes made: added script/CGI denial, handler removal and approved static-extension restrictions; retained listing/private-document denial.

Reason: prevent execution of uploaded server scripts.

### `verification.php`

Changes made: corrected canonical links, added validated annual-fee notifications and checkout before session-only document processing, and retained independent private-upload validation.

Reason: complete verification dependencies using existing USERS fee fields and no booking-linked fee payment rows.

## 3. Files Created

These are Git-new paths. Migrated root pages replace their old `pages/` locations; they are not duplicate endpoints. Each entry states its purpose, required responsibility and interactions.

- `.dockerignore`: protects the Docker build context; used by Dockerfile to exclude local secrets, review files and user uploads.
- `about.php`: required factual platform information; connects shared layout, events, organiser registration and contact.
- `admin-panel/categories.php`: required category CRUD with all approved reference checks; interacts with CATEGORIES, EVENTS, Post_Categories, career-field text and recommendation cache.
- `admin-panel/escrow.php`: required payment/escrow view with totals and cent-conserving documented commission estimates; calls the existing job handler and links booking/event context.
- `admin-panel/moderation.php`: required blocks/suspension investigation view; joins BLOCKS/USERS and links admin management without inventing reports.
- `business-dashboard.php`: migrated/completed private organiser overview; connects login to verification/account/event/booking/post/review management.
- `business-register.php`: migrated organiser-intent entry into register.php; documents are submitted separately after login.
- `career-fields.php`: required database category browsing; links real CategoryID values to events.php.
- `composer.json`: declares PHPMailer and PHP compatibility for existing approved SMTP workflows; used by Docker and shared/event mail helpers.
- `contact.php`: migrated/completed support form with CSRF and server-selected recipient; uses shared mail and layout.
- `cookie-policy.php`: migrated existing essential-cookie policy with contents navigation; links shared policy pages.
- `create-event.php`: migrated/completed event/ticket form with ownership, transactions and constraints; also supplies editing mode.
- `create-post.php`: migrated/completed owned event composer, upload preview and deletion controls; submits to post.php.
- `edit-event.php`: migrated entry selecting edit mode in create-event.php; avoids duplicate validation.
- `faq.php`: required approved-scope answers using native disclosure controls; connects public guidance and support.
- `leave-review.php`: migrated/completed eligible private review form; submits to review.php and uses review-form.js.
- `manage-bookings.php`: required root organiser booking view; joins only owned events and supplies verified context to block-user.php.
- `manage-events.php`: required root owned-event listing; connects create/edit/cancel/complete/booking workflows.
- `manage-reviews.php`: migrated/completed private organiser filters, summary cards and consent-limited contact fields; joins REVIEWS through owned EVENTS.
- `my-bookings.php`: migrated/completed attendee history, expiry, retry/review/report and cancellation; uses booking/payment helpers and existing tables.
- `organiser-account.php`: required organiser mode of the supported-field profile form; connects verification and deletion.
- `organiser-verification.php`: migrated/completed document/status/fee form; submits to verification.php.
- `privacy-policy.php`: migrated/completed accurate privacy guidance; describes actual consent, private data and retained financial history.
- `profile.php`: required session-owned profile updates and cache invalidation; also supplies organiser account mode.
- `report-event.php`: required authenticated support-email report with verified event/owned booking context; no report table is created.
- `scheduled-tasks/check-pending-bookings.php`: directly required CLI expiry dependency; calls the same transactional cleanup helper as interactive pages.
- `settings.php`: required password/consent/session/deletion controls; interacts with USERS, recommendations and canonical account handlers.
- `shared-components/ai-recommendation-service.php`: completed the initially untracked central optional embedding/cache/SQL fallback service; called by index.php and controlled through existing account consent.
- `terms.php`: migrated existing terms with in-page contents; describes actual prototype payment/cancellation/escrow scope.
- `user-dashboard.php`: migrated/completed session-owned attendee overview; connects login to bookings/profile/settings.
- `EVENTNEST_CODE_CHANGES.md`: this required final change/configuration/test/limitations inventory, checked against Git status.

## 4. Files Deleted

The following misplaced/empty/duplicate paths were removed after repository callers were migrated. Replacements are canonical root routes, not copied duplicate endpoints.

| Deleted path | Replacement and reason |
| --- | --- |
| `check-suspension-reset.php` | `scheduled-tasks/check-suspension-reset.php`; duplicate root job responsibility. |
| `pages/business-dashboard.php` | `business-dashboard.php`; Coding Guide root placement. |
| `pages/business-register.php` | `business-register.php`; centralised registration and root placement. |
| `pages/contact.php` | `contact.php`; root placement and completion. |
| `pages/cookie-policy.php` | `cookie-policy.php`; root placement. |
| `pages/create-event.php` | `create-event.php`; root placement and completion. |
| `pages/create-post` | `create-post.php`; canonical filename/extension and completion. |
| `pages/edit-event.php` | `edit-event.php`; root placement and shared form. |
| `pages/leave-review.php` | `leave-review.php`; root placement and completion. |
| `pages/manage-reviews.php` | `manage-reviews.php`; root placement and completion. |
| `pages/my-bookings.php` | `my-bookings.php`; root placement and completed actions. |
| `pages/organiser-verification.php` | `organiser-verification.php`; root placement and completion. |
| `pages/privacy-policy.php` | `privacy-policy.php`; root placement and completion. |
| `pages/terms.php` | `terms.php`; root placement. |
| `pages/user-dashboard.php` | `user-dashboard.php`; root placement and completion. |

## 5. Files Inspected but Not Changed

- `database/eventnest_schema.sql` and `database/eventnest_seed.sql`: inspected as sources of truth and intentionally NOT modified, including approved dates, hashes and data.
- `events.php`: retained existing prepared filters/pagination; shared CSS styles its real markup.
- `organiser-profile.php`: retained public verified-organiser/event/post history and safe media; shared CSS adds the reference columns.
- `post-detail.php`: retained public eligibility, safe media and reaction markup; corrected its client script contract.
- `tag.php`: retained validated prepared hashtag/event/post filtering; its runtime failure was fixed in navbar scope.
- `reaction.php`: retained correct attendee/CSRF/block/visibility checks and JSON response contract.
- `database-config/.htaccess`: retained direct-browser configuration denial.
- `images/eventnest-logo.png`: approved PNG unchanged; no replacement was generated.
- `.github/workflows/eventnest-checks.yml` and `README.md`: inspected without unrelated edits.
- Ignored `database-config/connection.php`: not edited or copied into tests. Its configured database was offline; local setup must use the secure template/environment and keep the file uncommitted.
- Legacy root `auth.php` and `functions.php` are absent. Existing includes now delegate to canonical shared components.

## 6. Database Schema and Seed Confirmation

`eventnest_schema.sql` was NOT modified. `eventnest_seed.sql` was NOT modified. Application queries, mappings and status interpretation were corrected against their existing definitions.

Both SHA-256 hashes match the pre-edit baseline. Final Git status/diff has no changes to either file. Both unchanged files loaded successfully into isolated MariaDB 10.4.32. Additional records/future dates used during testing existed only in the disposable database; current project records and approved seed dates were not altered.

## 7. Wireframe Changes

All 54 screen images in the approved guide appendix were visually inspected. This table describes implemented structures, not a claim that browser screenshots passed. Shared colours, supplied PNG, controls, focus states and responsive stacking apply throughout.

| Screens | PHP files / area | Implemented visual changes |
| --- | --- | --- |
| 01-04, 29 | Shared layout, `index.php` | Consistent logo/header/footer, orange search, hero/fallback, featured/recommendation cards, neutral opt-out display and keyboard suggestions. |
| 05-06, 30 | `events.php`, `search-results.php`, `tag.php` | Actual filter/table/card classes, tabs, hashtags, pagination and empty states; server filtering retained. |
| 07 | `event-detail.php` | Event content beside tickets/information, stacking on mobile. |
| 08, 31 | Public organiser/profile/posts pages | Event/recent-post columns, media grids, badges and history without unsupported profile fields. |
| 09-10, 32 | `post-detail.php` | Styled text/image/video display and connected all three real reaction controls; no comments. |
| 11-12, 19 | Login/registration/verification pages | Labelled forms, organiser guidance panel, separate document step, annual-fee status and checkout. Unsupported demographic status fields were not invented. |
| 13, 17 | Attendee dashboard/bookings | Navy sidebar, actual upcoming/history records, statuses/references and valid retry/review/report/cancel actions. |
| 14-16 | Booking/payment/result/cancel | Ticket/summary split, quantity estimate, Student/age guidance and genuine pending/confirmed/cancellation states. |
| 18, 28 | Private review form/dashboard | Conditional fields, privacy guidance, owned review filters/cards and actual summary counts. |
| 20-24 | Organiser dashboard/event/post/booking pages | Navy navigation, actual totals, multi-section event/ticket forms, management tables and event-linked composer/media preview. |
| 25-27, 47-51 | Admin pages | Consistent navigation, real summary cards, filters/tables/forms, protected document decisions and guarded actions. |
| 33, 45-46 | Account/profile/settings | Supported-field forms, readonly email/status, consent controls, verification guidance and separate danger zones. |
| 34, 40-44 | Policies/about/contact/FAQ | Shared cards, contents navigation, native FAQ disclosures, support panels and responsive privacy columns. |
| 35-38 | Logout/cancel/report | Confirmations, common action colours, impact/refund guidance and database-backed report context. |
| 39, 52-54 | Career fields and shared responsive system | Database career cards, connected role routes, stacked narrow-screen content and container table scrolling. |

No sample user/event data was hard-coded, and no replacement logo, wishlist, hashtag following, public comments or unsupported organiser profile fields were added. Exact PDF/browser visual comparison remains outstanding in section 17.

## 8. Linking and Navigation Fixes

Migrated `pages/` responsibilities to approved root filenames and updated source callers, form actions, redirects and invitation links. The extensionless composer file became `create-post.php`. Organiser management remains distinct from `admin-panel/`. Login uses the trusted role dashboard; logout has its confirmation destination; public footer targets now exist.

Canonical shared components own includes, branding, escaping and URL helpers. Admin assets use the common base URL. Search consumes the actual `suggestions` response and form attributes. Reactions consume the actual widget attributes and `success/totals/current_reaction` response. Student visibility uses `data-student-email-field`. Navbar variables no longer overwrite hashtag filter arrays.

Static checks found no missing checked literal PHP/include/CSS/JS/image targets, excluding intentionally optional uninstalled Composer autoload. Dynamic role/asset paths were also exercised over HTTP. No duplicate job folder, auth implementation or substitute route was created.

## 9. Database Corrections

All changes use approved schema fields and prepared inputs. Role/status spellings follow section 1. Event gates map to `CareerGoalType`, category identifiers to `AllowedCareerField`, and verified domain checks to `AllowedEmailDomain`/`Is_email_verified`. Event imagery uses linked POSTS rather than a new cover column.

Reviews enforce rating-specific Comment/Tags and consent constraints. Category deletion checks EVENTS.CategoryID, EVENTS.AllowedCareerField and Post_Categories.category_id. Rename preserves existing text CareerField meaning and clears recommendation caches. Ticket/event relationships and historical prices are revalidated. Annual fees update USERS only; PAYMENTS remains booking-linked. Existing booking/payment/escrow enums and constraints are preserved. No schema or seed changes were used to make tests pass.

## 10. Authentication and Security Changes

Canonical auth refreshes database roles, account/verification/fee and suspension state. Protected pages derive identity from the session and check resource ownership separately. Login regenerates sessions and uses generic denial messages. Accounts can log in without an unavailable email-verification process; verified-email event gates still use trusted database verification.

State-changing forms enforce CSRF. Provider notifications instead require merchant, signature and server-provider validation. Logout GET is read-only. Password changes/deletion require the current password. Profile forms cannot submit privileged role/status/coverage values. Deletion preserves financial/booking/review relationships and checks actual obligations; emailed-report investigations remain a manual support prerequisite because no report-hold field exists.

Uploads independently check authorisation, actual MIME, extension, size, generated name and destination. Verification documents remain outside the document root, with admin-only MIME/path-checked retrieval; legacy reads remain bounded. Post deletion removes dependents transactionally. Upload/config/source protection and Git/Docker exclusions were hardened. No actual credential or key was added to source or this report.

## 11. Booking and Payment Changes

Paid bookings start pending; zero-value bookings use schema-supported paid status and bypass PayFast. Totals and ticket relationships are server-derived. Transactions/conditional updates protect stock. Matching pending retries reuse BookingID/reference. Shared four-minute cleanup restores stock only after its successful state transition and never overwrites verified success/refund history. It is called by booking/payment/bookings/dashboard pages and a CLI runner.

Attendee cancellation requires more than 24 hours before a published event. Paid cancellations retain successful payment/escrow state for verified refund review. Whole-event cancellation retains historical records and uses the existing shared owner/admin workflow. Callbacks reject mismatched merchant/signature/amount, cross-booking transaction reuse and duplicate successful effects. Replays preserve released/refunded states. A late charge for sold-out restored stock or a canceled event stays recorded/held for refund review without reviving an unfulfillable booking.

Annual fees use the documented R99 amount and 12 months from verified success. Existing `verification.php` handles checkout/notification; browser return does not grant coverage. Pending retry reuses the current fee reference. Replayed success cannot extend coverage again, and later failure cannot downgrade paid coverage. No fee row is inserted into booking-linked PAYMENTS.

Escrow release remains a prototype database decision requiring completed event, paid/successful held payment, at least 24 hours and explicit admin approval. Emailed investigations require manual review. The admin view displays documented 5% commission/share estimates; neither these nor release claim a real transfer. Actual provider refunds are unavailable: browser actions never fabricate refunded states. Use the existing trusted verified-refund CLI process only after independently verified provider evidence.

External setup still required: sandbox merchant configuration/passphrase, trusted HTTPS base URL, reachable booking notify `payment-success.php` and fee notify `verification.php`, plus genuine provider/replay/refund tests. PayFast's custom checkout requires merchant_id/merchant_key form fields; the signing passphrase stays server-side. These protocol fields follow [PayFast's official instructions](https://support.payfast.help/portal/en/kb/articles/how-do-i-create-a-custom-integration-request). No real merchant values are committed.

## 12. OpenAI API Key Configuration

## OpenAI API Key Configuration

Exact direct reader: `shared-components/ai-recommendation-service.php`, inside `eventnest_openai_api_key()`, immediately beside the required student-style comment. It is the only application source point calling getenv for this key. Other functions call the central helper; the frontend never reads or receives it.

Environment variable: `OPENAI_API_KEY`

Purpose: optional server-side OpenAI embeddings for opted-in recommendations. The real key is not stored in GitHub, source, database, JavaScript, comments or this report. Configure it in the Render SIT service environment. Genuine local AI testing also requires it in the local server environment. Never commit it or paste it into PHP/frontend code.

If missing, the service skips personalised cache/request usage and uses SQL fallback. Search, filtering and ordinary discovery remain available. The adjacent code comment explains that the real value comes from server/Render configuration and must be configured for AI recommendations. No actual key value is included here.

## 13. AI Recommendation Changes

`index.php` consumes the central reasons and personalised/fallback metadata. `settings.php` writes existing USERS.RecommendationOptIn, immediately clears cached rankings and stops personalised usage/display when off. Profile/category changes invalidate caches. The service checks active attendee/consent/profile/eligibility, caps candidates, batches requests, uses 2-second connection/3-second total timeouts and a 30-minute database cache.

Only approved category labels and event format labels go to the embedding endpoint. Names, email, phone, free-text private profile/event payloads, payment/booking records, reviews, moderation, verification documents, paths and secrets are excluded. Invalid/nonfinite vectors are rejected and scores are bounded for storage. Missing key/profile, empty/malformed results, API errors and timeouts preserve SQL fallback. Opted-out discovery uses neutral headings without an AI badge. Real external OpenAI requests were not performed.

## 14. Responsive Design Changes

Main CSS retains the exact approved palette and supplied PNG. Existing class names receive shared form/table/button/status/media/policy/dashboard styling. Navy sidebars and event-detail/booking/registration/support columns follow the references; policy navigation, review/escrow summary cards and native FAQ disclosures were added.

Responsive CSS stacks these structures on narrow screens, keeps account navigation reachable, preserves media proportions/touch-target sizing and confines wide tables to scrolling containers. Filters remain visible/usable. Real desktop/tablet/mobile viewport measurements and screenshots were NOT available; final overflow, spacing, touch and visual checks remain required.

## 15. Render SIT Changes

Docker installs required PHP extensions and Composer mail dependencies, generates ignored connection.php from its secure template, enables Apache overrides and makes public-upload/private-document locations writable. Build exclusions prevent local secrets/uploads/review files entering the image. Provider URLs require a trusted configured HTTPS base rather than the Host header. Trusted HTTPS configuration secures cookies behind the Render proxy. PHP/database event times use South African time.

Configure database variables/optional SSL CA, SMTP/support addresses, PayFast sandbox and optional OpenAI. PHPMailer is declared following [its official project instructions](https://github.com/PHPMailer/PHPMailer). No real deployment values were hard-coded.

Schedule `php scheduled-tasks/check-pending-bookings.php` at least every minute and `php scheduled-tasks/check-suspension-reset.php` daily. `php scheduled-tasks/escrow.php` reviews read-only by default; release requires targeted trusted approval. Render cron must use the same database environment/application image. Arrange persistent upload/private-document storage and backups because ordinary container storage is ephemeral. No Render dashboard/deployment changes were performed.

## 16. Testing Performed

Tests used a separate temporary application mirror, isolated MariaDB 10.4.32 on loopback 13307 and PHP 8.2.12 HTTP on loopback 18765. The real ignored connection file/current project database was never used for writes. Approved schema/seed loaded unchanged; extra/future fixtures existed only in the disposable database.

| Check | Actual result |
| --- | --- |
| PHP syntax | PASS: all 71 PHP files found under the repository, including ignored local/review PHP. |
| JavaScript syntax | PASS: main.js, reactions.js, review-form.js, student-email-check.js, tag-follow.js and validation.js parsed with Node VM. |
| HTTP/database integration | PASS: 179 assertions covering public pages/assets, role access/redirects, consent/fallback, reactions, free/pending bookings, retry/expiry/restoration, event creation/publication, posts, private reviews and logout. Repeated asset checks are included in this count. |
| Security/edge cases | PASS: 29 assertions covering malformed/foreign IDs, draft preview, document denial, cancellation cutoff, expired fees, category CRUD/references, unavailable mail, deletion/password/privilege checks, job denial and escrow boundaries. |
| Upload/verification | PASS: 12 assertions covering PNG private-document/public-image upload, extension mismatch, admin retrieval, approval/rejection/resubmission, private-review exclusion and duplicate blocks. |
| Payment/AI logic | PASS: 20 assertions on isolated MariaDB: merchant/amount/signature checks, state writes, callback replay, second-transaction rejection, late/sold-out success, timeout precedence, vector calculations, consent/missing key/SQL fallback and mocked error/timeout/empty transport. Provider transport was mocked. |
| Annual fee logic | PASS: 10 assertions: R99/reference/retry/notify, amount rejection, annual coverage, document/booking-payment separation, replay/late failure and expired renewal. Provider transport was mocked. |
| Client validation | PASS: 10 DOM-mock radio/integer/price/domain checks and 5 Student visibility/domain/reset checks. These are not browser tests. |
| Source routes | PASS: no missing checked literal PHP/include/asset targets except intentionally optional uninstalled Composer autoload. Dynamic role/assets also exercised over HTTP. |
| CSS structure | PASS: balanced brace checks in both stylesheets; not a complete parser or render check. |
| SQL preservation | PASS: original SHA-256 matches and no final Git SQL differences. |
| Diff/secrets | PASS: whitespace and recognised secret-pattern scans; no actual API key/database credential added. |
| Real OpenAI | NOT TESTED - requires OPENAI_API_KEY in the environment. |
| Real PayFast booking/fee notifications/refunds | NOT TESTED - requires external service credentials and public HTTPS callbacks. |
| SMTP delivery / Composer install | NOT TESTED - requires configured email/dependency installation. Missing-service failure was exercised. |
| Docker / Render SIT | NOT TESTED - requires Render environment / Docker tooling. |
| Actual Apache protection responses | NOT TESTED: available Apache rejected the isolated configuration path; PHP development server does not enforce .htaccess. Rules were statically inspected. |
| Rendered desktop/tablet/mobile pages | NOT TESTED: browser inventory was empty and in-app browser creation failed. This includes public, attendee, organiser and admin viewport checks. |

Total workflow/logic assertions: 250 PHP/HTTP/database checks plus 15 JavaScript DOM-mock checks, all passing. Test scripts/results are outside the repository in temporary `eventnest-audit-eggcoi0r`; no debug routes, test database or upload fixtures entered Git. Original ignored `.review/` artifacts were not rewritten and describe older contracts.

## 17. Remaining Issues

| File / area | Issue and reason | Next action |
| --- | --- | --- |
| Shared styles/all wireframed pages | No browser rendering/viewport tests. Guide appendix images were inspected; the separate updated PDF could not be rendered with available tools. Exact matching remains unverified. | Compare desktop/tablet/mobile against the final PDF, including public, attendee, organiser and admin pages; fix visual/overflow/accessibility defects found. |
| Dockerfile/composer.json/config/protection | Docker/Composer/Render builds and Apache response checks were unavailable; the local configured DB was offline. No Composer lock exists yet. | Install/lock reviewed dependencies, build Docker, verify Apache denial/media rules, configure environment and run SIT. |
| Payment/verification callbacks | Genuine sandbox requests/ITNs were not exercised. USERS stores one current fee reference rather than a historical fee ledger. | Test real booking/R99 renewal callbacks and manually reconcile obsolete/unmatched references, duplicate charges and late success. Do not change schema to hide this limitation. |
| Cancellation/escrow/refunds | Actual provider refunds/transfers are unavailable. Unfulfillable late charges remain held for review. | Verify real provider refund evidence before trusted local refund recording; never fabricate refunded/transfer success. |
| Shared/event/verification email | Dependency is declared but not locally installed; genuine delivery is untested. Specific rejection guidance has no approved persistence column. | Build/install mail dependency, configure SMTP/support and verify delivery; follow up manually when email fails. |
| AI service | Genuine model call/relevance untested without a valid key. | Configure server-side key and test real opt-in/failure/timeout behavior while preserving fallback. |
| delete-account.php / emailed reports | No schema field represents unresolved emailed-report holds. Automatic checks cover actual database obligations; UI directs unresolved reports to support. | Administrator must review emailed investigations before relevant deletion processing; do not invent a reports/hold table. |
| Public/private uploaded storage | Persistence/backup is operational configuration. Valid video/PDF fixtures and playback were not exercised; PNG paths were. | Configure Render storage/backup and test approved PDF/image/video retrieval plus script denial on real Apache. |
| Scheduled jobs/concurrency | Render scheduling and multi-worker simultaneous requests were not exercised. Locks/conditional transitions were inspected and sequentially tested on MariaDB. | Configure trusted runners, then test overlapping timeout/cancel/callback and last-ticket races in SIT. |
| Approved seed | Several event dates are already past on 23 October 2026 and seed media is absent; empty/logo fallback states can be correct. | Use authorised disposable future fixtures for tests; never rewrite approved seed dates/media. |

## 18. Environment Variables Required

Names only. Optional integration variables are needed when that integration is exercised.

```text
DB_HOST
DB_PORT
DB_NAME
DB_USER
DB_PASS
DB_SSL_CA
EVENTNEST_BASE_URL
OPENAI_API_KEY
PAYFAST_MERCHANT_ID
PAYFAST_MERCHANT_KEY
PAYFAST_PASSPHRASE
EVENTNEST_SMTP_HOST
EVENTNEST_SMTP_PORT
EVENTNEST_SMTP_USER
EVENTNEST_SMTP_PASS
EVENTNEST_FROM_EMAIL
EVENTNEST_FROM_NAME
EVENTNEST_SUPPORT_EMAIL
```

## 19. Final Status

PARTIALLY READY - REMAINING ISSUES LISTED ABOVE
