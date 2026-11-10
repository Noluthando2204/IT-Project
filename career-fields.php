<?php
require_once __DIR__.'/shared-components/auth.php';
$categories=eventnest_db()->query('SELECT CategoryID,CategoryName,Description,ImagePath FROM CATEGORIES WHERE IsVisible=1 ORDER BY CategoryName')->fetchAll();
$page_title='Career fields';
require __DIR__.'/shared-components/header.php';
?>
<main><h1>Explore career fields</h1>
<p><?= escape_html(eventnest_public_copy('career_intro','Find events, build your skills and connect with professionals in your field.')) ?> <a href="organisers.php">Meet the organisers</a>.</p>
<?php eventnest_inline_copy('career_intro','Find events, build your skills and connect with professionals in your field.'); ?>
<div class="event-grid">
<?php foreach($categories as $category): ?>
<article class="content-card">
<img class="event-card__image" src="<?= escape_html(eventnest_image_url($category['ImagePath'],'category')) ?>" alt="<?= escape_html($category['CategoryName']) ?>" width="1600" height="900" loading="lazy">
<h2><?= escape_html($category['CategoryName']) ?></h2>
<p><?= escape_html($category['Description'] ?? '') ?></p>
<a class="btn btn-blue" href="events.php?category_id=<?= (int)$category['CategoryID'] ?>">View events</a>
<?php eventnest_inline_category($category); ?></article>
<?php endforeach; ?></div>
<?php if(!$categories): ?><p class="empty-state">No career fields are available yet.</p><?php endif; ?>
<?php eventnest_inline_category(null); ?></main>
<?php require __DIR__.'/shared-components/footer.php'; ?>
