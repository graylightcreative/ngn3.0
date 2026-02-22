<?php
/**
 * NGN Sovereign Legal & Compliance Terminal v3.1.0
 */
$root = dirname(__DIR__) . '/../';
require_once $root . 'lib/bootstrap.php';

$view = $_GET['view'] ?? 'overview';
?>
<!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>NGN // LEGAL - Compliance Terminal</title>
  <?php include $root . 'lib/partials/head-sovereign.php'; ?>
  <?php include $root . 'lib/partials/app-styles.php'; ?>
</head>

<body class="h-full selection:bg-brand/30 dark bg-charcoal text-white font-space">
  <div class="app-frame flex flex-col min-h-screen">
    <?php include $root . 'lib/partials/sovereign-menu.php'; ?>

    <main class="flex-1 content-container flex flex-col p-6 max-w-5xl mx-auto w-full">
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row items-center justify-between mb-16 mt-10 gap-8">
            <div class="text-center md:text-left">
                <div class="inline-block px-3 py-1 mb-4 border border-brand/30 rounded-full bg-brand/10">
                    <span class="text-brand text-[9px] font-black tracking-widest uppercase">Immutable Sovereignty</span>
                </div>
                <h1 class="text-5xl md:text-7xl font-black text-white mb-4 tracking-tighter uppercase italic drop-shadow-2xl">
                    Legal <span class="text-brand">Terminal</span>
                </h1>
                <p class="text-zinc-400 max-w-md text-sm leading-relaxed font-mono">
                    The authoritative hub for NGN 3.0 governance, industrial agreements, and fiduciary compliance.
                </p>
            </div>
            <div class="w-32 h-32 md:w-48 md:h-48 border border-white/5 bg-zinc-900/50 rounded-3xl flex items-center justify-center relative overflow-hidden group">
                <div class="absolute inset-0 bg-mesh opacity-30 group-hover:scale-110 transition-transform duration-700"></div>
                <i class="bi bi-shield-lock-fill text-6xl md:text-8xl text-brand/20 group-hover:text-brand/40 transition-colors"></i>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Core Governance -->
            <div class="glass-panel p-8 rounded-[2rem] border-white/5 hover:border-brand/30 transition-all flex flex-col h-full">
                <div class="w-12 h-12 bg-brand/10 rounded-xl flex items-center justify-center mb-6 border border-brand/20">
                    <i class="bi bi-bank text-brand text-xl"></i>
                </div>
                <h3 class="text-xl font-black text-white italic uppercase tracking-tighter mb-4">Core_Governance</h3>
                <ul class="space-y-4 font-mono text-[10px] text-zinc-400 flex-1">
                    <li class="p-3 bg-white/5 rounded-lg flex items-center gap-3 hover:bg-white/10 cursor-pointer transition-colors">
                        <i class="bi bi-file-earmark-text text-brand"></i> Terms of Service
                    </li>
                    <li class="p-3 bg-white/5 rounded-lg flex items-center gap-3 hover:bg-white/10 cursor-pointer transition-colors">
                        <i class="bi bi-shield-check text-brand"></i> Privacy & Data Policy
                    </li>
                    <li class="p-3 bg-white/5 rounded-lg flex items-center gap-3 hover:bg-white/10 cursor-pointer transition-colors border border-brand/20">
                        <i class="bi bi-lightning-charge-fill text-brand"></i> Foundry Merger Policy
                    </li>
                </ul>
            </div>

            <!-- Rights & IP -->
            <div class="glass-panel p-8 rounded-[2rem] border-white/5 hover:border-brand/30 transition-all flex flex-col h-full">
                <div class="w-12 h-12 bg-blue-500/10 rounded-xl flex items-center justify-center mb-6 border border-blue-500/20">
                    <i class="bi bi-collection-play text-blue-500 text-xl"></i>
                </div>
                <h3 class="text-xl font-black text-white italic uppercase tracking-tighter mb-4">Rights_&_IP</h3>
                <ul class="space-y-4 font-mono text-[10px] text-zinc-400 flex-1">
                    <li class="p-3 bg-white/5 rounded-lg flex items-center gap-3 hover:bg-white/10 cursor-pointer transition-colors">
                        <i class="bi bi-key text-blue-500"></i> Rights Management
                    </li>
                    <li class="p-3 bg-white/5 rounded-lg flex items-center gap-3 hover:bg-white/10 cursor-pointer transition-colors">
                        <i class="bi bi-person-badge text-blue-500"></i> Artist Agreements
                    </li>
                    <li class="p-3 bg-white/5 rounded-lg flex items-center gap-3 hover:bg-white/10 cursor-pointer transition-colors">
                        <i class="bi bi-journal-text text-blue-500"></i> Content Licensing
                    </li>
                </ul>
            </div>

            <!-- Financials -->
            <div class="glass-panel p-8 rounded-[2rem] border-white/5 hover:border-brand/30 transition-all flex flex-col h-full">
                <div class="w-12 h-12 bg-emerald-500/10 rounded-xl flex items-center justify-center mb-6 border border-emerald-500/20">
                    <i class="bi bi-cash-stack text-emerald-500 text-xl"></i>
                </div>
                <h3 class="text-xl font-black text-white italic uppercase tracking-tighter mb-4">Financial_Plumbing</h3>
                <ul class="space-y-4 font-mono text-[10px] text-zinc-400 flex-1">
                    <li class="p-3 bg-white/5 rounded-lg flex items-center gap-3 hover:bg-white/10 cursor-pointer transition-colors border border-emerald-500/20">
                        <i class="bi bi-scissors text-emerald-500"></i> Rule 5 (75/25 Split)
                    </li>
                    <li class="p-3 bg-white/5 rounded-lg flex items-center gap-3 hover:bg-white/10 cursor-pointer transition-colors">
                        <i class="bi bi-graph-up-arrow text-emerald-500"></i> Payout Terms (BFL 2.4)
                    </li>
                    <li class="p-3 bg-white/5 rounded-lg flex items-center gap-3 hover:bg-white/10 cursor-pointer transition-colors">
                        <i class="bi bi-safe2 text-emerald-500"></i> Equity Market Protocol
                    </li>
                </ul>
            </div>
        </div>

        <div class="mt-16 separator-slash opacity-30"></div>

        <!-- Ledger Badge -->
        <div class="mt-16 flex flex-col items-center">
            <div class="px-8 py-6 glass-panel rounded-full border-brand/20 bg-brand/5 flex items-center gap-6 shadow-2xl">
                <div class="w-12 h-12 bg-brand text-black rounded-full flex items-center justify-center shadow-[0_0_20px_rgba(255,85,0,0.4)]">
                    <i class="bi bi-shield-lock-fill text-2xl"></i>
                </div>
                <div class="text-left">
                    <h3 class="text-xs font-black uppercase tracking-widest text-white mb-1">On-Chain Verification Active</h3>
                    <p class="text-[9px] text-zinc-500 font-mono uppercase tracking-[0.2em]">All Industrial Agreements Anchored to Content Ledger</p>
                </div>
            </div>
        </div>

    </main>
    
    <?php include $root . 'lib/partials/player.php'; ?>
  </div>
  <script src="/lib/js/sovereign-nav.js"></script>
</body>
</html>
