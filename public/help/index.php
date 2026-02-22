<?php
/**
 * NGN Sovereign Knowledge Base
 */
$root = dirname(__DIR__) . '/../';
require_once $root . 'lib/bootstrap.php';
?>
<!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>NGN // HELP - Knowledge Base</title>
  <?php include $root . 'lib/partials/head-sovereign.php'; ?>
  <?php include $root . 'lib/partials/app-styles.php'; ?>
</head>

<body class="h-full selection:bg-brand/30 dark bg-charcoal text-white font-space">
  <div class="app-frame flex flex-col min-h-screen">
    <?php include $root . 'lib/partials/sovereign-menu.php'; ?>

    <main class="flex-1 content-container flex flex-col p-6 max-w-4xl mx-auto w-full">
        <div class="text-center mb-12 mt-10">
            <h1 class="text-4xl md:text-6xl font-black text-white mb-4 tracking-tighter uppercase italic">
                Help <span class="text-brand">Center</span>
            </h1>
            <p class="text-zinc-400 max-w-2xl mx-auto text-sm leading-relaxed mb-12 font-mono">
                Master the Sovereign Industrial Engine. Guides, tutorials, and technical support for creators.
            </p>
        </div>

        <!-- Search Bar -->
        <div class="relative mb-12">
            <i class="bi bi-search absolute left-6 top-1/2 -translate-y-1/2 text-zinc-500"></i>
            <input type="text" placeholder="Search Knowledge Base..." class="w-full bg-zinc-900/50 border border-white/10 p-6 pl-16 rounded-full text-white font-medium focus:border-brand outline-none transition-all text-lg">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <a href="#" class="glass-panel p-8 rounded-3xl border-white/10 hover:border-brand/30 transition-all group">
                <i class="bi bi-rocket-takeoff text-3xl text-brand mb-4 block"></i>
                <h3 class="text-xl font-black text-white italic uppercase tracking-tighter mb-2">Getting Started</h3>
                <p class="text-xs text-zinc-500 font-mono">Learn how to claim your profile and initialize your entity node.</p>
            </a>

            <a href="#" class="glass-panel p-8 rounded-3xl border-white/10 hover:border-brand/30 transition-all group">
                <i class="bi bi-bag-check text-3xl text-brand mb-4 block"></i>
                <h3 class="text-xl font-black text-white italic uppercase tracking-tighter mb-2">Shop & Merch</h3>
                <p class="text-xs text-zinc-500 font-mono">Master the Foundry Merchant UI and set up your DTF storefront.</p>
            </a>

            <a href="#" class="glass-panel p-8 rounded-3xl border-white/10 hover:border-brand/30 transition-all group">
                <i class="bi bi-broadcast text-3xl text-brand mb-4 block"></i>
                <h3 class="text-xl font-black text-white italic uppercase tracking-tighter mb-2">SMR & Radio</h3>
                <p class="text-xs text-zinc-500 font-mono">Understanding Radio Rotation, Charts, and Airplay Monitoring.</p>
            </a>

            <a href="#" class="glass-panel p-8 rounded-3xl border-white/10 hover:border-brand/30 transition-all group">
                <i class="bi bi-cpu text-3xl text-brand mb-4 block"></i>
                <h3 class="text-xl font-black text-white italic uppercase tracking-tighter mb-2">Technical API</h3>
                <p class="text-xs text-zinc-500 font-mono">Documentation for developers integrating with the NGN Fleet.</p>
            </a>
        </div>
    </main>
    
    <?php include $root . 'lib/partials/player.php'; ?>
  </div>
  <script src="/lib/js/sovereign-nav.js"></script>
</body>
</html>
