<?php
/*
|--------------------------------------------------------------------------
| EventNest - Event Card Component
|--------------------------------------------------------------------------
| Reusable card for the homepage, event listings, search results and
| hashtag results. Per the wireframe's coding consistency rules: one
| clear action (View details), no heart/wishlist icon, no secondary
| save action.
|
| Set $event, then include this file for each event in a loop, e.g.:
|     foreach ($events as $event) {
|         require __DIR__ . '/../shared-components/event.php';
|     }
|
| Expected $event keys:
|   'id'       => int
|   'title'    => string
|   'date'     => string    e.g. '17 Sep 2026'
|   'location' => string    e.g. 'Johannesburg' or 'Online'
|   'price'    => string    e.g. 'From R150' or 'Free'
|   'category' => string|null   optional tag, e.g. 'Cloud'
|   'featured' => bool|null     optional 'Featured' badge
|   'image'    => string|null   linked POSTS.Media path; EVENTS has no
|                                cover-image field per the ERD, so this
|                                falls back to the default banner
|--------------------------------------------------------------------------
*/

$event['image']    = $event['image']    ?? '/IT-Project/images/default-banner.png';
$event['category'] = $event['category'] ?? null;
$event['featured'] = $event['featured'] ?? false;
?>
<article class="event-card">

    <div class="event-card-media">
        <img src="<?php echo htmlspecialchars($event['image']); ?>" alt="" loading="lazy">

        <?php if ($event['featured']): ?>
            <span class="event-card-badge event-card-badge-featured">Featured</span>
        <?php endif; ?>
    </div>

    <div class="event-card-body">

        <h3 class="event-card-title">
            <?php echo htmlspecialchars($event['title']); ?>
        </h3>

        <p class="event-card-meta">
            <?php echo htmlspecialchars($event['date']); ?>
            &middot;
            <?php echo htmlspecialchars($event['location']); ?>
        </p>

        <?php if (!empty($event['category'])): ?>
            <span class="event-card-tag">
                <?php echo htmlspecialchars($event['category']); ?>
            </span>
        <?php endif; ?>

        <div class="event-card-footer">

            <span class="event-card-price">
                <?php echo htmlspecialchars($event['price']); ?>
            </span>

            <a
                href="/IT-Project/pages/event-detail.php?id=<?php echo (int) $event['id']; ?>"
                class="btn btn-primary btn-sm"
            >
                View details
            </a>

        </div>

    </div>

</article>