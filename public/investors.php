<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>NGN 2.0: The Transparent Royalty OS - Investor Portal</title>
    <!-- Load Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Load Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #000000;
            color: #e5e5e5;
            overflow-x: hidden;
            -webkit-tap-highlight-color: transparent;
        }
        h1, h2, h3, h4, .brand-font {
            font-family: 'Orbitron', sans-serif;
        }
        /* Neon Utilities */
        .neon-text-green {
            color: #39ff14;
            text-shadow: 0 0 10px rgba(57, 255, 20, 0.6);
        }
        .neon-border-green {
            border-color: #39ff14;
            box-shadow: 0 0 15px rgba(57, 255, 20, 0.2);
        }
        .neon-text-blue {
            color: #00ffff;
            text-shadow: 0 0 10px rgba(0, 255, 255, 0.6);
        }
        .neon-text-purple {
            color: #d946ef;
            text-shadow: 0 0 10px rgba(217, 70, 239, 0.6);
        }

        /* Backgrounds */
        .cyber-bg {
            background-color: #000000;
            background-image:
                    radial-gradient(circle at 50% 0%, #111111 0%, #000000 70%),
                    radial-gradient(circle at 85% 90%, rgba(57, 255, 20, 0.05) 0%, transparent 20%);
            background-attachment: fixed;
        }
        .card-bg {
            background-color: #0a0a0a;
            border: 1px solid #262626;
            transition: all 0.3s ease;
        }
        .card-bg:hover {
            transform: translateY(-4px);
            border-color: #404040;
            box-shadow: 0 10px 30px -10px rgba(0, 255, 0, 0.05);
        }

        /* Tooltip Styling */
        .jargon-tooltip {
            cursor: help;
            border-bottom: 1px dotted #39ff14;
            position: relative;
            display: inline-block;
        }
        .tooltip-box {
            position: absolute;
            background-color: rgba(10, 10, 10, 0.95);
            border: 1px solid #39ff14;
            color: #fff;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 0.75rem;
            width: 280px;
            text-align: left;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
            z-index: 1000;
            bottom: 120%;
            left: 50%;
            transform: translateX(-50%) translateY(5px);
            pointer-events: none;
            box-shadow: 0 0 20px rgba(57, 255, 20, 0.1);
            backdrop-filter: blur(10px);
            font-family: 'Inter', sans-serif;
            line-height: 1.4;
        }
        .tooltip-box::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -6px;
            border-width: 6px;
            border-style: solid;
            border-color: #39ff14 transparent transparent transparent;
        }
        .jargon-tooltip:hover .tooltip-box {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(0);
        }

        /* Chart Containers */
        .chart-container {
            position: relative;
            width: 100%;
            height: 300px;
        }

        /* Mobile Menu Transitions */
        #mobile-menu {
            transition: max-height 0.3s ease-in-out, opacity 0.3s ease-in-out;
            max-height: 0;
            opacity: 0;
            overflow: hidden;
        }
        #mobile-menu.open {
            max-height: 400px; /* Arbitrary large height */
            opacity: 1;
        }
    </style>
</head>

<body class="cyber-bg min-h-screen pb-24">

<!-- NAVIGATION -->
<nav class="sticky top-0 z-50 bg-black/95 backdrop-blur-md border-b border-neutral-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-20">
            <!-- Logo -->
            <div class="flex-shrink-0">
                <a href="#" class="text-2xl font-black text-white brand-font tracking-tighter">
                    NGN <span class="text-emerald-500">INVESTOR</span>
                </a>
            </div>

            <!-- Desktop Menu -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="https://nextgennoise.com/business-plan.php" class="text-xs font-bold uppercase tracking-widest text-neutral-400 hover:text-white transition-colors">Business Plan</a>
                <a href="https://nextgennoise.com/invest/pitch.php" class="text-xs font-bold uppercase tracking-widest text-neutral-400 hover:text-white transition-colors">Pitch Deck</a>
                <a href="https://nextgennoise.com/invest/invest.php?amount=2500" class="bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold uppercase tracking-widest px-6 py-3 rounded transition-all shadow-[0_0_15px_rgba(16,185,129,0.3)]">
                    Invest Now
                </a>
            </div>

            <!-- Mobile Hamburger Button -->
            <div class="md:hidden">
                <button id="mobile-menu-btn" class="text-white hover:text-emerald-400 focus:outline-none p-2">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Menu Dropdown -->
    <div id="mobile-menu" class="md:hidden bg-neutral-900 border-b border-neutral-800">
        <div class="px-4 pt-2 pb-6 space-y-2">
            <a href="https://nextgennoise.com/business-plan.php" class="block px-3 py-4 rounded-md text-sm font-bold uppercase tracking-widest text-white hover:bg-neutral-800 border-b border-neutral-800">Business Plan</a>
            <a href="https://nextgennoise.com/invest/pitch.php" class="block px-3 py-4 rounded-md text-sm font-bold uppercase tracking-widest text-white hover:bg-neutral-800 border-b border-neutral-800">Pitch Deck</a>
            <a href="https://nextgennoise.com/invest/invest.php?amount=2500" class="block px-3 py-4 rounded-md text-sm font-bold uppercase tracking-widest text-emerald-400 hover:bg-neutral-800">Invest Now</a>
        </div>
    </div>
</nav>

<div class="max-w-7xl mx-auto p-4 sm:p-8 mt-4">

    <!-- HEADER: THE MANDATE -->
    <header class="text-center py-16 mb-16 relative">
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-64 h-64 bg-emerald-500/10 rounded-full blur-[100px] -z-10"></div>

        <div class="inline-block px-4 py-1 mb-6 border border-emerald-500/30 rounded-full bg-emerald-900/10">
            <span class="text-emerald-400 text-xs font-bold tracking-widest uppercase">Series A Financial Mandate</span>
        </div>

        <h1 class="text-5xl md:text-7xl font-black mb-6 text-white tracking-tighter leading-tight">
            THE TRANSPARENT <br>
            <span class="neon-text-green">ROYALTY OS</span>
        </h1>

        <p class="mt-6 text-xl text-neutral-400 max-w-3xl mx-auto font-light leading-relaxed">
            NextGen Noise replaces the corrupt, opaque music royalty infrastructure with a direct-to-creator platform. <strong class="text-white">We validate the data. We pay the creators. We own the niche.</strong>
        </p>
    </header>

    <!-- IMMEDIATE MANDATE: THE GATES -->
    <section class="mb-20">
        <div class="flex items-center justify-between mb-8 border-b border-neutral-800 pb-4">
            <h2 class="text-2xl font-bold text-white">⚠️ Immediate Mandate: Series A Gates</h2>
        </div>

        <div class="grid md:grid-cols-2 gap-8">
            <!-- Mandate 1: Revenue -->
            <div class="card-bg p-8 rounded-2xl relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                    <span class="text-9xl">💰</span>
                </div>
                <div class="flex items-center space-x-4 mb-6">
                    <div class="w-12 h-12 rounded bg-emerald-900/30 flex items-center justify-center text-2xl">💰</div>
                    <h3 class="text-xl font-bold text-white">Market Validation</h3>
                </div>
                <p class="text-sm text-neutral-400 mb-6">
                    Secure verifiable demand by funding the initial Engagement Royalty Pool.
                </p>
                <div class="space-y-4">
                    <div class="bg-neutral-900 p-4 rounded border border-neutral-800 flex justify-between items-center">
                        <span class="text-xs text-neutral-500 uppercase tracking-widest">Target</span>
                        <span class="text-emerald-400 font-bold font-mono">$50K+ LOI Value</span>
                    </div>
                    <div class="bg-neutral-900 p-4 rounded border border-neutral-800 flex justify-between items-center">
                        <span class="text-xs text-neutral-500 uppercase tracking-widest">Requirement</span>
                        <span class="text-white font-bold font-mono">5 Corporate Sponsors</span>
                    </div>
                </div>
            </div>

            <!-- Mandate 2: Compliance -->
            <div class="card-bg p-8 rounded-2xl relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                    <span class="text-9xl">🛡️</span>
                </div>
                <div class="flex items-center space-x-4 mb-6">
                    <div class="w-12 h-12 rounded bg-blue-900/30 flex items-center justify-center text-2xl">🛡️</div>
                    <h3 class="text-xl font-bold text-white">Regulatory Compliance</h3>
                </div>
                <p class="text-sm text-neutral-400 mb-6">
                    Establish the legal framework for compliant global fund distribution via <span class="jargon-tooltip text-blue-400" data-definition="Know Your Customer and Tax Documentation. Legal steps (W-8/W-9 forms) required to verify identity and tax status of creators before paying them.">KYC/Tax</span>.
                </p>
                <div class="space-y-4">
                    <div class="bg-neutral-900 p-4 rounded border border-neutral-800 flex justify-between items-center">
                        <span class="text-xs text-neutral-500 uppercase tracking-widest">Focus</span>
                        <span class="text-blue-400 font-bold font-mono">Global Payouts</span>
                    </div>
                    <div class="bg-neutral-900 p-4 rounded border border-neutral-800 flex justify-between items-center">
                        <span class="text-xs text-neutral-500 uppercase tracking-widest">Tolerance</span>
                        <span class="text-white font-bold font-mono">Zero Failures</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- DEFENSIVILITY & IP -->
    <section class="mb-20">
        <h2 class="text-3xl font-bold text-center mb-12 text-white">Proprietary Tech Stack</h2>

        <div class="grid lg:grid-cols-3 gap-6">
            <!-- IP 1 -->
            <div class="card-bg p-6 rounded-xl border-t-4 border-emerald-500">
                <h3 class="text-lg font-bold text-white mb-2 flex items-center gap-2">
                    <span>⚙️</span> The EQS Engine
                </h3>
                <p class="text-xs text-neutral-400 mb-4">
                    Our <span class="jargon-tooltip text-emerald-400" data-definition="Engagement Quality Score. Proprietary anti-fraud scoring that validates user activity (likes, shares, views) to ensure only real engagement is monetized.">EQS Algorithm</span> solves UGC fraud.
                </p>
                <div class="bg-neutral-900 p-3 rounded text-xs font-mono text-emerald-400 border border-emerald-900/30">
                    Payout = Pool x (Artist EQS / Total EQS)
                </div>
            </div>

            <!-- IP 2 -->
            <div class="card-bg p-6 rounded-xl border-t-4 border-blue-500">
                <h3 class="text-lg font-bold text-white mb-2 flex items-center gap-2">
                    <span>🔗</span> Royalty Model
                </h3>
                <p class="text-xs text-neutral-400 mb-4">
                    Direct-to-Creator payouts via the Transparent Player. Eliminates legacy opacity.
                </p>
                <div class="bg-neutral-900 p-3 rounded text-xs font-mono text-blue-400 border border-blue-900/30">
                    Funds held in Segregated Fiduciary Accounts
                </div>
            </div>

            <!-- IP 3 -->
            <div class="card-bg p-6 rounded-xl border-t-4 border-purple-500">
                <h3 class="text-lg font-bold text-white mb-2 flex items-center gap-2">
                    <span>📊</span> The Data Moat
                </h3>
                <p class="text-xs text-neutral-400 mb-4">
                    Exclusive <span class="jargon-tooltip text-purple-400" data-definition="Specialty Music Reporter. Critical radio spin data licensed to fuel our ranking system and provide intelligence to labels.">SMR Licensing</span> and In-House Fulfillment.
                </p>
                <div class="bg-neutral-900 p-3 rounded text-xs font-mono text-purple-400 border border-purple-900/30">
                    Target: 60%+ Net Margin on Commerce
                </div>
            </div>
        </div>
    </section>

    <!-- FINANCIAL PROJECTIONS (CHARTS) -->
    <section class="mb-20">
        <h2 class="text-3xl font-bold text-center mb-4 text-white">Financial Projections</h2>
        <p class="text-center text-neutral-400 mb-12">Validating the high-margin transactional revenue model.</p>

        <div class="grid md:grid-cols-2 gap-12">
            <!-- Revenue Chart -->
            <div class="card-bg p-6 rounded-2xl shadow-2xl">
                <h3 class="text-lg font-bold text-white mb-4">Revenue Growth (Conservative)</h3>
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>

            <!-- Key Metrics Grid -->
            <div class="grid grid-cols-1 gap-6">
                <div class="card-bg p-6 rounded-xl flex items-center justify-between">
                    <div>
                        <p class="text-xs text-neutral-500 uppercase tracking-widest">Y3 ARR Target</p>
                        <h4 class="text-3xl font-black text-white">$500K+</h4>
                    </div>
                    <div class="text-right text-xs text-neutral-400">
                        Based on 1,000<br>Active Subs
                    </div>
                </div>

                <div class="card-bg p-6 rounded-xl flex items-center justify-between border border-emerald-500/30">
                    <div>
                        <p class="text-xs text-neutral-500 uppercase tracking-widest">Commerce Margin</p>
                        <h4 class="text-3xl font-black text-emerald-400">60%</h4>
                    </div>
                    <div class="text-right text-xs text-neutral-400">
                        Via In-House<br><span class="jargon-tooltip text-emerald-400" data-definition="Capital Expenditure. Investment in physical assets like DTG printers to vertically integrate merchandise production.">CAPEX</span> Model
                    </div>
                </div>

                <div class="card-bg p-6 rounded-xl flex items-center justify-between">
                    <div>
                        <p class="text-xs text-neutral-500 uppercase tracking-widest">Market Control</p>
                        <h4 class="text-3xl font-black text-purple-400">$5M+</h4>
                    </div>
                    <div class="text-right text-xs text-neutral-400">
                        Y5 Booking<br>Volume (GMV)
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FUND ALLOCATION -->
    <section class="mb-20">
        <h2 class="text-3xl font-bold text-center mb-12 text-white">Use of Funds (Series A)</h2>
        <div class="max-w-4xl mx-auto card-bg p-8 rounded-2xl">
            <div class="grid md:grid-cols-2 gap-8 items-center">
                <div class="chart-container" style="height: 250px;">
                    <canvas id="fundChart"></canvas>
                </div>
                <div class="space-y-4">
                    <div class="flex items-center">
                        <div class="w-4 h-4 rounded bg-blue-600 mr-3"></div>
                        <div class="flex-1">
                            <strong class="text-white">40% Engineering & DevOps</strong>
                            <p class="text-xs text-neutral-400">API Build, Security Hardening</p>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <div class="w-4 h-4 rounded bg-purple-600 mr-3"></div>
                        <div class="flex-1">
                            <strong class="text-white">30% Legal & Compliance</strong>
                            <p class="text-xs text-neutral-400">Royalty Audits, Global Payouts</p>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <div class="w-4 h-4 rounded bg-emerald-500 mr-3"></div>
                        <div class="flex-1">
                            <strong class="text-white">15% Market Growth</strong>
                            <p class="text-xs text-neutral-400">Partner Acquisition, Ad Sales</p>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <div class="w-4 h-4 rounded bg-neutral-600 mr-3"></div>
                        <div class="flex-1">
                            <strong class="text-white">15% Operations</strong>
                            <p class="text-xs text-neutral-400">G&A, Infrastructure</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER / CTA -->
    <footer class="text-center pt-12 pb-12 border-t border-neutral-900">
        <h2 class="text-4xl font-black text-white mb-6">THE TIME IS NOW</h2>
        <p class="text-neutral-400 mb-8">Commit to the mandate. Fund the compliance. Secure the market.</p>

        <div class="flex flex-col md:flex-row gap-4 justify-center items-center">
            <a href="invest.php?amount=2500" class="inline-block px-12 py-5 bg-gradient-to-r from-emerald-600 to-emerald-500 rounded-lg text-white font-bold text-xl hover:from-emerald-500 hover:to-emerald-400 transition-all shadow-[0_0_30px_rgba(16,185,129,0.4)] transform hover:scale-105">
                SECURE PROMISSORY NOTE
            </a>
        </div>
        <p class="mt-6 text-xs text-neutral-600 font-mono">
            Minimum Investment: $50 USD | Accredited & Non-Accredited Investors Accepted
        </p>
    </footer>

</div>

<!-- MOBILE STICKY FOOTER -->
<div class="fixed bottom-0 left-0 w-full bg-black/95 backdrop-blur-md border-t border-neutral-800 p-4 md:hidden z-50 flex justify-between items-center">
    <div class="text-white">
        <div class="text-[10px] text-emerald-400 uppercase tracking-wider font-bold">Series A Live</div>
        <div class="font-bold text-sm">Min: $50</div>
    </div>
    <a href="invest.php?amount=2500" class="bg-emerald-500 text-black text-sm font-bold px-6 py-2 rounded-full uppercase tracking-wide">
        Invest Now
    </a>
</div>

<!-- SCRIPTS -->
<script>
    // MOBILE MENU LOGIC
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');

    mobileMenuBtn.addEventListener('click', () => {
        mobileMenu.classList.toggle('open');
    });

    // --- CHART CONFIGURATION ---
    Chart.defaults.color = '#737373';
    Chart.defaults.borderColor = '#262626';
    Chart.defaults.font.family = "'Inter', sans-serif";

    // Revenue Chart
    if(document.getElementById('revenueChart')) {
        const ctxRev = document.getElementById('revenueChart').getContext('2d');
        new Chart(ctxRev, {
            type: 'bar',
            data: {
                labels: ['Year 1', 'Year 2', 'Year 3'],
                datasets: [
                    { label: 'SaaS', data: [1.2, 3.5, 6.0], backgroundColor: '#2563eb' }, // Blue
                    { label: 'Ads', data: [0.5, 2.0, 4.5], backgroundColor: '#9333ea' }, // Purple
                    { label: 'Commerce', data: [0.1, 1.5, 5.5], backgroundColor: '#10b981' } // Green
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: { stacked: true, grid: { color: '#262626' }, ticks: { callback: v => '$' + v + 'M' } }
                },
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }

    // Fund Allocation Chart
    if(document.getElementById('fundChart')) {
        const ctxFund = document.getElementById('fundChart').getContext('2d');
        new Chart(ctxFund, {
            type: 'doughnut',
            data: {
                labels: ['Eng/DevOps', 'Legal/Compliance', 'Market/Growth', 'Operations'],
                datasets: [{
                    data: [40, 30, 15, 15],
                    backgroundColor: ['#2563eb', '#9333ea', '#10b981', '#525252'],
                    borderColor: '#0a0a0a',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: { legend: { display: false } }
            }
        });
    }

    // --- TOOLTIP LOGIC ---
    document.addEventListener('DOMContentLoaded', () => {
        let activeTooltip = null;

        function showTooltip(target) {
            if (activeTooltip) activeTooltip.remove();

            const definition = target.getAttribute('data-definition');
            if (!definition) return;

            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip-box';
            tooltip.innerHTML = definition;
            document.body.appendChild(tooltip);

            const rect = target.getBoundingClientRect();

            // Position above
            let left = rect.left + rect.width / 2;
            let top = rect.top - 10;

            tooltip.style.left = `${left}px`;
            tooltip.style.top = `${top}px`;
            tooltip.style.transform = `translateX(-50%) translateY(-100%)`;

            // Mobile adjustment
            if (window.innerWidth < 768) {
                tooltip.style.width = '90vw';
                tooltip.style.left = '50%';
                tooltip.style.transform = `translateX(-50%) translateY(-100%)`;
            }

            requestAnimationFrame(() => {
                tooltip.style.opacity = '1';
                tooltip.style.visibility = 'visible';
            });

            activeTooltip = tooltip;
        }

        function hideTooltip() {
            if (activeTooltip) {
                activeTooltip.style.opacity = '0';
                setTimeout(() => {
                    if (activeTooltip) activeTooltip.remove();
                    activeTooltip = null;
                }, 200);
            }
        }

        document.body.addEventListener('mouseover', (e) => {
            if (e.target.closest('.jargon-tooltip')) showTooltip(e.target.closest('.jargon-tooltip'));
            else hideTooltip();
        });

        document.body.addEventListener('touchstart', (e) => {
            if (e.target.closest('.jargon-tooltip')) {
                showTooltip(e.target.closest('.jargon-tooltip'));
            } else {
                hideTooltip();
            }
        }, {passive: true});
    });
</script>
</body>
</html>