<?php
declare(strict_types=1);
require_once __DIR__ . '/shared-components/auth.php';
$user = auth_require_organiser();
$page_title = 'Organiser verification';
require __DIR__ . '/shared-components/header.php';
?>
<div class="dashboard-layout"><?php eventnest_sidebar('organiser'); ?><main class="dashboard-main"><h1>Organiser verification</h1>
<section class="content-card"><p>Verification: <?= eventnest_status_badge($user['VerificationStatus']) ?></p>
<p>Annual fee coverage: <?= auth_has_current_verification_fee($user) ? 'Active until '.escape_html(format_datetime($user['VerificationFeePaidUntil'])) : 'No current paid coverage' ?></p>
<p>Document approval and annual fee coverage are separate. The fee is R99 annually. Coverage starts on verified sandbox payment, separately from document approval. Existing bookings are preserved when coverage expires.</p>
<?php if (!auth_has_current_verification_fee($user)): ?>
<form method="post" action="verification.php"><?php eventnest_csrf_field(); ?><button class="btn btn-orange" name="action" value="pay_fee">Pay / renew annual R99 fee</button></form>
<?php if ($user['VerificationFeePaymentStatus'] === 'pending'): ?><p>Fee payment is pending provider verification. Retrying uses the current fee reference.</p><?php endif; ?>
<?php endif; ?>
<?php if ($user['VerificationStatus'] !== 'verified'): ?>
<form method="post" action="<?= escape_html(eventnest_url('verification.php')) ?>" enctype="multipart/form-data">
<?php eventnest_csrf_field(); ?><div class="field"><label for="verification_doc">Private ID or CIPC document</label><input id="verification_doc" name="verification_doc" type="file" accept=".pdf,.jpg,.jpeg,.png" required><small>PDF, JPG or PNG, up to 5 MB. Only authorised administrators can view your document.</small></div><button class="btn btn-orange">Submit for review</button></form>
<?php else: ?><p>Your document verification is complete.</p><?php endif; ?></section></main></div>
<?php require __DIR__ . '/shared-components/footer.php'; ?>
