<?php
declare(strict_types=1);
require_once __DIR__.'/shared-components/auth.php';
$user=auth_require_attendee();
$pdo=eventnest_db();
$booking_id=eventnest_id($_GET['booking_id'] ?? null);
$reference=trim_input($_GET['reference'] ?? '');
$stmt=$pdo->prepare("SELECT b.BookingID,b.BookingReference,e.EventName,e.ImagePath FROM BOOKINGS b JOIN EVENTS e ON e.EventID=b.EventID WHERE b.UserID=? AND (b.BookingID=? OR b.BookingReference=?) AND b.Status='paid' AND e.Status='completed' AND e.EventDate < NOW() AND NOT EXISTS(SELECT 1 FROM REVIEWS r WHERE r.BookingID=b.BookingID) LIMIT 1");
$stmt->execute([$user['UserID'],$booking_id,$reference]);
$booking=$stmt->fetch();
if(!$booking) {
    http_response_code(404);
    exit('An eligible unreviewed completed booking could not be found.');
}
$page_title='Leave private review';
require __DIR__.'/shared-components/header.php';
?>
<main class="content-card review-form">
    <img class="review-banner" src="<?= escape_html(eventnest_image_url($booking['ImagePath'], 'event')) ?>" alt="Event banner for <?= escape_html($booking['EventName']) ?>" onerror="this.onerror=null;this.src='<?= escape_html(eventnest_url('images/event-placeholder.svg')) ?>'">
    <h1>How was <?= escape_html($booking['EventName']) ?>?</h1>
    <p class="review-privacy">Your review is private to the organiser and authorised administrators. Contact information is shared with the organiser only if you opt into follow-up.</p>
    <form method="post" action="review.php">
        <?php eventnest_csrf_field(); ?>
        <input type="hidden" name="booking_id" value="<?= (int)$booking['BookingID'] ?>">
        <fieldset class="review-rating"><legend>Rate your experience</legend>
            <div class="review-stars"><?php foreach(range(1,5) as $rating): ?>
                <label class="review-star"><input type="radio" name="rating" value="<?= $rating ?>" required aria-label="<?= $rating ?> out of 5 stars"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 2 3 6.1 6.7 1-4.9 4.8 1.2 6.7-6-3.2-6 3.2 1.2-6.7L2.3 9.1l6.7-1Z"/></svg></label>
            <?php endforeach; ?></div>
            <p class="review-rating-cue" data-rating-cue aria-live="polite">Choose a star to begin</p>
        </fieldset>
        <fieldset data-high-rating hidden><legend>What did you enjoy? (optional)</legend>
            <p id="compliment-help">Choose up to one compliment. Select it again to clear it.</p>
            <div class="review-compliments"><?php foreach(['Helpful','Professional','Well organised','Inspiring','Good networking'] as $tag): ?>
                <label class="review-chip"><input type="checkbox" name="compliment_tags[]" value="<?= escape_html($tag) ?>" aria-describedby="compliment-help" disabled><span><?= escape_html($tag) ?></span></label>
            <?php endforeach; ?></div>
        </fieldset>
        <div class="field" data-low-rating hidden>
            <label for="complaint_text">What could we improve?</label>
            <p id="complaint-help">Describe your experience privately. Required for 1–3 stars, up to 5,000 characters.</p>
            <textarea id="complaint_text" name="complaint_text" maxlength="5000" rows="4" aria-describedby="complaint-help" disabled></textarea>
        </div>
        <div data-contact-choice hidden>
            <label class="review-contact"><input type="checkbox" name="wants_contact" value="1" data-contact-permission disabled> The organiser may contact me about this review</label>
            <div class="field" data-contact-email hidden><label for="contact_email">Contact email</label><input id="contact_email" type="email" name="contact_email" maxlength="100" value="<?= escape_html($user['Email']) ?>" aria-describedby="review-email-error" disabled><p id="review-email-error" class="field-error" aria-live="polite"></p></div>
        </div>
        <p class="field-error" data-review-error role="alert"></p>
        <div class="form-actions"><button type="submit" class="btn btn-orange" data-review-submit hidden>Submit review</button><a class="btn btn-outline" href="my-bookings.php">Back to bookings</a></div>
        <noscript><p>JavaScript is needed for the interactive form. You can still submit a private review here: select a rating above, then complete the matching fields below.</p>
            <label>Optional compliment for 4–5 stars <select name="compliment_tags[]"><option value="">None</option><?php foreach(['Helpful','Professional','Well organised','Inspiring','Good networking'] as $tag): ?><option><?= escape_html($tag) ?></option><?php endforeach; ?></select></label>
            <label>Required explanation for 1–3 stars <textarea name="complaint_text" maxlength="5000"></textarea></label>
            <label><input type="checkbox" name="wants_contact" value="1"> The organiser may contact me about this review</label>
            <label>Contact email (required only if selected above)<input type="email" name="contact_email" maxlength="100"></label>
            <button class="btn btn-orange" type="submit">Submit review</button>
        </noscript>
    </form>
</main>
<script src="<?= escape_html(eventnest_url('scripts/review-form.js')) ?>?v=<?= filemtime(__DIR__.'/scripts/review-form.js') ?>" defer></script>
<?php require __DIR__.'/shared-components/footer.php'; ?>
