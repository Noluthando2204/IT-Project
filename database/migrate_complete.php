<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../shared-components/functions.php';
require_once __DIR__.'/../database-config/connection.php';
$pdo=eventnest_db();
if(!in_array('--apply',$argv,true)){echo "Back up first. Use --apply for additive schema and category reconciliation.\n";exit;}
$column=function(string $table,string $name,string $definition)use($pdo){$s=$pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$s->execute([$table,$name]);if(!$s->fetchColumn())$pdo->exec("ALTER TABLE $table ADD COLUMN $name $definition");};
$column('EVENTS','City','VARCHAR(100) NULL');
$column('POSTS','EditedAt','DATETIME NULL');
$column('CATEGORIES','IsVisible','TINYINT NOT NULL DEFAULT 1');
$pdo->exec("CREATE TABLE IF NOT EXISTS SECURITY_AUDIT (AuditID BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,ActorID INT UNSIGNED NULL,EventName VARCHAR(80) NOT NULL,Outcome VARCHAR(40) NOT NULL,CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
$column('SECURITY_AUDIT','Summary','TEXT NULL');
$pdo->exec("CREATE TABLE IF NOT EXISTS ACCOUNT_TOKENS (TokenHash CHAR(64) PRIMARY KEY,UserID INT UNSIGNED NOT NULL,Purpose VARCHAR(30) NOT NULL,Payload VARCHAR(255) NULL,PasswordMarker CHAR(64) NOT NULL,ExpiresAt DATETIME NOT NULL,CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,KEY(UserID,Purpose),FOREIGN KEY(UserID) REFERENCES USERS(UserID) ON DELETE CASCADE) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE IF NOT EXISTS PUBLIC_COPY (CopyKey VARCHAR(80) PRIMARY KEY,Content TEXT NOT NULL) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE IF NOT EXISTS LOGIN_FAILURES (AccountKey CHAR(64) PRIMARY KEY,Attempts INT NOT NULL DEFAULT 0,WindowStart DATETIME NOT NULL,AlertAt DATETIME NULL) ENGINE=InnoDB");
$pdo->beginTransaction();
try{
 $rows=$pdo->query('SELECT CategoryID,CategoryName FROM CATEGORIES FOR UPDATE')->fetchAll(PDO::FETCH_KEY_PAIR);$ids=array_flip($rows);
 $target=$ids['ICT & Digital Technology']??$ids['Technology']??null;
 if(!$target)throw new RuntimeException('ICT category missing; review catalogue before applying.');
 foreach(['Technology','Security'] as $name){if(!isset($ids[$name]))continue;$old=$ids[$name];if($old!=$target){foreach(['CategoryID','AllowedCareerField'] as $field)$pdo->prepare("UPDATE EVENTS SET $field=? WHERE $field=?")->execute([$target,$old]);$pdo->prepare('INSERT IGNORE INTO Post_Categories(post_id,category_id) SELECT post_id,? FROM Post_Categories WHERE category_id=?')->execute([$target,$old]);$pdo->prepare('DELETE FROM Post_Categories WHERE category_id=?')->execute([$old]);$pdo->prepare('UPDATE CATEGORIES SET IsVisible=0 WHERE CategoryID=?')->execute([$old]);}$pdo->prepare('UPDATE USERS SET CareerField=? WHERE CareerField=?')->execute(['ICT & Digital Technology',$name]);}
 $pdo->prepare('UPDATE CATEGORIES SET CategoryName=? WHERE CategoryID=?')->execute(['ICT & Digital Technology',$target]);
 if(isset($ids['Design']) && !isset($ids['Design & Creative Careers'])) {
  $pdo->prepare('UPDATE CATEGORIES SET CategoryName=? WHERE CategoryID=?')->execute(['Design & Creative Careers',$ids['Design']]);
  $pdo->prepare('UPDATE USERS SET CareerField=? WHERE CareerField=?')->execute(['Design & Creative Careers','Design']);
 }
 if(isset($ids['Design'],$ids['Design & Creative Careers']) && $ids['Design']!==$ids['Design & Creative Careers']) {
  $old=$ids['Design'];$canonical=$ids['Design & Creative Careers'];
  foreach(['CategoryID','AllowedCareerField'] as $field)$pdo->prepare("UPDATE EVENTS SET $field=? WHERE $field=?")->execute([$canonical,$old]);
  $pdo->prepare('INSERT IGNORE INTO Post_Categories(post_id,category_id) SELECT post_id,? FROM Post_Categories WHERE category_id=?')->execute([$canonical,$old]);
  $pdo->prepare('DELETE FROM Post_Categories WHERE category_id=?')->execute([$old]);
  $pdo->prepare('UPDATE CATEGORIES SET IsVisible=0 WHERE CategoryID=?')->execute([$old]);
  $pdo->prepare('UPDATE USERS SET CareerField=? WHERE CareerField=?')->execute(['Design & Creative Careers','Design']);
 }
 $pdo->exec("UPDATE CATEGORIES SET CategoryName='Allied Health & Medical Careers' WHERE CategoryName='Healthcare & Medical'");
 $pdo->exec("UPDATE USERS SET CareerField='Allied Health & Medical Careers' WHERE CareerField='Healthcare & Medical'");
 $map=['Career Development'=>'01_Career_Development.jpg','Cloud Computing'=>'02_Cloud_Computing.jpg','Dermatology'=>'03_Dermatology.jpg','Design'=>'04_Design_and_Creative_Careers.jpg','Design & Creative Careers'=>'04_Design_and_Creative_Careers.jpg','Engineering'=>'05_Engineering.jpg','Finance & Accounting'=>'06_Finance_and_Accounting.jpg','Allied Health & Medical Careers'=>'07_Allied_Health_&_Medical_Careers.png','Networking'=>'08_Networking.png','Leadership'=>'09_Leadership.png','ICT & Digital Technology'=>'10_ICT_&_Digital_Technology.png'];
 $map['Skilled Trades & Technical Careers']='11_Skilled_Trades_&_Techinal_Careers.png';
 foreach($map as $name=>$file){
  $path='uploaded-content/category-images/'.$file;
  if(!@getimagesize(dirname(__DIR__).'/'.$path))throw new RuntimeException('Missing supplied category image: '.$file);
  $s=$pdo->prepare('SELECT ImagePath FROM CATEGORIES WHERE CategoryName=?');$s->execute([$name]);$current=$s->fetchColumn();
  // Preserve a valid administrator-uploaded category image on subsequent starts.
  $custom=is_string($current) && preg_match('#^uploaded-content/category-images/category_[a-f0-9]+\.(jpg|jpeg|png|webp)$#D',$current) && eventnest_safe_media_path($current,'image')!==null;
  if(!$custom)$pdo->prepare('UPDATE CATEGORIES SET ImagePath=? WHERE CategoryName=?')->execute([$path,$name]);
 }
 $pdo->commit();echo "Migration complete. No events, bookings, payments or users deleted.\n";
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
