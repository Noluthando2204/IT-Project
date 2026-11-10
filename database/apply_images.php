<?php
declare(strict_types=1);

// Runs on every Render deploy (see Dockerfile). Connects to the SAME database the website uses,
// so MySQL Workbench is not needed. Safe to run repeatedly. Never stops the deploy.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/database-config/connection.php';

function img_slug(string $text): string
{
    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($text)) ?? '';
    return trim($slug, '-');
}

function img_find(string $folder, string $prefix, array $slugs): ?string
{
    foreach ($slugs as $slug) {
        foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
            $relative = "uploaded-content/$folder/$prefix-$slug-01.$ext";
            $info = @getimagesize(dirname(__DIR__) . '/' . $relative);
            if ($info && in_array($info['mime'], ['image/png', 'image/jpeg', 'image/webp'], true)) {
                return $relative;
            }
        }
    }
    return null;
}

// Every seeded event now ships with its own cover (uploaded-content/event-banners/event-<slug>-01.jpg),
// so no event borrows another event's picture (those carried the wrong event name in the artwork).
$fallbacks = [];

echo "== EventNest image check ==\n";
foreach (['event-banners', 'category-images'] as $folder) {
    $names = array_map('basename', glob(dirname(__DIR__) . "/uploaded-content/$folder/*") ?: []);
    echo "FOLDER $folder: " . count($names) . ' file(s): ' . implode(', ', $names) . "\n";
}

// Career fields without their own picture borrow the closest finished one.
$categoryFallbacks = [
    'design-creative-careers' => 'design',
    'ict-digital-technology' => 'technology',
    'healthcare-medical' => 'dermatology',
];

try {

    foreach ($pdo->query('SELECT EventID, EventName, ImagePath FROM EVENTS')->fetchAll() as $event) {
        $slug = img_slug((string) $event['EventName']);
        $slugs = array_unique([$slug, preg_replace('/-20\d\d$/', '', $slug) ?? $slug]);
        $path = img_find('event-banners', 'event', $slugs);
        $source = 'own image';

        if ($path === null && empty($event['ImagePath']) && isset($fallbacks[$slug])) {
            $path = img_find('event-banners', 'event', [$fallbacks[$slug]]);
            $source = 'shared image';
        }

        if ($path === null) {
            echo "EVENT  {$event['EventName']}: no image. Add uploaded-content/event-banners/event-{$slugs[0]}-01.png\n";
            continue;
        }
        if ($path !== $event['ImagePath']) {
            $pdo->prepare('UPDATE EVENTS SET ImagePath=? WHERE EventID=?')->execute([$path, $event['EventID']]);
        }
        echo "EVENT  {$event['EventName']}: OK ($source) $path\n";
    }

    foreach ($pdo->query('SELECT CategoryID, CategoryName, ImagePath FROM CATEGORIES')->fetchAll() as $category) {
        if(!empty($category['ImagePath']) && @getimagesize(dirname(__DIR__).'/'.$category['ImagePath'])) continue;
        $slug = img_slug((string) $category['CategoryName']);
        $path = img_find('category-images', 'category', [$slug]);
        if ($path === null && empty($category['ImagePath']) && isset($categoryFallbacks[$slug])) {
            $path = img_find('category-images', 'category', [$categoryFallbacks[$slug]]);
        }

        if ($path === null) {
            echo "FIELD  {$category['CategoryName']}: no image. Add uploaded-content/category-images/category-$slug-01.png\n";
            continue;
        }
        if ($path !== $category['ImagePath']) {
            $pdo->prepare('UPDATE CATEGORIES SET ImagePath=? WHERE CategoryID=?')->execute([$path, $category['CategoryID']]);
        }
        echo "FIELD  {$category['CategoryName']}: OK $path\n";
    }
} catch (Throwable $e) {
    echo 'Image step skipped: ' . $e->getMessage() . "\n";
}

exit(0);
