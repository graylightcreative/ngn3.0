<?php
/**
 * Admin Topbar Partial
 * Include this after sidebar in all 2.0 admin pages
 * 
 * Required variables before include:
 *   $pageTitle - title for the page header
 */
$pageTitle = $pageTitle ?? 'Admin';
?>
<main class="min-h-screen">
  <header class="sticky top-0 z-10 bg-white/80 dark:bg-[#0b1020]/80 backdrop-blur border-b border-gray-200 dark:border-white/10">
    <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between gap-3">
      <div class="flex items-center gap-2">
        <a class="lg:hidden inline-flex items-center justify-center h-9 w-9 rounded bg-brand text-white font-bold" href="/admin/index.php">N</a>
        <h1 class="text-lg font-semibold hidden sm:block"><?php echo htmlspecialchars($pageTitle); ?></h1>
      </div>
      <div class="flex items-center gap-2">
        <label class="inline-flex items-center cursor-pointer select-none" title="Toggle dark mode">
          <input id="darkToggle" type="checkbox" class="sr-only peer" onchange="__toggleTheme()" />
          <div class="w-9 h-5 bg-gray-200 dark:bg-gray-700 rounded-full relative transition-colors peer-checked:bg-brand">
            <div class="absolute top-[2px] left-[2px] h-4 w-4 bg-white rounded-full shadow transform transition-transform peer-checked:translate-x-4"></div>
          </div>
        </label>
        <button id="refreshBtn" class="inline-flex items-center px-3 h-8 rounded bg-brand text-white text-sm hover:bg-brand-dark" title="Refresh data">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
        </button>
      </div>
    </div>
  </header>
  <section class="max-w-7xl mx-auto px-4 py-6 space-y-6">

