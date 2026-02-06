<?php
/**
 * Station Profile Partial - Modern 2.0
 */
$station = $entity;
$stationName = $station['name'] ?? 'Unknown Station';
$stationSlug = $station['slug'] ?? '';
$isClaimed = !empty($station['user_id']); // Stations use user_id for ownership in core schema

// Robust Image Detection
$stationImg = DEFAULT_AVATAR;
if (!empty($station['image_url'])) {
    if (str_starts_with($station['image_url'], 'http') || str_starts_with($station['image_url'], '/')) {
        $stationImg = $station['image_url'];
    } else {
        $paths = [
            "/uploads/stations/{$stationSlug}/{$station['image_url']}",
            "/uploads/users/{$stationSlug}/{$station['image_url']}"
        ];
        foreach ($paths as $p) {
            if (file_exists(dirname(__DIR__, 3) . '/public' . $p)) {
                $stationImg = $p;
                break;
            }
        }
    }
}

$bio = $station['bio'] ?? '';
$scores = $station['scores'] ?? ['Score' => 0];
?>

<div class="station-profile">
    <!-- HERO HEADER -->
    <div class="relative -mt-8 -mx-8 mb-12 h-[450px] flex items-end overflow-hidden group">
        <!-- Immersive Background -->
        <div class="absolute inset-0 bg-gradient-to-br from-emerald-600/40 to-black z-0 transition-transform duration-1000 group-hover:scale-105"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-transparent z-10"></div>
        
        <!-- Animated Broadcast Signal -->
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-0 opacity-10 pointer-events-none">
            <i class="bi bi-broadcast text-[400px] text-emerald-500 animate-pulse"></i>
        </div>

        <div class="relative z-20 p-12 flex flex-col md:flex-row items-center md:items-end gap-10 w-full">
            <!-- Station Logo -->
            <div class="w-48 h-48 md:w-64 md:h-64 flex-shrink-0 shadow-[0_20px_50px_rgba(0,0,0,0.5)] rounded-2xl overflow-hidden bg-zinc-900 border-4 border-white/5 group-hover:scale-[1.02] transition-transform duration-500 relative">
                <img src="<?= htmlspecialchars($stationImg) ?>" class="w-full h-full object-cover" alt="<?= htmlspecialchars($stationName) ?>" onerror="this.src='<?= DEFAULT_AVATAR ?>'">
                <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent flex items-end p-4">
                    <div class="text-xs font-black text-emerald-400 uppercase tracking-widest"><?= htmlspecialchars($station['call_sign'] ?? 'AIR') ?></div>
                </div>
            </div>
            
            <!-- Info -->
            <div class="flex-1 text-center md:text-left">
                <div class="flex items-center justify-center md:justify-start gap-2 mb-4">
                    <div class="flex items-center gap-2 px-3 py-1 bg-red-600 text-white text-[10px] font-black uppercase tracking-[0.2em] rounded-full animate-pulse">
                        <span class="w-2 h-2 bg-white rounded-full"></span> On Air
                    </div>
                    <?php if ($isClaimed): ?>
                        <span class="px-3 py-1 bg-white/10 text-white text-[10px] font-black uppercase tracking-[0.2em] rounded-full border border-white/10">Verified Network</span>
                    <?php else: ?>
                        <a href="/claim-profile.php?slug=<?= urlencode($stationSlug) ?>" class="px-3 py-1 bg-emerald-500/20 text-emerald-400 text-[10px] font-black uppercase tracking-[0.2em] rounded-full border border-emerald-500/20 hover:bg-emerald-500 hover:text-black transition-all">Claim Station</a>
                    <?php endif; ?>
                </div>
                <h1 class="text-5xl lg:text-8xl font-black mb-6 tracking-tighter leading-none text-white drop-shadow-2xl"><?= htmlspecialchars($stationName) ?></h1>
                <div class="flex flex-wrap items-center justify-center md:justify-start gap-6 text-sm font-black uppercase tracking-widest text-zinc-400">
                    <div class="flex items-center gap-2"><i class="bi bi-broadcast text-emerald-400"></i> Score: <?= number_format($scores['Score'] ?? 0) ?></div>
                    <?php if (!empty($station['format'])): ?>
                        <div class="flex items-center gap-2"><i class="bi-music-note-beamed text-emerald-400"></i> <?= htmlspecialchars($station['format']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ACTION BAR -->
    <div class="flex flex-wrap items-center gap-6 mb-16 px-4">
        <button data-play-track 
                data-track-url="<?= htmlspecialchars($station['stream_url'] ?? '') ?>" 
                data-track-title="<?= htmlspecialchars($stationName) ?>" 
                data-track-artist="Live Broadcast" 
                data-track-art="<?= htmlspecialchars($stationImg) ?>"
                class="px-12 py-5 bg-emerald-500 text-black font-black rounded-full hover:scale-105 transition-all shadow-xl shadow-emerald-500/20 uppercase tracking-widest text-sm flex items-center gap-3">
            <i class="bi bi-play-fill text-2xl"></i> Listen Live
        </button>
        <button class="w-14 h-14 rounded-full border-2 border-zinc-800 text-white flex items-center justify-center hover:border-white transition-all text-xl"><i class="bi-heart"></i></button>
        <button class="w-14 h-14 rounded-full border-2 border-zinc-800 text-white flex items-center justify-center hover:border-white transition-all text-xl"><i class="bi-share"></i></button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 px-4">
        <div class="lg:col-span-8 space-y-20">
            
            <!-- Live Rotation -->
            <section>
                <div class="flex items-center justify-between mb-8">
                    <h2 class="text-3xl font-black tracking-tight text-white">Live Rotation</h2>
                </div>
                
                <?php if (!empty($station['smr_rankings'])): ?>
                    <div class="bg-zinc-900/30 rounded-3xl border border-white/5 overflow-hidden">
                        <div class="divide-y divide-white/5">
                            <?php foreach (array_slice($station['smr_rankings'], 0, 10) as $spin): ?>
                            <div class="flex items-center gap-6 p-6 hover:bg-white/5 transition-all group">
                                <div class="w-12 h-12 rounded-lg overflow-hidden bg-zinc-800 flex-shrink-0 relative">
                                    <i class="bi-music-note-beamed absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 text-zinc-700"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="font-black text-lg truncate text-white group-hover:text-emerald-400 transition-colors"><?= htmlspecialchars($spin['Song'] ?? 'Unknown Track') ?></div>
                                    <div class="text-[10px] font-black text-zinc-500 uppercase tracking-widest mt-1"><?= htmlspecialchars($spin['Artist'] ?? 'Unknown Artist') ?></div>
                                </div>
                                <div class="text-xs font-mono text-zinc-600 font-bold"><?= !empty($spin['chart_date']) ? date('H:i', strtotime($spin['chart_date'])) : '' ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <?php render_profile_upsell("Radio Airplay", "Connect your station's automation or log spins manually. Your station's data powers the NGN Regional and Format charts.", $isClaimed, $stationSlug); ?>
                <?php endif; ?>
            </section>

            <!-- Latest Posts -->
            <section>
                <h2 class="text-3xl font-black mb-8 tracking-tight">Station News</h2>
                <?php if (!empty($entity['posts'])): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <?php foreach ($entity['posts'] as $post): ?>
                        <?php 
                            $postImg = $post['featured_image_url'] ?? DEFAULT_AVATAR;
                            if ($postImg && !str_starts_with($postImg, 'http') && !str_starts_with($postImg, '/')) {
                                $postImg = "/uploads/{$postImg}";
                            }
                        ?>
                        <a href="/post/<?= htmlspecialchars($post['slug'] ?? $post['id']) ?>" class="group flex flex-col bg-zinc-900/30 rounded-2xl overflow-hidden border border-white/5 hover:border-emerald-500 transition-all">
                            <div class="aspect-video relative overflow-hidden">
                                <img src="<?= htmlspecialchars($postImg) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" onerror="this.src='<?= DEFAULT_AVATAR ?>'">
                            </div>
                            <div class="p-6">
                                <div class="font-black text-lg text-white mb-2 leading-tight"><?= htmlspecialchars($post['title']) ?></div>
                                <div class="text-[10px] font-black text-zinc-500 uppercase tracking-widest"><?= date('M j, Y', strtotime($post['published_at'])) ?></div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <?php render_profile_upsell("Station Announcements", "Share program schedules, DJ lineups, and local rock news with your listeners.", $isClaimed, $stationSlug); ?>
                <?php endif; ?>
            </section>

        </div>

        <!-- Sidebar Info -->
        <div class="lg:col-span-4 space-y-12">
            <!-- About -->
            <section class="sp-card border border-white/5 p-8">
                <h2 class="text-xs font-black text-zinc-500 uppercase tracking-[0.3em] mb-6">Profile</h2>
                <div class="prose prose-invert prose-sm text-zinc-400 font-medium leading-relaxed mb-8">
                    <?= !empty($bio) ? nl2br($bio) : 'Broadcasting the best in underground rock and metal. Part of the NGN verified station network.' ?>
                </div>
                <div class="pt-6 border-t border-white/5">
                    <div class="text-[10px] font-black text-zinc-600 uppercase tracking-widest mb-4">Location</div>
                    <div class="flex items-center gap-3 text-white font-bold">
                        <i class="bi-geo-alt-fill text-emerald-500"></i>
                        <?= htmlspecialchars($station['city'] ?? 'Global') ?><?= !empty($station['region']) ? ', ' . htmlspecialchars($station['region']) : '' ?>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>