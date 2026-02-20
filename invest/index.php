<?php
/**
 * NGN Investor Portal - Foundry Edition
 * Aesthetic: Electric Orange / Deep Charcoal / Tactical Grid
 * Bible Ref: Investor Bible v3.0
 */
require_once __DIR__ . '/../lib/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NGN // INVESTOR TERMINAL</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700;800&family=Space+Grotesk:wght@300;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: '#FF5F1F',
                        charcoal: '#050505',
                        surface: '#0A0A0A',
                        moat: '#121212'
                    },
                    fontFamily: {
                        mono: ['JetBrains Mono', 'monospace'],
                        sans: ['Space Grotesk', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #050505;
            color: #ffffff;
            font-family: 'Space Grotesk', sans-serif;
            background-image: 
                linear-gradient(rgba(255, 95, 31, 0.03) 1px, transparent 1px),
                linear-gradient(90(rgba(255, 95, 31, 0.03) 1px, transparent 1px);
            background-size: 40px 40px;
        }
        .glow-brand { text-shadow: 0 0 20px rgba(255, 95, 31, 0.5); }
        .border-brand-glow { border-color: rgba(255, 95, 31, 0.3); box-shadow: 0 0 15px rgba(255, 95, 31, 0.1); }
        .glass { background: rgba(10, 10, 10, 0.8); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.05); }
        .tactical-line { height: 1px; background: linear-gradient(90deg, transparent, #FF5F1F, transparent); }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #050505; }
        ::-webkit-scrollbar-thumb { background: #FF5F1F; border-radius: 10px; }

        .stat-value { font-family: 'JetBrains Mono', monospace; font-weight: 800; }
        .phase-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 8px; }
        .active-dot { background: #FF5F1F; box-shadow: 0 0 10px #FF5F1F; }
    </style>
</head>
<body class="min-h-screen selection:bg-brand selection:text-white">

<!-- HUD / NAVIGATION -->
<nav class="sticky top-0 z-50 glass border-b border-white/5 px-6 h-20 flex items-center justify-between">
    <div class="flex items-center gap-8">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-brand rounded-lg flex items-center justify-center text-black">
                <i class="bi-shield-check text-2xl"></i>
            </div>
            <span class="font-bold tracking-tighter text-xl font-mono">NGN // <span class="text-brand">SOVEREIGN</span></span>
        </div>
        <div class="hidden lg:flex gap-6 text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-500">
            <a href="#market" class="hover:text-white transition-colors">Market</a>
            <a href="#moats" class="hover:text-white transition-colors">21 Nodes</a>
            <a href="#traction" class="hover:text-white transition-colors">Traction</a>
            <a href="#revenue" class="hover:text-white transition-colors">Economics</a>
        </div>
    </div>
    <div class="flex items-center gap-6">
        <div class="hidden md:flex flex-col text-right">
            <span class="text-[10px] font-black uppercase text-brand tracking-widest">Target Exit</span>
            <span class="text-lg stat-value">$54,000,000</span>
        </div>
        <a href="invest.php" class="px-8 py-3 bg-brand text-black font-black uppercase tracking-widest text-xs rounded-full hover:scale-105 transition-all shadow-2xl shadow-brand/20">
            Secure Entry
        </a>
    </div>
</nav>

<!-- HERO SECTION -->
<header class="relative pt-24 pb-32 px-6 overflow-hidden">
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-6xl aspect-square bg-brand/5 rounded-full blur-[120px] -z-10"></div>
    <div class="container mx-auto text-center">
        <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full glass border-brand/20 mb-8">
            <span class="phase-dot active-dot"></span>
            <span class="text-[10px] font-black uppercase tracking-[0.3em] text-brand">Series A Now Open // 2026</span>
        </div>
        <h1 class="text-6xl lg:text-9xl font-black tracking-tighter leading-[0.85] mb-12">
            THE MUSIC <br>
            <span class="text-brand glow-brand">INFRASTRUCTURE</span> <br>
            MONOPOLY.
        </h1>
        <p class="text-xl text-zinc-400 max-w-3xl mx-auto font-medium leading-relaxed mb-16">
            We aren't just building a streaming app. We are building the <span class="text-white font-bold">cryptographic source of truth</span> for a $28.6B market. Zero fluff. Full sovereignty.
        </p>
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 max-w-5xl mx-auto">
            <div class="glass p-8 rounded-3xl text-left border-brand/10">
                <div class="text-[10px] font-black text-zinc-500 uppercase tracking-widest mb-2">Artists Reached</div>
                <div class="text-4xl stat-value">15,978</div>
            </div>
            <div class="glass p-8 rounded-3xl text-left border-brand/10">
                <div class="text-[10px] font-black text-zinc-500 uppercase tracking-widest mb-2">Data Moats</div>
                <div class="text-4xl stat-value">21 Nodes</div>
            </div>
            <div class="glass p-8 rounded-3xl text-left border-brand/10">
                <div class="text-[10px] font-black text-zinc-500 uppercase tracking-widest mb-2">Unit Economics</div>
                <div class="text-4xl stat-value text-brand">60X LTV</div>
            </div>
            <div class="glass p-8 rounded-3xl text-left border-brand/10">
                <div class="text-[10px] font-black text-zinc-500 uppercase tracking-widest mb-2">SMR Coverage</div>
                <div class="text-4xl stat-value">98.2%</div>
            </div>
        </div>
    </div>
</header>

<div class="tactical-line"></div>

<!-- THE MARKET PROBLEM -->
<section id="market" class="py-32 px-6">
    <div class="container mx-auto">
        <div class="flex flex-col lg:flex-row gap-20 items-center">
            <div class="flex-1">
                <h2 class="text-sm font-black text-brand uppercase tracking-[0.4em] mb-6">01 // The Opportunity</h2>
                <h3 class="text-5xl lg:text-7xl font-bold tracking-tight mb-8 leading-none">THE INCUMBENTS <br>ARE FAILING.</h3>
                <div class="space-y-8">
                    <div class="flex gap-6">
                        <div class="w-12 h-12 rounded-xl glass flex items-center justify-center text-brand shrink-0">
                            <i class="bi-exclamation-triangle-fill text-xl"></i>
                        </div>
                        <div>
                            <h4 class="text-xl font-bold mb-2">$0.003 Payouts</h4>
                            <p class="text-zinc-500">Traditional streaming is economically dead for the 40% of the market that generates the most content.</p>
                        </div>
                    </div>
                    <div class="flex gap-6">
                        <div class="w-12 h-12 rounded-xl glass flex items-center justify-center text-brand shrink-0">
                            <i class="bi-eye-slash-fill text-xl"></i>
                        </div>
                        <div>
                            <h4 class="text-xl font-bold mb-2">Algorithm Opacity</h4>
                            <p class="text-zinc-500">Major labels buy the charts. Independent breakouts are suppressed by a black box.</p>
                        </div>
                    </div>
                    <div class="flex gap-6">
                        <div class="w-12 h-12 rounded-xl glass flex items-center justify-center text-brand shrink-0">
                            <i class="bi-lock-fill text-xl"></i>
                        </div>
                        <div>
                            <h4 class="text-xl font-bold mb-2">Rights Gridlock</h4>
                            <p class="text-zinc-500">15+ holders per song. Manual splits. 6-month delays. We solve it with SHA-256 integrity.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex-1 w-full max-w-xl">
                <div class="glass p-10 rounded-[40px] border-brand/20 relative">
                    <div class="absolute -top-10 -right-10 w-32 h-32 bg-brand/20 rounded-full blur-3xl"></div>
                    <h4 class="text-sm font-black uppercase tracking-widest text-zinc-500 mb-8">Artist Payout Disparity</h4>
                    <canvas id="marketChart" class="h-80"></canvas>
                    <div class="mt-8 pt-8 border-t border-white/5">
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-bold text-zinc-500 uppercase">Independent SOM</span>
                            <span class="text-xl font-mono text-brand">$11.4B</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- THE 21 SOVEREIGN NODES (THE RIG) -->
<section id="moats" class="py-32 px-6 bg-surface">
    <div class="container mx-auto">
        <div class="text-center mb-20">
            <h2 class="text-sm font-black text-brand uppercase tracking-[0.4em] mb-6">02 // The Backbone</h2>
            <h3 class="text-5xl lg:text-7xl font-bold tracking-tight mb-8">THE 21 SOVEREIGN NODES.</h3>
            <p class="text-zinc-500 max-w-2xl mx-auto">A multi-layered ecosystem pressurized for speed and scale. No single point of failure.</p>
        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-7 gap-4">
            <!-- HUD style grid items -->
            <?php
            $nodes = [
                ['name' => 'Beacon', 'role' => 'ID'], ['name' => 'Vault', 'role' => 'Secrets'], ['name' => 'Ledger', 'role' => 'Finance'],
                ['name' => 'Sentinel', 'role' => 'Defense'], ['name' => 'Pulse', 'role' => 'Metrics'], ['name' => 'Mint', 'role' => 'Economy'],
                ['name' => 'Vent', 'role' => 'Email'], ['name' => 'A-OS', 'role' => 'Logic'], ['name' => 'Oracle', 'role' => 'Truth'],
                ['name' => 'Search', 'role' => 'Neural'], ['name' => 'Simulator', 'role' => 'ROI'], ['name' => 'Uplink', 'role' => 'Bridge'],
                ['name' => 'Signal', 'role' => 'Events'], ['name' => 'Messenger', 'role' => 'Chat'], ['name' => 'Forge', 'role' => 'Infra'],
                ['name' => 'Reception', 'role' => 'Air'], ['name' => 'Judge', 'role' => 'Law'], ['name' => 'Manual', 'role' => 'Rules'],
                ['name' => 'Studio', 'role' => 'Assets'], ['name' => 'Depot', 'role' => 'Storage'], ['name' => 'Clock', 'role' => 'Jobs']
            ];
            foreach ($nodes as $node):
            ?>
            <div class="glass p-4 rounded-xl hover:border-brand/50 transition-all group">
                <div class="text-[10px] font-black text-zinc-600 group-hover:text-brand uppercase mb-1"><?= $node['role'] ?></div>
                <div class="font-mono font-bold text-sm"><?= $node['name'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- REVENUE STREAMS -->
<section id="revenue" class="py-32 px-6">
    <div class="container mx-auto">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-20">
            <div>
                <h2 class="text-sm font-black text-brand uppercase tracking-[0.4em] mb-6">03 // The Economics</h2>
                <h3 class="text-5xl lg:text-7xl font-bold tracking-tight mb-12">DIVERSIFIED <br>HIGH-MARGIN.</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="glass p-8 rounded-3xl">
                        <div class="text-brand text-2xl mb-4"><i class="bi-lightning-charge-fill"></i></div>
                        <h4 class="font-bold mb-2">Spark Tips</h4>
                        <p class="text-sm text-zinc-500">5% Platform fee on direct fan-to-artist micro-tips. 95% margin.</p>
                    </div>
                    <div class="glass p-8 rounded-3xl">
                        <div class="text-blue-500 text-2xl mb-4"><i class="bi-reception-4"></i></div>
                        <h4 class="font-bold mb-2">Subscriptions</h4>
                        <p class="text-sm text-zinc-500">$9.99 - $49.99/mo SaaS recurring revenue. 85% gross margin.</p>
                    </div>
                    <div class="glass p-8 rounded-3xl">
                        <div class="text-emerald-500 text-2xl mb-4"><i class="bi-ticket-perforated-fill"></i></div>
                        <h4 class="font-bold mb-2">Ticketing</h4>
                        <p class="text-sm text-zinc-500">2.5% + $1.50 per live event ticket. Capturing the $162M+ volume.</p>
                    </div>
                    <div class="glass p-8 rounded-3xl">
                        <div class="text-purple-500 text-2xl mb-4"><i class="bi-cpu-fill"></i></div>
                        <h4 class="font-bold mb-2">B2B API</h4>
                        <p class="text-sm text-zinc-500">Data licensing for labels and aggregators. High-ticket enterprise value.</p>
                    </div>
                </div>
            </div>
            <div class="glass p-12 rounded-[40px] border-brand/20">
                <h4 class="text-xl font-bold mb-8 font-mono">FINANCIAL TRAJECTORY</h4>
                <div class="h-[400px]">
                    <canvas id="revChart"></canvas>
                </div>
                <div class="mt-12 space-y-4">
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-zinc-500 font-bold uppercase tracking-widest">Artist LTV</span>
                        <span class="stat-value text-brand">$4,500</span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-zinc-500 font-bold uppercase tracking-widest">Acquisition Cost (CAC)</span>
                        <span class="stat-value text-white">$75</span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-zinc-500 font-bold uppercase tracking-widest">Payback Period</span>
                        <span class="stat-value text-emerald-500">20 Days</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CALL TO ACTION -->
<section class="py-40 px-6 text-center relative overflow-hidden">
    <div class="absolute inset-0 bg-brand/5 -z-10"></div>
    <div class="container mx-auto">
        <h2 class="text-7xl lg:text-[12rem] font-black tracking-tighter leading-none mb-12">THE EXIT <br><span class="text-brand">IS THE GOAL.</span></h2>
        <p class="text-2xl text-zinc-400 max-w-2xl mx-auto mb-20 font-medium">
            Join the Series A round to reach 10,000 artists and capture the data monopoly.
        </p>
        <div class="flex flex-col md:flex-row gap-6 justify-center">
            <a href="invest.php?amount=2500" class="px-16 py-6 bg-brand text-black font-black uppercase tracking-[0.2em] rounded-2xl text-xl hover:scale-105 transition-all shadow-[0_0_50px_rgba(255,95,31,0.3)]">
                Secure Note Entry
            </a>
            <a href="pitch.php" class="px-16 py-6 border border-white/10 rounded-2xl text-xl font-bold hover:bg-white/5 transition-all">
                Access Data Room
            </a>
        </div>
        <p class="mt-12 text-[10px] font-black text-zinc-600 uppercase tracking-[0.5em]">Pressurized // Verified // Sovereign</p>
    </div>
</section>

<script>
    Chart.defaults.color = '#737373';
    Chart.defaults.font.family = "'Space Grotesk', sans-serif";

    // Market Disparity Chart
    new Chart(document.getElementById('marketChart'), {
        type: 'bar',
        data: {
            labels: ['Legacy ($/stream)', 'NGN Potential'],
            datasets: [{
                data: [0.003, 0.45], // Representing total value extraction
                backgroundColor: ['#222', '#FF5F1F'],
                borderRadius: 12
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { display: false },
                x: { grid: { display: false } }
            }
        }
    });

    // Revenue Trajectory Chart
    new Chart(document.getElementById('revChart'), {
        type: 'line',
        data: {
            labels: ['2024', '2025', '2026', '2027'],
            datasets: [{
                label: 'Revenue (Millions)',
                data: [0.05, 0.8, 4.2, 8.6],
                borderColor: '#FF5F1F',
                backgroundColor: 'rgba(255, 95, 31, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 0,
                borderWidth: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { callback: v => '$' + v + 'M' } },
                x: { grid: { display: false } }
            }
        }
    });
</script>

</body>
</html>
