<?php
/**
 * NGN 2.1.0 Feature Listing & Roadmap (Beta)
 * Bible Ref: Master Progress & Roadmap Ch. 7
 */

$root = dirname(__DIR__) . '/';
require_once $root . 'lib/bootstrap.php';

use NGN\Lib\Config;

// Load Roadmap Data
$roadmapPath = $root . 'storage/plan/progress-master.json';
$roadmapData = [];
if (file_exists($roadmapPath)) {
    $roadmapData = json_decode(file_get_contents($roadmapPath), true);
}

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
        'name' => 'Investor Portal',
        'category' => 'Capital',
        'status' => 'Verified',
        'description' => 'High-velocity terminal for institutional capital and Series A promissory note entry.',
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
  <title>NGN // BETA Manifest & Roadmap</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700;800&family=Space+Grotesk:wght@300;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <script>
    window.tailwind = { 
        theme: { 
            extend: { 
                colors: { brand: '#FF5F1F', surface: '#0A0A0A', moat: '#121212' },
                fontFamily: { mono: ['JetBrains Mono', 'monospace'], sans: ['Space Grotesk', 'sans-serif'] }
            } 
        } 
    };
  </script>
  <style>
    :root { --primary: #FF5F1F; --charcoal: #050505; }
    body { background-color: var(--charcoal); color: white; font-family: 'Space Grotesk', sans-serif; }
    .glass { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
    .feature-card:hover { border-color: var(--primary); transform: translateY(-4px); }
    .tactical-grid {
        background-image: linear-gradient(rgba(255, 95, 31, 0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 95, 31, 0.03) 1px, transparent 1px);
        background-size: 40px 40px;
    }
    .stat-value { font-family: 'JetBrains Mono', monospace; font-weight: 800; }
    .roadmap-line { width: 2px; background: linear-gradient(to bottom, #FF5F1F, transparent); }
  </style>
</head>
<body class="selection:bg-brand/30 tactical-grid min-h-screen">

    <div class="py-24 px-6 lg:px-12 max-w-7xl mx-auto">
        
        <!-- Header -->
        <header class="mb-24 text-center">
            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full glass border-brand/20 mb-8">
                <span class="w-2 h-2 rounded-full bg-brand animate-pulse"></span>
                <span class="text-[10px] font-black uppercase tracking-[0.3em] text-brand">System_Manifest_v<?= $roadmapData['master_version'] ?? '2.1.0' ?></span>
            </div>
            <h1 class="text-6xl lg:text-9xl font-black tracking-tighter mb-8 leading-[0.8]">THE FUTURE OF <br><span class="text-brand">MUSIC TECH</span></h1>
            <p class="text-xl text-zinc-500 max-w-2xl mx-auto font-medium leading-relaxed">A sovereign command center for independent rock and metal. Zero fluff. Full transparency.</p>
        </header>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-24">
            <div class="glass p-8 rounded-3xl text-center">
                <div class="text-zinc-500 font-black text-[10px] uppercase tracking-widest mb-2">Verified_Features</div>
                <div class="text-5xl stat-value text-white"><?= count($features) ?></div>
            </div>
            <div class="glass p-8 rounded-3xl text-center border-brand/20">
                <div class="text-zinc-500 font-black text-[10px] uppercase tracking-widest mb-2">Network_Reach</div>
                <div class="text-5xl stat-value text-brand"><?= number_format($roadmapData['quick_stats']['artists_reached'] ?? 15978) ?></div>
            </div>
            <div class="glass p-8 rounded-3xl text-center">
                <div class="text-zinc-500 font-black text-[10px] uppercase tracking-widest mb-2">Active_Nodes</div>
                <div class="text-5xl stat-value text-white"><?= $roadmapData['quick_stats']['nodes_active'] ?? 21 ?></div>
            </div>
        </div>

        <!-- ROADMAP SECTION -->
        <section class="mb-32">
            <div class="flex items-center gap-4 mb-12">
                <h2 class="text-3xl font-black uppercase tracking-tighter">Mission_Roadmap</h2>
                <div class="flex-1 h-px bg-white/5"></div>
                <div class="text-[10px] font-mono text-zinc-500">Updated: <?= date('Y-m-d', strtotime($roadmapData['last_updated'] ?? 'now')) ?></div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-12 relative">
                <!-- Column 1: DONE -->
                <div>
                    <div class="flex items-center gap-3 mb-8">
                        <div class="w-2 h-2 rounded-full bg-green-500"></div>
                        <h3 class="text-sm font-black uppercase tracking-widest text-zinc-400">Deployed_Verified</h3>
                    </div>
                    <div class="space-y-6">
                        <?php foreach ($roadmapData['history'] ?? [] as $ver): ?>
                            <div class="glass p-6 rounded-2xl border-l-2 border-green-500/50">
                                <div class="text-[10px] font-mono text-zinc-500 mb-2">v<?= $ver['version'] ?> // <?= $ver['release_date'] ?></div>
                                <ul class="space-y-3">
                                    <?php foreach ($ver['key_achievements'] as $ach): ?>
                                        <li class="text-xs font-bold flex gap-2">
                                            <i class="bi-check2-circle text-green-500"></i>
                                            <span class="text-zinc-300"><?= $ach ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Column 2: IN PROGRESS / FORTHCOMING -->
                <div>
                    <div class="flex items-center gap-3 mb-8">
                        <div class="w-2 h-2 rounded-full bg-brand animate-pulse"></span></div>
                        <h3 class="text-sm font-black uppercase tracking-widest text-brand">Forthcoming_v<?= $roadmapData['next_landmark'] ?? '2.2.0' ?></h3>
                    </div>
                    <div class="space-y-6">
                        <?php 
                        $landmark = $roadmapData['milestones']['2_2_0'] ?? [];
                        foreach ($landmark['categories'] ?? [] as $cat): 
                        ?>
                            <div class="glass p-6 rounded-2xl border-l-2 border-brand/50">
                                <div class="text-[10px] font-black uppercase tracking-widest text-brand mb-4"><?= $cat['name'] ?></div>
                                <div class="space-y-6">
                                    <?php foreach ($cat['tasks'] as $task): ?>
                                        <div>
                                            <div class="text-xs font-bold text-white mb-2"><?= $task['description'] ?></div>
                                            <?php if (!empty($task['sub_tasks'])): ?>
                                                <ul class="space-y-2 ml-2">
                                                    <?php foreach ($task['sub_tasks'] as $sub): ?>
                                                        <li class="text-[10px] font-medium text-zinc-500 flex gap-2">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-zinc-800 mt-1"></span>
                                                            <?= $sub ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Column 3: COMING SOON / NEXT QUARTER -->
                <div>
                    <div class="flex items-center gap-3 mb-8">
                        <div class="w-2 h-2 rounded-full bg-zinc-700"></div>
                        <h3 class="text-sm font-black uppercase tracking-widest text-zinc-500">Future_Landmarks</h3>
                    </div>
                    <div class="space-y-6">
                        <div class="glass p-6 rounded-2xl border-l-2 border-zinc-800">
                            <div class="text-[10px] font-black uppercase tracking-widest text-zinc-500 mb-4">v2.3.0 // The Growth Monopoly</div>
                            <ul class="space-y-4">
                                <?php foreach ($roadmapData['milestones']['2_3_0']['focus'] ?? [] as $focus): ?>
                                    <li class="text-xs font-bold text-zinc-400 flex gap-3">
                                        <i class="bi-lock text-zinc-700"></i>
                                        <?= $focus ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="glass p-6 rounded-2xl border-l-2 border-zinc-800">
                            <div class="text-[10px] font-black uppercase tracking-widest text-zinc-500 mb-4">v3.0.0 // Sovereign Empire</div>
                            <div class="space-y-4">
                                <?php foreach ($roadmapData['path_to_3_0_0']['pillars'] ?? [] as $pillar): ?>
                                    <div>
                                        <div class="text-xs font-bold text-zinc-300"><?= $pillar['name'] ?></div>
                                        <div class="text-[9px] font-mono text-zinc-600 uppercase tracking-widest"><?= $pillar['focus'] ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Feature Showroom -->
        <div class="flex items-center gap-4 mb-12">
            <h2 class="text-3xl font-black uppercase tracking-tighter">Feature_Showroom</h2>
            <div class="flex-1 h-px bg-white/5"></div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-32">
            <?php foreach ($features as $f): ?>
            <div class="glass p-8 rounded-3xl transition-all duration-500 feature-card group">
                <div class="flex items-start justify-between mb-8">
                    <div class="w-12 h-12 rounded-xl bg-zinc-900 flex items-center justify-center border border-white/5 group-hover:bg-brand transition-colors duration-500">
                        <i class="bi <?= $f['icon'] ?> text-xl group-hover:text-black transition-colors duration-500"></i>
                    </div>
                    <div class="text-right">
                        <div class="text-[9px] font-black uppercase tracking-widest text-green-500">
                            <?= $f['status'] ?>
                        </div>
                    </div>
                </div>
                <h3 class="text-lg font-black mb-3 tracking-tight text-white uppercase"><?= $f['name'] ?></h3>
                <p class="text-xs text-zinc-500 font-medium leading-relaxed"><?= $f['description'] ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Footer -->
        <footer class="pt-12 border-t border-white/5 text-center">
            <img src="/lib/images/site/2026/NGN-Logo-Full-Light.png" alt="NGN" class="h-8 mx-auto mb-8 opacity-20 object-contain">
            <p class="text-zinc-600 text-[10px] font-black uppercase tracking-[0.4em]">NextGenNoise // Pressurized // Sovereign</p>
        </footer>

    </div>

</body>
</html>
