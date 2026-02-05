<?php
/**
 * Artist Profile Partial - Premium 2.0 (Mobile First)
 */

$artist = $entity;
$artistName = $artist['name'] ?? 'Unknown Artist';
$artistImg = user_image($artist['slug'] ?? '', $artist['image_url'] ?? null);
$bio = $artist['bio'] ?? '';
$scores = $artist['scores'] ?? ['Score' => 0];

// Fetch recent SMR rankings if missing
if (empty($entity['smr_rankings'])) {
    try {
        $smrPdo = \NGN\Lib\DB\ConnectionFactory::named($config, 'smr2025');
        $stmt = $smrPdo->prepare('
            SELECT sc.track AS Song, sc.rank AS TWP, sc.window_date AS chart_date, sc.label AS Label
            FROM `ngn_smr_2025`.`smr_chart` sc
            WHERE sc.artist_id = ?
            ORDER BY sc.window_date DESC LIMIT 5
        ');
        $stmt->execute([$entity['id']]);
        $entity['smr_rankings'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (\Throwable $e) {
        error_log("Error fetching artist SMR rankings: " . $e->getMessage());
    }
}
?>

<div class="artist-profile">
    <!-- Spotify-style Hero Header -->
    <div class="relative -mt-6 -mx-4 lg:-mx-8 mb-8 group">
        <div class="absolute inset-0 bg-gradient-to-b from-brand/20 via-black/60 to-black z-10"></div>
        <div class="h-[300px] md:h-[450px] w-full overflow-hidden">
            <img src="<?= htmlspecialchars($artistImg) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-1000 blur-sm opacity-40" alt="">
        </div>
        
        <div class="absolute inset-0 z-20 flex flex-col justify-end p-6 lg:p-12">
            <div class="flex items-center gap-2 mb-4 animate-in slide-in-from-left duration-500">
                <span class="w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center shadow-lg"><i class="bi-check-lg text-xs"></i></span>
                <span class="text-xs font-black uppercase tracking-[0.3em] text-white shadow-sm">Verified Artist</span>
            </div>
            <h1 class="text-5xl lg:text-8xl font-black text-white tracking-tighter mb-6 leading-none drop-shadow-2xl"><?= htmlspecialchars($artistName) ?></h1>
            
            <div class="flex items-center gap-8 text-sm font-black text-white/80 uppercase tracking-widest">
                <div class="flex flex-col">
                    <span class="text-brand text-xl leading-none mb-1"><?= number_format($scores['Score']) ?></span>
                    <span class="text-[10px] opacity-60">Impact Score</span>
                </div>
                <div class="flex flex-col">
                    <span class="text-white text-xl leading-none mb-1"><?= number_format($entity['engagement_metrics']['total_shows'] ?? 0) ?></span>
                    <span class="text-[10px] opacity-60">Gigs Played</span>
                </div>
                <?php if (!empty($entity['label_info'])): ?>
                <div class="flex flex-col">
                    <span class="text-white text-xl leading-none mb-1"><?= htmlspecialchars($entity['label_info']['name']) ?></span>
                    <span class="text-[10px] opacity-60">Label</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Action Bar (Sticky) -->
    <div class="sticky top-16 z-30 flex items-center justify-between py-6 mb-12 bg-black/50 backdrop-blur-xl -mx-4 px-4 lg:-mx-8 lg:px-8 border-b border-white/5">
        <div class="flex items-center gap-6">
            <button class="w-16 h-14 bg-brand text-black rounded-full flex items-center justify-center hover:scale-105 transition-all shadow-xl shadow-brand/20 group">
                <i class="bi-play-fill text-4xl ml-1 group-active:scale-90 transition-transform"></i>
            </button>
            <button class="px-8 py-3 rounded-full border-2 border-white/20 text-white font-black text-xs uppercase tracking-widest hover:border-white hover:bg-white hover:text-black transition-all">Follow</button>
            <button class="text-zinc-500 hover:text-white transition-colors"><i class="bi-three-dots text-2xl"></i></button>
        </div>
        
        <div class="flex items-center gap-4">
            <a href="/tip/<?= $artist['id'] ?>" class="hidden md:flex items-center gap-2 px-6 py-3 bg-white/5 hover:bg-white/10 rounded-full font-black text-xs uppercase tracking-widest border border-white/10 transition-all">
                <i class="bi-lightning-charge-fill text-brand"></i> Tip Artist
            </a>
            <button class="w-12 h-12 rounded-full bg-white/5 border border-white/10 flex items-center justify-center text-white hover:bg-white/10"><i class="bi-share"></i></button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
        <!-- Main Profile Content -->
        <div class="lg:col-span-8 space-y-16">
            
            <!-- Popular Tracks -->
            <?php if (!empty($entity['all_songs'])): ?>
            <section>
                <h2 class="text-2xl font-black mb-8 tracking-tight">Popular Tracks</h2>
                <div class="space-y-1">
                    <?php $idx = 1; foreach (array_slice($entity['all_songs'], 0, 5) as $song): ?>
                    <div class="group flex items-center gap-6 p-4 rounded-xl hover:bg-white/5 transition-all cursor-pointer" 
                         data-play-track 
                         data-track-url="<?= htmlspecialchars($song['mp3_url'] ?? '') ?>"
                         data-track-title="<?= htmlspecialchars($song['title']) ?>"
                         data-track-artist="<?= htmlspecialchars($artistName) ?>"
                         data-track-art="<?= htmlspecialchars($artistImg) ?>">
                        <span class="w-4 text-center text-zinc-600 font-bold group-hover:text-white"><?= $idx++ ?></span>
                        <div class="w-12 h-12 rounded bg-zinc-800 flex items-center justify-center flex-shrink-0 overflow-hidden relative shadow-lg">
                            <i class="bi-play-fill text-2xl opacity-0 group-hover:opacity-100 transition-opacity z-10"></i>
                            <img src="<?= htmlspecialchars(($artist['image_url'] ?? null) ?: DEFAULT_AVATAR) ?>" class="absolute inset-0 w-full h-full object-cover opacity-40 group-hover:opacity-20 transition-opacity">
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-black text-white truncate"><?= htmlspecialchars($song['title']) ?></div>
                            <div class="text-[10px] text-zinc-500 font-bold uppercase tracking-widest mt-1"><?= htmlspecialchars($song['ReleaseName'] ?? 'Single') ?></div>
                        </div>
                        <div class="text-sm font-mono text-zinc-500"><?= ($song['duration_seconds'] ?? 0) ? gmdate('i:s', $song['duration_seconds']) : '--:--' ?></div>
                        <button class="text-zinc-600 hover:text-brand opacity-0 group-hover:opacity-100 transition-all"><i class="bi-plus-circle text-lg"></i></button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- Discography -->
            <?php if (!empty($entity['releases'])): ?>
            <section>
                <div class="flex items-center justify-between mb-8">
                    <h2 class="text-2xl font-black tracking-tight">Discography</h2>
                    <a href="#" class="text-[10px] font-black text-zinc-500 hover:text-white uppercase tracking-[0.2em]">Show All</a>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                    <?php foreach (array_slice($entity['releases'], 0, 4) as $release): ?>
                    <?php 
                        $releaseImg = ($release['cover_url'] ?? $release['cover_image_url'] ?? '') ?: DEFAULT_AVATAR; 
                        // Get first track for play button shortcut
                        $firstTrack = $release['tracks'][0] ?? null;
                        $trackUrl = $firstTrack ? ($firstTrack['mp3_url'] ?? '') : '';
                    ?>
                    <div class="group sp-card border border-white/5">
                        <div class="aspect-square rounded-xl overflow-hidden mb-4 shadow-2xl relative">
                            <img src="<?= htmlspecialchars($releaseImg) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 bg-zinc-800" onerror="this.src='<?= DEFAULT_AVATAR ?>'">
                            <button class="absolute bottom-3 right-3 w-10 h-10 bg-brand text-black rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 translate-y-2 group-hover:translate-y-0 transition-all shadow-lg"
                                    data-play-track
                                    data-track-url="<?= htmlspecialchars($trackUrl) ?>"
                                    data-track-title="<?= htmlspecialchars($release['title']) ?>"
                                    data-track-artist="<?= htmlspecialchars($artistName) ?>"
                                    data-track-art="<?= htmlspecialchars($releaseImg) ?>">
                                <i class="bi-play-fill text-xl"></i>
                            </button>
                        </div>
                        <a href="/release/<?= htmlspecialchars($release['slug'] ?? '') ?>" class="font-black text-sm truncate text-white block hover:text-brand transition-colors"><?= htmlspecialchars($release['title']) ?></a>
                        <div class="text-[10px] font-black text-zinc-500 uppercase mt-1"><?= !empty($release['release_date']) ? date('Y', strtotime($release['release_date'])) : 'N/A' ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- Videos -->
            <?php if (!empty($entity['all_videos'])): ?>
            <section>
                <h2 class="text-2xl font-black mb-8 tracking-tight">Featured Videos</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <?php foreach (array_slice($entity['all_videos'], 0, 2) as $video): ?>
                    <div class="group bg-zinc-900/50 rounded-2xl overflow-hidden border border-white/5 shadow-2xl">
                        <div class="aspect-video relative">
                            <iframe
                                src="https://www.youtube.com/embed/<?= htmlspecialchars($video['external_id'] ?? $video['video_id'] ?? '') ?>?rel=0&modestbranding=1"
                                class="w-full h-full"
                                frameborder="0"
                                allowfullscreen
                                loading="lazy"
                            ></iframe>
                        </div>
                        <div class="p-6">
                            <div class="font-black text-white truncate mb-2"><?= htmlspecialchars($video['title']) ?></div>
                            <div class="text-[10px] font-black text-zinc-500 uppercase tracking-widest"><?= !empty($video['published_at']) ? date('F j, Y', strtotime($video['published_at'])) : '' ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

        </div>

        <!-- Sidebar Info -->
        <div class="lg:col-span-4 space-y-12">
            <!-- Artist Bio -->
            <section class="sp-card border border-white/5 p-8">
                <h2 class="text-xs font-black text-zinc-500 uppercase tracking-[0.3em] mb-6">About</h2>
                <div class="prose prose-invert prose-sm text-zinc-400 font-medium leading-relaxed mb-8 line-clamp-6 group-hover:line-clamp-none transition-all">
                    <?= !empty($bio) ? nl2br($bio) : 'No biography available.' ?>
                </div>
                <div class="flex gap-4">
                    <?php foreach ($entity['social_links'] ?? [] as $provider => $url): ?>
                        <a href="<?= htmlspecialchars($url) ?>" class="w-10 h-10 rounded-full bg-white/5 border border-white/10 flex items-center justify-center text-white hover:bg-brand hover:text-black transition-all">
                            <i class="bi-<?= $provider ?>"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Radio Rankings -->
            <?php if (!empty($entity['smr_rankings']) && $config->featureAiEnabled()): ?>
            <section class="sp-card border border-white/5 p-8">
                <h2 class="text-xs font-black text-zinc-500 uppercase tracking-[0.3em] mb-6">Radio Intelligence</h2>
                <div class="space-y-6">
                    <?php foreach ($entity['smr_rankings'] as $rank): ?>
                    <div class="flex items-center justify-between">
                        <div class="min-w-0">
                            <div class="font-black text-white text-sm truncate"><?= htmlspecialchars($rank['Song']) ?></div>
                            <div class="text-[9px] text-zinc-500 font-bold uppercase tracking-tighter mt-1"><?= date('M j, Y', strtotime($rank['chart_date'])) ?></div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <div class="text-xl font-black text-brand">#<?= $rank['TWP'] ?></div>
                            <div class="text-[8px] text-zinc-600 font-black uppercase">Rank</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- Chart History -->
            <?php if (!empty($entity['chart_rankings'])): ?>
            <section class="sp-card border border-white/5 p-8">
                <h2 class="text-xs font-black text-zinc-500 uppercase tracking-[0.3em] mb-6">Chart History</h2>
                <div class="space-y-4">
                    <?php foreach (array_slice($entity['chart_rankings'], 0, 5) as $r): ?>
                    <div class="flex items-center gap-4">
                        <div class="w-8 font-black text-zinc-700 text-xs">#<?= $r['RankNum'] ?></div>
                        <div class="flex-1 h-1.5 bg-zinc-800 rounded-full overflow-hidden">
                            <div class="h-full bg-brand rounded-full" style="width: <?= min(100, $r['Score']/10) ?>%"></div>
                        </div>
                        <div class="text-[9px] font-black text-zinc-500 uppercase w-12 text-right"><?= date('M j', strtotime($r['PeriodEnd'])) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
        </div>
    </div>
</div>