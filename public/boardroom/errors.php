<?php
/**
 * Sovereign Error Terminal - NGN 3.0
 */
$root = __DIR__ . '/../../';
require_once $root . 'lib/bootstrap.php';

// Auth check (Boardroom access restricted to Admin/Staff)
// dashboard_require_auth(); 
?>
<!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>NGN // BOARDROOM - Error Terminal</title>
  <?php include $root . 'lib/partials/head-sovereign.php'; ?>
  <?php include $root . 'lib/partials/app-styles.php'; ?>
</head>

<body class="h-full selection:bg-brand/30 dark bg-charcoal text-white font-space">
  <div class="app-frame flex flex-col min-h-screen">
    <?php include $root . 'lib/partials/sovereign-menu.php'; ?>

    <main class="flex-1 content-container flex flex-col p-6 max-w-6xl mx-auto w-full">
        <div class="mt-10 mb-12">
            <div class="inline-block px-3 py-1 mb-4 border border-brand/30 rounded-full bg-brand/10">
                <span class="text-brand text-[9px] font-black tracking-widest uppercase">Institutional Access Only</span>
            </div>
            <h1 class="text-4xl md:text-6xl font-black text-white mb-2 tracking-tighter uppercase italic">
                Sovereign <span class="text-brand">Boardroom</span>
            </h1>
            <p class="text-zinc-500 font-mono text-xs uppercase tracking-widest">Master Monitoring Node // Cluster 01</p>
        </div>

        <!-- Integrated Error Terminal -->
        <?php include $root . 'lib/partials/error-terminal.php'; ?>

    </main>
    
    <?php include $root . 'lib/partials/player.php'; ?>
  </div>
  <script src="/lib/js/sovereign-nav.js"></script>
</body>
</html>
