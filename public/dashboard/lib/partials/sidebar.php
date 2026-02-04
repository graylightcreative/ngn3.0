<?php
/**
 * Dashboard Sidebar Partial
 * Navigation sidebar for entity dashboards
 */
$user = dashboard_get_user();
$entityType = dashboard_get_entity_type();
$entityLabel = ucfirst($entityType ?? 'User');
$currentPage = $currentPage ?? 'home';
$baseurl = '/'; // Base URL for navigation links

// Get user initials for avatar fallback
$initials = '';
if (!empty($user['Title'])) {
    $words = explode(' ', $user['Title']);
    $initials = strtoupper(substr($words[0], 0, 1));
    if (count($words) > 1) {
        $initials .= strtoupper(substr(end($words), 0, 1));
    }
}

// Build navigation based on entity type
$navItems = [
    'main' => [
        ['id' => 'home', 'label' => 'Dashboard', 'icon' => 'bi-house-fill', 'href' => 'index.php'],
        ['id' => 'analytics', 'label' => 'Analytics', 'icon' => 'bi-graph-up', 'href' => 'analytics.php'],
        ['id' => 'score', 'label' => 'NGN Score', 'icon' => 'bi-trophy-fill', 'href' => 'score.php'],
    ],
    'content' => [],
    'settings' => [
        ['id' => 'profile', 'label' => 'Profile', 'icon' => 'bi-person-fill', 'href' => 'profile.php'],
        ['id' => 'connections', 'label' => 'Connections', 'icon' => 'bi-share-fill', 'href' => 'connections.php'],
        ['id' => 'settings', 'label' => 'Settings', 'icon' => 'bi-gear-fill', 'href' => 'settings.php'],
    ]
];

// Add entity-specific content items
if ($entityType === 'artist') {
    $navItems['content'] = [
        ['id' => 'releases', 'label' => 'releases', 'icon' => 'bi-disc-fill', 'href' => 'releases.php'],
        ['id' => 'songs', 'label' => 'Songs', 'icon' => 'bi-music-note-beamed', 'href' => 'songs.php'],
        ['id' => 'shows', 'label' => 'shows', 'icon' => 'bi-calendar-event-fill', 'href' => 'shows.php'],
        ['id' => 'tours', 'label' => 'Tours', 'icon' => 'bi-map', 'href' => 'tours.php'],
        ['id' => 'videos', 'label' => 'videos', 'icon' => 'bi-camera-video-fill', 'href' => 'videos.php'],
        ['id' => 'posts', 'label' => 'posts', 'icon' => 'bi-newspaper', 'href' => 'posts.php'],
        ['id' => 'fans', 'label' => 'Fans', 'icon' => 'bi-people-fill', 'href' => 'fans.php'],
        ['id' => 'tiers', 'label' => 'Fan Tiers', 'icon' => 'bi-gem', 'href' => 'tiers.php'],
    ];
} elseif ($entityType === 'label') {
    $navItems['content'] = [
        ['id' => 'roster', 'label' => 'Artist Roster', 'icon' => 'bi-people-fill', 'href' => 'roster.php'],
        ['id' => 'releases', 'label' => 'releases', 'icon' => 'bi-disc-fill', 'href' => 'releases.php'],
        ['id' => 'posts', 'label' => 'News & Posts', 'icon' => 'bi-newspaper', 'href' => 'posts.php'],
        ['id' => 'videos', 'label' => 'videos', 'icon' => 'bi-camera-video-fill', 'href' => 'videos.php'],
        ['id' => 'campaigns', 'label' => 'Email Campaigns', 'icon' => 'bi-envelope-fill', 'href' => 'campaigns.php'],
    ];
} elseif ($entityType === 'venue') {
    $navItems['content'] = [
        ['id' => 'shows', 'label' => 'shows', 'icon' => 'bi-calendar-event-fill', 'href' => 'shows.php'],
        ['id' => 'bookings', 'label' => 'Bookings', 'icon' => 'bi-clipboard-check', 'href' => 'bookings.php'],
        ['id' => 'posts', 'label' => 'News & Posts', 'icon' => 'bi-newspaper', 'href' => 'posts.php'],
        ['id' => 'videos', 'label' => 'videos', 'icon' => 'bi-camera-video-fill', 'href' => 'videos.php'],
    ];
} elseif ($entityType === 'station') {
    $navItems['content'] = [
        ['id' => 'spins', 'label' => 'Radio Spins', 'icon' => 'bi-broadcast', 'href' => 'spins.php'],
        ['id' => 'content', 'label' => 'BYOS Content', 'icon' => 'bi-music-note-list', 'href' => 'content.php'],
        ['id' => 'playlists', 'label' => 'PLN Playlists', 'icon' => 'bi-list-ul', 'href' => 'playlists.php'],
        ['id' => 'live', 'label' => 'Live Requests', 'icon' => 'bi-chat-right-text', 'href' => 'live.php'],
        ['id' => 'tier', 'label' => 'Subscription', 'icon' => 'bi-star-fill', 'href' => 'tier.php'],
        ['id' => 'shows', 'label' => 'shows', 'icon' => 'bi-calendar-event-fill', 'href' => 'shows.php'],
        ['id' => 'posts', 'label' => 'News & Posts', 'icon' => 'bi-newspaper', 'href' => 'posts.php'],
    ];
}

// Add shop if commerce is enabled
$shopEnabled = strtolower((string)(getenv('FEATURE_SHOPS') ?: 'false'));
if (in_array($shopEnabled, ['1','true','on','yes'], true)) {
    $navItems['content'][] = ['id' => 'shop', 'label' => 'Shop', 'icon' => 'bi-bag-fill', 'href' => 'shop.php'];
}
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <a href="<?= $baseurl ?>" class="sidebar-logo">NGN</a>
    </div>
    
    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-section-title">Main</div>
            <?php foreach ($navItems['main'] as $item): ?>
            <a href="<?= $item['href'] ?>" class="nav-item <?= $currentPage === $item['id'] ? 'active' : '' ?>">
                <i class="<?= $item['icon'] ?>"></i>
                <span><?= $item['label'] ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        
        <?php if (!empty($navItems['content'])): ?>
        <div class="nav-section">
            <div class="nav-section-title">Content</div>
            <?php foreach ($navItems['content'] as $item): ?>
            <a href="<?= $item['href'] ?>" class="nav-item <?= $currentPage === $item['id'] ? 'active' : '' ?>">
                <i class="<?= $item['icon'] ?>"></i>
                <span><?= $item['label'] ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <div class="nav-section">
            <div class="nav-section-title">Account</div>
            <?php foreach ($navItems['settings'] as $item): ?>
            <a href="<?= $item['href'] ?>" class="nav-item <?= $currentPage === $item['id'] ? 'active' : '' ?>">
                <i class="<?= $item['icon'] ?>"></i>
                <span><?= $item['label'] ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </nav>
    
    <div class="sidebar-footer">
        <div class="user-card">
            <div class="user-avatar">
                <?php if (!empty($user['Image'])): ?>
                <img src="<?= $baseurl ?>lib/images/users/<?= htmlspecialchars($user['Slug']) ?>/<?= htmlspecialchars($user['Image']) ?>" alt="">
                <?php else: ?>
                <?= $initials ?>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($user['Title'] ?? 'User') ?></div>
                <div class="user-role"><?= $entityLabel ?></div>
            </div>
            <a href="<?= $baseurl ?>logout" title="Logout" style="color: var(--text-muted);">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</aside>

