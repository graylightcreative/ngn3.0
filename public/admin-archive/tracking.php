<?php
require_once dirname(__DIR__, 2) . '/_guard.php';
$root = dirname(__DIR__, 2);
require_once $root.'/lib/bootstrap.php';

use NGN\Lib\Env;
use NGN\Lib\Config;
use NGN\Lib\Tracking\PixelManager;

if (!class_exists('NGN\\Lib\\Env')) { exit('Bootstrap failed'); }
Env::load($root);
$cfg = new Config();
include __DIR__.'/_mint_token.php';

// Get pixel status
$pixelManager = PixelManager::getInstance();
$status = $pixelManager->getStatus();

$pageTitle = 'Tracking Pixels';
$currentPage = 'tracking';
include __DIR__.'/_header.php';

include __DIR__.'/_topbar.php';
?>

  <main class="flex-1 p-4 md:p-6 overflow-y-auto">
    <section class="max-w-4xl mx-auto space-y-6">

      <!-- Overview -->
      <div class="rounded-lg border <?= $status['enabled'] ? 'border-emerald-200 dark:border-emerald-500/30 bg-emerald-50/50 dark:bg-emerald-500/5' : 'border-amber-200 dark:border-amber-500/30 bg-amber-50/50 dark:bg-amber-500/5' ?> p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-full flex items-center justify-center <?= $status['enabled'] ? 'bg-emerald-100 dark:bg-emerald-500/20' : 'bg-amber-100 dark:bg-amber-500/20' ?>">
            <svg class="w-5 h-5 <?= $status['enabled'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
          </div>
          <div class="flex-1">
            <h3 class="font-semibold <?= $status['enabled'] ? 'text-emerald-800 dark:text-emerald-200' : 'text-amber-800 dark:text-amber-200' ?>">
              Tracking <?= $status['enabled'] ? 'Enabled' : 'Disabled' ?>
            </h3>
            <p class="text-sm <?= $status['enabled'] ? 'text-emerald-600 dark:text-emerald-300' : 'text-amber-600 dark:text-amber-300' ?>">
              <?= $status['enabled'] ? 'Pixels are active on public pages' : 'Set TRACKING_ENABLED=true in .env to enable' ?>
            </p>
          </div>
        </div>
      </div>

      <!-- GA4 Configuration -->
      <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 rounded-full flex items-center justify-center bg-blue-100 dark:bg-blue-500/20">
            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" viewBox="0 0 24 24" fill="currentColor">
              <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
              <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
              <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
              <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
            </svg>
          </div>
          <div class="flex-1">
            <h3 class="font-semibold">Google Analytics 4</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              <?php if ($status['ga4']['configured']): ?>
                Measurement ID: <?= htmlspecialchars($status['ga4']['id']) ?>
              <?php else: ?>
                Not configured - Set GA4_MEASUREMENT_ID in .env
              <?php endif; ?>
            </p>
          </div>
          <span class="px-2 py-1 rounded text-xs font-medium <?= $status['ga4']['configured'] ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400' : 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-400' ?>">
            <?= $status['ga4']['configured'] ? 'Active' : 'Inactive' ?>
          </span>
        </div>
        <div class="text-sm text-gray-600 dark:text-gray-400">
          <p class="mb-2">GA4 tracks page views, user sessions, and custom events. Configure in your .env file:</p>
          <code class="block bg-gray-100 dark:bg-white/5 p-2 rounded text-xs">GA4_MEASUREMENT_ID=G-XXXXXXXXXX</code>
        </div>
      </div>

      <!-- Meta Pixel Configuration -->
      <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 rounded-full flex items-center justify-center bg-blue-100 dark:bg-blue-500/20">
            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 24 24">
              <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
            </svg>
          </div>
          <div class="flex-1">
            <h3 class="font-semibold">Meta Pixel (Facebook)</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              <?php if ($status['meta_pixel']['configured']): ?>
                Pixel ID: <?= htmlspecialchars($status['meta_pixel']['id']) ?>
              <?php else: ?>
                Not configured - Set META_PIXEL_ID in .env
              <?php endif; ?>
            </p>
          </div>
          <span class="px-2 py-1 rounded text-xs font-medium <?= $status['meta_pixel']['configured'] ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400' : 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-400' ?>">
            <?= $status['meta_pixel']['configured'] ? 'Active' : 'Inactive' ?>
          </span>
        </div>
        <div class="text-sm text-gray-600 dark:text-gray-400">
          <p class="mb-2">Meta Pixel tracks conversions and enables retargeting on Facebook/Instagram. Configure in your .env file:</p>
          <code class="block bg-gray-100 dark:bg-white/5 p-2 rounded text-xs">META_PIXEL_ID=1234567890123456</code>
        </div>
      </div>

      <!-- TikTok Pixel Configuration -->
      <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 rounded-full flex items-center justify-center bg-gray-100 dark:bg-white/10">
            <svg class="w-5 h-5 text-gray-800 dark:text-white" fill="currentColor" viewBox="0 0 24 24">
              <path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-5.2 1.74 2.89 2.89 0 012.31-4.64 2.93 2.93 0 01.88.13V9.4a6.84 6.84 0 00-1-.05A6.33 6.33 0 005 20.1a6.34 6.34 0 0010.86-4.43v-7a8.16 8.16 0 004.77 1.52v-3.4a4.85 4.85 0 01-1-.1z"/>
            </svg>
          </div>
          <div class="flex-1">
            <h3 class="font-semibold">TikTok Pixel</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              <?php if ($status['tiktok_pixel']['configured']): ?>
                Pixel ID: <?= htmlspecialchars($status['tiktok_pixel']['id']) ?>
              <?php else: ?>
                Not configured - Set TIKTOK_PIXEL_ID in .env
              <?php endif; ?>
            </p>
          </div>
          <span class="px-2 py-1 rounded text-xs font-medium <?= $status['tiktok_pixel']['configured'] ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400' : 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-400' ?>">
            <?= $status['tiktok_pixel']['configured'] ? 'Active' : 'Inactive' ?>
          </span>
        </div>
        <div class="text-sm text-gray-600 dark:text-gray-400">
          <p class="mb-2">TikTok Pixel tracks conversions and enables retargeting on TikTok. Configure in your .env file:</p>
          <code class="block bg-gray-100 dark:bg-white/5 p-2 rounded text-xs">TIKTOK_PIXEL_ID=XXXXXXXXXX</code>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <h3 class="font-semibold mb-4">Quick Actions</h3>
        <div class="flex flex-wrap gap-3">
          <a href="/admin/env.php" class="px-4 py-2 rounded bg-brand text-white text-sm hover:bg-brand/90">Edit .env File</a>
          <a href="https://analytics.google.com/" target="_blank" class="px-4 py-2 rounded bg-gray-100 dark:bg-white/10 text-sm hover:bg-gray-200 dark:hover:bg-white/20">Open GA4 Dashboard →</a>
          <a href="https://business.facebook.com/events_manager" target="_blank" class="px-4 py-2 rounded bg-gray-100 dark:bg-white/10 text-sm hover:bg-gray-200 dark:hover:bg-white/20">Open Meta Events Manager →</a>
          <a href="https://ads.tiktok.com/i18n/events_manager" target="_blank" class="px-4 py-2 rounded bg-gray-100 dark:bg-white/10 text-sm hover:bg-gray-200 dark:hover:bg-white/20">Open TikTok Events Manager →</a>
        </div>
      </div>

    </section>
  </main>
</div>

<?php include __DIR__.'/_footer.php'; ?>
