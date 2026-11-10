<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../shared-components/config.php';
// Provider secrets belong in the job environment. No password arguments or public trigger.
$root=dirname(__DIR__);$dir=$root.'/.review/backups';
foreach(['DB_HOST','DB_NAME','DB_USER'] as $key)if(!getenv($key))throw new RuntimeException('Backup job DB environment is incomplete.');
if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('Private backup storage unavailable.');
$binary=getenv('EVENTNEST_MYSQLDUMP')?:'mysqldump';$file=$dir.'/eventnest-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3)).'.sql';
$cfg=tempnam($dir,'client-');chmod($cfg,0600);
$quote=fn($v)=>'"'.str_replace(['\\','"',"\n","\r"],['\\\\','\\"','\\n','\\r'],(string)$v).'"';
$options="[client]\nhost=".$quote(getenv('DB_HOST'))."\nport=".(int)(getenv('DB_PORT')?:3306)."\nuser=".$quote(getenv('DB_USER'))."\npassword=".$quote(getenv('DB_PASS'))."\n";
if(getenv('DB_SSL_CA'))$options.='ssl-ca='.$quote(getenv('DB_SSL_CA'))."\n";
file_put_contents($cfg,$options);
try{$process=proc_open([$binary,'--defaults-extra-file='.$cfg,'--single-transaction','--routines','--triggers','--hex-blob','--result-file='.$file,(string)getenv('DB_NAME')],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($process))throw new RuntimeException('Backup tool unavailable.');fclose($pipes[0]);stream_get_contents($pipes[1]);fclose($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[2]);if(proc_close($process)!==0||!is_file($file)||filesize($file)<100){@unlink($file);throw new RuntimeException('Backup failed; inspect protected job logs.');}chmod($file,0600);file_put_contents($file.'.sha256',hash_file('sha256',$file));echo "Private database backup produced; SHA-256 sidecar saved. Restore verification is a separate step.\n";}finally{@unlink($cfg);}
