<?php
declare(strict_types=1);
require_once __DIR__.'/shared-components/auth.php';
$user=auth_require_attendee();
$pdo=eventnest_db();
$booking_id=eventnest_id($_GET['booking_id'] ?? null);
$reference=trim_input($_GET['reference'] ?? '');
$stmt=$pdo->prepare("SELECT b.BookingID,b.BookingReference,e.EventName FROM BOOKINGS b JOIN EVENTS e ON e.EventID=b.EventID WHERE b.UserID=? AND (b.BookingID=? OR b.BookingReference=?) AND b.Status='paid' AND e.Status='completed' AND NOT EXISTS(SELECT 1 FROM REVIEWS r WHERE r.BookingID=b.BookingID) LIMIT 1");
$stmt->execute([$user['UserID'],$booking_id,$reference]);
$booking=$stmt->fetch();
if(!$booking) {
    http_response_code(404);
    exit('An eligible unreviewed completed booking could not be found.');
}
$page_title='Leave private review';
require __DIR__.'/shared-components/header.php';
?>
<main class="content-card review-form"><h1>How was <?= escape_html($booking['EventName']) ?>?</h1><p>Your feedback is private to the organiser and authorised administrators.</p>
<div class="support-layout"><form method="post" action="review.php"><?php eventnest_csrf_field(); ?><input type="hidden" name="booking_id" value="<?= (int)$booking['BookingID'] ?>">
<fieldset><legend>Rating</legend><?php foreach(range(1,5) as $rating): ?><label><input type="radio" name="rating" value="<?= $rating ?>" required> <?= $rating ?> star<?= $rating>1?'s':'' ?></label> <?php endforeach; ?></fieldset>
<fieldset data-high-rating><legend>What went well? (4?5 stars)</legend><?php foreach(['Helpful','Professional','Well organised','Inspiring','Good networking'] as $tag): ?><label><input type="checkbox" name="compliment_tags[]" value="<?= $tag ?>"> <?= $tag ?></label> <?php endforeach; ?></fieldset>
<div class="field" data-low-rating><label for="complaint_text">What could improve? (1?3 stars)</label><textarea id="complaint_text" name="complaint_text" maxlength="5000"></textarea></div>
<p><label><input type="checkbox" name="wants_contact" value="1" data-contact-permission> The organiser may contact me about this review</label></p><div class="field" data-contact-email><label for="contact_email">Contact email</label><input id="contact_email" type="email" name="contact_email" maxlength="100" value="<?= escape_html($user['Email']) ?>"></div>
<div class="form-actions"><button class="btn btn-orange">Submit private review</button><a class="btn btn-outline" href="my-bookings.php">Back to bookings</a></div></form><aside class="callout callout-teal"><h2>Privacy note</h2><p>Your review is visible only to the organiser and authorised administrators. Contact details are shared only if you request follow-up.</p></aside></div></main>
<script src="<?= escape_html(eventnest_url('scripts/review-form.js')) ?>" defer></script>
<?php require __DIR__.'/shared-components/footer.php'; ?>
