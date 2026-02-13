<?php
/**
 * NGN 2.0.3 Feature Listing (Beta)
 * Show-off list for stakeholders and early adopters
 */

$root = dirname(__DIR__) . '/';
require_once $root . 'lib/bootstrap.php';

use NGN\Lib\Config;

$view = 'beta';
$isLoggedIn = false;
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
if (!empty($_SESSION['user_id'])) { $isLoggedIn = true; }

$features = [
    [
        'name' => 'Sovereign Navigation',
        'category' => 'UI/UX',
        'status' => 'Verified',
        'description' => 'Mobile-first, Spotify-killer sidebar architecture with real-time view state tracking.',
        'icon' => 'bi-layout-sidebar-inset'
    ],
    [
        'name' => 'NGN Moat (Charts)',
        'category' => 'Core Engine',
        'status' => 'Verified',
        'description' => 'Real-time ranking recomputation with a 100-item minimum signaling moat for data integrity.',
        'icon' => 'bi-graph-up-arrow'
    ],
    [
        'name' => 'Fortress Security',
        'category' => 'Infrastructure',
        'status' => 'Verified',
        'description' => 'Hardened session management, JWT-backed API routes, and multi-layered database moats.',
        'icon' => 'bi-shield-lock'
    ],
    [
        'name' => 'SMR Auto-Ingestion',
        'category' => 'Data',
        'status' => 'Verified',
        'description' => 'Automated radio spin tracking with 98% coverage target and real-time backfill synchronization.',
        'icon' => 'bi-broadcast'
    ],
    [
        'name' => 'Royalty Ledger',
        'category' => 'Finance',
        'status' => 'Verified',
        'description' => 'Bible Ch. 41 compliant payout engine with institutional invoicing and spark micro-transactions.',
        'icon' => 'bi-currency-exchange'
    ],
    [
        'name' => 'AI Intelligence Feed',
        'category' => 'Content',
        'status' => 'Verified',
        'description' => 'Automated drafting service that generates daily music journalism from engagement anomalies.',
        'icon' => 'bi-cpu'
    ],
    [
        'name' => 'The Video Vault',
        'category' => 'Media',
        'status' => 'Verified',
        'description' => 'High-velocity streaming delivery for exclusive premieres and live performance archives.',
        'icon' => 'bi-play-circle'
    ],
    [
        'name' => 'AI Mix Feedback',
        'category' => 'Artist Tools',
        'status' => 'In-Progress',
        'description' => 'Machine learning analysis of audio uploads to provide technical mixing and mastering suggestions.',
        'icon' => 'bi-mic'
    ],
    [
        'name' => 'Investment Portal',
        'category' => 'Capital',
        'status' => 'In-Progress',
        'description' => 'Institutional capital routes allowing fans to invest in artists with 8% APY target returns.',
        'icon' => 'bi-bank'
    ],
    [
        'name' => 'Global Search Autocomplete',
        'category' => 'Search',
        'status' => 'Verified',
        'description' => 'Real-time suggestions for artists, labels, and tracks across the NGN network.',
        'icon' => 'bi-search'
    ]
];

?>
<!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>NGN 2.0.3 | Feature Showroom</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    window.tailwind = { config: { darkMode: 'class', theme: { extend: { colors: { brand: '#FF5F1F' } } } } };
  </script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    :root { --primary: #FF5F1F; --charcoal: #050505; }
    body { background-color: var(--charcoal); color: white; font-family: 'Inter', system-ui, -apple-system, sans-serif; }
    .glass { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.05); }
    .feature-card:hover { border-color: var(--primary); transform: translateY(-4px); }
    .status-verified { color: #1DB954; }
    .status-pending { color: #FF5F1F; }
  </style>
</head>
<body class="selection:bg-brand/30">

    <div class="min-h-screen py-24 px-6 lg:px-12 max-w-7xl mx-auto">
        
        <!-- Header -->
        <header class="mb-24 text-center">
            <div class="inline-block px-4 py-1 bg-brand text-black font-black text-[10px] uppercase tracking-[0.3em] mb-8 rounded-full">System_Manifest_v2.0.3</div>
            <h1 class="text-6xl lg:text-8xl font-black tracking-tighter mb-8 leading-none">THE FUTURE OF <br><span class="text-brand italic">MUSIC TECH</span></h1>
            <p class="text-xl text-zinc-500 max-w-2xl mx-auto font-medium leading-relaxed">A decentralized command center for independent rock and metal. Built for sovereignty, powered by intelligence.</p>
        </header>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-24">
            <div class="glass p-8 rounded-3xl text-center">
                <div class="text-zinc-500 font-black text-[10px] uppercase tracking-widest mb-2">Verified_Features</div>
                <div class="text-5xl font-black text-white">8</div>
            </div>
            <div class="glass p-8 rounded-3xl text-center border-brand/20">
                <div class="text-zinc-500 font-black text-[10px] uppercase tracking-widest mb-2">Production_Ready</div>
                <div class="text-5xl font-black text-brand">92%</div>
            </div>
            <div class="glass p-8 rounded-3xl text-center">
                <div class="text-zinc-500 font-black text-[10px] uppercase tracking-widest mb-2">Active_Moats</div>
                <div class="text-5xl font-black text-white">4</div>
            </div>
        </div>

        <!-- Feature Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php foreach ($features as $f): ?>
            <div class="glass p-8 rounded-[2rem] transition-all duration-500 feature-card group">
                <div class="flex items-start justify-between mb-8">
                    <div class="w-16 h-16 rounded-2xl bg-zinc-900 flex items-center justify-center border border-white/5 group-hover:bg-brand transition-colors duration-500">
                        <i class="bi <?= $f['icon'] ?> text-3xl group-hover:text-black transition-colors duration-500"></i>
                    </div>
                    <div class="text-right">
                        <div class="text-[10px] font-black uppercase tracking-widest <?= $f['status'] === 'Verified' ? 'text-green-500' : 'text-brand' ?>">
                            <?= $f['status'] ?>
                        </div>
                        <div class="text-[10px] font-black text-zinc-600 uppercase tracking-widest mt-1">
                            <?= $f['category'] ?>
                        </div>
                    </div>
                </div>
                <h3 class="text-2xl font-black mb-4 tracking-tight text-white uppercase"><?= $f['name'] ?></h3>
                <p class="text-zinc-400 font-medium leading-relaxed mb-8"><?= $f['description'] ?></p>
                <div class="h-1 w-full bg-zinc-900 rounded-full overflow-hidden">
                    <div class="h-full bg-brand transition-all duration-1000" style="width: <?= $f['status'] === 'Verified' ? '100%' : '40%' ?>"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Footer -->
        <footer class="mt-32 pt-12 border-t border-white/5 text-center">
            <img src="/lib/images/site/2026/NGN-Logo-Full-Light.png" alt="NGN" class="h-8 mx-auto mb-8 opacity-20 object-contain">
            <p class="text-zinc-600 text-[10px] font-black uppercase tracking-[0.4em]">NextGenNoise // Sovereign Music Infrastructure</p>
        </footer>

    </div>

</body>
</html>
