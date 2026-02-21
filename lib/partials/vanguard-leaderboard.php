<?php
/**
 * Vanguard Leaderboard Partial
 * Gamified High-Score table for Sovereign Contributors
 */

// MOCK DATA FOR PROTOTYPE (To be wired to `user_badges` and `sovereign_fund` later)
$vanguardLeaders = [
    [
        'name' => 'Heroes and Villains',
        'avatar' => '/lib/images/users/heroes-and-villains/PFP.jpg',
        'xp' => 12500,
        'badge' => 'Sovereign Architect',
        'color' => '#FF5F1F', // Brand Orange
        'rank' => 1
    ],
    [
        'name' => 'The Rage Online',
        'avatar' => '/lib/images/users/the-rage-online/The Rage Slash Bitly.png',
        'xp' => 8400,
        'badge' => 'Empire Builder',
        'color' => '#f59e0b', // Gold/Amber
        'rank' => 2
    ],
    [
        'name' => 'Coldwards',
        'avatar' => '/lib/images/users/coldwards/coldward-logo-1.jpg',
        'xp' => 5200,
        'badge' => 'Empire Builder',
        'color' => '#f59e0b',
        'rank' => 3
    ],
    [
        'name' => 'The Sound 228',
        'avatar' => '/lib/images/users/the-sound-228/IMG_0136.jpeg',
        'xp' => 3100,
        'badge' => 'Network Node',
        'color' => '#94a3b8', // Silver/Zinc
        'rank' => 4
    ],
    [
        'name' => 'Ghost_Protocol_09',
        'avatar' => DEFAULT_AVATAR,
        'xp' => 1500,
        'badge' => 'Signal Booster',
        'color' => '#b45309', // Bronze
        'rank' => 5
    ]
];
?>

<section class="mb-32">
    <div class="flex items-center gap-4 mb-12 px-2">
        <h2 class="text-2xl font-black uppercase italic tracking-tighter">Vanguard_Leaderboard</h2>
        <div class="h-px flex-1 bg-white/10"></div>
    </div>

    <div class="glass-panel p-8 md:p-12 border-white/5 relative overflow-hidden">
        <!-- Grid Background -->
        <div class="absolute inset-0 bg-mesh opacity-10 pointer-events-none"></div>
        
        <div class="relative z-10">
            <div class="flex justify-between items-end mb-8 border-b border-white/10 pb-4">
                <div class="text-[10px] font-black uppercase tracking-widest text-zinc-500">Top_Contributors</div>
                <div class="text-[10px] font-black uppercase tracking-widest text-zinc-500">Total_XP_Generated</div>
            </div>

            <div class="space-y-4">
                <?php foreach ($vanguardLeaders as $leader): ?>
                <div class="flex items-center justify-between p-4 bg-black/40 rounded-2xl border border-white/5 hover:border-white/20 transition-all group relative overflow-hidden">
                    <!-- Rank Highlight -->
                    <?php if ($leader['rank'] === 1): ?>
                        <div class="absolute left-0 top-0 bottom-0 w-1 bg-brand shadow-[0_0_15px_#FF5F1F]"></div>
                    <?php endif; ?>

                    <div class="flex items-center gap-6 z-10">
                        <div class="w-8 text-center text-xl font-black text-zinc-600 group-hover:text-white transition-colors font-mono">
                            #<?= $leader['rank'] ?>
                        </div>
                        <div class="w-12 h-12 rounded-xl overflow-hidden border border-white/10 shrink-0">
                            <img src="<?= htmlspecialchars($leader['avatar']) ?>" class="w-full h-full object-cover" onerror="this.src='<?= DEFAULT_AVATAR ?>'">
                        </div>
                        <div>
                            <div class="font-black text-white uppercase tracking-tight text-sm md:text-base group-hover:text-brand transition-colors"><?= htmlspecialchars($leader['name']) ?></div>
                            <div class="flex items-center gap-2 mt-1">
                                <i class="bi bi-shield-fill text-[10px]" style="color: <?= $leader['color'] ?>;"></i>
                                <span class="text-[9px] font-black uppercase tracking-widest" style="color: <?= $leader['color'] ?>;"><?= $leader['badge'] ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="text-right z-10">
                        <div class="stat-value text-xl md:text-2xl font-black text-white"><?= number_format($leader['xp']) ?> <span class="text-[10px] text-brand">XP</span></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Join the Fight CTA -->
            <div class="mt-12 text-center">
                <p class="text-sm text-zinc-400 font-medium mb-6">Want to see your name on the board? Secure your rank and unlock exclusive Sovereign Badges.</p>
                <a href="/invest" style="background-color: #FF5F1F; color: #000;" class="inline-block px-8 py-3 font-black uppercase tracking-widest text-[10px] rounded-full hover:scale-105 transition-all shadow-[0_0_20px_rgba(255,95,31,0.2)]">
                    Generate_XP
                </a>
            </div>
        </div>
    </div>
</section>
