<?php
/**
 * NGN Investor Portal - Foundry Edition
 * Aesthetic: Electric Orange / Deep Charcoal / Tactical Grid
 * Bible Ref: Investor Bible v3.0 // Chapter 32 - Community Investment Notes
 */
require_once __DIR__ . '/../lib/bootstrap.php';

// --- FIXED FINANCIAL PARAMETERS (Bible Ch. 32) ---
$baseApy = 0.08; // 8% Annual Interest Rate (Fixed)
$termYears = 5;
$defaultInvestment = 2500;
$minimumInvestment = 50;
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NGN // INVESTOR TERMINAL // Series A</title>
    <meta name="description" content="Institutional capital routes for the independent music monopoly. High-yield Sovereign Notes providing 8% APY target returns. Built for sovereignty, pressurized for exit.">
    
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-LHGQG7HXKH"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-LHGQG7HXKH');
    </script>

    <!-- SOVEREIGN SEO PROTOCOL -->
    <meta property="og:site_name" content="NextGenNoise">
    <meta property="og:title" content="NGN // INVESTOR TERMINAL // Series A">
    <meta property="og:description" content="Secure your entry into the first platform providing cryptographic source of truth for the $28.6B music industry.">
    <meta property="og:image" content="https://nextgennoise.com/lib/images/site/og-image-investors.jpg">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="NGN // INVESTOR TERMINAL // Series A">
    <meta name="twitter:description" content="Institutional capital routes for the independent music monopoly. 8% APY Sovereign Notes active.">

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
                linear-gradient(90deg, rgba(255, 95, 31, 0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            min-height: 100vh;
            margin: 0;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
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

        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .chart-wrap { position: relative; width: 100%; height: 100%; }
    </style>
</head>
<body class="selection:bg-brand selection:text-white">

<!-- HUD / NAVIGATION -->
<nav class="sticky top-0 z-50 glass border-b border-white/5 px-6 h-20 flex items-center justify-between">
    <div class="flex items-center gap-8">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-brand rounded-lg flex items-center justify-center text-black">
                <i class="bi-shield-check text-2xl"></i>
            </div>
            <span class="font-bold tracking-tighter text-xl font-mono">NGN // <span class="text-brand">INVESTOR</span></span>
        </div>
        <div class="hidden lg:flex gap-6 text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-500">
            <a href="#notes" class="hover:text-white transition-colors">Sovereign Notes</a>
            <a href="#market" class="hover:text-white transition-colors">Market</a>
            <a href="#moats" class="hover:text-white transition-colors">21 Nodes</a>
            <a href="#revenue" class="hover:text-white transition-colors">Economics</a>
        </div>
    </div>
    <div class="flex items-center gap-6">
        <div class="hidden md:flex flex-col text-right">
            <span class="text-[10px] font-black uppercase text-brand tracking-widest">Fixed APY</span>
            <span class="text-lg stat-value">8.00%</span>
        </div>
        <a href="#notes" class="px-8 py-3 bg-brand text-black font-black uppercase tracking-widest text-xs rounded-full hover:scale-105 transition-all shadow-2xl shadow-brand/20">
            Get Started
        </a>
    </div>
</nav>

<div class="flex-grow">
    <!-- HERO SECTION -->
    <header class="relative pt-24 pb-20 px-6 overflow-hidden">
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-6xl aspect-square bg-brand/5 rounded-full blur-[120px] -z-10"></div>
        <div class="container mx-auto text-center">
            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full glass border-brand/20 mb-8">
                <span class="phase-dot active-dot"></span>
                <span class="text-[10px] font-black uppercase tracking-[0.3em] text-brand">Institutional Capital Routes Active</span>
            </div>
            <h1 class="text-6xl lg:text-9xl font-black tracking-tighter leading-[0.85] mb-12 uppercase">
                Invest in <br>
                <span class="text-brand glow-brand">The Truth.</span>
            </h1>
            <p class="text-xl text-zinc-400 max-w-3xl mx-auto font-medium leading-relaxed mb-16 italic font-mono">
                "Institutional capital routes allowing fans to invest in artists with 8% APY target returns."
            </p>
        </div>
    </header>

    <!-- THE SOVEREIGN NOTE (8% APY PRODUCT) -->
    <section id="notes" class="py-20 px-6 relative">
        <div class="container mx-auto">
            <div class="max-w-6xl mx-auto glass rounded-[3rem] border-brand/20 overflow-hidden shadow-2xl flex flex-col lg:flex-row">
                <!-- Left Side: Narrative -->
                <div class="flex-1 p-12 lg:p-20 bg-surface">
                    <h2 class="text-sm font-black text-brand uppercase tracking-[0.4em] mb-6">Product // Sovereign Note</h2>
                    <h3 class="text-4xl lg:text-6xl font-bold tracking-tight mb-8 leading-none">HIGH-YIELD <br>MUSIC DEBT.</h3>
                    <p class="text-zinc-400 mb-12 leading-relaxed">
                        The NGN Sovereign Note is a simple promissory note (debt instrument) that allows you to provide liquidity directly to the creator economy while securing a guaranteed return.
                    </p>
                    <div class="space-y-6">
                        <div class="flex items-center gap-4">
                            <i class="bi-check-circle-fill text-brand"></i>
                            <span class="text-sm font-bold uppercase tracking-widest">Fixed 8.00% APY Paid Quarterly</span>
                        </div>
                        <div class="flex items-center gap-4">
                            <i class="bi-check-circle-fill text-brand"></i>
                            <span class="text-sm font-bold uppercase tracking-widest">5-Year Maturity Term</span>
                        </div>
                        <div class="flex items-center gap-4">
                            <i class="bi-check-circle-fill text-brand"></i>
                            <span class="text-sm font-bold uppercase tracking-widest">Priority AI Tool Access (Elite-Host)</span>
                        </div>
                    </div>
                </div>

                <!-- Right Side: Calculator -->
                <div class="w-full lg:w-[450px] p-12 bg-moat border-l border-white/5">
                    <h4 class="text-[10px] font-black text-zinc-500 uppercase tracking-[0.3em] mb-10 text-center">ROI Terminal v1.0</h4>
                    
                    <div class="space-y-8">
                        <div>
                            <label class="block text-[10px] font-black text-zinc-600 uppercase tracking-widest mb-4">Investment Principal (USD)</label>
                            <div class="relative">
                                <span class="absolute left-6 top-1/2 -translate-y-1/2 text-zinc-500 stat-value text-xl">$</span>
                                <input type="number" id="principal" value="<?= $defaultInvestment ?>" min="<?= $minimumInvestment ?>" step="100" 
                                       class="w-full bg-black border border-white/10 rounded-2xl py-6 pl-12 pr-6 text-2xl stat-value text-brand focus:border-brand focus:ring-1 focus:ring-brand outline-none transition-all">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-black/40 p-6 rounded-2xl border border-white/5">
                                <div class="text-[9px] font-black text-zinc-600 uppercase tracking-widest mb-2">Total Return</div>
                                <div id="total-return" class="text-xl stat-value text-white">$0.00</div>
                            </div>
                            <div class="bg-black/40 p-6 rounded-2xl border border-white/5">
                                <div class="text-[9px] font-black text-zinc-600 uppercase tracking-widest mb-2">Quarterly</div>
                                <div id="quarterly-payout" class="text-xl stat-value text-emerald-500">$0.00</div>
                            </div>
                        </div>

                        <div class="pt-4">
                            <a href="https://nextgennoise.com/invest/invest.php" id="cta-link" class="block w-full py-6 bg-brand text-black text-center font-black uppercase tracking-[0.2em] rounded-2xl text-sm hover:scale-[1.02] active:scale-95 transition-all shadow-2xl shadow-brand/20">
                                Secure Promissory Note
                            </a>
                            <p class="text-[9px] text-zinc-600 text-center mt-6 uppercase tracking-widest leading-relaxed">
                                Secured by NGN Cash Reserves & <br>Institutional Royalty Pools.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="tactical-line"></div>

    <!-- THE MARKET PROBLEM -->
    <section id="market" class="py-32 px-6">
        <div class="container mx-auto">
            <div class="flex flex-col lg:flex-row gap-20 items-center">
                <div class="flex-1">
                    <h2 class="text-sm font-black text-brand uppercase tracking-[0.4em] mb-6">01 // The Opportunity</h2>
                    <h3 class="text-5xl lg:text-7xl font-bold tracking-tight mb-8 leading-none uppercase text-white">The Incumbents <br>are failing.</h3>
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
                    </div>
                </div>
                <div class="flex-1 w-full max-w-xl">
                    <div class="glass p-10 rounded-[40px] border-brand/20 relative overflow-hidden">
                        <div class="absolute -top-10 -right-10 w-32 h-32 bg-brand/20 rounded-full blur-3xl"></div>
                        <h4 class="text-sm font-black uppercase tracking-widest text-zinc-500 mb-8 font-mono italic">Market Inefficiency // Value Capture</h4>
                        <div class="relative h-80">
                            <canvas id="marketChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- THE 21 SOVEREIGN NODES (THE GRAYLIGHT RIG) -->
    <section id="moats" class="py-32 px-6 bg-surface">
        <div class="container mx-auto text-center">
            <div class="mb-20">
                <h2 class="text-sm font-black text-brand uppercase tracking-[0.4em] mb-6">02 // The Infrastructure</h2>
                <h3 class="text-5xl lg:text-7xl font-bold tracking-tight mb-8 uppercase text-white">The Graylight Foundry.</h3>
                <p class="text-zinc-500 max-w-2xl mx-auto">NGN is a premier tenant of the <span class="text-white font-bold">Graylight Creative</span> infrastructure ecosystem. 21 specialized nodes pressurized for institutional scale.</p>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-7 gap-4">
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
                <div class="glass p-4 rounded-xl hover:border-brand/50 transition-all group text-left">
                    <div class="text-[10px] font-black text-zinc-600 group-hover:text-brand uppercase mb-1 font-mono">Graylight_<?= $node['role'] ?></div>
                    <div class="font-mono font-bold text-sm text-white"><?= $node['name'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-16 text-center">
                <p class="text-[10px] font-black text-zinc-700 uppercase tracking-[0.4em]">Proprietary IP // Graylight Creative ecosystem</p>
            </div>
        </div>
    </section>

    <!-- REVENUE STREAMS -->
    <section id="revenue" class="py-32 px-6">
        <div class="container mx-auto">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-20">
                <div>
                    <h2 class="text-sm font-black text-brand uppercase tracking-[0.4em] mb-6">03 // The Economics</h2>
                    <h3 class="text-5xl lg:text-7xl font-bold tracking-tight mb-12 uppercase leading-none text-white">Diversified <br>High-Margin.</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="glass p-8 rounded-3xl">
                            <div class="text-brand text-2xl mb-4"><i class="bi-lightning-charge-fill"></i></div>
                            <h4 class="font-bold mb-2 text-white">Spark Tips</h4>
                            <p class="text-sm text-zinc-500">5% Platform fee on direct fan-to-artist micro-tips. 95% margin.</p>
                        </div>
                        <div class="glass p-8 rounded-3xl">
                            <div class="text-blue-500 text-2xl mb-4"><i class="bi-reception-4"></i></div>
                            <h4 class="font-bold mb-2 text-white">Subscriptions</h4>
                            <p class="text-sm text-zinc-500">$9.99 - $49.99/mo SaaS recurring revenue. 85% gross margin.</p>
                        </div>
                        <div class="glass p-8 rounded-3xl">
                            <div class="text-emerald-500 text-2xl mb-4"><i class="bi-ticket-perforated-fill"></i></div>
                            <h4 class="font-bold mb-2 text-white">Ticketing</h4>
                            <p class="text-sm text-zinc-500">2.5% + $1.50 per live event ticket. Capturing the $162M+ volume.</p>
                        </div>
                        <div class="glass p-8 rounded-3xl">
                            <div class="text-purple-500 text-2xl mb-4"><i class="bi-cpu-fill"></i></div>
                            <h4 class="font-bold mb-2 text-white">B2B API</h4>
                            <p class="text-sm text-zinc-500">Data licensing for labels and aggregators. High-ticket enterprise value.</p>
                        </div>
                    </div>
                </div>
                <div class="glass p-12 rounded-[40px] border-brand/20 overflow-hidden relative">
                    <h4 class="text-xl font-bold mb-8 font-mono uppercase tracking-tighter text-brand">Financial Trajectory</h4>
                    <div class="relative h-[400px]">
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
</div>

<!-- FOOTER -->
<footer class="py-20 px-6 text-center bg-surface border-t border-white/5 w-full">
    <div class="container mx-auto">
        <img src="/lib/images/site/2026/NGN-Logo-Full-Light.png" class="h-8 mx-auto mb-8 opacity-20 object-contain" alt="NGN">
        <p class="text-zinc-600 text-[10px] font-black uppercase tracking-[0.5em]">NextGenNoise // Pressurized // Sovereign</p>
    </div>
</footer>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const principalInput = document.getElementById('principal');
        const totalReturnEl = document.getElementById('total-return');
        const quarterlyPayoutEl = document.getElementById('quarterly-payout');
        const ctaLink = document.getElementById('cta-link');

        const APY = <?= $baseApy ?>;
        const TERM = <?= $termYears ?>;

        const formatter = new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            minimumFractionDigits: 2
        });

        function updateCalc() {
            const principal = parseFloat(principalInput.value) || 0;
            const interest = principal * APY * TERM;
            const totalReturn = principal + interest;
            const quarterlyPayout = (interest / TERM) / 4;

            totalReturnEl.innerText = formatter.format(totalReturn);
            quarterlyPayoutEl.innerText = formatter.format(quarterlyPayout);
            
            ctaLink.href = `invest.php?amount=${principal}`;
        }

        principalInput.addEventListener('input', updateCalc);
        updateCalc();

        // Charts
        Chart.defaults.color = '#737373';
        Chart.defaults.font.family = "'JetBrains Mono', monospace";

        // Market Disparity Chart
        new Chart(document.getElementById('marketChart'), {
            type: 'bar',
            data: {
                labels: ['Legacy ($/stream)', 'NGN Potential'],
                datasets: [{
                    data: [0.003, 0.45],
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
                    x: { grid: { display: false }, ticks: { color: '#ffffff' } }
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
                    y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { callback: v => '$' + v + 'M', color: '#737373' } },
                    x: { grid: { display: false }, ticks: { color: '#737373' } }
                }
            }
        });
    });
</script>

</body>
</html>
