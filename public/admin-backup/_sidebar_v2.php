<?php
/**
 * Admin Sidebar Partial V2
 * Include this in all 2.0 admin pages
 * 
 * Required variables before include:
 *   $env - current environment (from Config)
 *   $currentPage - current page identifier for highlighting (e.g., 'posts', 'users', 'artists')
 */
$currentPage = $currentPage ?? '';

$navItems = [
    ['section' => 'Overview'],
    ['href' => '/admin/index.php', 'label' => 'Dashboard', 'id' => 'dashboard', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>'],
    ['section' => 'Portals'],
    ['href' => '/admin/erik-smr/login.php', 'label' => 'Erik Baker SMR', 'id' => 'erik-smr-portal', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>'],
    ['section' => 'Content'],
    ['href' => '/admin/posts.php', 'label' => 'posts', 'id' => 'posts', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path>'],
    ['href' => '/admin/pages.php', 'label' => 'Pages', 'id' => 'pages', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>'],
    ['href' => '/admin/videos.php', 'label' => 'videos', 'id' => 'videos', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>'],
    ['href' => '/admin/spins.php', 'label' => 'Spins', 'id' => 'spins', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"></path>'],
	['href' => '/admin/ads.php', 'label' => 'Ads', 'id' => 'ads', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"></path>'],
	['href' => '/admin/sparks.php', 'label' => 'Sparks', 'id' => 'sparks', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>'],
    ['href' => '/admin/events.php', 'label' => 'Events', 'id' => 'events', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>'],
    ['section' => 'Entities'],
    ['href' => '/admin/artists.php', 'label' => 'Artists', 'id' => 'artists', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>'],
    ['href' => '/admin/labels.php', 'label' => 'Labels', 'id' => 'labels', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>'],
    ['href' => '/admin/venues.php', 'label' => 'Venues', 'id' => 'venues', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>'],
    ['href' => '/admin/stations.php', 'label' => 'Stations', 'id' => 'stations', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.636 18.364a9 9 0 010-12.728m12.728 0a9 9 0 010 12.728m-9.9-2.829a5 5 0 010-7.07m7.072 0a5 5 0 010 7.07M13 12a1 1 0 11-2 0 1 1 0 012 0z"></path>'],
    ['section' => 'users'],
    ['href' => '/admin/users.php', 'label' => 'users', 'id' => 'users', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>'],
    ['href' => '/admin/contacts.php', 'label' => 'Contacts', 'id' => 'contacts', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>'],
    ['href' => '/admin/claims.php', 'label' => 'Claims', 'id' => 'claims', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>'],
    ['section' => 'Commerce'],
    ['href' => '/admin/orders.php', 'label' => 'Orders', 'id' => 'orders', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />'],
    ['href' => '/admin/products.php', 'label' => 'Products', 'id' => 'products', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />'],
    ['href' => '/admin/donations.php', 'label' => 'Donations', 'id' => 'donations', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />'],
    ['section' => 'Data'],
    ['href' => '/admin/data-2025.php', 'label' => '2025 Data', 'id' => 'data-2025', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path>'],
    ['href' => '/admin/charts-2025.php', 'label' => 'Charts', 'id' => 'charts', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>'],
    ['section' => 'Analytics'],
    ['href' => '/admin/analytics.php', 'label' => 'Overview', 'id' => 'analytics', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>'],
    ['href' => '/admin/analytics-spotify.php', 'label' => 'Spotify', 'id' => 'analytics-spotify', 'icon' => '<circle cx="12" cy="12" r="10" stroke-width="2"/><path stroke-linecap="round" stroke-width="2" d="M8 15c2-1 4-1 6 0M7 12c2.5-1.5 5.5-1.5 8 0M6 9c3-2 7-2 10 0"/>'],
    ['href' => '/admin/analytics-meta.php', 'label' => 'Meta/Facebook', 'id' => 'analytics-meta', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/>'],
    ['href' => '/admin/analytics-tiktok.php', 'label' => 'TikTok', 'id' => 'analytics-tiktok', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12a4 4 0 104 4V4a5 5 0 005 5"/>'],
    ['href' => '/admin/tracking.php', 'label' => 'Tracking Pixels', 'id' => 'tracking', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>'],
    ['section' => 'Data Ingestion'],
    ['href' => '/admin/smr-ingestion.php', 'label' => 'SMR Data', 'id' => 'smr-ingestion', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>'],
    ['section' => 'Royalties'],
    ['href' => '/admin/royalties.php', 'label' => 'Royalties', 'id' => 'royalties', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />'],
    ['section' => 'Legal'],
    ['href' => '/admin/counter_notice.php', 'label' => 'Counter Notices', 'id' => 'counter_notice', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />'],
    ['href' => '/admin/takedown_requests.php', 'label' => 'Takedown Requests', 'id' => 'takedown_requests', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />'],
    ['section' => 'Community Funding'],
    ['href' => '/admin/investments.php', 'label' => 'Investments', 'id' => 'investments', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />'],
    ['href' => '/admin/digital-signatures/index.php', 'label' => 'Digital Signatures', 'id' => 'digital-signatures', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>'],
    ['section' => 'Settings'],
    ['href' => '/admin/settings.php', 'label' => 'All Settings', 'id' => 'all-settings', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><circle cx="12" cy="12" r="3"></circle>'],
    ['href' => '/admin/ngn2.php', 'label' => 'Feature Flags', 'id' => 'settings', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>'],
    ['href' => '/admin/keys.php', 'label' => 'API Keys', 'id' => 'keys', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>'],
    ['href' => '/admin/env.php', 'label' => 'Raw .env', 'id' => 'env', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path>'],
    ['section' => 'Database'],
    ['href' => '/admin/database.php', 'label' => 'Overview', 'id' => 'database', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>'],
    ['href' => '/admin/database_explorer.php', 'label' => 'Explorer', 'id' => 'database-explorer', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>'],
    ['href' => '/admin/database_migrations.php', 'label' => 'Migrations', 'id' => 'database-migrations', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>'],
    ['href' => '/admin/database_backups.php', 'label' => 'Backups', 'id' => 'database-backups', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>'],
    ['href' => '/admin/database_schema.php', 'label' => 'Schema', 'id' => 'database-schema', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>'],
    ['section' => 'Migration'],
    ['href' => '/admin/upgrade.php', 'label' => 'Upgrade Wizard', 'id' => 'upgrade', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>'],
    ['href' => '/admin/progress.php', 'label' => 'Live Progress', 'id' => 'progress', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>'],
    ['href' => '/admin/roadmap.php', 'label' => 'Roadmap', 'id' => 'roadmap', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>'],
    ['href' => '/admin/user-stories/index.php', 'label' => 'User Story Checklists', 'id' => 'user-stories', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>'],
    ['href' => '/admin/testing/index.php', 'label' => 'Integrations Test', 'id' => 'testing', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>'],
];
?>
<aside class="hidden lg:flex flex-col border-r border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 backdrop-blur">
  <div class="p-4 flex items-center gap-3 border-b border-gray-200 dark:border-white/10">
    <div class="h-8 w-8 rounded bg-brand flex items-center justify-center text-white font-bold text-sm">N</div>
    <div>
      <div class="font-semibold">NGN Admin</div>
      <div class="text-xs text-gray-500 dark:text-gray-400">Env: <?php echo htmlspecialchars($env ?? 'unknown'); ?></div>
    </div>
  </div>
  <nav class="p-3 space-y-1 text-sm flex-1 overflow-y-auto">
    <?php foreach ($navItems as $item): ?>
      <?php if (isset($item['section'])): ?>
        <div class="text-[11px] uppercase tracking-wide text-gray-500 dark:text-gray-400 mt-4 mb-1 px-3 first:mt-0"><?php echo htmlspecialchars($item['section']); ?></div>
      <?php else: ?>
        <?php 
          $isActive = ($currentPage === $item['id']);
          $classes = $isActive 
            ? 'block px-3 py-2 rounded bg-brand/10 text-brand border border-brand/20 font-medium'
            : 'block px-3 py-2 rounded hover:bg-gray-100 dark:hover:bg-white/10';
        ?>
        <a href="<?php echo htmlspecialchars($item['href']); ?>" class="<?php echo $classes; ?>">
          <span class="inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?php echo $item['icon']; ?></svg>
            <?php echo htmlspecialchars($item['label']); ?>
          </span>
        </a>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>
  <div class="p-3 border-t border-gray-200 dark:border-white/10 text-xs text-gray-500 dark:text-gray-400">
    <div class="flex items-center justify-between">
      <span>NGN 2.0 Admin</span>
      <a href="/" class="text-brand hover:underline">View Site →</a>
    </div>
  </div>
</aside>