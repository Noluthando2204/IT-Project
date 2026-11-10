<?php
require_once __DIR__.'/shared-components/auth.php';
// Visible categories also have useful empty states when no events are scheduled.
$categories=eventnest_db()->query("SELECT c.CategoryID,c.CategoryName,c.Description,c.ImagePath,COUNT(CASE WHEN u.UserID IS NOT NULL THEN e.EventID END) AS Upcoming
    FROM CATEGORIES c
    LEFT JOIN EVENTS e ON e.CategoryID=c.CategoryID AND e.Status='published' AND e.EventDate>NOW()
    LEFT JOIN USERS u ON u.UserID=e.OrganiserID AND u.Role='organiser' AND u.VerificationStatus='verified' AND u.UserStatus='active'
    WHERE c.IsVisible=1
    GROUP BY c.CategoryID,c.CategoryName,c.Description,c.ImagePath
    ORDER BY c.CategoryName")->fetchAll();
$page_title='Career fields';
require __DIR__.'/shared-components/header.php';
?>
<main><h1>Explore career fields</h1><p><?= escape_html(eventnest_public_copy('career_intro','Find events, build your skills and connect with professionals in your field.')) ?> <a href="organisers.php">Meet the organisers</a>.</p><?php eventnest_inline_copy('career_intro','Find events, build your skills and connect with professionals in your field.'); ?><div class="event-grid"><?php foreach($categories as $category): ?><article class="content-card"><img class="event-card__image" src="<?= escape_html(eventnest_image_url($category['ImagePath'], 'category')) ?>" alt="<?= escape_html($category['CategoryName']) ?>" width="1600" height="900" loading="lazy"><h2><?= escape_html($category['CategoryName']) ?></h2><p><?= escape_html($category['Description']) ?></p><p class="muted"><?= (int)$category['Upcoming'] ?> upcoming <?= (int)$category['Upcoming']===1?'event':'events' ?></p><a class="btn btn-blue" href="events.php?category_id=<?= (int)$category['CategoryID'] ?>">View events</a><?php eventnest_inline_category($category); ?></article><?php endforeach; ?></div><?php if(!$categories): ?><p class="empty-state">No career fields have upcoming events yet.</p><?php endif; ?><?php eventnest_inline_category(null); ?></main>
<?php require __DIR__.'/shared-components/footer.php'; ?>
