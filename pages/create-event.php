<?php
// Create an event for an approved organiser with current annual fee coverage.
require_once __DIR__ . '/shared-components/auth.php';
require_once __DIR__ . '/database-config/connection.php';
require_once __DIR__ . '/shared-components/functions.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
requireRole('organiser');
$user_id = (int)($_SESSION['UserID'] ?? 0);
if (!$user_id) { header('Location: login.php'); exit; }
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$errors = []; $success = '';
$u = $pdo->prepare("SELECT UserStatus, VerificationStatus, VerificationFeePaymentStatus, VerificationFeePaidUntil FROM USERS WHERE UserID=:id");
$u->execute(['id'=>$user_id]); $organiser = $u->fetch(PDO::FETCH_ASSOC);
$eligible = $organiser && $organiser['UserStatus'] === 'active' && $organiser['VerificationStatus'] === 'verified' && $organiser['VerificationFeePaymentStatus'] === 'paid' && !empty($organiser['VerificationFeePaidUntil']) && strtotime($organiser['VerificationFeePaidUntil']) >= time();
$categories = $pdo->query('SELECT CategoryID, CategoryName FROM CATEGORIES ORDER BY CategoryName')->fetchAll(PDO::FETCH_ASSOC);
$values = ['EventName'=>'','Description'=>'','Venue'=>'','EventDate'=>'','Format'=>'physical','CategoryID'=>'','AgeRestriction'=>'','Province'=>'','Hashtags'=>'','CareerGoalType'=>'none','AllowedCareerField'=>'','AllowedEmailDomain'=>''];
$tiers = [
 'General'=>['price'=>'0.00','capacity'=>'','description'=>''],
 'VIP'=>['price'=>'','capacity'=>'','description'=>''],
 'Early Bird'=>['price'=>'','capacity'=>'','description'=>''],
 'Student'=>['price'=>'','capacity'=>'','description'=>'']
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) { $errors[] = 'Your session token is invalid. Refresh the page and try again.'; }
  foreach ($values as $k=>$v) { $values[$k] = trim((string)($_POST[$k] ?? $v)); }
  foreach ($tiers as $type=>$tier) { $tiers[$type] = ['price'=>trim((string)($_POST['tier'][$type]['price'] ?? $tier['price'])), 'capacity'=>trim((string)($_POST['tier'][$type]['capacity'] ?? $tier['capacity'])), 'description'=>trim((string)($_POST['tier'][$type]['description'] ?? $tier['description']))]; }
  if (mb_strlen($values['EventName']) < 3 || mb_strlen($values['EventName']) > 100) $errors[] = 'Event name must be between 3 and 100 characters.';
  if (mb_strlen($values['Description']) > 10000) $errors[] = 'Description is too long.';
  if (!in_array($values['Format'], ['online','physical','hybrid'], true)) $errors[] = 'Choose a valid event format.';
  $date = DateTime::createFromFormat('Y-m-d\TH:i', $values['EventDate']);
  if (!$date || $date <= new DateTime()) $errors[] = 'Choose a valid future event date and time.';
  $category_id = filter_var($values['CategoryID'], FILTER_VALIDATE_INT);
  if (!$category_id || !in_array((int)$category_id, array_map(fn($c)=>(int)$c['CategoryID'], $categories), true)) $errors[] = 'Choose a valid category.';
  if (!in_array($values['CareerGoalType'], ['none','field','email_domain'], true)) $errors[] = 'Choose a valid eligibility rule.';
  if ($values['CareerGoalType']==='field' && !in_array((int)$values['AllowedCareerField'], array_map(fn($c)=>(int)$c['CategoryID'],$categories), true)) $errors[] = 'Choose a valid eligible career field.';
  if ($values['CareerGoalType']==='email_domain' && !preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $values['AllowedEmailDomain'])) $errors[] = 'Enter a valid email domain without an @ symbol.';
  $valid_tiers = [];
  foreach ($tiers as $type=>$tier) {
    if ($tier['price']==='' && $tier['capacity']==='' && $tier['description']==='') continue;
    if (!is_numeric($tier['price']) || (float)$tier['price'] < 0 || !preg_match('/^\d+$/', $tier['capacity']) || (int)$tier['capacity'] < 1 || (int)$tier['capacity'] > 100000) { $errors[] = $type . ' ticket needs a non-negative price and capacity from 1 to 100000.'; continue; }
    $valid_tiers[] = ['type'=>$type,'price'=>(float)$tier['price'],'capacity'=>(int)$tier['capacity'],'description'=>mb_substr($tier['description'],0,255)];
  }
  if (!$valid_tiers) $errors[] = 'Enter at least one ticket tier and its capacity.';
  if (!$eligible) $errors[] = 'Your organiser account must be verified and have current annual fee coverage before creating events.';
  if (!$errors) {
    try {
      $pdo->beginTransaction();
      $sql = "INSERT INTO EVENTS (OrganiserID, CategoryID, EventName, Description, Venue, EventDate, AgeRestriction, Status, Format, Hashtags, CareerGoalType, AllowedCareerField, AllowedEmailDomain, Province) VALUES (:uid,:category,:name,:description,:venue,:event_date,:age,'draft',:format,:hashtags,:goal,:career_field,:domain,:province)";
      $stmt = $pdo->prepare($sql);
      $stmt->execute(['uid'=>$user_id,'category'=>(int)$category_id,'name'=>$values['EventName'],'description'=>$values['Description'] ?: null,'venue'=>$values['Venue'] ?: null,'event_date'=>$date->format('Y-m-d H:i:s'),'age'=>$values['AgeRestriction'] ?: null,'format'=>$values['Format'],'hashtags'=>$values['Hashtags'] ?: null,'goal'=>$values['CareerGoalType'],'career_field'=>$values['CareerGoalType']==='field' ? (int)$values['AllowedCareerField'] : null,'domain'=>$values['CareerGoalType']==='email_domain' ? strtolower($values['AllowedEmailDomain']) : null,'province'=>$values['Province'] ?: null]);
      $event_id = (int)$pdo->lastInsertId();
      $ticket_stmt = $pdo->prepare('INSERT INTO TICKETS (EventID, TicketType, Price, QuantityAvailable, Capacity, Description) VALUES (:event,:type,:price,:quantity,:capacity,:description)');
      foreach ($valid_tiers as $tier) $ticket_stmt->execute(['event'=>$event_id,'type'=>$tier['type'],'price'=>$tier['price'],'quantity'=>$tier['capacity'],'capacity'=>$tier['capacity'],'description'=>$tier['description'] ?: null]);
      $pdo->commit();
      $success = 'Event saved as a draft. It must pass the EventNest approval/publication process before it is publicly bookable.';
      foreach ($values as $k=>$v) $values[$k] = '';
      $values['Format']='physical'; $values['CareerGoalType']='none';
      foreach ($tiers as $type=>$_) $tiers[$type]=['price'=>$type==='General'?'0.00':'','capacity'=>'','description'=>''];
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log('EventNest create-event failure: '.$e->getMessage()); $errors[] = 'The event could not be saved. Please try again later.'; }
  }
}
$page_title = 'Create event'; require_once __DIR__ . '/shared-components/header.php';
?>
<main id="main-content" class="container form-page"><h1>Create an event</h1>
<?php if (!$eligible): ?><p class="alert alert-warning">Event creation is available only to active, verified organisers with current R99 annual fee coverage. Existing events are not automatically removed when fee coverage expires.</p><?php endif; ?>
<?php foreach ($errors as $error): ?><p class="alert alert-danger"><?= en_e($error) ?></p><?php endforeach; ?><?php if ($success): ?><p class="alert alert-success"><?= en_e($success) ?></p><?php endif; ?>
<form method="post" action="create-event.php" novalidate><input type="hidden" name="csrf_token" value="<?= en_e($_SESSION['csrf_token']) ?>">
<label for="EventName">Event name</label><input id="EventName" name="EventName" maxlength="100" required value="<?= en_e($values['EventName']) ?>">
<label for="Description">Description</label><textarea id="Description" name="Description" rows="5" maxlength="10000"><?= en_e($values['Description']) ?></textarea>
<label for="CategoryID">Category / career field</label><select id="CategoryID" name="CategoryID" required><option value="">Select category</option><?php foreach($categories as $c): ?><option value="<?= (int)$c['CategoryID'] ?>" <?= (string)$c['CategoryID']===$values['CategoryID']?'selected':'' ?>><?= en_e($c['CategoryName']) ?></option><?php endforeach; ?></select>
<label for="Venue">Venue / location</label><input id="Venue" name="Venue" maxlength="150" value="<?= en_e($values['Venue']) ?>">
<label for="Province">Province</label><input id="Province" name="Province" maxlength="100" value="<?= en_e($values['Province']) ?>">
<label for="EventDate">Event date and time</label><input id="EventDate" type="datetime-local" name="EventDate" required value="<?= en_e($values['EventDate']) ?>">
<label for="Format">Format</label><select id="Format" name="Format"><option value="physical" <?= $values['Format']==='physical'?'selected':'' ?>>In-person</option><option value="online" <?= $values['Format']==='online'?'selected':'' ?>>Online</option><option value="hybrid" <?= $values['Format']==='hybrid'?'selected':'' ?>>Hybrid</option></select>
<label for="AgeRestriction">Age restriction (optional)</label><select id="AgeRestriction" name="AgeRestriction"><option value="">No additional restriction</option><option value="18+" <?= $values['AgeRestriction']==='18+'?'selected':'' ?>>18+</option><option value="21+" <?= $values['AgeRestriction']==='21+'?'selected':'' ?>>21+</option></select>
<label for="Hashtags">Public hashtags (optional, e.g. #Finance #Careers)</label><input id="Hashtags" name="Hashtags" maxlength="255" value="<?= en_e($values['Hashtags']) ?>">
<h2>Eligibility gate</h2><label for="CareerGoalType">Eligibility</label><select id="CareerGoalType" name="CareerGoalType"><option value="none" <?= $values['CareerGoalType']==='none'?'selected':'' ?>>No additional career/email gate</option><option value="field" <?= $values['CareerGoalType']==='field'?'selected':'' ?>>Restrict to career field</option><option value="email_domain" <?= $values['CareerGoalType']==='email_domain'?'selected':'' ?>>Restrict to email domain</option></select>
<label for="AllowedCareerField">Allowed career field (if selected)</label><select id="AllowedCareerField" name="AllowedCareerField"><option value="">Select field</option><?php foreach($categories as $c): ?><option value="<?= (int)$c['CategoryID'] ?>" <?= (string)$c['CategoryID']===$values['AllowedCareerField']?'selected':'' ?>><?= en_e($c['CategoryName']) ?></option><?php endforeach; ?></select>
<label for="AllowedEmailDomain">Allowed email domain (if selected, without @)</label><input id="AllowedEmailDomain" name="AllowedEmailDomain" maxlength="100" value="<?= en_e($values['AllowedEmailDomain']) ?>">
<h2>Ticket tiers</h2><p>Enter at least one tier. Leave unused tiers empty. Ticket availability starts at the capacity you enter.</p>
<?php foreach($tiers as $type=>$tier): ?><fieldset><legend><?= en_e($type) ?></legend><label for="price-<?= en_e(str_replace(' ','-',$type)) ?>">Price (R)</label><input id="price-<?= en_e(str_replace(' ','-',$type)) ?>" type="number" min="0" step="0.01" name="tier[<?= en_e($type) ?>][price]" value="<?= en_e($tier['price']) ?>"><label for="capacity-<?= en_e(str_replace(' ','-',$type)) ?>">Capacity</label><input id="capacity-<?= en_e(str_replace(' ','-',$type)) ?>" type="number" min="1" max="100000" step="1" name="tier[<?= en_e($type) ?>][capacity]" value="<?= en_e($tier['capacity']) ?>"><label for="desc-<?= en_e(str_replace(' ','-',$type)) ?>">Description</label><input id="desc-<?= en_e(str_replace(' ','-',$type)) ?>" maxlength="255" name="tier[<?= en_e($type) ?>][description]" value="<?= en_e($tier['description']) ?>"></fieldset><?php endforeach; ?>
<button class="btn btn-primary" type="submit" <?= !$eligible?'disabled':'' ?>>Save draft</button> <a class="btn btn-outline" href="user-dashboard.php">Cancel</a>
</form></main><?php require_once __DIR__ . '/shared-components/footer.php'; ?>
