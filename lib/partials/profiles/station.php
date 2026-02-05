<?php
/**
 * Station Profile Partial - Modern 2.0
 */
$station = $entity;
$stationName = $station['name'] ?? 'Unknown Station';
$stationImg = user_image($station['slug'] ?? '', $station['image_url'] ?? null);
$bio = $station['bio'] ?? '';
$scores = $station['scores'] ?? [];

// SMR Integration
if (empty($station['smr_rankings'])) {
    try {
        $smrPdo = \NGN\Lib\DB\ConnectionFactory::named($config, 'smr2025');
        $stmt = $smrPdo->prepare('
            SELECT sc.track AS Song, sc.artist AS Artist, sc.window_date AS chart_date, sc.rank as TWP
            FROM `ngn_smr_2025`.`smr_chart` sc
            WHERE sc.station_id = ?
            ORDER BY sc.window_date DESC LIMIT 20
        ');
        $stmt->execute([$station['id']]);
        $station['smr_rankings'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (\Throwable $e) {}
}
?>

<!-- HERO HEADER -->
<div class="relative -mt-8 -mx-8 mb-12 h-[450px] flex items-end overflow-hidden group">
    <!-- Immersive Background -->
    <div class="absolute inset-0 bg-gradient-to-br from-emerald-600/40 to-black z-0 transition-transform duration-1000 group-hover:scale-105"></div>
    <div class="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-transparent z-10"></div>
    
    <!-- Animated Broadcast Signal -->
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-0 opacity-10 pointer-events-none">
        <i class="bi-broadcast text-[400px] text-emerald-500 animate-pulse"></i>
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
                <?php if (!empty($station['format'])): ?>
                    <span class="px-3 py-1 bg-white/10 text-white text-[10px] font-black uppercase tracking-[0.2em] rounded-full border border-white/10">
                        <?= htmlspecialchars($station['format']) ?>
                    </span>
                <?php endif; ?>
            </div>
            <h1 class="text-5xl lg:text-8xl font-black mb-6 tracking-tighter leading-none text-white drop-shadow-2xl"><?= htmlspecialchars($stationName) ?></h1>
            <div class="flex flex-wrap items-center justify-center md:justify-start gap-6 text-sm font-black uppercase tracking-widest text-zinc-400">
                <div class="flex items-center gap-2"><i class="bi-broadcast text-emerald-400"></i> <?= number_format($station['engagement_metrics']['total_spins'] ?? 0) ?> Total Spins</div>
                <?php if (!empty($station['city'])): ?>
                    <div class="flex items-center gap-2"><i class="bi-geo-alt-fill text-zinc-500"></i> <?= htmlspecialchars($station['city']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ACTION BAR -->
<div class="flex flex-wrap items-center gap-6 mb-16 px-4">
    <button data-play-track 
            data-track-url="<?= htmlspecialchars($station['stream_url'] ?? 'https://ice1.somafm.com/groovesalad-256-mp3') ?>" 
            data-track-title="<?= htmlspecialchars($stationName) ?>" 
            data-track-artist="Live Broadcast" 
            data-track-art="<?= htmlspecialchars($stationImg) ?>"
            class="px-12 py-5 bg-emerald-500 text-black font-black rounded-full hover:scale-105 transition-all shadow-xl shadow-emerald-500/20 uppercase tracking-widest text-sm flex items-center gap-3">
        <i class="bi-play-fill text-2xl"></i> Listen Live
    </button>
    <button class="w-14 h-14 rounded-full border-2 border-zinc-800 text-white flex items-center justify-center hover:border-white transition-all text-xl"><i class="bi-heart"></i></button>
    <button class="w-14 h-14 rounded-full border-2 border-zinc-800 text-white flex items-center justify-center hover:border-white transition-all text-xl"><i class="bi-share"></i></button>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-12 px-4">
    <div class="lg:col-span-8 space-y-20">
        
        <!-- Live Rotation (SMR Data) -->
        <section>
            <div class="flex items-center justify-between mb-8">
                <h2 class="text-3xl font-black tracking-tight text-white">Live Rotation</h2>
                <div class="flex items-center gap-2 px-4 py-2 bg-zinc-900 rounded-full text-[10px] font-black uppercase tracking-widest text-zinc-500 border border-white/5">
                    <i class="bi-clock-history"></i> Real-time Reports
                </div>
            </div>
            
            <div class="bg-zinc-900/30 rounded-3xl border border-white/5 overflow-hidden">
                <div class="divide-y divide-white/5">
                    <?php if (!empty($station['smr_rankings'])): ?>
                        <?php foreach (array_slice($station['smr_rankings'], 0, 15) as $spin): ?>
                        <div class="flex items-center gap-6 p-6 hover:bg-white/5 transition-all group">
                            <div class="w-12 h-12 rounded-lg overflow-hidden bg-zinc-800 flex-shrink-0 relative">
                                <i class="bi-music-note-beamed absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 text-zinc-700"></i>
                                <div class="absolute inset-0 flex items-center justify-center bg-emerald-500/10 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <i class="bi-play-fill text-emerald-400"></i>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-black text-lg truncate text-white group-hover:text-emerald-400 transition-colors"><?= htmlspecialchars($spin['Song'] ?? 'Unknown Track') ?></div>
                                <div class="text-[10px] font-black text-zinc-500 uppercase tracking-widest mt-1"><?= htmlspecialchars($spin['Artist'] ?? 'Unknown Artist') ?></div>
                            </div>
                            <div class="hidden md:block">
                                <span class="px-3 py-1 bg-zinc-800 text-zinc-500 text-[10px] font-black uppercase tracking-tighter rounded border border-white/5">Rank #<?= $spin['TWP'] ?? '--' ?></span>
                            </div>
                            <div class="text-xs font-mono text-zinc-600 font-bold"><?= !empty($spin['chart_date']) ? date('H:i', strtotime($spin['chart_date'])) : '' ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-20 text-center">
                            <i class="bi-broadcast text-6xl text-zinc-800 mb-6 block"></i>
                            <div class="text-zinc-500 font-black uppercase tracking-widest text-xs">Waiting for Rotation Report</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- About / Story -->
        <?php if (!empty($bio)): ?>
        <section class="sp-card border border-white/5 p-12 relative overflow-hidden">
            <h2 class="text-sm font-black uppercase tracking-[0.3em] text-emerald-400 mb-8">About the Station</h2>
            <div class="prose prose-invert max-w-none text-zinc-400 font-medium leading-[1.8] text-lg">
                <?= $bio ?>
            </div>
        </section>
        <?php endif; ?>

    </div>

    <!-- SIDEBAR -->
    <div class="lg:col-span-4 space-y-8">
        
        <!-- Station Metrics -->
        <div class="bg-zinc-900/50 rounded-3xl border border-white/5 p-8">
            <h3 class="text-xs font-black uppercase tracking-[0.2em] text-zinc-500 mb-8">Intelligence</h3>
            <div class="space-y-6">
                <div class="flex justify-between items-center">
                    <span class="text-zinc-400 font-bold">NGN Rank</span>
                    <span class="text-3xl font-black text-emerald-400"><?= number_format((float)($scores['Score'] ?? 0), 1) ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-zinc-400 font-bold">Total Announcements</span>
                    <span class="text-xl font-black text-white"><?= number_format($station['engagement_metrics']['total_posts'] ?? 0) ?></span>
                </div>
                
                <div class="pt-6 border-t border-white/5">
                    <div class="text-[10px] font-black text-zinc-600 uppercase tracking-widest mb-4">Location</div>
                    <div class="flex items-center gap-3 text-white font-bold">
                        <i class="bi-geo-alt-fill text-emerald-500"></i>
                        <?= htmlspecialchars($station['city'] ?? 'Global') ?><?= !empty($station['region']) ? ', ' . htmlspecialchars($station['region']) : '' ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Official Links -->
        <div class="bg-zinc-900/50 rounded-3xl border border-white/5 p-8">
            <h3 class="text-xs font-black uppercase tracking-[0.2em] text-zinc-500 mb-8">Official Links</h3>
            <div class="flex flex-col gap-3">
                <?php if (!empty($station['website_url'])): ?>
                <a href="<?= htmlspecialchars($station['website_url']) ?>" target="_blank" class="w-full py-4 rounded-xl bg-white/5 hover:bg-white/10 border border-white/5 font-black text-xs uppercase tracking-[0.2em] transition-all text-center">
                    <i class="bi-globe mr-2"></i> Website
                </a>
                <?php endif; ?>
                <?php if (!empty($station['social_links']['facebook'])): ?>
                <a href="<?= htmlspecialchars($station['social_links']['facebook']) ?>" target="_blank" class="w-full py-4 rounded-xl bg-white/5 hover:bg-white/10 border border-white/5 font-black text-xs uppercase tracking-[0.2em] transition-all text-center">
                    <i class="bi-facebook mr-2"></i> Facebook
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

