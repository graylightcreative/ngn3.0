<?php
// NGN Community Funding Calculator & Proposal Page
// Visual Overhaul: Cyberpunk Financial OS Theme

// Headers
$now = gmdate('D, d M Y H:i:s') . ' GMT';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// --- FIXED LEGAL/FINANCIAL PARAMETERS ---
$baseApy = 0.08; // 8% Annual Interest Rate (Fixed)
$termYears = 5;
$investorPerk = 'NGN Elite-Host Access + AI Mix Feedback Tool for 5 years.';
$defaultInvestment = 2500;
$minimumInvestment = 50;

// Fixed Allocation Splits
$allocation = [
        ['label' => 'PLN Content Pool (Licensing)', 'percent' => 40, 'impact' => 'Secures licenses for 500+ new indie tracks and invests directly into BFL 0.1 Global Compliance.', 'color' => 'bg-emerald-500'],
        ['label' => 'AI Writers & B2B Tooling', 'percent' => 30, 'impact' => 'Accelerates launch of AI Mix Feedback & Ad Copy Generator. Funds 500 AI Sparks for artists.', 'color' => 'bg-purple-600'],
        ['label' => 'Streaming Infra & CDN', 'percent' => 20, 'impact' => 'Funds global CDN expansion to handle PPV Livestreams and increased Station capacity.', 'color' => 'bg-blue-500'],
        ['label' => 'Operational Runway', 'percent' => 10, 'impact' => 'Secures the team and core platform stability (BFL 1.1) to ensure the 5-year project term.', 'color' => 'bg-neutral-600'],
];

// --- CORE CALCULATOR LOGIC ---
if (!function_exists('calculate_fixed_note_payout')) {
    function calculate_fixed_note_payout($principal, $apy, $term) {
        $principal = max($principal, 0);
        $interest = $principal * $apy * $term;
        $totalReturn = $principal + $interest;
        $quarterlyPayout = ($interest / $term) / 4;
        return [
                'principal' => $principal,
                'interest' => round($interest, 2),
                'totalReturn' => round($totalReturn, 2),
                'quarterlyPayout' => round($quarterlyPayout, 2),
        ];
    }
}

$initialAmount = isset($_GET['amount']) ? max((int)$_GET['amount'], $minimumInvestment) : $defaultInvestment;
$roiData = calculate_fixed_note_payout($initialAmount, $baseApy, $termYears);

// Financial Forecast KPIs
$forecast = [
        ['metric' => 'Active B2B Users', 'baseline' => '100 (Pilot)', 'target' => '5,000+ (Labels/Stations)'],
        ['metric' => 'Annual Booking GMV', 'baseline' => '$0', 'target' => '$5M+ (Tours Epic)'],
        ['metric' => 'Commerce Margin', 'baseline' => '40% (Dropship)', 'target' => '60%+ (In-House DTG)'],
        ['metric' => 'Target Exit Valuation', 'baseline' => '$5M - $10M', 'target' => '$100M+ (IPO Readiness)'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no" />
    <title>NGN Community Capital — Fixed-Term Note</title>
    <meta name="robots" content="noindex, follow" />

    <!-- Fonts & Tailwind -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #000000;
            color: #e5e5e5;
            -webkit-tap-highlight-color: transparent;
        }
        h1, h2, h3, h4, .brand-font {
            font-family: 'Orbitron', sans-serif;
        }
        /* Neon Utilities */
        .neon-text-green { color: #39ff14; text-shadow: 0 0 10px rgba(57, 255, 20, 0.6); }
        .neon-text-blue { color: #00ffff; text-shadow: 0 0 10px rgba(0, 255, 255, 0.6); }
        .neon-text-purple { color: #d946ef; text-shadow: 0 0 10px rgba(217, 70, 239, 0.6); }

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
            transform: translateY(-2px);
            border-color: #404040;
            box-shadow: 0 10px 30px -10px rgba(0, 255, 0, 0.05);
        }

        /* Form Inputs */
        .input-cyber {
            background-color: #171717;
            border: 1px solid #333;
            color: #fff;
            transition: all 0.2s;
        }
        .input-cyber:focus {
            border-color: #39ff14;
            box-shadow: 0 0 10px rgba(57, 255, 20, 0.2);
            outline: none;
        }

        /* Mobile Menu */
        #mobile-menu {
            transition: max-height 0.3s ease-in-out, opacity 0.3s ease-in-out;
            max-height: 0;
            opacity: 0;
            overflow: hidden;
        }
        #mobile-menu.open {
            max-height: 400px;
            opacity: 1;
        }

        @media print {
            .no-print { display: none !important; }
            body { background: white; color: black; }
            .card-bg { border: 1px solid #ccc; box-shadow: none; }
            .text-white { color: black !important; }
            .text-gray-400 { color: #555 !important; }
        }
    </style>
</head>
<body class="cyber-bg min-h-screen pb-12">

<!-- NAVIGATION -->
<nav class="sticky top-0 z-50 bg-black/95 backdrop-blur-md border-b border-neutral-800 no-print">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-20">
            <!-- Logo -->
            <div class="flex-shrink-0">
                <a href="https://nextgennoise.com/investors.php" class="text-2xl font-black text-white brand-font tracking-tighter hover:opacity-80 transition-opacity">
                    NGN <span class="text-emerald-500">INVESTOR</span>
                </a>
            </div>

            <!-- Desktop Menu -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="https://nextgennoise.com/business-plan.php" class="text-xs font-bold uppercase tracking-widest text-neutral-400 hover:text-white transition-colors">Business Plan</a>
                <a href="https://nextgennoise.com/invest/pitch.php" class="text-xs font-bold uppercase tracking-widest text-neutral-400 hover:text-white transition-colors">Pitch Deck</a>
                <span class="bg-emerald-900/30 border border-emerald-500/30 text-emerald-400 text-xs font-bold uppercase tracking-widest px-4 py-2 rounded">
                    Funding Active
                </span>
            </div>

            <!-- Mobile Hamburger -->
            <div class="md:hidden">
                <button id="mobile-menu-btn" class="text-white hover:text-emerald-400 focus:outline-none p-2">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Menu -->
    <div id="mobile-menu" class="md:hidden bg-neutral-900 border-b border-neutral-800">
        <div class="px-4 pt-2 pb-6 space-y-2">
            <a href="https://nextgennoise.com/business-plan.php" class="block px-3 py-4 rounded-md text-sm font-bold uppercase tracking-widest text-white hover:bg-neutral-800 border-b border-neutral-800">Business Plan</a>
            <a href="https://nextgennoise.com/investors.php" class="block px-3 py-4 rounded-md text-sm font-bold uppercase tracking-widest text-white hover:bg-neutral-800 border-b border-neutral-800">Pitch Deck</a>
            <div class="block px-3 py-4 text-emerald-400 font-bold uppercase text-xs tracking-widest">
                ● Funding Active
            </div>
        </div>
    </div>
</nav>

<main class="py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- TITLE BLOCK -->
        <div class="text-center mb-12">
            <div class="inline-block px-3 py-1 mb-4 border border-purple-500/30 rounded-full bg-purple-900/10">
                <span class="text-purple-400 text-xs font-bold tracking-widest uppercase">Interest-Bearing Note</span>
            </div>
            <h1 class="text-4xl md:text-6xl font-black text-white mb-4 tracking-tight">
                NGN COMMUNITY <span class="neon-text-green">CAPITAL</span>
            </h1>
            <p class="text-neutral-400 max-w-2xl mx-auto text-sm md:text-base">
                **Legal Note:** This is a **Simple Promissory Note (Debt)**, not equity. It provides a fixed **<?= $baseApy * 100 ?>% APY** interest rate, payable over the 5-year term.
            </p>
        </div>

        <div class="lg:grid lg:grid-cols-3 gap-10">

            <!-- COLUMN 1: CALCULATOR (STICKY) -->
            <div id="calculator-column" class="lg:col-span-1 space-y-6 no-print">
                <div class="card-bg p-6 rounded-2xl sticky lg:top-24 border-t-4 border-emerald-500 shadow-2xl">
                    <h3 class="text-xl font-bold text-white brand-font mb-6 flex items-center gap-2">
                        <span class="text-emerald-400">⚡</span> Payout Calculator
                    </h3>

                    <form id="investment-form" class="space-y-6">
                        <div>
                            <label for="investment-amount" class="block text-xs font-bold text-neutral-400 uppercase tracking-widest mb-2">
                                Investment Principal (Min $<?= $minimumInvestment ?>)
                            </label>
                            <div class="relative">
                                <span class="absolute left-4 top-3 text-neutral-500">$</span>
                                <input type="number" id="investment-amount" name="amount" value="<?= $initialAmount ?>" min="<?= $minimumInvestment ?>" step="50" required
                                       class="input-cyber w-full pl-8 p-3 rounded-lg text-lg font-mono font-bold">
                            </div>
                        </div>

                        <div class="bg-black/50 p-4 rounded-xl border border-neutral-800 space-y-4">
                            <div class="flex justify-between items-center pb-4 border-b border-neutral-800">
                                <span class="text-sm text-neutral-400">Principal Return</span>
                                <span id="principal-return" class="font-mono text-white">$<?= number_format($initialAmount, 0) ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-neutral-400">Total Interest (<?= $baseApy * 100 ?>%)</span>
                                <span id="total-interest" class="font-mono text-emerald-400 font-bold">+$<?= number_format($roiData['interest'], 0) ?></span>
                            </div>
                            <div class="flex justify-between items-center pt-2">
                                <span class="text-sm text-purple-400">Quarterly Payout</span>
                                <span id="quarterly-payout" class="font-mono text-purple-400 font-bold">$<?= number_format($roiData['quarterlyPayout'], 0) ?></span>
                            </div>
                        </div>

                        <div class="bg-emerald-900/20 p-4 rounded-xl border border-emerald-500/30 text-center">
                            <span class="block text-xs text-emerald-400 uppercase tracking-widest mb-1">Total 5-Year Return</span>
                            <span id="total-return" class="block text-3xl font-black text-white brand-font">$<?= number_format($roiData['totalReturn'], 0) ?></span>
                        </div>
                    </form>

                    <div class="mt-8 space-y-3">
                        <button id="invest-button" class="w-full py-4 bg-gradient-to-r from-emerald-600 to-emerald-500 hover:from-emerald-500 hover:to-emerald-400 text-white font-bold rounded-lg uppercase tracking-widest text-sm shadow-[0_0_20px_rgba(16,185,129,0.4)] transition-all transform hover:scale-105">
                            Secure Promissory Note
                        </button>
                        <button id="print-proposal-button" class="w-full py-3 bg-neutral-800 hover:bg-neutral-700 text-neutral-300 font-bold rounded-lg uppercase tracking-widest text-xs border border-neutral-700 transition-colors">
                            Download PDF Proposal
                        </button>
                    </div>
                    <p class="text-[10px] text-neutral-600 text-center mt-4">
                        Payments processed securely via Stripe Connect.
                    </p>
                </div>
            </div>

            <!-- COLUMN 2 & 3: PROPOSAL DETAILS -->
            <div class="lg:col-span-2 space-y-8 print-area">

                <!-- 1. Allocation -->
                <div class="card-bg p-8 rounded-2xl">
                    <h3 class="text-2xl font-bold text-white mb-6 brand-font">
                        Capital Allocation Strategy
                    </h3>
                    <p class="text-neutral-400 mb-8 text-sm leading-relaxed">
                        Your investment of <strong class="text-white">$<span id="allocation-amount-display"><?= number_format($initialAmount, 0) ?></span></strong> is strictly allocated to the following high-growth initiatives. We prioritize revenue-generating compliance and tech.
                    </p>

                    <div class="space-y-6">
                        <?php foreach ($allocation as $item): ?>
                            <div class="bg-neutral-900/50 p-4 rounded-xl border border-neutral-800 hover:border-neutral-700 transition-colors">
                                <div class="flex justify-between items-center mb-2">
                                    <h4 class="font-bold text-white text-sm"><?= htmlspecialchars($item['label']) ?></h4>
                                    <span class="text-xs font-mono font-bold text-white bg-neutral-800 px-2 py-1 rounded"><?= $item['percent'] ?>%</span>
                                </div>
                                <!-- Progress Bar -->
                                <div class="w-full bg-black rounded-full h-2 mb-3">
                                    <div class="<?= $item['color'] ?> h-2 rounded-full" style="width: <?= $item['percent'] ?>%"></div>
                                </div>
                                <p class="text-xs text-neutral-500">
                                    <span class="text-emerald-500 font-bold">Impact:</span> <?= htmlspecialchars($item['impact']) ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-8 p-4 bg-purple-900/10 border border-purple-500/20 rounded-xl flex items-start gap-4">
                        <div class="text-2xl">🎁</div>
                        <div>
                            <h4 class="text-sm font-bold text-purple-400 uppercase tracking-widest mb-1">Investor Perk Unlocked</h4>
                            <p class="text-neutral-300 text-sm"><?= htmlspecialchars($investorPerk) ?></p>
                        </div>
                    </div>
                </div>

                <!-- 2. Forecast Table -->
                <div class="card-bg p-8 rounded-2xl">
                    <h3 class="text-2xl font-bold text-white mb-6 brand-font">
                        5-Year Growth Forecast
                    </h3>
                    <p class="text-neutral-400 mb-6 text-sm">
                        Repayment is secured by hitting these conservative revenue milestones.
                    </p>

                    <div class="overflow-x-auto rounded-xl border border-neutral-800">
                        <table class="min-w-full divide-y divide-neutral-800">
                            <thead class="bg-neutral-900">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-bold text-neutral-500 uppercase tracking-wider">Metric</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-neutral-500 uppercase tracking-wider">Current Baseline</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-emerald-500 uppercase tracking-wider">Y5 Target</th>
                            </tr>
                            </thead>
                            <tbody class="bg-black/50 divide-y divide-neutral-800">
                            <?php foreach ($forecast as $item): ?>
                                <tr class="hover:bg-neutral-900/50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-white"><?= htmlspecialchars($item['metric']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500"><?= htmlspecialchars($item['baseline']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-emerald-400 font-bold"><?= htmlspecialchars($item['target']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 3. Risk & Governance -->
                <div class="card-bg p-8 rounded-2xl">
                    <h3 class="text-2xl font-bold text-white mb-6 brand-font">
                        Risk & Governance
                    </h3>
                    <div class="grid md:grid-cols-2 gap-6">
                        <div class="p-4 rounded-xl border border-neutral-800 bg-neutral-900/30">
                            <h4 class="font-bold text-white mb-2 flex items-center gap-2">
                                <span>⚖️</span> Fiduciary Accounts
                            </h4>
                            <p class="text-xs text-neutral-400">
                                Creator royalty pools are held in legally separate bank accounts, isolated from operational burn.
                            </p>
                        </div>
                        <div class="p-4 rounded-xl border border-neutral-800 bg-neutral-900/30">
                            <h4 class="font-bold text-white mb-2 flex items-center gap-2">
                                <span>📋</span> External Audits
                            </h4>
                            <p class="text-xs text-neutral-400">
                                We mandate an annual third-party Royalty System Audit (BFL 2.4) to validate payout integrity.
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 p-4 rounded-xl border border-red-900/30 bg-red-900/10">
                        <h4 class="text-xs font-bold text-red-400 uppercase tracking-widest mb-2">Risk Disclosure</h4>
                        <p class="text-[10px] text-neutral-500 leading-relaxed">
                            NGN is a development-stage company. Investing carries risk. While the Note carries a fixed <?= $baseApy * 100 ?>% APY, repayment depends on future solvency. This is debt, not equity. By proceeding, you acknowledge these risks.
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</main>

<!-- FOOTER -->
<footer class="py-8 text-center border-t border-neutral-900 text-xs text-neutral-600 no-print">
    <p class="mb-2">© <?= date('Y') ?> NextGenNoise. All rights reserved.</p>
    <p>Columbus, OH | Building the Future of Rock</p>
</footer>

<!-- JAVASCRIPT LOGIC -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Mobile Menu ---
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        mobileMenuBtn.addEventListener('click', () => {
            mobileMenu.classList.toggle('open');
        });

        // --- Calculator Variables ---
        const baseApy = <?= $baseApy ?>;
        const term = <?= $termYears ?>;
        const minimumInvestment = <?= $minimumInvestment ?>;

        // Elements
        const amountInput = document.getElementById('investment-amount');
        const principalReturn = document.getElementById('principal-return');
        const totalInterest = document.getElementById('total-interest');
        const quarterlyPayout = document.getElementById('quarterly-payout');
        const totalReturn = document.getElementById('total-return');
        const allocationAmountDisplay = document.getElementById('allocation-amount-display');
        const investButton = document.getElementById('invest-button');
        const printButton = document.getElementById('print-proposal-button');

        // Formatter
        const formatter = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 0, maximumFractionDigits: 0 });

        // Logic
        function calculateROI(principal) {
            // Ensure min investment logic in calc (visual only, real validation on input blur)
            const safePrincipal = Math.max(principal, 0);
            const interest = safePrincipal * baseApy * term;
            const totalReturnVal = safePrincipal + interest;
            const quarterlyPayoutVal = (interest / term) / 4;

            return {
                principal: safePrincipal,
                interest: interest,
                totalReturn: totalReturnVal,
                quarterlyPayout: quarterlyPayoutVal
            };
        }

        function updateCalculator() {
            let amount = parseFloat(amountInput.value) || 0;
            const roi = calculateROI(amount);

            principalReturn.textContent = formatter.format(roi.principal);
            totalInterest.textContent = '+' + formatter.format(roi.interest);
            quarterlyPayout.textContent = formatter.format(roi.quarterlyPayout);
            totalReturn.textContent = formatter.format(roi.totalReturn);
            allocationAmountDisplay.textContent = formatter.format(roi.principal).replace('$', '').trim();
        }

        // Event Listeners
        amountInput.addEventListener('input', updateCalculator);

        amountInput.addEventListener('blur', function() {
            let amount = parseInt(this.value) || 0;
            if (amount < minimumInvestment) {
                this.value = minimumInvestment;
                updateCalculator();
            }
        });

        printButton.addEventListener('click', function() {
            window.print();
        });

        investButton.addEventListener('click', function(e) {
            e.preventDefault();
            let amount = parseInt(amountInput.value) || minimumInvestment;
            if (amount < minimumInvestment) {
                alert('Minimum investment is $' + minimumInvestment);
                amountInput.value = minimumInvestment;
                return;
            }

            const email = window.prompt('Enter your email to secure your Promissory Note allocation:');
            if (!email) return;

            this.disabled = true;
            this.textContent = 'Processing...';
            this.classList.remove('from-emerald-600', 'to-emerald-500');
            this.classList.add('bg-neutral-600', 'cursor-not-allowed');

            // Simulate API call for demo purposes (Replace with actual fetch in production)
            setTimeout(() => {
                const redirectUrl = `https://nextgennoise.com/api/v1/investments/checkout?amount=${amount}&email=${encodeURIComponent(email)}`;
                // In a real app, you'd POST to an endpoint that returns a Stripe URL.
                // For this static/demo file, we'll alert the action.
                alert(`Redirecting to Stripe for $${amount} investment...`);
                this.disabled = false;
                this.textContent = 'Secure Promissory Note';
                this.classList.add('from-emerald-600', 'to-emerald-500');
                this.classList.remove('bg-neutral-600', 'cursor-not-allowed');
                // window.location.href = redirectUrl;
            }, 1000);
        });

        // Init
        updateCalculator();
    });
</script>
</body>
</html>