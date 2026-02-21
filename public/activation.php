<?php
/**
 * NGN Sovereign Activation Terminal v3.0
 * Aesthetic: Cyberpunk RPG / Tactical HUD / Gamified Progression
 */
require_once __DIR__ . '/../lib/bootstrap.php';

$config = new \NGN\Lib\Config();
$pColor = \NGN\Lib\Env::get('THEME_COLOR_PRIMARY', '#FF5F1F');

define('DEFAULT_AVATAR', '/lib/images/site/2026/default-avatar.png');
$root = __DIR__ . '/../';

$policy = new \NGN\Lib\AI\SovereignAIPolicy($config);
$traffic = new \NGN\Lib\Http\SovereignTrafficPolicy($config);
$expansion = new \NGN\Lib\Sovereign\SovereignExpansionPolicy($config);
$ops = new \NGN\Lib\Sovereign\SovereignOperationsPolicy($config);

// Dynamic Progress
$progress = [
    'ai' => ['p' => $policy->getProgressPercentage(), 'label' => 'AI Research Lab', 'id' => 'CONTENT_AUTOMATION', 'color' => $pColor, 'tip' => 'Automated intelligence for content generation and market analysis.'],
    'lb' => ['p' => min(100, ($traffic->getCurrentProgress() / $traffic->getLBGoal()) * 100), 'label' => 'Global Infrastructure', 'id' => 'RELIABILITY', 'color' => '#6366f1', 'tip' => 'Global high-speed network ensuring 99.9% uptime for all partners.'],
    'ex' => ['p' => min(100, ($expansion->getCurrentProgress() / $expansion->getExpansionGoal()) * 100), 'label' => 'Production Centers', 'id' => 'MANUFACTURING', 'color' => '#10b981', 'tip' => 'Physical and digital hubs for large-scale content production.'],
    'op' => ['p' => min(100, ($ops->getCurrentProgress() / $ops->getOperationsGoal()) * 100), 'label' => 'Partner Revenue', 'id' => 'FINANCIAL_ENGINE', 'color' => '#f59e0b', 'tip' => 'The core payout system distributing quarterly returns to our partners.']
];

$contributors = $policy->getContributorCount();
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NGN // INVESTMENT IMPACT</title>
    <?php include $root . 'lib/partials/head-sovereign.php'; ?>
    <?php include $root . 'lib/partials/app-styles.php'; ?>
    <style>
        .activation-bg { 
            background-color: var(--charcoal); 
            background-image: 
                radial-gradient(circle at 50% 50%, rgba(255,95,31,0.05) 0%, transparent 70%),
                linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
            background-size: 100% 100%, 40px 40px, 40px 40px;
        }
        .stat-value { font-family: 'JetBrains Mono', monospace; }
        .glitch-text:hover { animation: glitch 0.3s cubic-bezier(.25,.46,.45,.94) both infinite; }
        @keyframes glitch {
            0% { transform: translate(0); }
            20% { transform: translate(-2px, 2px); }
            40% { transform: translate(-2px, -2px); }
            60% { transform: translate(2px, 2px); }
            80% { transform: translate(2px, -2px); }
            100% { transform: translate(0); }
        }
        .xp-bar { height: 6px; background: rgba(255,255,255,0.05); border-radius: 10px; overflow: hidden; position: relative; }
        .xp-progress { height: 100%; transition: width 1.5s ease-out; position: relative; }
        .xp-progress::after {
            content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            animation: scan 2s linear infinite;
        }
        @keyframes scan { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }
    </style>
</head>
<body class="selection:bg-brand/30 dark bg-charcoal text-white font-space">

<?php include $root . 'lib/partials/pwa-mobilizer.php'; ?>

<div class="app-frame flex flex-col min-h-screen activation-bg">
    <?php include $root . 'lib/partials/sovereign-menu.php'; ?>

    <!-- TOP MISSION HUD -->
    <nav class="px-6 h-20 flex justify-between items-center border-b border-white/10 bg-black/80 backdrop-blur-xl sticky top-0 z-40 w-full">
        <div class="flex items-center gap-4">
            <a href="/" class="flex items-center gap-3 group shrink-0">
                <div style="background-color: #FF5F1F; color: #000;" class="w-8 h-8 rounded flex items-center justify-center group-hover:rotate-90 transition-transform">
                    <i class="bi bi-crosshair"></i>
                </div>
                <span class="text-xs font-black uppercase tracking-[0.4em] hidden sm:block">Impact Dashboard</span>
                <span class="text-xs font-black uppercase tracking-[0.4em] sm:hidden">Impact</span>
            </a>
            <div class="hidden md:flex items-center gap-4 px-4 py-1.5 bg-white/5 rounded-md border border-white/5">
                <span class="text-[9px] font-black text-zinc-500 uppercase tracking-widest">Active Partners</span>
                <span class="stat-value text-brand font-black text-xs"><?= number_format($contributors) ?></span>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <div class="text-right hidden lg:block">
                <div class="text-[8px] font-black text-zinc-500 uppercase tracking-widest">Network Status</div>
                <div class="text-[10px] font-black text-emerald-500 uppercase italic">SYSTEM_ONLINE</div>
            </div>
            <a href="/register/artist" class="hidden sm:inline-block px-6 py-2 bg-white text-black font-black text-[10px] uppercase tracking-widest rounded hover:bg-brand transition-colors">Become a Partner</a>
            
            <button onclick="toggleSovereignMenu()" class="w-9 h-9 rounded-full bg-white/5 flex items-center justify-center text-zinc-400 hover:text-white transition-all flex-shrink-0 ml-2">
                <i class="bi bi-three-dots-vertical text-xl"></i>
            </button>
        </div>
    </nav>

    <main class="flex-1 content-container flex flex-col px-6 py-12 max-w-7xl mx-auto w-full">
        <!-- HERO TITLE -->
        <header class="mb-24 relative">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-8 mb-12 border-b border-white/5 pb-12">
                <div class="max-w-2xl">
                    <h4 class="text-brand font-black uppercase tracking-[0.5em] text-[10px] mb-4 flex items-center gap-3">
                        <span class="w-2 h-2 bg-brand rounded-full animate-ping"></span>
                        Current_Objective: Full_System_Activation
                    </h4>
                    <h1 class="text-5xl sm:text-6xl md:text-8xl font-black tracking-tighter leading-[0.9] uppercase italic mb-6">
                        Unlock_The <br><span class="text-brand">Empire.</span>
                    </h1>
                    <p class="text-base sm:text-lg text-zinc-400 font-medium leading-relaxed italic font-mono">
                        "The autonomous future is locked. Human collective funding is the only key to ignition."
                    </p>
                </div>
                <div class="flex flex-col gap-4">
                    <a href="#quests" style="background-color: #FF5F1F; color: #000;" class="px-8 py-4 sm:px-10 sm:py-5 font-black uppercase tracking-[0.2em] text-xs text-center shadow-[0_0_30px_rgba(255,95,31,0.3)] hover:scale-105 transition-all">Start_Contribution</a>
                    <div class="text-[9px] font-black text-center text-zinc-600 uppercase tracking-[0.3em]">Difficulty: Sovereign</div>
                </div>
            </div>
        </header>

        <!-- QUEST GRID -->
        <section id="quests" class="mb-24 sm:mb-32">
            <div class="flex items-center gap-4 mb-8 sm:mb-12">
                <h2 class="text-xl sm:text-2xl font-black uppercase italic tracking-tighter">Main_Quests</h2>
                <div class="h-px flex-1 bg-white/10"></div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php foreach ($progress as $key => $q): ?>
                <div class="glass-panel p-6 sm:p-8 flex flex-col min-h-[350px] sm:min-h-[400px] group hover:border-<?= $key === 'ai' ? 'brand' : ($key === 'lb' ? 'indigo-500' : ($key === 'ex' ? 'emerald-500' : 'amber-500')) ?>/50 transition-all">
                    <div class="flex justify-between items-start mb-8 sm:mb-12">
                        <div class="w-10 h-10 sm:w-12 sm:h-12 rounded bg-white/5 border border-white/10 flex items-center justify-center text-xl sm:text-2xl" style="color: <?= $q['color'] ?>;">
                            <i class="bi bi-<?= $key === 'ai' ? 'cpu' : ($key === 'lb' ? 'hdd-network' : ($key === 'ex' ? 'buildings-fill' : 'cash-stack')) ?>"></i>
                        </div>
                        <div class="text-right">
                            <div class="text-[8px] font-black text-zinc-600 uppercase tracking-widest mb-1">Quest_ID</div>
                            <div class="stat-value text-[10px] font-bold text-white"><?= $q['id'] ?></div>
                        </div>
                    </div>

                    <div class="flex-1">
                        <h3 class="text-xl sm:text-2xl font-black uppercase italic mb-3 sm:mb-4 glitch-text"><?= $q['label'] ?></h3>
                        <p class="text-[11px] sm:text-xs text-zinc-300 leading-relaxed font-bold">
                            <?php if($key === 'ai') echo "UNLEASH THE SUPER-PRODUCER: Activating NIKO, the world's first AI industry analyst. It writes bios, creates marketing copy, and analyzes trends 24/7 to make our partners famous."; ?>
                            <?php if($key === 'lb') echo "THE UNSTOPPABLE BROADCAST: Deploying a global 'bulletproof' network. This ensures your music is NEVER offline and reaches every corner of the earth with zero delay."; ?>
                            <?php if($key === 'ex') echo "THE PHYSICAL FACTORY: Turning digital hits into real-world cash. We are building automated labs to manufacture high-end merch and physical media instantly for our top partners."; ?>
                            <?php if($key === 'op') echo "THE WEALTH ENGINE: A fully automated $1M payout system. No banks, no middle-men. When you earn money, you get paid instantly and securely."; ?>
                        </p>
                    </div>

                    <div class="mt-8 sm:mt-12">
                        <div class="flex justify-between items-end mb-3">
                            <span class="text-[9px] font-black text-zinc-600 uppercase">Synchronization</span>
                            <span class="stat-value text-xs sm:text-sm font-black" style="color: <?= $q['color'] ?>;"><?= number_format($q['p'], 1) ?>%</span>
                        </div>
                        <div class="xp-bar border border-white/5">
                            <div class="xp-progress" style="width: <?= $q['p'] ?>%; background-color: <?= $q['color'] ?>;"></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- THE BOSS BATTLE (Math Breakdown) -->
        <section class="mb-24 sm:mb-32">
            <div class="glass-panel p-6 sm:p-12 bg-black/40 border-brand/20 relative overflow-hidden">
                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-brand to-transparent"></div>
                
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-16 items-center relative z-10">
                    <div class="lg:col-span-5">
                        <h2 class="text-3xl sm:text-4xl font-black uppercase italic tracking-tighter mb-6 sm:mb-8 leading-none">The_Growth <br><span class="text-brand">Formula.</span></h2>
                        <p class="text-sm sm:text-base text-zinc-300 mb-8 sm:mb-10 leading-relaxed font-bold">To achieve total independence, we have calculated the exact funding required to automate the music industry. This is not a donation; it is a strategic investment in a high-tech future where creators keep 100% of their value.</p>
                        
                        <div class="space-y-4 sm:space-y-6 mb-8 sm:mb-12">
                            <div class="flex items-center gap-4 px-4 py-3 sm:px-6 sm:py-4 bg-white/5 rounded-xl border border-white/5">
                                <i class="bi bi-shield-check text-brand text-lg"></i>
                                <div>
                                    <div class="text-[9px] sm:text-[10px] font-black uppercase tracking-widest text-zinc-500">Protection_Status</div>
                                    <div class="text-xs sm:text-sm font-bold uppercase tracking-tight">Data Rights & IP Security Active</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 px-4 py-3 sm:px-6 sm:py-4 bg-white/5 rounded-xl border border-white/5">
                                <i class="bi bi-currency-dollar text-emerald-500 text-lg"></i>
                                <div>
                                    <div class="text-[9px] sm:text-[10px] font-black uppercase tracking-widest text-zinc-500">Revenue_Status</div>
                                    <div class="text-xs sm:text-sm font-bold uppercase tracking-tight">Instant Cash Payouts at 100% Fund</div>
                                </div>
                            </div>
                        </div>

                        <a href="/invest" style="background-color: <?= $pColor ?>; color: #000;" class="inline-block px-8 py-4 sm:px-12 sm:py-6 font-black uppercase tracking-[0.2em] text-[10px] sm:text-xs text-center w-full sm:w-auto hover:scale-105 transition-all brand-glow">
                            POWER_THE_FUTURE
                        </a>
                    </div>

                    <div class="lg:col-span-7 space-y-8 sm:space-y-12">
                        <!-- AI Math Row -->
                        <div class="flex flex-col sm:flex-row sm:items-center gap-4 sm:gap-8 border-b border-white/5 pb-6 sm:pb-8">
                            <div class="w-24 stat-value text-xl sm:text-2xl font-black text-brand">$100K</div>
                            <div class="flex-1">
                                <h5 class="text-[9px] sm:text-[10px] font-black uppercase tracking-[0.3em] text-zinc-500 mb-2 font-mono">Unlock // AI Research Lab</h5>
                                <p class="text-[11px] sm:text-xs text-zinc-200 font-bold">"Deploying the world's most advanced AI writing staff to handle all artist marketing and SEO automatically."</p>
                            </div>
                        </div>
                        <!-- LB Math Row -->
                        <div class="flex flex-col sm:flex-row sm:items-center gap-4 sm:gap-8 border-b border-white/5 pb-6 sm:pb-8">
                            <div class="w-24 stat-value text-xl sm:text-2xl font-black text-indigo-500">$250K</div>
                            <div class="flex-1">
                                <h5 class="text-[9px] sm:text-[10px] font-black uppercase tracking-[0.3em] text-zinc-500 mb-2 font-mono">Unlock // Global Infrastructure</h5>
                                <p class="text-[11px] sm:text-xs text-zinc-200 font-bold">"Securing high-speed global servers so your music is always online and un-hackable."</p>
                            </div>
                        </div>
                        <!-- Foundry Math Row -->
                        <div class="flex flex-col sm:flex-row sm:items-center gap-4 sm:gap-8 border-b border-white/5 pb-6 sm:pb-8">
                            <div class="w-24 stat-value text-xl sm:text-2xl font-black text-emerald-500">$500K</div>
                            <div class="flex-1">
                                <h5 class="text-[9px] sm:text-[10px] font-black uppercase tracking-[0.3em] text-zinc-500 mb-2 font-mono">Unlock // Production Centers</h5>
                                <p class="text-[11px] sm:text-xs text-zinc-200 font-bold">"Building physical manufacturing centers to print merch and vinyl for artists on demand."</p>
                            </div>
                        </div>
                        <!-- Ops Math Row -->
                        <div class="flex flex-col sm:flex-row sm:items-center gap-4 sm:gap-8">
                            <div class="w-24 stat-value text-xl sm:text-2xl font-black text-amber-500">$1.0M</div>
                            <div class="flex-1">
                                <h5 class="text-[9px] sm:text-[10px] font-black uppercase tracking-[0.3em] text-zinc-500 mb-2 font-mono">Unlock // Partner Revenue Fund</h5>
                                <p class="text-[11px] sm:text-xs text-zinc-200 font-bold">"Ensuring $1,000,000 in liquid reserves to guarantee instant payouts for every dollar you earn."</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- VANGUARD LEADERBOARD -->
        <?php include $root . 'lib/partials/vanguard-leaderboard.php'; ?>

        <!-- ONBOARDING HUB -->
        <section class="text-center bg-zinc-900/30 px-6 py-16 sm:py-24 rounded-[2rem] sm:rounded-[4rem] border border-dashed border-white/10 mt-24">
            <h2 class="text-3xl sm:text-4xl md:text-6xl font-black mb-6 sm:mb-8 italic uppercase tracking-tighter">Level_Up_The_Signal</h2>
            <p class="text-zinc-500 max-w-xl mx-auto mb-12 sm:mb-16 text-sm sm:text-lg font-medium leading-relaxed">Every artist that joins and every fan that contributes provides the XP required to reach the next milestone.</p>
            
            <div class="flex flex-wrap justify-center gap-6 sm:gap-8">
                <div class="flex flex-col items-center gap-3 sm:gap-4">
                    <a href="/register/artist" class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center text-2xl sm:text-3xl hover:bg-brand hover:text-black transition-all group">
                        <i class="bi bi-mic group-hover:scale-110 transition-transform"></i>
                    </a>
                    <span class="text-[9px] sm:text-[10px] font-black uppercase tracking-widest text-zinc-600">Register_Artist</span>
                </div>
                <div class="flex flex-col items-center gap-3 sm:gap-4">
                    <a href="/invest" class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center text-2xl sm:text-3xl hover:bg-emerald-500 hover:text-black transition-all group">
                        <i class="bi bi-lightning-charge group-hover:scale-110 transition-transform"></i>
                    </a>
                    <span class="text-[9px] sm:text-[10px] font-black uppercase tracking-widest text-zinc-600">Invest_XP</span>
                </div>
                <div class="flex flex-col items-center gap-3 sm:gap-4">
                    <a href="/advertisers" class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center text-2xl sm:text-3xl hover:bg-indigo-500 hover:text-black transition-all group">
                        <i class="bi bi-broadcast group-hover:scale-110 transition-transform"></i>
                    </a>
                    <span class="text-[9px] sm:text-[10px] font-black uppercase tracking-widest text-zinc-600">Partner_Signal</span>
                </div>
            </div>
        </section>
    </main>

    <!-- FOOTER -->
    <footer class="py-16 sm:py-20 text-center border-t border-white/5 bg-surface mt-16 sm:mt-32 relative">
        <div class="absolute inset-0 bg-mesh opacity-20 pointer-events-none"></div>
        <div class="relative z-10">
            <img src="/lib/images/site/2026/NGN-Logo-Full-Light.png" class="h-5 sm:h-6 mx-auto mb-6 sm:mb-8 opacity-20" alt="NGN">
            <p class="text-zinc-700 text-[9px] sm:text-[10px] font-black uppercase tracking-[0.5em] px-4">System_Version: 3.0.1 // Environment: Sovereign</p>
        </div>
    </footer>
    
    <?php include $root . 'lib/partials/player.php'; ?>
</div>

<script src="/lib/js/sovereign-nav.js"></script>
</body>
</html>