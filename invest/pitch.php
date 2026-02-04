<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>NextGen Noise | Investor Sales App</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #000000;
            color: #e5e5e5;
            overflow-x: hidden;
            -webkit-tap-highlight-color: transparent;
        }
        h1, h2, h3, .brand-font {
            font-family: 'Orbitron', sans-serif;
        }
        .neon-text-green {
            color: #39ff14;
            text-shadow: 0 0 10px rgba(57, 255, 20, 0.8);
        }
        .cyber-bg {
            background-color: #000000;
            background-image:
                    radial-gradient(circle at 50% 0%, #111111 0%, #000000 70%),
                    radial-gradient(circle at 90% 90%, rgba(57, 255, 20, 0.05) 0%, transparent 20%);
            background-attachment: fixed;
        }
        .card-bg {
            background-color: #0a0a0a;
            border: 1px solid #262626;
            transition: transform 0.2s ease;
        }
        .card-bg:active {
            transform: scale(0.98);
        }
        .chart-container {
            position: relative;
            width: 100%;
        }

        /* DESKTOP NAV OPTIMIZATION */
        .tab-btn {
            border-bottom: 2px solid transparent;
            opacity: 0.6;
            white-space: nowrap;
            transition: all 0.2s;
        }
        .tab-btn:hover {
            opacity: 1;
            color: white;
        }
        .tab-btn.active {
            border-color: #39ff14;
            opacity: 1;
            color: #39ff14;
        }

        /* MOBILE MENU ANIMATION */
        #mobile-menu {
            transition: transform 0.3s ease-in-out, opacity 0.3s ease-in-out;
            transform: translateY(-20px);
            opacity: 0;
            pointer-events: none;
        }
        #mobile-menu.open {
            transform: translateY(0);
            opacity: 1;
            pointer-events: auto;
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease-out;
        }
        .tab-content.active {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .story-badge {
            font-family: monospace;
            font-size: 0.65rem;
            padding: 2px 5px;
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.1);
            color: #a3a3a3;
            border: 1px solid #404040;
        }

        /* Sticky CTA for Mobile */
        .mobile-cta {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: rgba(0,0,0,0.95);
            backdrop-filter: blur(10px);
            border-top: 1px solid #333;
            padding: 12px 16px;
            z-index: 100;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    </style>
</head>
<body class="cyber-bg min-h-screen pb-24">

<!-- HEADER / NAV -->
<nav class="sticky top-0 z-50 bg-black/95 backdrop-blur-md border-b border-neutral-800">
    <div class="container mx-auto px-4 md:px-6">
        <div class="flex items-center justify-between h-16 md:h-20">
            <!-- Branding (Linked to Investor Portal) -->
            <a href="https://nextgennoise.com/investors.php" class="text-lg md:text-xl font-black text-white brand-font tracking-tighter hover:opacity-80 transition-opacity">
                NGN <span class="text-emerald-500">INVESTOR</span>
            </a>

            <!-- Desktop Tabs (Hidden on Mobile) -->
            <div class="hidden md:flex gap-8">
                <button onclick="switchTab('opportunity')" id="btn-opportunity" class="tab-btn active py-6 font-bold uppercase text-xs tracking-widest">Opportunity</button>
                <button onclick="switchTab('artists')" id="btn-artists" class="tab-btn py-6 font-bold uppercase text-xs tracking-widest text-emerald-400">Artist Income</button>
                <button onclick="switchTab('stations')" id="btn-stations" class="tab-btn py-6 font-bold uppercase text-xs tracking-widest text-blue-400">Partner Revenue</button>
                <button onclick="switchTab('marketing')" id="btn-marketing" class="tab-btn py-6 font-bold uppercase text-xs tracking-widest">Marketing</button>
                <button onclick="switchTab('roadmap')" id="btn-roadmap" class="tab-btn py-6 font-bold uppercase text-xs tracking-widest">Roadmap</button>
                <button onclick="switchTab('earnings')" id="btn-earnings" class="tab-btn py-6 font-bold uppercase text-xs tracking-widest text-emerald-400">ROI</button>
            </div>

            <!-- Desktop CTA -->
            <div class="hidden md:block">
                <a href="https://nextgennoise.com/invest/invest.php?amount=2500" class="text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white px-6 py-2 rounded transition-colors uppercase tracking-wider">
                    Invest Now
                </a>
            </div>

            <!-- Mobile Hamburger Button -->
            <button onclick="toggleMobileMenu()" class="md:hidden text-white p-2 focus:outline-none">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path>
                </svg>
            </button>
        </div>
    </div>

    <!-- Mobile Menu Overlay -->
    <div id="mobile-menu" class="absolute top-16 left-0 w-full bg-black/95 border-b border-neutral-800 md:hidden flex flex-col p-4 shadow-2xl backdrop-blur-xl h-screen z-40">
        <div class="flex flex-col gap-4 mt-4">
            <button onclick="switchTab('opportunity')" class="text-left px-4 py-4 rounded-lg bg-neutral-900 border border-neutral-800 text-white font-bold uppercase tracking-wider text-sm active:bg-neutral-800">
                1. The Opportunity
            </button>
            <button onclick="switchTab('artists')" class="text-left px-4 py-4 rounded-lg bg-neutral-900 border border-emerald-900/50 text-emerald-400 font-bold uppercase tracking-wider text-sm active:bg-neutral-800">
                2. Maximizing Artist Income
            </button>
            <button onclick="switchTab('stations')" class="text-left px-4 py-4 rounded-lg bg-neutral-900 border border-blue-900/50 text-blue-400 font-bold uppercase tracking-wider text-sm active:bg-neutral-800">
                3. Partner Revenue (B2B)
            </button>
            <button onclick="switchTab('marketing')" class="text-left px-4 py-4 rounded-lg bg-neutral-900 border border-neutral-800 text-white font-bold uppercase tracking-wider text-sm active:bg-neutral-800">
                4. Marketing Forecast
            </button>
            <button onclick="switchTab('roadmap')" class="text-left px-4 py-4 rounded-lg bg-neutral-900 border border-neutral-800 text-white font-bold uppercase tracking-wider text-sm active:bg-neutral-800">
                5. Roadmap
            </button>
            <button onclick="switchTab('earnings')" class="text-left px-4 py-4 rounded-lg bg-neutral-900 border border-neutral-800 text-white font-bold uppercase tracking-wider text-sm active:bg-neutral-800">
                6. Investor Returns
            </button>

            <div class="border-t border-neutral-800 pt-4 mt-2">
                <a href="https://nextgennoise.com/business-plan.php" class="block text-center text-xs text-neutral-400 py-3 uppercase tracking-widest hover:text-white">
                    Read Business Plan
                </a>
                <a href="https://nextgennoise.com/investor-details.html" class="block text-center text-xs text-neutral-400 py-3 uppercase tracking-widest hover:text-white">
                    Investor Details
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- TAB 1: OPPORTUNITY -->
<main id="tab-opportunity" class="tab-content active container mx-auto px-4 md:px-6 py-8">
    <div class="text-center mb-10">
        <h1 class="text-4xl md:text-7xl font-black mb-4 text-white leading-tight">
            MONETIZING <br>
            <span class="neon-text-green">THE TRUTH</span>
        </h1>
        <p class="text-base md:text-xl text-neutral-400 max-w-2xl mx-auto font-light mb-8">
            The first platform where <strong>Data = Dollars</strong>. We don't just rank music; we create a profitable ecosystem for Artists, Stations, and Venues.
        </p>

        <!-- Link to Business Plan -->
        <a href="https://nextgennoise.com/business-plan.php" class="inline-block border border-neutral-600 text-neutral-300 hover:text-white hover:border-white px-6 py-2 rounded-full text-xs uppercase tracking-widest mb-12 transition-all">
            Read Full Business Plan
        </a>

        <!-- Mobile Stacked Cards -->
        <div class="grid grid-cols-1 gap-4 mb-8">
            <div class="p-4 rounded-xl bg-neutral-900/50 border border-neutral-800 flex items-center text-left">
                <div class="text-2xl mr-4">🕵️‍♂️</div>
                <div>
                    <h3 class="text-white font-bold text-sm">We Catch Cheaters</h3>
                    <p class="text-xs text-neutral-400">Tech that spots bots so real artists get paid.</p>
                </div>
            </div>
            <div class="p-4 rounded-xl bg-neutral-900/50 border border-neutral-800 flex items-center text-left">
                <div class="text-2xl mr-4">💰</div>
                <div>
                    <h3 class="text-white font-bold text-sm">We Share the Wealth</h3>
                    <p class="text-xs text-neutral-400">Artists, Venues, and Stations all earn revenue.</p>
                </div>
            </div>
            <div class="p-4 rounded-xl bg-neutral-900/50 border border-neutral-800 flex items-center text-left">
                <div class="text-2xl mr-4">💸</div>
                <div>
                    <h3 class="text-white font-bold text-sm">We Sell Truth</h3>
                    <p class="text-xs text-neutral-400">SaaS fees for clean, valuable data.</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 mb-8">
            <div class="card-bg p-3 rounded-lg text-center">
                <div class="text-xl font-bold text-white">$250M+</div>
                <div class="text-[10px] text-neutral-500 uppercase">Market Size</div>
            </div>
            <div class="card-bg p-3 rounded-lg text-center border-emerald-500/30 border">
                <div class="text-xl font-bold text-emerald-400">60%</div>
                <div class="text-[10px] text-neutral-500 uppercase">Margin</div>
            </div>
            <div class="card-bg p-3 rounded-lg text-center">
                <div class="text-xl font-bold text-cyan-400">10x</div>
                <div class="text-[10px] text-neutral-500 uppercase">ROI Target</div>
            </div>
            <div class="card-bg p-3 rounded-lg text-center">
                <div class="text-xl font-bold text-purple-400">$50K+</div>
                <div class="text-[10px] text-neutral-500 uppercase">LOIs</div>
            </div>
        </div>
    </div>

    <div class="card-bg p-4 rounded-xl shadow-2xl mb-8">
        <h3 class="text-base font-bold text-white mb-4">Projected Income (3 Years)</h3>
        <div class="chart-container h-64 md:h-80">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>
</main>

<!-- TAB 2: ARTISTS / LABELS (INCOME FOCUSED) -->
<main id="tab-artists" class="tab-content container mx-auto px-4 md:px-6 py-8">
    <div class="text-center mb-8">
        <h2 class="text-2xl md:text-3xl font-bold text-white mb-2">Maximizing Artist Revenue</h2>
        <p class="text-sm text-neutral-400">Legacy streaming pays pennies. The NGN Ecosystem pays the rent.</p>
    </div>

    <!-- The "More Money" Chart -->
    <div class="card-bg p-4 rounded-xl border border-emerald-500/20 mb-6">
        <div class="flex justify-between items-center mb-4">
            <div>
                <h3 class="text-lg font-bold text-white">Income Potential (Per 10k Fans)</h3>
                <p class="text-[10px] text-neutral-500">Comparing Traditional Streaming vs. NGN Economy</p>
            </div>
        </div>
        <div class="chart-container h-64 md:h-80">
            <canvas id="artistIncomeChart"></canvas>
        </div>
        <div class="mt-4 p-3 bg-black/50 rounded-lg text-xs text-neutral-400 border border-emerald-900/50">
            <strong class="text-emerald-400 block mb-1">The NGN Multiplier</strong>
            By combining Engagement Royalties, Merch Sales, and Direct Tipping ("Sparks"), artists earn exponentially more per fan than streaming alone.
        </div>
    </div>

    <!-- Money Features -->
    <div class="space-y-4">
        <div class="card-bg p-4 rounded-xl border-l-4 border-emerald-500">
            <div class="flex justify-between items-start mb-1">
                <h4 class="text-base font-bold text-white">Engagement Royalties (EQS)</h4>
                <span class="story-badge">Ref: A.5</span>
            </div>
            <p class="text-xs text-neutral-400">Artists get paid for real engagement (Shares, Saves, Comments), not just passive listening. High EQS = Higher Payout.</p>
        </div>

        <div class="card-bg p-4 rounded-xl border-l-4 border-blue-500">
            <div class="flex justify-between items-start mb-1">
                <h4 class="text-base font-bold text-white">Automated Merch</h4>
                <span class="story-badge">Ref: A.10</span>
            </div>
            <p class="text-xs text-neutral-400">Zero-inventory dropshipping. Artists upload art, we handle the rest. They keep the profit, we handle the logistics.</p>
        </div>

        <div class="card-bg p-4 rounded-xl border-l-4 border-purple-500">
            <div class="flex justify-between items-start mb-1">
                <h4 class="text-base font-bold text-white">Sparks & Tips</h4>
                <span class="story-badge">Ref: C.6</span>
            </div>
            <p class="text-xs text-neutral-400">Micro-transactions allow fans to "Tip" artists instantly on any post. Direct-to-creator revenue.</p>
        </div>
    </div>
</main>

<!-- TAB 3: STATIONS / VENUES (REVENUE SHARING) -->
<main id="tab-stations" class="tab-content container mx-auto px-4 md:px-6 py-8">
    <div class="text-center mb-8">
        <h2 class="text-2xl md:text-3xl font-bold text-white mb-2">Partner Profit Models</h2>
        <p class="text-sm text-neutral-400">Turning "Infrastructure" into "Revenue Centers" for our partners.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <!-- Station Revenue -->
        <div class="card-bg p-5 rounded-xl border-t-4 border-blue-500">
            <div class="flex justify-between items-start mb-4">
                <div class="text-2xl">📻</div>
                <span class="story-badge">Ref: S.4 / S.8</span>
            </div>
            <h3 class="text-lg font-bold text-white mb-2">Stations: Ad Revenue Share</h3>
            <p class="text-xs text-neutral-400 mb-4">
                Stations that use our player earn a <strong>50% Split</strong> of all ad revenue generated.
            </p>
            <div class="bg-neutral-900 p-3 rounded text-center">
                <div class="text-blue-400 font-bold text-lg">50/50</div>
                <div class="text-[10px] text-neutral-500 uppercase">Rev Share Model</div>
            </div>
        </div>

        <!-- Venue Revenue -->
        <div class="card-bg p-5 rounded-xl border-t-4 border-emerald-500">
            <div class="flex justify-between items-start mb-4">
                <div class="text-2xl">🏟️</div>
                <span class="story-badge">Ref: V.3 / V.9</span>
            </div>
            <h3 class="text-lg font-bold text-white mb-2">Venues: PPV Ticket Sales</h3>
            <p class="text-xs text-neutral-400 mb-4">
                Venues can sell "Digital Tickets" for sold-out shows. We calculate Net Splits after expenses (Ref: V.9).
            </p>
            <div class="bg-neutral-900 p-3 rounded text-center">
                <div class="text-emerald-400 font-bold text-lg">New Revenue</div>
                <div class="text-[10px] text-neutral-500 uppercase">From Live Streams</div>
            </div>
        </div>
    </div>

    <div class="card-bg p-4 rounded-xl">
        <div class="flex justify-between items-start mb-1">
            <h4 class="text-base font-bold text-white">SMR Ingestion Portal</h4>
            <span class="story-badge">Ref: S.2</span>
        </div>
        <p class="text-xs text-neutral-400 mb-4">
            Before they can earn, they must upload. Our SMR portal makes data entry effortless, feeding the entire revenue engine.
        </p>
        <div class="flex justify-between items-center gap-2">
            <div class="text-center flex-1">
                <div style="height: 60px; width: 60px; margin: 0 auto;">
                    <canvas id="gaugeCoverage"></canvas>
                </div>
                <div class="text-sm font-bold text-white mt-1">98%</div>
                <div class="text-[8px] text-neutral-500 uppercase">Monitoring</div>
            </div>
            <div class="h-8 w-px bg-neutral-800"></div>
            <div class="text-center flex-1">
                <div style="height: 60px; width: 60px; margin: 0 auto;">
                    <canvas id="gaugeLinkage"></canvas>
                </div>
                <div class="text-sm font-bold text-white mt-1">95%</div>
                <div class="text-[8px] text-neutral-500 uppercase">Accuracy</div>
            </div>
        </div>
    </div>
</main>

<!-- TAB 4: MARKETING -->
<main id="tab-marketing" class="tab-content container mx-auto px-4 md:px-6 py-8">
    <div class="text-center mb-8">
        <h2 class="text-2xl md:text-3xl font-bold text-white mb-2">The Ad Engine</h2>
        <p class="text-sm text-neutral-400">Monetizing data through precision targeting.</p>
    </div>

    <div class="card-bg p-4 rounded-xl border border-purple-500/20 mb-6">
        <h3 class="text-base font-bold text-white mb-4">Inventory Growth (Impressions)</h3>
        <div class="chart-container h-56 md:h-80">
            <canvas id="adGrowthChart"></canvas>
        </div>
    </div>

    <div class="space-y-4">
        <div class="card-bg p-4 rounded-xl border-l-4 border-purple-500">
            <div class="flex justify-between items-start mb-1">
                <h4 class="text-base font-bold text-white">Self-Serve Platform</h4>
                <span class="story-badge">AD.1</span>
            </div>
            <p class="text-xs text-neutral-400">Advertisers launch audio/display campaigns instantly.</p>
        </div>
        <div class="card-bg p-4 rounded-xl border-l-4 border-purple-500">
            <div class="flex justify-between items-start mb-1">
                <h4 class="text-base font-bold text-white">Score Targeting</h4>
                <span class="story-badge">AD.4</span>
            </div>
            <p class="text-xs text-neutral-400">Target users listening to "Rising Stars" vs "Established Acts".</p>
        </div>
    </div>
</main>

<!-- TAB 5: ROADMAP -->
<main id="tab-roadmap" class="tab-content container mx-auto px-4 md:px-6 py-8">
    <div class="text-center mb-8">
        <h2 class="text-2xl md:text-3xl font-bold text-white mb-2">Execution Plan</h2>
        <p class="text-sm text-neutral-400">Path to Industry Standard.</p>
    </div>

    <div class="relative border-l-2 border-neutral-800 ml-3 space-y-8">
        <!-- Phase 1 -->
        <div class="relative pl-6">
            <div class="absolute -left-[7px] top-0 w-4 h-4 rounded-full bg-emerald-500 shadow-[0_0_10px_rgba(16,185,129,0.5)]"></div>
            <h3 class="text-lg font-bold text-emerald-400 mb-2">NOW: Foundation</h3>
            <div class="bg-neutral-900 p-3 rounded border border-neutral-800 mb-2">
                <strong class="text-white text-sm block">Artist Dashboard</strong>
                <p class="text-[10px] text-neutral-500">Launch stats frontend.</p>
            </div>
            <div class="bg-neutral-900 p-3 rounded border border-neutral-800">
                <strong class="text-white text-sm block">Station Uploads</strong>
                <p class="text-[10px] text-neutral-500">Ingest radio data at scale.</p>
            </div>
        </div>

        <!-- Phase 2 -->
        <div class="relative pl-6">
            <div class="absolute -left-[7px] top-0 w-4 h-4 rounded-full bg-blue-600 border-2 border-black"></div>
            <h3 class="text-lg font-bold text-blue-400 mb-2">NEXT: Revenue</h3>
            <div class="bg-neutral-900 p-3 rounded border border-neutral-800 mb-2">
                <strong class="text-white text-sm block">Merch Store</strong>
                <p class="text-[10px] text-neutral-500">Start transactional commerce.</p>
            </div>
        </div>

        <!-- Phase 3 -->
        <div class="relative pl-6">
            <div class="absolute -left-[7px] top-0 w-4 h-4 rounded-full bg-purple-600 border-2 border-black"></div>
            <h3 class="text-lg font-bold text-purple-400 mb-2">FUTURE: Ecosystem</h3>
            <div class="bg-neutral-900 p-3 rounded border border-neutral-800">
                <strong class="text-white text-sm block">Booking & Ticketing</strong>
                <p class="text-[10px] text-neutral-500">Own the live experience.</p>
            </div>
        </div>
    </div>
</main>

<!-- TAB 6: EARNINGS (INVESTOR) -->
<main id="tab-earnings" class="tab-content container mx-auto px-4 md:px-6 py-8 pb-20">
    <div class="text-center mb-8">
        <h2 class="text-2xl md:text-3xl font-bold text-white mb-2">Investor Returns</h2>
        <p class="text-sm text-neutral-400">Our Triple-Threat Model drives ROI.</p>
    </div>

    <div class="space-y-4 mb-8">
        <div class="flex items-center bg-neutral-900/50 p-3 rounded-lg">
            <div class="w-8 h-8 rounded bg-blue-600 flex items-center justify-center mr-3 text-white font-bold text-sm">1</div>
            <div>
                <strong class="text-white block text-sm">Subscriptions (SaaS)</strong>
                <p class="text-[10px] text-neutral-500">High margin recurring revenue.</p>
            </div>
        </div>
        <div class="flex items-center bg-neutral-900/50 p-3 rounded-lg">
            <div class="w-8 h-8 rounded bg-purple-600 flex items-center justify-center mr-3 text-white font-bold text-sm">2</div>
            <div>
                <strong class="text-white block text-sm">Ad Platform</strong>
                <p class="text-[10px] text-neutral-500">Scalable programmatic inventory.</p>
            </div>
        </div>
        <div class="flex items-center bg-neutral-900/50 p-3 rounded-lg">
            <div class="w-8 h-8 rounded bg-emerald-600 flex items-center justify-center mr-3 text-white font-bold text-sm">3</div>
            <div>
                <strong class="text-white block text-sm">Commerce</strong>
                <p class="text-[10px] text-neutral-500">Transactional fees from Merch/PPV.</p>
            </div>
        </div>
    </div>

    <div class="card-bg p-4 rounded-xl shadow-2xl mb-8">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-base font-bold text-white">3-Year Projection</h3>
            <div class="text-right">
                <div class="text-xl font-bold text-emerald-400">$16M+</div>
            </div>
        </div>
        <div class="chart-container h-64 md:h-80">
            <canvas id="revenueChart2"></canvas>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3 mb-8">
        <div class="bg-neutral-900 p-3 rounded-lg border-t-2 border-emerald-500 text-center">
            <h4 class="text-white font-bold text-sm">10x ROI</h4>
            <p class="text-[10px] text-neutral-500">Target Return</p>
        </div>
        <div class="bg-neutral-900 p-3 rounded-lg border-t-2 border-blue-500 text-center">
            <h4 class="text-white font-bold text-sm">Acquisition</h4>
            <p class="text-[10px] text-neutral-500">Target: DSPs/Majors</p>
        </div>
    </div>

    <div class="mt-16 text-center border-t border-neutral-900 pt-12">
        <h3 class="text-2xl font-bold text-white mb-6">Ready to Join the Revolution?</h3>

        <div class="flex flex-col md:flex-row gap-4 justify-center items-center mb-8">
            <a href="https://nextgennoise.com/invest/invest.php?amount=2500" class="inline-block px-12 py-5 bg-gradient-to-r from-emerald-600 to-emerald-500 rounded-lg text-white font-bold text-xl hover:from-emerald-500 hover:to-emerald-400 transition-all cursor-pointer shadow-[0_0_30px_rgba(16,185,129,0.4)] transform hover:scale-105 border border-emerald-400/20 w-full md:w-auto text-center">
                SECURE PROMISSORY NOTE
            </a>
            <a href="https://nextgennoise.com/investor-details.html" class="inline-block px-8 py-5 border border-neutral-600 rounded-lg text-neutral-300 font-bold hover:text-white hover:border-white transition-all w-full md:w-auto text-center">
                VIEW DETAILS
            </a>
        </div>

        <p class="mt-4 text-xs text-neutral-500 uppercase tracking-widest">
            Minimum Investment: $50 <span class="text-emerald-500 ml-2 font-bold">(Suggested: $2,500)</span>
        </p>
        <a href="https://nextgennoise.com/business-plan.php" class="block mt-6 text-xs text-neutral-600 hover:text-emerald-500 underline">Read Full Business Plan</a>
    </div>
</main>

<!-- MOBILE STICKY FOOTER CTA -->
<div class="mobile-cta md:hidden">
    <div class="text-white">
        <div class="text-[10px] text-emerald-400 uppercase tracking-wider font-bold">Series A Live</div>
        <div class="font-bold text-sm">Min: $50 <span class="text-[10px] text-neutral-400 font-normal inline-block ml-1">Sugg: $2,500</span></div>
    </div>
    <a href="https://nextgennoise.com/invest/invest.php?amount=2500" class="bg-emerald-500 hover:bg-emerald-400 text-black text-sm font-bold px-6 py-2 rounded-full uppercase tracking-wide">
        Invest Now
    </a>
</div>

<!-- SCRIPTS -->
<script>
    function switchTab(tabId) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));

        // Activate Desktop Buttons
        const btn = document.getElementById(`btn-${tabId}`);
        if (btn) btn.classList.add('active');

        // Activate Tab
        document.getElementById(`tab-${tabId}`).classList.add('active');

        // Close Mobile Menu if Open
        toggleMobileMenu(false);

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function toggleMobileMenu(forceState) {
        const menu = document.getElementById('mobile-menu');
        if (typeof forceState !== 'undefined') {
            if (!forceState) menu.classList.remove('open');
            else menu.classList.add('open');
        } else {
            menu.classList.toggle('open');
        }
    }

    Chart.defaults.color = '#737373';
    Chart.defaults.borderColor = '#262626';
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.maintainAspectRatio = false;

    // Shared Config
    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        layout: { padding: 0 }
    };

    // 1. Revenue Chart (Tab 1)
    if (document.getElementById('revenueChart')) {
        new Chart(document.getElementById('revenueChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Y1', 'Y2', 'Y3'],
                datasets: [
                    { label: 'SaaS', data: [1.2, 3.5, 6.0], backgroundColor: '#2563eb' },
                    { label: 'Ads', data: [0.5, 2.0, 4.5], backgroundColor: '#9333ea' },
                    { label: 'Merch', data: [0.1, 1.5, 5.5], backgroundColor: '#10b981' }
                ]
            },
            options: {
                ...commonOptions,
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: { stacked: true, grid: { color: '#262626' }, ticks: { callback: v => '$' + v + 'M' } }
                },
                plugins: { legend: { position: 'bottom', labels: { color: '#a3a3a3', boxWidth: 10, font: {size: 10} } } }
            }
        });
    }

    // 2. Artist Income Stack (Tab 2) - Money Chart
    if (document.getElementById('artistIncomeChart')) {
        new Chart(document.getElementById('artistIncomeChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Legacy Streaming', 'NGN Ecosystem'],
                datasets: [
                    {
                        label: 'Revenue',
                        data: [30, 450], // Comparative arbitrary scale: $30 vs $450
                        backgroundColor: ['#333', '#10b981'],
                        borderRadius: 4
                    }
                ]
            },
            options: {
                ...commonOptions,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                if (context.dataIndex === 0) return ' Legacy: ~$0.003/stream';
                                return ' NGN: Royalties + Merch + Tips';
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { display: false } // Hide Y axis for cleaner look
                }
            }
        });
    }

    // 3. Ad Growth (Tab 4)
    if (document.getElementById('adGrowthChart')) {
        new Chart(document.getElementById('adGrowthChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: ['Q1', 'Q2', 'Q3', 'Q4', 'Q5', 'Q6'],
                datasets: [{
                    label: 'Impressions',
                    data: [100, 250, 600, 1500, 3000, 5000],
                    borderColor: '#a855f7',
                    backgroundColor: 'rgba(168, 85, 247, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0
                }]
            },
            options: {
                ...commonOptions,
                scales: {
                    y: { grid: { color: '#262626' }, ticks: { callback: v => v + 'k' } },
                    x: { grid: { display: false }, ticks: { display: false } }
                }
            }
        });
    }

    // 4. Revenue Chart Duplicate (Tab 6)
    if (document.getElementById('revenueChart2')) {
        new Chart(document.getElementById('revenueChart2').getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Y1', 'Y2', 'Y3'],
                datasets: [
                    { label: 'SaaS', data: [1.2, 3.5, 6.0], backgroundColor: '#2563eb' },
                    { label: 'Ads', data: [0.5, 2.0, 4.5], backgroundColor: '#9333ea' },
                    { label: 'Merch', data: [0.1, 1.5, 5.5], backgroundColor: '#10b981' }
                ]
            },
            options: {
                ...commonOptions,
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: { stacked: true, grid: { color: '#262626' }, ticks: { callback: v => '$' + v + 'M' } }
                },
                plugins: { legend: { position: 'bottom', labels: { color: '#a3a3a3', boxWidth: 10, font: {size: 10} } } }
            }
        });
    }

    // Gauges
    function createGauge(ctx, val, color) {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [val, 100-val],
                    backgroundColor: [color, '#262626'],
                    borderWidth: 0,
                    circumference: 180,
                    rotation: 270
                }]
            },
            options: {
                ...commonOptions,
                cutout: '85%',
                plugins: { tooltip: { enabled: false } }
            }
        });
    }
    if (document.getElementById('gaugeCoverage')) createGauge(document.getElementById('gaugeCoverage').getContext('2d'), 98, '#06b6d4');
    if (document.getElementById('gaugeLinkage')) createGauge(document.getElementById('gaugeLinkage').getContext('2d'), 95, '#10b981');

</script>
</body>
</html>