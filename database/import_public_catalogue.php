<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../shared-components/auth.php';
// Manifest is reviewed data, not executable PHP. No users, passwords or reactions are imported.
$file=$argv[1]??'';$apply=in_array('--apply',$argv,true);
$data=json_decode((string)@file_get_contents($file),true,32,JSON_THROW_ON_ERROR);
if(!is_array($data)||!array_is_list($data)||count($data)>100)throw new RuntimeException('Expected up to 100 reviewed catalogue entries.');
$pdo=eventnest_db();$pdo->beginTransaction();$created=0;
try{foreach($data as $row){
 $key=$row['key']??'';$owner=eventnest_id($row['organiser_id']??0);$name=trim_input($row['name']??'');$date=$row['date']??'';$category=trim_input($row['category']??'');$format=$row['format']??'';
 if(!is_string($key)||!preg_match('/^[a-z0-9_-]{1,60}$/D',$key)||$name===''||mb_strlen($name)>100||!is_string($date)||!DateTimeImmutable::createFromFormat('Y-m-d H:i:s',$date)||!in_array($format,['online','physical','hybrid'],true))throw new RuntimeException('Invalid catalogue entry.');
 $s=$pdo->prepare("SELECT UserID FROM USERS WHERE UserID=? AND Role='organiser' AND UserStatus='active' AND VerificationStatus='verified'");$s->execute([$owner]);if(!$s->fetchColumn())throw new RuntimeException('An explicitly approved existing organiser is required.');
 $s=$pdo->prepare('SELECT EntityID FROM DEMO_FIXTURES WHERE FixtureKey=? FOR UPDATE');$s->execute(['public:'.$key]);if($id=$s->fetchColumn()){$s=$pdo->prepare('SELECT OrganiserID FROM EVENTS WHERE EventID=?');$s->execute([$id]);if((int)$s->fetchColumn()!==$owner)throw new RuntimeException('Fixture ownership mismatch.');continue;}
 $s=$pdo->prepare('SELECT CategoryID FROM CATEGORIES WHERE CategoryName=? AND IsVisible=1');$s->execute([$category]);$cid=$s->fetchColumn();if(!$cid)throw new RuntimeException('Unknown visible category.');
 $s=$pdo->prepare('SELECT 1 FROM EVENTS WHERE EventName=? AND OrganiserID=?');$s->execute([$name,$owner]);if($s->fetchColumn())throw new RuntimeException('Unmanaged event collision; no existing event adopted.');
 $description=trim_input($row['description']??'');$venue=trim_input($row['venue']??'');$province=trim_input($row['province']??'');$city=trim_input($row['city']??'');
 if(mb_strlen($description)>10000||mb_strlen($venue)>255||mb_strlen($city)>100||($format!=='online'&&(!in_array($province,eventnest_provinces(),true)||$venue===''||$city==='')))throw new RuntimeException('Invalid location or description.');
 $image=eventnest_safe_media_path($row['image']??null,'image');if($image&&!str_starts_with($image,'uploaded-content/event-banners/'))throw new RuntimeException('Invalid event image directory.');
 $pdo->prepare("INSERT INTO EVENTS(OrganiserID,CategoryID,EventName,Description,EventDate,Format,Venue,Province,City,ImagePath,Status) VALUES(?,?,?,?,?,?,?,?,?,?,'published')")->execute([$owner,$cid,$name,$description,$date,$format,$venue?:null,$province?:null,$city?:null,$image]);$id=(int)$pdo->lastInsertId();
 $pdo->prepare('INSERT INTO DEMO_FIXTURES(FixtureKey,EntityType,EntityID) VALUES(?,?,?)')->execute(['public:'.$key,'event',$id]);
 $quantity=filter_var($row['capacity']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100000]]);$price=filter_var($row['price']??0,FILTER_VALIDATE_FLOAT);if(!$quantity||$price===false||$price<0||$price>100000)throw new RuntimeException('Invalid inventory or price.');
 $pdo->prepare("INSERT INTO TICKETS(EventID,TicketType,Price,QuantityAvailable,Capacity) VALUES(?,'General',?,?,?)")->execute([$id,$price,$quantity,$quantity]);$created++;
 }if($apply)$pdo->commit();else $pdo->rollBack();echo ($apply?'Applied':'Dry run').": $created new public events; no account or reaction fixtures imported.\n";
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
