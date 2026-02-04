<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NGN 2.0: The Financial Ecosystem</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap');

        body {
            font-family: 'Inter', sans-serif;
            background-color: #0f172a;
            color: #e2e8f0;
        }

        :root {
            --color-primary: #06b6d4;
            --color-secondary: #8b5cf6;
            --color-accent: #ec4899;
            --color-success: #10b981;
            --color-warning: #f59e0b;
        }

        .chart-container {
            position: relative;
            width: 100%;
            max-width: 600px;
            height: 350px;
            max-height: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        @media (max-width: 640px) {
            .chart-container {
                height: 300px;
            }
        }

        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #1e293b;
        }
        ::-webkit-scrollbar-thumb {
            background: #475569;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }

        .glass-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(148, 163, 184, 0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .glass-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
            border-color: rgba(6, 182, 212, 0.3);
        }

        .input-field {
            background-color: #1e293b;
            border: 1px solid #475569;
            color: #e2e8f0;
            padding: 8px;
            border-radius: 6px;
            width: 100%;
        }

        .input-label {
            font-size: 0.875rem;
            color: #94a3b8;
            margin-bottom: 4px;
        }
    </style>
</head>
<body class="antialiased">

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">

    <header class="text-center mb-16">
        <h1 class="text-5xl md:text-7xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 to-purple-500 tracking-tight mb-4">
            NGN 2.0
        </h1>
        <p class="text-xl md:text-2xl text-slate-400 font-light max-w-3xl mx-auto">
            The Financial Operating System for the Music Industry. <br>
            <span class="text-cyan-400 font-semibold">Transparency. Integrity. Profit.</span>
        </p>
    </header>

    <section class="mb-20">
        <div class="mb-8 border-l-4 border-cyan-500 pl-4">
            <h2 class="text-3xl font-bold text-white mb-2">The Financial Engines & Tiered Advantage</h2>
            <p class="text-slate-400 max-w-4xl">
                Our core formulas now incorporate a <strong>Subscription Multiplier</strong> ($\mathbf{S_{\text{EQS}}}$ and $\mathbf{S_{\text{TEVS}}}$). Paying for <strong>Pro</strong> or <strong>Enterprise</strong> not only unlocks AI tools, but also amplifies a partner's influence in rankings and their share of the payout pool.
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div class="glass-card rounded-2xl p-6 flex flex-col items-center">
                <h3 class="text-xl font-semibold text-cyan-300 mb-4">Engagement Quality Score (EQS) Weights</h3>
                <div class="chart-container">
                    <canvas id="eqsChart"></canvas>
                </div>
                <p class="text-sm text-slate-400 mt-4 text-center">
                    <span class="font-bold text-white">EQS Multiplier:</span> Free (1.0), Pro (1.1), Enterprise (1.2). Payment directly increases a creator's potential payout.
                </p>
            </div>

            <div class="glass-card rounded-2xl p-6 flex flex-col items-center">
                <h3 class="text-xl font-semibold text-purple-300 mb-4">Total Enterprise Value Score (TEVS)</h3>
                <div class="chart-container">
                    <canvas id="tevsChart"></canvas>
                </div>
                <p class="text-sm text-slate-400 mt-4 text-center">
                    <span class="font-bold text-white">TEVS Multiplier:</span> Free (1.0), Pro (1.15), Enterprise (1.3). Payment directly increases ranking influence.
                </p>
            </div>
        </div>
    </section>

    <!-- SECTION 2: INTERACTIVE FINANCIAL PROJECTION -->
    <section class="mb-20">
        <div class="mb-8 border-l-4 border-purple-500 pl-4">
            <h2 class="text-3xl font-bold text-white mb-2">Interactive Subscription & Profit Scenario</h2>
            <p class="text-slate-400 max-w-4xl">
                Simulate the financial impact of subscription adoption across B2B partners, calculating both individual partner income and NGN's total high-margin **Annual Recurring Revenue (ARR)**.
            </p>
        </div>

        <div class="glass-card rounded-2xl p-8">

            <!-- SUBSCRIPTION INPUTS -->
            <h4 class="text-lg font-semibold text-purple-300 mb-4 border-b border-slate-700 pb-2">B2B Subscription Volume (Annual Fees)</h4>
            <div class="mb-8 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-6">

                <!-- Artist Tiers -->
                <div class="col-span-2 md:col-span-1 p-3 bg-slate-800 rounded-lg">
                    <label class="input-label" for="artistProCount">Artists Pro ($199)</label>
                    <input type="number" id="artistProCount" class="input-field mb-3" value="100" min="0">
                    <label class="input-label" for="artistEntCount">Artists Enterprise ($999)</label>
                    <input type="number" id="artistEntCount" class="input-field" value="20" min="0">
                </div>

                <!-- Label Tiers -->
                <div class="col-span-2 md:col-span-1 p-3 bg-slate-800 rounded-lg">
                    <label class="input-label" for="labelProCount">Labels Pro ($2.5k)</label>
                    <input type="number" id="labelProCount" class="input-field mb-3" value="10" min="0">
                    <label class="input-label" for="labelEntCount">Labels Enterprise ($5k)</label>
                    <input type="number" id="labelEntCount" class="input-field" value="5" min="0">
                </div>

                <!-- Station Tiers -->
                <div class="col-span-2 md:col-span-1 p-3 bg-slate-800 rounded-lg">
                    <label class="input-label" for="stationProCount">Stations Pro ($1.8k)</label>
                    <input type="number" id="stationProCount" class="input-field mb-3" value="50" min="0">
                    <label class="input-label" for="stationEntCount">Stations Enterprise ($3.5k)</label>
                    <input type="number" id="stationEntCount" class="input-field" value="10" min="0">
                </div>

                <!-- Venue/Agent Tiers -->
                <div class="col-span-2 md:col-span-1 p-3 bg-slate-800 rounded-lg">
                    <label class="input-label" for="venueProCount">Venue/Agent Pro ($999)</label>
                    <input type="number" id="venueProCount" class="input-field mb-3" value="30" min="0">
                    <label class="input-label" for="venueEntCount">Venue/Agent Enterprise ($2k)</label>
                    <input type="number" id="venueEntCount" class="input-field" value="5" min="0">
                </div>

                <!-- Global Pool (Financial Drivers) -->
                <div class="col-span-2 md:col-span-2 p-3 bg-slate-800 rounded-lg border-b-2 border-cyan-500">
                    <label class="input-label" for="poolSize">Monthly Royalty Pool ($)</label>
                    <input type="number" id="poolSize" class="input-field mb-3" value="20000" min="0">
                    <label class="input-label" for="artistEQSShare">Artist EQS Share (%)</label>
                    <input type="number" id="artistEQSShare" class="input-field" value="5" min="0" max="100">
                </div>
            </div>

            <!-- SCENARIO DRIVERS (FIXED INPUTS REINTRODUCED) -->
            <h4 class="text-lg font-semibold text-yellow-300 mb-4 border-b border-slate-700 pb-2">Single Partner Scenario Drivers</h4>
            <div class="mb-8 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-6">
                <div class="col-span-1 p-3 bg-slate-800 rounded-lg">
                    <label class="input-label" for="artistMerchUnits">Artist Merch Units Sold</label>
                    <input type="number" id="artistMerchUnits" class="input-field" value="1000" min="0">
                </div>
                <div class="col-span-1 p-3 bg-slate-800 rounded-lg">
                    <label class="input-label" for="labelCommissionRate">Label Commission Rate (%)</label>
                    <input type="number" id="labelCommissionRate" class="input-field" value="20" min="0" max="100">
                </div>
                <div class="col-span-1 p-3 bg-slate-800 rounded-lg">
                    <label class="input-label" for="labelSubCost">Label Sub Cost ($) (Scenario)</label>
                    <input type="number" id="labelSubCost" class="input-field" value="5000" min="0">
                </div>
                <div class="col-span-1 p-3 bg-slate-800 rounded-lg">
                    <label class="input-label" for="stationAdSpots">Station Ad Spots Sold</label>
                    <input type="number" id="stationAdSpots" class="input-field" value="10" min="0">
                </div>
                <div class="col-span-1 p-3 bg-slate-800 rounded-lg">
                    <label class="input-label" for="venueBookings">Venue Booking Commissions (count)</label>
                    <input type="number" id="venueBookings" class="input-field" value="5" min="0">
                </div>
            </div>

            <!-- RUN BUTTON -->
            <div class="text-center mb-10">
                <button onclick="calculateProjection()" class="px-10 py-3 bg-pink-600 hover:bg-pink-700 text-white font-bold rounded-full text-lg shadow-lg shadow-pink-500/50 transition duration-150">
                    Run New Projection
                </button>
            </div>

            <!-- RESULTS DASHBOARD -->
            <h4 class="text-lg font-semibold text-cyan-300 mb-4 border-b border-slate-700 pb-2">Projection Results (Single Scenario & Total ARR)</h4>
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mt-4 text-center">

                <div class="p-4 bg-slate-800 rounded-lg">
                    <div id="artistIncome" class="text-2xl font-bold text-cyan-400">$28,800</div>
                    <div class="text-sm text-slate-400">Artist Net Income<br>(Scenario)</div>
                </div>
                <div class="p-4 bg-slate-800 rounded-lg">
                    <div id="labelIncome" class="text-2xl font-bold text-purple-400">$4,900</div>
                    <div class="text-sm text-slate-400">Label Net Income<br>(Scenario)</div>
                </div>
                <div class="p-4 bg-slate-800 rounded-lg">
                    <div id="totalB2BARR" class="text-2xl font-extrabold text-yellow-400">$318,800</div>
                    <div class="text-sm text-slate-400">NGN Total B2B ARR<br>(High-Margin)</div>
                </div>
                <div class="p-4 bg-slate-800 rounded-lg">
                    <div id="venueIncome" class="text-2xl font-bold text-pink-400">$3,300</div>
                    <div class="text-sm text-slate-400">Venue Net Income<br>(Scenario)</div>
                </div>
                <div class="p-4 bg-slate-800 rounded-lg">
                    <div id="stationIncome" class="text-2xl font-bold text-yellow-400">$2,700</div>
                    <div class="text-sm text-slate-400">Station Net Income<br>(Scenario)</div>
                </div>
            </div>

            <!-- BAR CHART -->
            <div class="chart-container" style="max-width: 800px; height: 400px; margin-top: 40px;">
                <canvas id="incomeChart"></canvas>
            </div>

        </div>
    </section>

    <!-- SECTION 3: ARCHITECTURE DIAGRAM (HTML/CSS) -->
    <section class="mb-20">
        <div class="mb-8 border-l-4 border-pink-500 pl-4">
            <h2 class="text-3xl font-bold text-white mb-2">The Multi-Sided Pool Architecture</h2>
            <p class="text-slate-400 max-w-4xl">
                To scale to 1,000+ artists and beyond, NGN utilizes a segregated fiduciary pool structure. Funds are strictly isolated to ensure legal compliance, auditability, and trust.
            </p>
        </div>

        <div class="glass-card rounded-2xl p-8 overflow-x-auto">
            <div class="min-w-[700px] flex flex-col gap-8">

                <div class="grid grid-cols-3 gap-8 text-center">
                    <div class="bg-cyan-900/30 border border-cyan-500/50 rounded-xl p-4">
                        <div class="text-3xl mb-2">🏢</div>
                        <h4 class="font-bold text-cyan-400">Sponsors & Brands</h4>
                        <p class="text-xs text-cyan-200">LOIs & Ad Buys</p>
                    </div>
                    <div class="bg-purple-900/30 border border-purple-500/50 rounded-xl p-4">
                        <div class="text-3xl mb-2">🎫</div>
                        <h4 class="font-bold text-purple-400">Fans & Users</h4>
                        <p class="text-xs text-purple-200">PPV Tix & Merch</p>
                    </div>
                    <div class="bg-yellow-900/30 border border-yellow-500/50 rounded-xl p-4">
                        <div class="text-3xl mb-2">💼</div>
                        <h4 class="font-bold text-yellow-400">B2B Subscribers</h4>
                        <p class="text-xs text-yellow-200">Labels & Agents</p>
                    </div>
                </div>

                <div class="grid grid-cols-3 text-center text-slate-500 text-2xl">
                    <div>⬇️</div>
                    <div>⬇️</div>
                    <div>⬇️</div>
                </div>

                <div class="grid grid-cols-3 gap-8 relative">
                    <div class="bg-slate-800 border-2 border-cyan-500 rounded-xl p-6 relative z-10">
                        <div class="absolute -top-3 left-1/2 transform -translate-x-1/2 bg-cyan-600 text-xs font-bold px-2 py-1 rounded text-white">FIDUCIARY</div>
                        <h4 class="font-bold text-xl text-white mb-2">Engagement Royalty Pool (ERP)</h4>
                        <p class="text-sm text-slate-400">Funded by Sponsors. Allocated via <strong>EQS Formula</strong>.</p>
                    </div>

                    <div class="bg-slate-800 border-2 border-purple-500 rounded-xl p-6 relative z-10">
                        <div class="absolute -top-3 left-1/2 transform -translate-x-1/2 bg-purple-500 text-xs font-bold px-2 py-1 rounded text-white">ESCROW</div>
                        <h4 class="font-bold text-xl text-white mb-2">Transactional Escrow Pool (TEP)</h4>
                        <p class="text-sm text-slate-400">Holds Gross Sales. Releases on Fulfillment.</p>
                    </div>

                    <div class="bg-slate-800 border-2 border-yellow-500 rounded-xl p-6 relative z-10">
                        <div class="absolute -top-3 left-1/2 transform -translate-x-1/2 bg-yellow-600 text-xs font-bold px-2 py-1 rounded text-white">OPERATIONAL</div>
                        <h4 class="font-bold text-xl text-white mb-2">NGN Operating Capital</h4>
                        <p class="text-sm text-slate-400">Subscriptions & Commissions. Runs the Business.</p>
                    </div>
                </div>

                <div class="grid grid-cols-3 text-center text-slate-500 text-2xl">
                    <div>⬇️</div>
                    <div>⬇️</div>
                    <div>🔄</div>
                </div>

                <div class="grid grid-cols-3 gap-8">
                    <div class="col-span-2 bg-gradient-to-r from-slate-800 to-slate-900 border border-slate-700 rounded-xl p-6 flex justify-between items-center">
                        <div class="text-left">
                            <h4 class="font-bold text-lg text-white">Creators & Partners</h4>
                            <p class="text-sm text-slate-400">Artists, Labels, Stations, Venues</p>
                        </div>
                        <div class="text-right">
                            <div class="text-2xl font-bold text-emerald-400">Stripe Connect Payouts 💸</div>
                            <p class="text-xs text-emerald-600">Automated Tax & KYC</p>
                        </div>
                    </div>
                    <div class="flex items-center justify-center text-slate-500 text-sm italic">
                        Reinvested into Growth
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- SECTION 4: 5-YEAR ROADMAP -->
    <section class="mb-12">
        <div class="mb-8 border-l-4 border-yellow-500 pl-4">
            <h2 class="text-3xl font-bold text-white mb-2">Strategic Valuation Roadmap</h2>
            <p class="text-slate-400 max-w-4xl">
                A clear path from initial compliance and seed funding to Series B expansion and eventual market dominance.
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
            <div class="glass-card p-6 rounded-xl border-t-4 border-cyan-500">
                <h3 class="text-2xl font-bold text-white mb-1">Year 1</h3>
                <p class="text-cyan-400 font-semibold mb-4">Foundation & Security</p>
                <ul class="space-y-2 text-sm text-slate-300">
                    <li class="flex items-start"><span class="mr-2 text-cyan-500">✓</span> Global Payout Compliance</li>
                    <li class="flex items-start"><span class="mr-2 text-cyan-500">✓</span> SMR Equity Deal Signed</li>
                    <li class="flex items-start"><span class="mr-2 text-cyan-500">✓</span> $50k+ Sponsorship LOIs</li>
                    <li class="flex items-start"><span class="mr-2 text-cyan-500">✓</span> Series A Readiness</li>
                </ul>
            </div>

            <div class="glass-card p-6 rounded-xl border-t-4 border-purple-500">
                <h3 class="text-2xl font-bold text-white mb-1">Years 2-3</h3>
                <p class="text-purple-400 font-semibold mb-4">Scale & Diversify</p>
                <ul class="space-y-2 text-sm text-slate-300">
                    <li class="flex items-start"><span class="mr-2 text-purple-500">✓</span> 1,000+ Active B2B Subs</li>
                    <li class="flex items-start"><span class="mr-2 text-purple-500">✓</span> Ad Platform Validation</li>
                    <li class="flex items-start"><span class="mr-2 text-purple-500">✓</span> Commerce CAPEX Approval</li>
                    <li class="flex items-start"><span class="mr-2 text-purple-500">✓</span> Series B ($20M-$40M)</li>
                </ul>
            </div>

            <div class="glass-card p-6 rounded-xl border-t-4 border-pink-500">
                <h3 class="text-2xl font-bold text-white mb-1">Years 4-5</h3>
                <p class="text-pink-400 font-semibold mb-4">Dominance & Exit</p>
                <ul class="space-y-2 text-sm text-slate-300">
                    <li class="flex items-start"><span class="mr-2 text-pink-500">✓</span> Industry OS Status</li>
                    <li class="flex items-start"><span class="mr-2 text-pink-500">✓</span> Booking GMV > $5M</li>
                    <li class="flex items-start"><span class="mr-2 text-pink-500">✓</span> In-House DTG Fulfillment</li>
                    <li class="flex items-start"><span class="mr-2 text-pink-500">✓</span> Acquisition / IPO Ready</li>
                </ul>
            </div>
        </div>

        <div class="glass-card rounded-2xl p-6">
            <h3 class="text-xl font-semibold text-white mb-4">Projected Valuation Trajectory</h3>
            <div class="chart-container" style="height: 300px;">
                <canvas id="valuationChart"></canvas>
            </div>
        </div>
    </section>

    <footer class="border-t border-slate-800 pt-8 mt-12 text-center">
        <p class="text-slate-500 text-sm">
            NextGen Noise Financial & Legal Strategy • Prepared by Bernard • 2025
        </p>
    </footer>

</main>

<script>
    let incomeChartInstance = null;
    const TIER_PRICES = {
        artistPro: 199, artistEnt: 999,
        labelPro: 2500, labelEnt: 5000,
        stationPro: 1800, stationEnt: 3500,
        venuePro: 999, venueEnt: 2000
    };

    function wrapLabel(str, maxChars) {
        if (str.length <= maxChars) return str;
        const words = str.split(' ');
        const lines = [];
        let currentLine = words[0];

        for (let i = 1; i < words.length; i++) {
            if (currentLine.length + 1 + words[i].length <= maxChars) {
                currentLine += ' ' + words[i];
            } else {
                lines.push(currentLine);
                currentLine = words[i];
            }
        }
        lines.push(currentLine);
        return lines;
    }

    function formatCurrency(value) {
        return '$' + Math.round(value).toLocaleString();
    }

    function calculateProjection() {
        // Retrieve necessary inputs for the single scenario AND ARR calculation
        const POOL_SIZE = parseFloat(document.getElementById('poolSize').value) || 0;
        const ARTIST_EQS_SHARE_PERCENT = parseFloat(document.getElementById('artistEQSShare').value) / 100 || 0;

        // Scenario Drivers (Reintroduced)
        const ARTIST_MERCH_UNITS = parseFloat(document.getElementById('artistMerchUnits').value) || 0;
        const LABEL_COMMISSION_RATE = parseFloat(document.getElementById('labelCommissionRate').value) / 100 || 0;
        const LABEL_SUB_COST = parseFloat(document.getElementById('labelSubCost').value) || 0;
        const STATION_AD_SPOTS = parseFloat(document.getElementById('stationAdSpots').value) || 0;
        const VENUE_BOOKINGS = parseFloat(document.getElementById('venueBookings').value) || 0;


        // --- FIXED SCENARIO PARAMETERS ---
        const ARTIST_MERCH_NET_PROFIT = 15.00;
        const ARTIST_PPV_EVENT_REVENUE = 5000;
        const ARTIST_PPV_NET_SPLIT = 0.40;
        const STATION_AD_GROSS_PRICE = 500.00;
        const STATION_AD_SPLIT = 0.80;
        const VENUE_BOOKING_AVG = 2000.00;
        const VENUE_NGN_COMMISSION_RATE = 0.10;
        const STATION_SUB_COST = 1800.00; // FIX: Define the missing variable here


        // --- ARR CALCULATION (NGN Operating Capital) ---
        let totalARR = 0;
        totalARR += (parseFloat(document.getElementById('artistProCount').value) || 0) * TIER_PRICES.artistPro;
        totalARR += (parseFloat(document.getElementById('artistEntCount').value) || 0) * TIER_PRICES.artistEnt;
        totalARR += (parseFloat(document.getElementById('labelProCount').value) || 0) * TIER_PRICES.labelPro;
        totalARR += (parseFloat(document.getElementById('labelEntCount').value) || 0) * TIER_PRICES.labelEnt;
        totalARR += (parseFloat(document.getElementById('stationProCount').value) || 0) * TIER_PRICES.stationPro;
        totalARR += (parseFloat(document.getElementById('stationEntCount').value) || 0) * TIER_PRICES.stationEnt;
        totalARR += (parseFloat(document.getElementById('venueProCount').value) || 0) * TIER_PRICES.venuePro;
        totalARR += (parseFloat(document.getElementById('venueEntCount').value) || 0) * TIER_PRICES.venueEnt;

        // 1. ARTIST INCOME CALCULATION
        // NOTE: Assumes the scenario artist is FREE tier (S_EQS = 1.0) for a neutral example
        const ARTIST_EQS_TOTAL = (POOL_SIZE * ARTIST_EQS_SHARE_PERCENT * 12);
        const ARTIST_MERCH_TOTAL = ARTIST_MERCH_UNITS * ARTIST_MERCH_NET_PROFIT;
        const ARTIST_PPV_TOTAL = (ARTIST_PPV_EVENT_REVENUE * (1 - VENUE_NGN_COMMISSION_RATE) * ARTIST_PPV_NET_SPLIT);

        const ARTIST_PAYOUT_TO_LABEL = ARTIST_EQS_TOTAL * LABEL_COMMISSION_RATE;
        const ARTIST_NET_INCOME = ARTIST_EQS_TOTAL + ARTIST_MERCH_TOTAL + ARTIST_PPV_TOTAL - ARTIST_PAYOUT_TO_LABEL;


        // 2. LABEL INCOME CALCULATION
        // NOTE: Assumes the scenario label is using the input LABEL_SUB_COST
        const LABEL_COMMISSION_COLLECTED = ARTIST_PAYOUT_TO_LABEL;
        const LABEL_MERCH_TOTAL = 500 * ARTIST_MERCH_NET_PROFIT; // Fixed 500 units for label scenario
        const LABEL_NET_INCOME = LABEL_COMMISSION_COLLECTED + LABEL_MERCH_TOTAL - LABEL_SUB_COST;

        // 3. VENUE INCOME CALCULATION
        const VENUE_PPV_INCOME = (ARTIST_PPV_EVENT_REVENUE * (1 - VENUE_NGN_COMMISSION_RATE) * (1 - ARTIST_PPV_NET_SPLIT));
        const VENUE_BOOKING_INCOME = VENUE_BOOKINGS * VENUE_BOOKING_AVG * VENUE_NGN_COMMISSION_RATE;

        const VENUE_NET_INCOME = VENUE_PPV_INCOME + VENUE_BOOKING_INCOME + 500; // +$500 assumed sponsorship

        // 4. STATION INCOME CALCULATION
        const STATION_AD_GROSS = STATION_AD_SPOTS * STATION_AD_GROSS_PRICE;
        const STATION_AD_INCOME = STATION_AD_GROSS * STATION_AD_SPLIT;
        const STATION_NET_INCOME = STATION_AD_INCOME - STATION_SUB_COST + 500; // +$500 estimated authority share


        // --- UPDATE UI ---
        document.getElementById('artistIncome').textContent = formatCurrency(ARTIST_NET_INCOME);
        document.getElementById('labelIncome').textContent = formatCurrency(LABEL_NET_INCOME);
        document.getElementById('venueIncome').textContent = formatCurrency(VENUE_NET_INCOME);
        document.getElementById('stationIncome').textContent = formatCurrency(STATION_NET_INCOME);
        document.getElementById('totalB2BARR').textContent = formatCurrency(totalARR);

        // --- UPDATE CHART ---
        if (incomeChartInstance) {
            incomeChartInstance.data.datasets[0].data = [
                ARTIST_NET_INCOME,
                LABEL_NET_INCOME,
                VENUE_NET_INCOME,
                STATION_NET_INCOME
            ];
            incomeChartInstance.update();
        }
    }


    window.onload = function() {
        // Initial Chart Definitions
        const eqsCtx = document.getElementById('eqsChart').getContext('2d');
        new Chart(eqsCtx, {
            type: 'doughnut',
            data: {
                labels: wrapLabel('Unique Shares (High Value), Unique Comments (Med Value), Verified Views (Low Value)', 20),
                datasets: [{
                    data: [5.0, 3.0, 0.1],
                    backgroundColor: ['#06b6d4', '#8b5cf6', '#334155'],
                    borderColor: '#0f172a',
                    borderWidth: 2,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: { position: 'bottom', labels: { color: '#94a3b8', font: { family: 'Inter' } } },
                    tooltip: {
                        callbacks: {
                            title: function(tooltipItems) {
                                const item = tooltipItems[0];
                                let label = item.chart.data.labels[item.dataIndex];
                                return Array.isArray(label) ? label.join(' ') : label;
                            },
                            label: function(context) { return ` Weight Factor: ${context.raw}`; }
                        },
                        backgroundColor: 'rgba(15, 23, 42, 0.9)', titleColor: '#fff', bodyColor: '#cbd5e1', borderColor: '#334155', borderWidth: 1
                    }
                }
            }
        });

        const tevsCtx = document.getElementById('tevsChart').getContext('2d');
        new Chart(tevsCtx, {
            type: 'pie',
            data: {
                labels: wrapLabel('Internal Activity (UGC/Views), External SMR Signals (Radio), Transaction Value (GMV)', 20),
                datasets: [{
                    data: [40, 40, 20],
                    backgroundColor: ['#ec4899', '#f59e0b', '#10b981'],
                    borderColor: '#0f172a',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { color: '#94a3b8', font: { family: 'Inter' } } },
                    tooltip: {
                        callbacks: {
                            title: function(tooltipItems) {
                                const item = tooltipItems[0];
                                let label = item.chart.data.labels[item.dataIndex];
                                return Array.isArray(label) ? label.join(' ') : label;
                            },
                            label: function(context) { return ` Impact: ${context.raw}%`; }
                        },
                        backgroundColor: 'rgba(15, 23, 42, 0.9)', borderColor: '#334155', borderWidth: 1
                    }
                }
            }
        });

        const valCtx = document.getElementById('valuationChart').getContext('2d');
        new Chart(valCtx, {
            type: 'line',
            data: {
                labels: ['Seed (Now)', 'Series A (Year 1)', 'Series B (Year 3)', 'Exit Target (Year 5)'],
                datasets: [{
                    label: 'Target Valuation ($M)',
                    data: [1, 10, 40, 100],
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 3,
                    tension: 0.3,
                    fill: true,
                    pointBackgroundColor: '#0f172a', pointBorderColor: '#10b981', pointBorderWidth: 2, pointRadius: 6, pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true, grid: { color: '#334155' },
                        ticks: { color: '#94a3b8', callback: function(value) { return '$' + value + 'M'; } }
                    },
                    x: { grid: { color: '#334155' }, ticks: { color: '#94a3b8' } }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: function(tooltipItems) {
                                const item = tooltipItems[0];
                                let label = item.chart.data.labels[item.dataIndex];
                                return Array.isArray(label) ? label.join(' ') : label;
                            },
                            label: function(context) { return ` Target Valuation: $${context.raw} Million`; }
                        },
                        backgroundColor: 'rgba(15, 23, 42, 0.9)', borderColor: '#334155', borderWidth: 1
                    }
                }
            }
        });

        // Initial Bar Chart setup
        const incomeCtx = document.getElementById('incomeChart').getContext('2d');
        incomeChartInstance = new Chart(incomeCtx, {
            type: 'bar',
            data: {
                labels: ['Artist (Aura Vox)', 'Label (Nexus Recs)', 'Venue (Phoenix)', 'Station (KUOS)'],
                datasets: [{
                    label: 'Projected Net Income (Year 1)',
                    data: [28800, 4900, 3300, 2700],
                    backgroundColor: ['rgba(6, 182, 212, 0.8)', 'rgba(139, 92, 246, 0.8)', 'rgba(236, 72, 153, 0.8)', 'rgba(245, 158, 11, 0.8)'],
                    borderColor: ['#06b6d4', '#8b5cf6', '#ec4899', '#f59e0b'],
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true, grid: { color: '#334155' },
                        ticks: { color: '#94a3b8', callback: function(value) { return '$' + value.toLocaleString(); } }
                    },
                    x: { grid: { display: false }, ticks: { color: '#e2e8f0', font: { weight: 'bold' } } }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: function(tooltipItems) {
                                const item = tooltipItems[0];
                                let label = item.chart.data.labels[item.dataIndex];
                                return Array.isArray(label) ? label.join(' ') : label;
                            },
                            label: function(context) { return ` Net Income: $${context.raw.toLocaleString()}`; }
                        },
                        backgroundColor: 'rgba(15, 23, 42, 0.9)', borderColor: '#334155', borderWidth: 1
                    }
                }
            }
        });

        // Run initial projection with defaults
        calculateProjection();
    };
</script>
</body>
</html>