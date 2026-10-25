<?php
declare(strict_types=1);
require_once __DIR__ . '/shared-components/auth.php';
$user = auth_require_verified_organiser();
$pdo = eventnest_db();
$editing = $editing ?? false;
$event_id = $editing ? eventnest_id($_POST['event_id'] ?? $_GET['event_id'] ?? null) : 0;
$categories = $pdo->query('SELECT CategoryID,CategoryName FROM CATEGORIES ORDER BY CategoryName')->fetchAll();
$category_ids = array_map('intval',array_column($categories,'CategoryID'));
$event = [];
$tickets = [];
$message = '';
if ($editing) {
    $stmt = $pdo->prepare('SELECT * FROM EVENTS WHERE EventID = ? AND OrganiserID = ?');
    $stmt->execute([$event_id,$user['UserID']]);
    $event = $stmt->fetch();
    if (!$event) {
        http_response_code(404);
        exit('Event not found.');
    }
    if (!in_array($event['Status'],['draft','published'],true)) {
        http_response_code(403);
        exit('This event can no longer be edited.');
    }
    $stmt = $pdo->prepare('SELECT * FROM TICKETS WHERE EventID = ?');
    $stmt->execute([$event_id]);
    foreach ($stmt->fetchAll() as $ticket) {
        $tickets[$ticket['TicketType']] = $ticket;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    eventnest_require_post();
    try {
        $name = trim_input($_POST['event_name'] ?? '');
        $description = trim_input($_POST['description'] ?? '');
        $venue = trim_input($_POST['venue'] ?? '');
        $province = trim_input($_POST['province'] ?? '');
        $date_input = trim_input($_POST['event_date'] ?? '');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$date_input);
        $format = trim_input($_POST['format'] ?? '');
        $gate = trim_input($_POST['career_gate_type'] ?? 'none');
        $category = eventnest_id($_POST['category_id'] ?? null);
        $allowed = $gate === 'field' ? eventnest_id($_POST['allowed_career_field'] ?? null) : null;
        $domain = $gate === 'email_domain' ? strtolower(ltrim(trim_input($_POST['allowed_email_domain'] ?? ''),'@')) : null;
        $age = trim_input($_POST['age_restriction'] ?? '');
        $tags = eventnest_normalise_hashtags(trim_input($_POST['hashtags'] ?? ''));
        if ($name === '' || mb_strlen($name)>100 || mb_strlen($description)>15000 || mb_strlen($venue)>150 || ($province !== '' && !in_array($province, eventnest_provinces(), true)) || strlen($age)>10 || !$date || $date->format('Y-m-d\TH:i')!==$date_input || $date<=new DateTimeImmutable() || !in_array($category,$category_ids,true) || !in_array($format,['online','physical','hybrid'],true) || !in_array($gate,['none','field','email_domain'],true) || ($gate==='field' && !in_array($allowed,$category_ids,true)) || ($gate==='email_domain' && (strlen($domain)>100 || !filter_var('attendee@'.$domain,FILTER_VALIDATE_EMAIL))) || filter_var($venue,FILTER_VALIDATE_URL)) {
            throw new DomainException('Check event details, future date, category, format and gate. Venue must be a public location description.');
        }
        $rows = $_POST['tickets'] ?? [];
        $valid_rows = [];
        if (!is_array($rows)) {
            throw new DomainException('Invalid ticket rows.');
        }
        foreach (['General','VIP','Early Bird','Student'] as $type) {
            $row = $rows[$type] ?? [];
            if (!is_array($row) || !isset($row['enabled'])) {
                continue;
            }
            $price = trim_input($row['price'] ?? '');
            $capacity = eventnest_id($row['capacity'] ?? null);
            $ticket_description = trim_input($row['description'] ?? '');
            if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D',$price) || $capacity<1 || $capacity>1000000 || mb_strlen($ticket_description)>255) {
                throw new DomainException('Ticket price must be non-negative with up to two decimals, and capacity must be a positive whole number.');
            }
            $valid_rows[$type] = [number_format((float)$price,2,'.',''),$capacity,$ticket_description ?: null];
        }
        if (!$valid_rows) {
            throw new DomainException('Enable at least one ticket tier.');
        }
        $pdo->beginTransaction();
        if ($editing) {
            // Lock the event before its tickets, matching the booking creation order.
            $lock = $pdo->prepare('SELECT Status FROM EVENTS WHERE EventID = ? AND OrganiserID = ? FOR UPDATE');
            $lock->execute([$event_id,$user['UserID']]);
            if (!in_array($lock->fetchColumn(),['draft','published'],true)) {
                throw new DomainException('Event state changed. Reload before editing.');
            }
            $stmt = $pdo->prepare('UPDATE EVENTS SET EventName=?,Description=?,Venue=?,EventDate=?,AgeRestriction=?,Format=?,Hashtags=?,CareerGoalType=?,AllowedCareerField=?,AllowedEmailDomain=?,Province=?,CategoryID=? WHERE EventID=? AND OrganiserID=?');
            $stmt->execute([$name,$description,$venue,$date->format('Y-m-d H:i:s'),$age ?: null,$format,$tags,$gate,$allowed,$domain,$province ?: null,$category,$event_id,$user['UserID']]);
        }
        else {
            // Publication and featured status are administrator-controlled.
            $stmt = $pdo->prepare("INSERT INTO EVENTS (OrganiserID,CategoryID,EventName,Description,Venue,EventDate,AgeRestriction,Format,Hashtags,CareerGoalType,AllowedCareerField,AllowedEmailDomain,Province,Status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'draft')");
            $stmt->execute([$user['UserID'],$category,$name,$description,$venue,$date->format('Y-m-d H:i:s'),$age ?: null,$format,$tags,$gate,$allowed,$domain,$province ?: null]);
            $event_id = (int)$pdo->lastInsertId();
        }
        $stmt = $pdo->prepare('SELECT * FROM TICKETS WHERE EventID = ? FOR UPDATE');
        $stmt->execute([$event_id]);
        $existing = [];
        foreach ($stmt->fetchAll() as $ticket) {
            $existing[$ticket['TicketType']] = $ticket;
        }
        foreach ($existing as $type=>$old) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM BOOKINGS WHERE TicketID = ?');
            $stmt->execute([$old['TicketID']]);
            $has_bookings = (int)$stmt->fetchColumn()>0;
            if (!isset($valid_rows[$type])) {
                if ($has_bookings) {
                    throw new DomainException('A ticket tier with booking history cannot be removed.');
                }
                $stmt = $pdo->prepare('DELETE FROM TICKETS WHERE TicketID=? AND EventID=?');
                $stmt->execute([$old['TicketID'],$event_id]);
                continue;
            }
            [$price,$capacity,$text] = $valid_rows[$type];
            $reserved = (int)$old['Capacity']-(int)$old['QuantityAvailable'];
            if ($capacity<$reserved || ($has_bookings && abs((float)$price-(float)$old['Price'])>0.001)) {
                throw new DomainException('Capacity cannot be below reserved tickets. Prices for tiers with booking history must stay unchanged.');
            }
            $stmt = $pdo->prepare('UPDATE TICKETS SET Price=?,Capacity=?,QuantityAvailable=?,Description=? WHERE TicketID=? AND EventID=?');
            $stmt->execute([$price,$capacity,$capacity-$reserved,$text,$old['TicketID'],$event_id]);
        }
        foreach ($valid_rows as $type=>[$price,$capacity,$text]) {
            if (isset($existing[$type])) {
                continue;
            }
            $stmt = $pdo->prepare('INSERT INTO TICKETS (EventID,TicketType,Price,Capacity,QuantityAvailable,Description) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$event_id,$type,$price,$capacity,$capacity,$text]);
        }
        $pdo->commit();
        auth_set_flash('Event saved. New events await administrator publication.','success');
        auth_redirect('manage-events.php');
    }
    catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = $exception instanceof DomainException ? $exception->getMessage() : 'The event could not be saved. Please try again.';
        if (!$exception instanceof DomainException) {
            error_log('EventNest event save failed: '.get_class($exception));
        }
    }
}
function event_form_value(string $field, string $column, array $event): string {
    return trim_input($_POST[$field] ?? $event[$column] ?? '');
}
$page_title = $editing ? 'Edit event' : 'Create event';
require __DIR__ . '/shared-components/header.php';
?>
<div class="dashboard-layout"><?php eventnest_sidebar('organiser'); ?><main class="dashboard-main"><h1><?= escape_html($page_title) ?></h1>
<?php if ($message): ?><p role="alert" class="alert alert-danger"><?= escape_html($message) ?></p><?php endif; ?>
<form class="content-card" method="post" data-validate="true"><?php eventnest_csrf_field(); ?>
<?php if ($editing): ?><input type="hidden" name="event_id" value="<?= $event_id ?>"><?php endif; ?>
<h2>Event details</h2><div class="form-grid">
<?php foreach (['event_name'=>['Event title','EventName',100],'venue'=>['Public venue / town or suburb','Venue',150],'age_restriction'=>['Age restriction','AgeRestriction',10],'hashtags'=>['Hashtags','Hashtags',255]] as $field=>[$label,$column,$max]): ?>
<div class="field"><label for="<?= $field ?>"><?= $label ?></label><input id="<?= $field ?>" name="<?= $field ?>" maxlength="<?= $max ?>" value="<?= escape_html(event_form_value($field,$column,$event)) ?>" <?= $field==='event_name'?'required':'' ?>></div><?php endforeach; ?>
<div class="field"><label for="province">Province</label><select id="province" name="province"><option value="">Choose province</option><?php foreach (eventnest_provinces() as $province_option): ?><option value="<?= escape_html($province_option) ?>" <?= event_form_value('province','Province',$event)===$province_option?'selected':'' ?>><?= escape_html($province_option) ?></option><?php endforeach; ?></select></div>
<p class="field-hint">Enter the actual venue and town or suburb, for example Pretoria, Hammanskraal or Stellenbosch. Locations are not limited to provincial capitals.</p>
<div class="field"><label for="event_date">Date and time</label><input id="event_date" name="event_date" type="datetime-local" required value="<?= escape_html($_POST['event_date'] ?? (!empty($event['EventDate'])?date('Y-m-d\TH:i',strtotime($event['EventDate'])):'')) ?>"></div>
<div class="field"><label for="category_id">Career field</label><select id="category_id" name="category_id" required><option value="">Choose category</option><?php foreach ($categories as $c): ?><option value="<?= (int)$c['CategoryID'] ?>" <?= event_form_value('category_id','CategoryID',$event)==$c['CategoryID']?'selected':'' ?>><?= escape_html($c['CategoryName']) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="format">Format</label><select id="format" name="format"><?php foreach (['online','physical','hybrid'] as $v): ?><option value="<?= $v ?>" <?= event_form_value('format','Format',$event)===$v?'selected':'' ?>><?= ucfirst($v) ?></option><?php endforeach; ?></select></div>
<div class="field full"><label for="description">Description</label><textarea id="description" name="description" maxlength="15000"><?= escape_html(event_form_value('description','Description',$event)) ?></textarea></div>
<div class="field"><label for="career_gate_type">Career gate</label><select id="career_gate_type" name="career_gate_type"><?php foreach (['none','field','email_domain'] as $v): ?><option value="<?= $v ?>" <?= event_form_value('career_gate_type','CareerGoalType',$event)===$v?'selected':'' ?>><?= escape_html(ucfirst(str_replace('_',' ',$v))) ?></option><?php endforeach; ?></select></div>
<div class="field" data-gate-field><label for="allowed_career_field">Permitted category</label><select id="allowed_career_field" name="allowed_career_field"><option value="">Choose category</option><?php foreach ($categories as $c): ?><option value="<?= (int)$c['CategoryID'] ?>" <?= event_form_value('allowed_career_field','AllowedCareerField',$event)==$c['CategoryID']?'selected':'' ?>><?= escape_html($c['CategoryName']) ?></option><?php endforeach; ?></select></div>
<div class="field" data-gate-domain><label for="allowed_email_domain">Permitted email domain</label><input id="allowed_email_domain" name="allowed_email_domain" maxlength="100" value="<?= escape_html(event_form_value('allowed_email_domain','AllowedEmailDomain',$event)) ?>"></div></div>
<h2>Ticket tiers</h2><p>Enable a tier, then enter its price and total capacity. Enter 0 for a free ticket.</p>
<div class="ticket-grid"><?php foreach (['General','VIP','Early Bird','Student'] as $type): $old=$tickets[$type] ?? []; $row=is_array($_POST['tickets'][$type] ?? null)?$_POST['tickets'][$type]:[]; ?>
<fieldset class="ticket-card"><legend><label><input type="checkbox" name="tickets[<?= $type ?>][enabled]" value="1" <?= ($_SERVER['REQUEST_METHOD']==='POST' ? isset($row['enabled']) : (!$editing ? $type==='General' : isset($tickets[$type])))?'checked':'' ?>> <?= $type ?></label></legend>
<?php foreach (['price'=>['Price','Price','0'],'capacity'=>['Capacity','Capacity','1'],'description'=>['Ticket description','Description','']] as $field=>[$label,$column,$default]): ?>
<label><?= $label ?><input name="tickets[<?= $type ?>][<?= $field ?>]" value="<?= escape_html(trim_input($row[$field] ?? $old[$column] ?? $default)) ?>" <?= $field==='description'?'maxlength="255"':'type="number" min="'.($field==='capacity'?'1':'0').'" step="'.($field==='capacity'?'1':'0.01').'"' ?>></label><?php endforeach; ?></fieldset><?php endforeach; ?></div>
<div class="form-actions"><a class="btn btn-outline" href="manage-events.php">Back</a><button class="btn btn-orange">Save <?= $editing?'changes':'draft for approval' ?></button></div></form></main></div>
<script src="<?= escape_html(eventnest_url('scripts/validation.js')) ?>" defer></script>
<?php require __DIR__ . '/shared-components/footer.php'; ?>
