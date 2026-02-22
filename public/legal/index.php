<?php
/**
 * NGN Sovereign Legal & Compliance Terminal
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

    <main class="flex-1 content-container flex flex-col p-6 max-w-4xl mx-auto w-full">
        <div class="text-center mb-12 mt-10">
            <h1 class="text-4xl md:text-6xl font-black text-white mb-4 tracking-tighter uppercase italic">
                Legal <span class="text-brand">Terminal</span>
            </h1>
            <p class="text-zinc-400 max-w-2xl mx-auto text-sm leading-relaxed mb-12 font-mono">
                Authoritative governance documents, compliance protocols, and sovereign industrial agreements.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="glass-panel p-8 rounded-3xl border-white/10 hover:border-brand/30 transition-all group">
                <h3 class="text-xl font-black text-white italic uppercase tracking-tighter mb-4">Core Governance</h3>
                <ul class="space-y-4 font-mono text-xs text-zinc-400">
                    <li class="flex items-center gap-3"><i class="bi bi-file-earmark-text text-brand"></i> Terms of Service</li>
                    <li class="flex items-center gap-3"><i class="bi bi-shield-check text-brand"></i> Privacy & Data Policy</li>
                    <li class="flex items-center gap-3"><i class="bi bi-bank text-brand"></i> Foundry Merger Policy</li>
                </ul>
            </div>

            <div class="glass-panel p-8 rounded-3xl border-white/10 hover:border-brand/30 transition-all group">
                <h3 class="text-xl font-black text-white italic uppercase tracking-tighter mb-4">Rights & Payouts</h3>
                <ul class="space-y-4 font-mono text-xs text-zinc-400">
                    <li class="flex items-center gap-3"><i class="bi bi-collection-play text-brand"></i> Rights Management</li>
                    <li class="flex items-center gap-3"><i class="bi bi-cash-stack text-brand"></i> Payout Terms (BFL 2.4)</li>
                    <li class="flex items-center gap-3"><i class="bi bi-person-badge text-brand"></i> Artist Agreements</li>
                </ul>
            </div>
        </div>

        <div class="mt-12 p-8 glass-panel rounded-3xl border-brand/20 bg-brand/5 text-center">
            <i class="bi bi-shield-lock text-4xl text-brand mb-4 block"></i>
            <h3 class="text-lg font-black uppercase italic mb-2">On-Chain Verification</h3>
            <p class="text-xs text-zinc-500 font-mono">All industrial agreements are anchored to the Content Ledger for immutable verification.</p>
        </div>
    </main>
    
    <?php include $root . 'lib/partials/player.php'; ?>
  </div>
  <script src="/lib/js/sovereign-nav.js"></script>
</body>
</html>
