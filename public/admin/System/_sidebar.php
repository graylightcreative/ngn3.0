<?php
/**
 * Admin Sidebar Partial V2 (Collapsible)
 * Include this in all 2.0 admin pages
 * 
 * Required variables before include:
 *   $env - current environment (from Config)
 *   $currentPage - current page identifier for highlighting (e.g., 'posts', 'users', 'artists')
 */
$currentPage = $currentPage ?? '';

// Define menu structure
$menu = [
    [
        'label' => 'Overview',
        'href' => '/admin/index.php',
        'id' => 'dashboard',
        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>'
    ],
    [
        'label' => 'Content',
        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path>',
        'items' => [
            ['href' => '/admin/posts.php', 'label' => 'posts', 'id' => 'posts'],
            ['href' => '/admin/pages.php', 'label' => 'Pages', 'id' => 'pages'],
            ['href' => '/admin/videos.php', 'label' => 'videos', 'id' => 'videos'],
            ['href' => '/admin/spins.php', 'label' => 'Spins', 'id' => 'spins'],
            ['href' => '/admin/ads.php', 'label' => 'Ads', 'id' => 'ads'],
            ['href' => '/admin/sparks.php', 'label' => 'Sparks', 'id' => 'sparks'],
        ]
    ],
    [
        'label' => 'Entities',
        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>',
        'items' => [
            ['href' => '/admin/artists.php', 'label' => 'Artists', 'id' => 'artists'],
            ['href' => '/admin/labels.php', 'label' => 'Labels', 'id' => 'labels'],
            ['href' => '/admin/venues.php', 'label' => 'Venues', 'id' => 'venues'],
            ['href' => '/admin/stations.php', 'label' => 'Stations', 'id' => 'stations'],
        ]
    ],
    [
        'label' => 'users',
        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>',
        'items' => [
            ['href' => '/admin/users.php', 'label' => 'users', 'id' => 'users'],
            ['href' => '/admin/contacts.php', 'label' => 'Contacts', 'id' => 'contacts'],
            ['href' => '/admin/claims.php', 'label' => 'Claims', 'id' => 'claims'],
        ]
    ],
    [
        'label' => 'Commerce',
        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />',
        'items' => [
            ['href' => '/admin/orders.php', 'label' => 'Orders', 'id' => 'orders'],
            ['href' => '/admin/products.php', 'label' => 'Products', 'id' => 'products'],
            ['href' => '/admin/donations.php', 'label' => 'Donations', 'id' => 'donations'],
        ]
    ],
    [
        'label' => 'Data & Analytics',
        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>',
        'items' => [
            ['href' => '/admin/data-2025.php', 'label' => '2025 Data', 'id' => 'data-2025'],
            ['href' => '/admin/charts-2025.php', 'label' => 'Charts', 'id' => 'charts'],
            ['href' => '/admin/analytics.php', 'label' => 'Analytics Overview', 'id' => 'analytics'],
            ['href' => '/admin/analytics-spotify.php', 'label' => 'Spotify', 'id' => 'analytics-spotify'],
            ['href' => '/admin/analytics-meta.php', 'label' => 'Meta/Facebook', 'id' => 'analytics-meta'],
            ['href' => '/admin/analytics-tiktok.php', 'label' => 'TikTok', 'id' => 'analytics-tiktok'],
            ['href' => '/admin/tracking.php', 'label' => 'Tracking Pixels', 'id' => 'tracking'],
        ]
    ],
    [
        'label' => 'Finance & Legal',
        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"></path>',
        'items' => [
            ['href' => '/admin/royalties.php', 'label' => 'Royalties', 'id' => 'royalties'],
            ['href' => '/admin/counter_notice.php', 'label' => 'Counter Notices', 'id' => 'counter_notice'],
            ['href' => '/admin/takedown_requests.php', 'label' => 'Takedown Requests', 'id' => 'takedown_requests'],
            ['href' => '/admin/ppv_expenses.php', 'label' => 'PPV Expenses', 'id' => 'ppv_expenses'],
            ['href' => '/admin/investments.php', 'label' => 'Investments', 'id' => 'investments'],
        ]
    ],
    [
        'label' => 'System',
        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><circle cx="12" cy="12" r="3"></circle>',
        'items' => [
            ['href' => '/admin/settings.php', 'label' => 'Settings', 'id' => 'all-settings'],
            ['href' => '/admin/ngn2.php', 'label' => 'Feature Flags', 'id' => 'settings'],
            ['href' => '/admin/keys.php', 'label' => 'API Keys', 'id' => 'keys'],
            ['href' => '/admin/env.php', 'label' => 'Environment', 'id' => 'env'],
            ['href' => '/admin/database.php', 'label' => 'Database', 'id' => 'database'],
            ['href' => '/admin/database_explorer.php', 'label' => 'DB Explorer', 'id' => 'database-explorer'],
            ['href' => '/admin/upgrade.php', 'label' => 'Upgrade Wizard', 'id' => 'upgrade'],
            ['href' => '/admin/progress.php', 'label' => 'Live Progress', 'id' => 'progress'],
            ['href' => '/admin/roadmap.php', 'label' => 'Roadmap', 'id' => 'roadmap'],
        ]
    ]
];
?>
<aside class="hidden lg:flex flex-col border-r border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 backdrop-blur w-64">
  <div class="p-4 flex items-center gap-3 border-b border-gray-200 dark:border-white/10">
    <div class="h-8 w-8 rounded bg-brand flex items-center justify-center text-white font-bold text-sm">N</div>
    <div>
      <div class="font-semibold">NGN Admin</div>
      <div class="text-xs text-gray-500 dark:text-gray-400">Env: <?php echo htmlspecialchars($env ?? 'unknown'); ?></div>
    </div>
  </div>
  <nav class="p-3 space-y-1 text-sm flex-1 overflow-y-auto" id="adminSidebarNav">
    <?php foreach ($menu as $idx => $section): ?>
      <?php if (isset($section['href'])): ?>
        <?php 
          $isActive = ($currentPage === $section['id']);
          $classes = $isActive 
            ? 'block px-3 py-2 rounded bg-brand/10 text-brand border border-brand/20 font-medium'
            : 'block px-3 py-2 rounded hover:bg-gray-100 dark:hover:bg-white/10 text-gray-700 dark:text-gray-300';
        ?>
        <a href="<?php echo htmlspecialchars($section['href']); ?>" class="<?php echo $classes; ?>">
          <span class="inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?php echo $section['icon']; ?></svg>
            <?php echo htmlspecialchars($section['label']); ?>
          </span>
        </a>
      <?php else: ?>
        <?php 
          // Check if any child is active to auto-expand
          $hasActiveChild = false;
          foreach ($section['items'] as $item) {
            if ($currentPage === $item['id']) { $hasActiveChild = true; break; }
          }
          $isOpen = $hasActiveChild ? 'open' : '';
          $rotate = $hasActiveChild ? 'rotate-90' : '';
        ?>
        <div class="group">
          <button type="button" class="w-full flex items-center justify-between px-3 py-2 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/5 rounded transition-colors" onclick="toggleMenu('menu-<?php echo $idx; ?>', this)">
            <span class="inline-flex items-center gap-2 font-medium">
              <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?php echo $section['icon']; ?></svg>
              <?php echo htmlspecialchars($section['label']); ?>
            </span>
            <svg class="w-3 h-3 text-gray-400 transition-transform duration-200 <?php echo $rotate; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
          </button>
          <div id="menu-<?php echo $idx; ?>" class="pl-9 pr-2 space-y-0.5 overflow-hidden transition-all duration-300 ease-in-out <?php echo $hasActiveChild ? 'max-h-96 opacity-100 mt-1 mb-2' : 'max-h-0 opacity-0'; ?>">
            <?php foreach ($section['items'] as $item): ?>
              <?php 
                $isActive = ($currentPage === $item['id']);
                $classes = $isActive 
                  ? 'block px-2 py-1.5 rounded text-brand font-medium text-xs bg-brand/5'
                  : 'block px-2 py-1.5 rounded text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-white/5 text-xs transition-colors';
              ?>
              <a href="<?php echo htmlspecialchars($item['href']); ?>" class="<?php echo $classes; ?>">
                <?php echo htmlspecialchars($item['label']); ?>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>
  <div class="p-3 border-t border-gray-200 dark:border-white/10">
    <button onclick="startNgnTour()" class="w-full flex items-center justify-center gap-2 px-3 py-2 text-xs bg-brand/10 text-brand border border-brand/20 rounded hover:bg-brand/20 transition-colors mb-2">
      <i class="bi bi-compass"></i> Take a Tour
    </button>
    <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
      <span>NGN 2.0 Admin</span>
      <a href="/" class="text-brand hover:underline">View Site →</a>
    </div>
  </div>
  <script>
    function toggleMenu(id, btn) {
      const el = document.getElementById(id);
      const icon = btn.querySelector('svg:last-child');
      
      if (el.classList.contains('max-h-0')) {
        el.classList.remove('max-h-0', 'opacity-0');
        el.classList.add('max-h-96', 'opacity-100', 'mt-1', 'mb-2');
        icon.classList.add('rotate-90');
      } else {
        el.classList.add('max-h-0', 'opacity-0');
        el.classList.remove('max-h-96', 'opacity-100', 'mt-1', 'mb-2');
        icon.classList.remove('rotate-90');
      }
    }
  </script>
</aside>