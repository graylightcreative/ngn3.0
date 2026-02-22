<?php
/**
 * NGN Sovereign Knowledge Base v3.1.0
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

    <main class="flex-1 content-container flex flex-col p-6 max-w-5xl mx-auto w-full">
        <div class="text-center mb-16 mt-10">
            <div class="inline-block px-3 py-1 mb-4 border border-emerald-500/30 rounded-full bg-emerald-500/10">
                <span class="text-emerald-400 text-[9px] font-black tracking-widest uppercase">System Operational</span>
            </div>
            <h1 class="text-5xl md:text-7xl font-black text-white mb-4 tracking-tighter uppercase italic drop-shadow-2xl">
                Knowledge <span class="text-brand">Base</span>
            </h1>
            <p class="text-zinc-400 max-w-xl mx-auto text-sm leading-relaxed font-mono">
                Master the Sovereign Industrial Engine. Real-time guides, protocols, and technical support for the independent network.
            </p>
        </div>

        <!-- Search Bar -->
        <div class="relative mb-16 group">
            <div class="absolute inset-0 bg-brand/20 blur-2xl opacity-0 group-focus-within:opacity-100 transition-opacity"></div>
            <i class="bi bi-search absolute left-8 top-1/2 -translate-y-1/2 text-zinc-500 group-focus-within:text-brand transition-colors text-xl"></i>
            <input type="text" placeholder="Search Industrial Protocols..." class="relative z-10 w-full bg-zinc-900 border border-white/10 p-8 pl-20 rounded-[2.5rem] text-white font-medium focus:border-brand outline-none transition-all text-xl shadow-2xl backdrop-blur-xl">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <a href="#" class="glass-panel p-8 rounded-[2rem] border-white/5 hover:border-brand/30 transition-all group flex flex-col items-center text-center">
                <div class="w-16 h-16 bg-brand/10 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform border border-brand/20">
                    <i class="bi bi-rocket-takeoff text-2xl text-brand"></i>
                </div>
                <h3 class="text-lg font-black text-white italic uppercase tracking-tighter mb-2">Getting_Started</h3>
                <p class="text-[10px] text-zinc-500 font-mono leading-relaxed uppercase tracking-wider">Initialize your entity node and claim your NGN profile.</p>
            </a>

            <a href="#" class="glass-panel p-8 rounded-[2rem] border-white/5 hover:border-brand/30 transition-all group flex flex-col items-center text-center">
                <div class="w-16 h-16 bg-blue-500/10 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform border border-blue-500/20">
                    <i class="bi bi-bag-check text-2xl text-blue-500"></i>
                </div>
                <h3 class="text-lg font-black text-white italic uppercase tracking-tighter mb-2">Shop_Setup</h3>
                <p class="text-[10px] text-zinc-500 font-mono leading-relaxed uppercase tracking-wider">Configure the Foundry Merchant UI and list your first 3001 tee.</p>
            </a>

            <a href="#" class="glass-panel p-8 rounded-[2rem] border-white/5 hover:border-brand/30 transition-all group flex flex-col items-center text-center">
                <div class="w-16 h-16 bg-purple-500/10 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform border border-purple-500/20">
                    <i class="bi bi-broadcast text-2xl text-purple-500"></i>
                </div>
                <h3 class="text-lg font-black text-white italic uppercase tracking-tighter mb-2">Radio_Charts</h3>
                <p class="text-[10px] text-zinc-500 font-mono leading-relaxed uppercase tracking-wider">How SMR data drives the NGN regional and format rankings.</p>
            </a>

            <a href="#" class="glass-panel p-8 rounded-[2rem] border-white/5 hover:border-brand/30 transition-all group flex flex-col items-center text-center">
                <div class="w-16 h-16 bg-emerald-500/10 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform border border-emerald-500/20">
                    <i class="bi bi-cpu text-2xl text-emerald-500"></i>
                </div>
                <h3 class="text-lg font-black text-white italic uppercase tracking-tighter mb-2">Empire_API</h3>
                <p class="text-[10px] text-zinc-500 font-mono leading-relaxed uppercase tracking-wider">Technical documentation for developers and node operators.</p>
            </a>
        </div>

        <!-- Featured Guide -->
        <div class="mt-16 glass-panel rounded-[3rem] p-10 border-brand/20 relative overflow-hidden group cursor-pointer hover:border-brand/50 transition-all">
            <div class="absolute top-0 right-0 p-12 opacity-10 group-hover:opacity-20 transition-opacity">
                <i class="bi bi-lightning-charge-fill text-[12rem] text-brand"></i>
            </div>
            <div class="relative z-10">
                <span class="text-brand font-black text-[10px] uppercase tracking-[0.3em] mb-4 block">New Feature Guide</span>
                <h2 class="text-3xl md:text-5xl font-black text-white italic uppercase tracking-tighter mb-6">Foundry Merchant Activation</h2>
                <p class="text-zinc-400 max-w-xl text-sm leading-relaxed font-mono mb-8">Learn the end-to-handshake process of listing Direct-to-Film (DTF) apparel on your profile. From Spark burn to Scan-to-Ship fulfillment.</p>
                <div class="flex items-center gap-4">
                    <div class="px-6 py-2 bg-white text-black font-black text-[10px] uppercase tracking-widest rounded-full group-hover:bg-brand transition-colors">Launch Guide</div>
                    <span class="text-zinc-600 font-mono text-[10px]">Estimated time: 4 mins</span>
                </div>
            </div>
        </div>

    </main>
    
    <?php include $root . 'lib/partials/player.php'; ?>
  </div>
  <script src="/lib/js/sovereign-nav.js"></script>
</body>
</html>
