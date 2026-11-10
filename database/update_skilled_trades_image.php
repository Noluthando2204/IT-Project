<?php
declare(strict_types=1);
// Targeted image update: does not rerun category renames or consolidation.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../shared-components/functions.php';
require_once __DIR__.'/../database-config/connection.php';
$path='uploaded-content/category-images/11_Skilled_Trades_&_Techinal_Careers.png';
if (!in_array('--apply',$argv,true)) {
    echo "Back up the target database, then use --apply to update only the Skilled Trades category image.\n";
    exit;
}
try {
    if (eventnest_safe_media_path($path,'image')===null || !@getimagesize(dirname(__DIR__).'/'.$path)) {
        throw new RuntimeException('Upload the supplied Skilled Trades PNG before applying this update.');
    }
    $pdo->beginTransaction();
    $s=$pdo->prepare('SELECT CategoryID,ImagePath FROM CATEGORIES WHERE CategoryName=? FOR UPDATE');
    $s->execute(['Skilled Trades & Technical Careers']);
    $rows=$s->fetchAll(PDO::FETCH_ASSOC);
    if(count($rows)!==1) throw new RuntimeException('Expected exactly one Skilled Trades & Technical Careers category; review its name before applying.');
    $row=$rows[0];
    $pdo->prepare('UPDATE CATEGORIES SET ImagePath=? WHERE CategoryID=?')->execute([$path,$row['CategoryID']]);
    $pdo->commit();
    echo "Skilled Trades image saved. No other categories or records changed.\n";
} catch(Throwable $e) {
    if($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR,$e->getMessage()."\n"); exit(1);
}
