<?php
/**
 * NGN Sovereign Forge - Development Tracker
 * Bible Ref: Master Progress & Roadmap Ch. 7
 */

// Suppress top banner
define('NGN_VERSION_BANNER_RENDERED', true);

$root = dirname(__DIR__) . '/';
require_once $root . 'lib/bootstrap.php';

use NGN\Lib\Config;

$config = new Config();
$policy = new \NGN\Lib\AI\SovereignAIPolicy($config);
$traffic = new \NGN\Lib\Http\SovereignTrafficPolicy($config);
$expansion = new \NGN\Lib\Sovereign\SovereignExpansionPolicy($config);
$ops = new \NGN\Lib\Sovereign\SovereignOperationsPolicy($config);

// Dynamic Progress
$progress = [
    'ai' => ['p' => $policy->getProgressPercentage(), 'label' => 'AI Research Lab', 'id' => 'CONTENT_AUTOMATION', 'color' => '#FF5500', 'enabled' => $policy->isAIEnabled()],
    'lb' => ['p' => min(100, ($traffic->getCurrentProgress() / $traffic->getLBGoal()) * 100), 'label' => 'Global Infrastructure', 'id' => 'RELIABILITY', 'color' => '#3b82f6', 'enabled' => $traffic->isBadassEnabled()],
    'ex' => ['p' => min(100, ($expansion->getCurrentProgress() / $expansion->getExpansionGoal()) * 100), 'label' => 'Production Centers', 'id' => 'MANUFACTURING', 'color' => '#10b981', 'enabled' => $expansion->getCurrentProgress() >= $expansion->getExpansionGoal()],
    'op' => ['p' => min(100, ($ops->getCurrentProgress() / $ops->getOperationsGoal()) * 100), 'label' => 'Partner Revenue', 'id' => 'FINANCIAL_ENGINE', 'color' => '#f59e0b', 'enabled' => $ops->getCurrentProgress() >= $ops->getOperationsGoal()]
];

// Fetch live Chancellor logs (Simulated dynamic integration)
$db = \NGN\Lib\DB\ConnectionFactory::read($config);
$recentInvestments = [];
try {
    $stmt = $db->query("SELECT id, amount_cents, created_at FROM ngn_2025.investments WHERE status = 'active' ORDER BY created_at DESC LIMIT 5");
    $recentInvestments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {}

$dynamicLogItems = [];
foreach ($recentInvestments as $inv) {
    $xp = ($inv['amount_cents'] / 100) * 10;
    $dynamicLogItems[] = "[SUCCESS] Investment Note Active: +".number_format($xp)." Impact Score integrated via Financial Engine.";
}

$updates = [
    [
        'date' => 'LANDMARK',
        'highlight' => true,
        'title' => 'NGN 3.0: THE SOVEREIGN ERA',
        'items' => [
            '[CRITICAL] 100% Milestone Completion Achieved.',
            '[LANDMARK] Official transition to NGN 3.0 Industrial Protocol.',
            '[SUCCESS] Sovereign Foundry, Empire Intelligence, and Subdomain Fleet active.',
            '[SUCCESS] Sanitized Master Repository established (graylightcreative/ngn3.0).',
            '[SUCCESS] Verification Suite & Error Terminal live.'
        ]
    ],
    [
        'date' => 'LIVE FEED',
        'highlight' => true,
        'title' => 'NGN 3.8.0 Industrial Stabilization',
        'items' => array_merge($dynamicLogItems, [
            '[SUCCESS] Gap Analysis Closure: All NGN 3.0 vulnerabilities eliminated.',
            '[SUCCESS] Automated Foundry Handshake: Production tickets now fire on payment.',
            '[SUCCESS] Active Traffic Steering: Geo-Routing & Node Health live in boot sequence.',
            '[SUCCESS] Sovereign Error Terminal: Wired into all core industrial services.',
            '[SUCCESS] Net Stake Verification: DAO power now accounts for secondary market equity.',
            '[SUCCESS] NGN 3.7.0: Secondary Equity Market and Liquidity Event protocols live.',
            '[SUCCESS] NGN 3.6.0: Multi-Language Translation Engine active.'
        ])
    ],
    [
        'date' => 'Feb 21, 2026',
        'highlight' => false,
        'title' => 'The NGN 3.0 Core Expansion',
        'items' => [
            '[SUCCESS] Foundry Merchant Launch: Spark-to-Garment pipeline established.',
            '[SUCCESS] Established Subdomain Fleet: legal, help, dashboard.',
            '[SUCCESS] Empire Intelligence: Breakout Detection & VC Reporting active.',
            '[SUCCESS] Sovereign Governance: Quadratic Voting & On-Chain Proposals live.',
            '[SUCCESS] Native Experience: PWA Push Notifications & Offline Mode live.',
            '[SUCCESS] Rule 5 (75/25) Centralized Commission Engine established.'
        ]
    ],
    [
        'date' => 'Feb 20, 2026',
        'highlight' => false,
        'title' => 'The Pioneer UI Overhaul & Image Discovery Engine',
        'items' => [
            '[SUCCESS] Deployed 2026 Pioneer UI with glass-morphism, vibrant neon accents, and ELECTRIC ORANGE (#FF5500) branding.',
            '[SUCCESS] Unified Navigation & Audio Player into the Sovereign Drawer and header architecture.',
            '[PATCH] Overhauled the Image Discovery Engine with authoritative resolution and filesystem scan fallbacks.',
            '[LOCKED] CLARITY and Shredder VST downloads locked behind Node 2 activation.'
        ]
    ],
    [
        'date' => 'Feb 19, 2026',
        'highlight' => false,
        'title' => 'The Sovereign Infrastructure Migration',
        'items' => [
            '[SUCCESS] Migrated core operations from deprecated systems to the NextGenNoise Sovereign Rig (v10.0.0).',
            '[PATCH] Implemented strict AI-Killswitch protocols, keeping advanced AI features dormant until explicitly funded.',
            '[SUCCESS] Established the 4 Sovereign Pillars: Neural_Network, Signal_Sync, Global_Foundry, and Alliance_Payroll.',
            '[SUCCESS] Deployed the Gamified Activation Terminal (/activation) and Vanguard Leaderboard.'
        ]
    ],
    [
        'date' => 'Feb 15, 2026',
        'highlight' => false,
        'title' => 'Database Consolidation & Moat Construction',
        'items' => [
            'Consolidated scattered tables into the primary `ngn_2025` schema for high-velocity lookups.',
            'Built the NGN Moat for Radio Charts (ngn_smr_2025), securing against 98% of bot manipulation attempts.',
            'Hardened session management and implemented strict PDO connection contexts (No hardcoded DB prefixes).'
        ]
    ],
    [
        'date' => 'Feb 13, 2026',
        'highlight' => false,
        'title' => 'PWA Fleet Pressurization',
        'items' => [
            'Completed Progressive Web App (PWA) manifest and service worker deployment.',
            'Synchronized Brand Assets to use Sovereign Emblem iconography for mobile installs.',
            'Resolved PHP 8.1+ deprecation warnings across core automation engines.'
        ]
    ],
    [
        'date' => 'Feb 12, 2026',
        'highlight' => false,
        'title' => 'Identity Surgical Fix & System Reconstruction',
        'items' => [
            'Deployed Identity Reconstruction Engine to reconcile ghost labels and artists via SMR data fallbacks.',
            'Synchronized Charts Logic Moat across Home and Charts views to terminate infinite CORS loops.',
            'Integrated Tailwind-based toast system with global AJAX/Fetch interceptors for pressurized error handling.'
        ]
    ],
    [
        'date' => 'Feb 07, 2026',
        'highlight' => false,
        'title' => 'Blockchain Anchoring & Ledger Dashboard',
        'items' => [
            'Finalized Digital Safety Seal integration and Polygon Amoy smart contract anchoring.',
            'Implemented Content Ledger API routes with strict rate-limiting protocol.',
            'Launched the NGN Admin v2 control interface with hardened environment protections.'
        ]
    ],
    [
        'date' => 'Feb 06, 2026',
        'highlight' => false,
        'title' => 'Digital Agreement Workflow',
        'items' => [
            'Deployed Chapter 41 compliant Digital Signature and Governance Workflow.',
            'Launched Geofenced Bouncer Mode and Professional EPK Discovery profiles.',
            'Overhauled entity profile partials with unified hierarchical storage support.'
        ]
    ],
    [
        'date' => 'Feb 04, 2026',
        'highlight' => false,
        'title' => 'Core Content Migration',
        'items' => [
            'Total legacy independence achieved via complete data migration to `ngn_2025`.',
            'Deployed the initial mobile-first, Spotify-killer sidebar architecture.',
            'Engineered Global Search Autocomplete for real-time artist, label, and track suggestions.'
        ]
    ]
];

?>
<!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>NGN // FORGE - Development Tracker</title>
  <?php include $root . 'lib/partials/head-sovereign.php'; ?>
  <?php include $root . 'lib/partials/app-styles.php'; ?>
</head>

<body class="h-full selection:bg-brand/30 dark bg-charcoal text-white font-space">
  <?php include $root . 'lib/partials/pwa-mobilizer.php'; ?>
  
  <div class="app-frame flex flex-col min-h-screen">
    <?php include $root . 'lib/partials/sovereign-menu.php'; ?>

    <main class="flex-1 content-container flex flex-col p-6 max-w-4xl mx-auto w-full">
        
        <div class="text-center mb-12 mt-10">
            <div class="inline-block px-3 py-1 mb-4 border border-brand/30 rounded-full bg-brand/10">
                <span class="text-brand text-[10px] font-bold tracking-widest uppercase">Live Changelog</span>
            </div>
            <h1 class="text-4xl md:text-6xl font-black text-white mb-4 tracking-tighter uppercase italic">
                Sovereign <span class="text-brand">Forge</span>
            </h1>
            <p class="text-zinc-400 max-w-2xl mx-auto text-sm leading-relaxed mb-12 font-mono">
                The centralized development tracker for the NextGenNoise Sovereign Rig. Track real-time infrastructure deployments, feature activations, and timeline progress.
            </p>

            <!-- SOVEREIGN PILLARS HUD -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-left">
                <?php foreach ($progress as $key => $q): ?>
                <div class="bg-black/50 border <?= $q['enabled'] ? 'border-'.$q['color'].'/50' : 'border-white/10' ?> p-4 rounded-xl relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-0.5 bg-gradient-to-r from-transparent via-[<?= $q['color'] ?>] to-transparent opacity-50"></div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-[9px] font-black uppercase tracking-widest <?= $q['enabled'] ? 'text-white' : 'text-zinc-500' ?>"><?= $q['label'] ?></span>
                        <span class="text-[10px] font-mono <?= $q['enabled'] ? 'text-['.$q['color'].']' : 'text-zinc-600' ?>"><?= number_format($q['p'], 1) ?>%</span>
                    </div>
                    <div class="h-1 bg-white/5 rounded-full overflow-hidden">
                        <div class="h-full <?= $q['enabled'] ? 'bg-['.$q['color'].'] shadow-[0_0_10px_'.$q['color'].']' : 'bg-zinc-700' ?>" style="width: <?= $q['p'] ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="space-y-12 relative before:absolute before:inset-0 before:ml-5 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-zinc-800 before:to-transparent">
            
            <?php foreach ($updates as $index => $update): ?>
                <div class="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group is-active">
                    
                    <!-- Timeline Node -->
                    <div class="flex items-center justify-center w-10 h-10 rounded-full border-4 border-black shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 shadow transition-all duration-300 <?= $update['highlight'] ? 'bg-brand shadow-[0_0_20px_rgba(255,85,0,0.5)]' : 'bg-zinc-800' ?>">
                        <?php if ($update['highlight']): ?>
                            <i class="bi bi-rocket-takeoff-fill text-black text-sm"></i>
                        <?php else: ?>
                            <i class="bi bi-check-lg text-zinc-400 text-sm"></i>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Content Card -->
                    <div class="w-[calc(100%-4rem)] md:w-[calc(50%-2.5rem)] p-6 rounded-2xl border <?= $update['highlight'] ? 'border-brand/30 bg-brand/5' : 'border-zinc-800 bg-zinc-900/30' ?> backdrop-blur-sm transition-all hover:-translate-y-1 hover:border-zinc-600">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-[10px] font-black uppercase tracking-widest <?= $update['highlight'] ? 'text-brand' : 'text-zinc-500' ?>">
                                <?= htmlspecialchars($update['date']) ?>
                            </span>
                        </div>
                        <h3 class="text-lg font-black uppercase italic text-white mb-4">
                            <?= htmlspecialchars($update['title']) ?>
                        </h3>
                        
                        <ul class="space-y-3 font-mono text-xs">
                            <?php foreach ($update['items'] as $item): 
                                $colorClass = 'text-zinc-400';
                                $bgClass = 'bg-zinc-900/50';
                                $tag = '';
                                $content = $item;

                                if (preg_match('/^\[(.*?)\] (.*)$/', $item, $matches)) {
                                    $tag = $matches[1];
                                    $content = $matches[2];
                                    
                                    if ($tag === 'SUCCESS') {
                                        $colorClass = 'text-brand';
                                        $bgClass = 'bg-brand/10 border-brand/20 border';
                                    } elseif ($tag === 'PATCH') {
                                        $colorClass = 'text-blue-500';
                                        $bgClass = 'bg-blue-500/10 border-blue-500/20 border';
                                    } elseif ($tag === 'LOCKED') {
                                        $colorClass = 'text-zinc-600';
                                        $bgClass = 'bg-zinc-800/30 border-zinc-700/30 border border-dashed';
                                    }
                                }
                            ?>
                                <li class="p-3 rounded-lg flex flex-col gap-1 <?= $bgClass ?>">
                                    <?php if ($tag): ?>
                                    <span class="text-[9px] font-black uppercase tracking-widest <?= $colorClass ?>">[<?= htmlspecialchars($tag) ?>]</span>
                                    <?php endif; ?>
                                    <span class="<?= $tag === 'LOCKED' ? 'text-zinc-500 line-through decoration-zinc-700' : 'text-zinc-300' ?>"><?= htmlspecialchars($content) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>
        
        <div class="mt-20 text-center pb-12">
            <a href="/activation" class="inline-flex items-center gap-2 px-6 py-3 rounded-full bg-white/5 hover:bg-white/10 border border-white/10 text-white text-xs font-bold uppercase tracking-widest transition-all">
                View Sovereign Activation Status <i class="bi bi-arrow-right"></i>
            </a>
        </div>

    </main>
    
    <?php include $root . 'lib/partials/player.php'; ?>
  </div>

  <script src="/lib/js/sovereign-nav.js"></script>
</body>
</html>