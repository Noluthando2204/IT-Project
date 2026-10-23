<?php
require_once __DIR__.'/shared-components/auth.php';
$categories=eventnest_db()->query('SELECT CategoryID,CategoryName,Description FROM CATEGORIES ORDER BY CategoryName')->fetchAll();
$page_title='Career fields';
require __DIR__.'/shared-components/header.php';
?>
<main><h1>Explore career fields</h1><p>Find events that support your next step.</p><div class="event-grid"><?php foreach($categories as $category): ?><article class="content-card"><h2><?= escape_html($category['CategoryName']) ?></h2><p><?= escape_html($category['Description']) ?></p><a class="btn btn-blue" href="events.php?category_id=<?= (int)$category['CategoryID'] ?>">View events</a></article><?php endforeach; ?></div><?php if(!$categories): ?><p class="empty-state">No career categories are available yet.</p><?php endif; ?></main>
<?php require __DIR__.'/shared-components/footer.php'; ?>
