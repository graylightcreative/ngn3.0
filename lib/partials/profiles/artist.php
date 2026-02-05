<?php
/**
 * Artist Profile Partial
 */
$legacy = $entity['legacy'] ?? [];
$scores = $entity['scores'] ?? [];

// Fetch chart history if not present
if (empty($entity['chart_rankings'])) {
    try {
        $rankingsPdo = \NGN\Lib\DB\ConnectionFactory::named($config, 'rankings2025');
        $stmt = $rankingsPdo->prepare('
            SELECT ri.rank AS RankNum, ri.score AS Score, rw.period_end AS PeriodEnd, rw.interval AS Interval
            FROM `ngn_rankings_2025`.`ranking_items` ri
            JOIN `ngn_rankings_2025`.`ranking_windows` rw ON ri.window_id = rw.id
            WHERE ri.entity_type = \'artist\' AND ri.entity_id = ?
            ORDER BY rw.period_end DESC LIMIT 10
        ');
        $stmt->execute([$entity['id']]);
        $entity['chart_rankings'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (\Throwable $e) {
        error_log("Error fetching chart history: " . $e->getMessage());
    }
}

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

$legacySlug = $legacy['Slug'] ?? $entity['slug'] ?? '';
$artistSlugForReleases = $legacySlug;
$entityImg = !empty($legacy['Image']) ? user_image($legacySlug, $legacy['Image']) : (($entity['image_url'] ?? null) ?: DEFAULT_AVATAR);
$bannerImg = !empty($legacy['Banner']) ? user_image($legacySlug, $legacy['Banner']) : null;
?>

<div class="relative -mt-6 -mx-4 lg:-mx-6 mb-8">
    <!-- Header/Banner Area -->
    <div class="h-64 lg:h-96 w-full relative bg-gray-900 overflow-hidden">
        <?php if ($bannerImg): ?>
            <img src="<?= htmlspecialchars($bannerImg) ?>" class="w-full h-full object-cover opacity-60 blur-sm scale-105" alt="">
        <?php else: ?>
            <div class="w-full h-full bg-gradient-to-b from-brand/20 to-black"></div>
        <?php endif; ?>
        
        <div class="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-transparent"></div>
        
        <div class="absolute bottom-0 left-0 w-full p-6 lg:p-12 flex flex-col md:flex-row items-center md:items-end gap-6 lg:gap-10">
            <div class="w-48 h-48 lg:w-64 lg:h-64 flex-shrink-0 rounded-xl overflow-hidden shadow-2xl border-4 border-white/10 group relative">
                <img src="<?= htmlspecialchars($entityImg) ?>" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" alt="<?= htmlspecialchars($entity['name']) ?>">
            </div>
            
            <div class="flex-1 text-center md:text-left mb-2">
                <div class="flex items-center justify-center md:justify-start gap-2 mb-2">
                    <span class="px-2 py-0.5 bg-brand/20 text-brand text-xs font-bold rounded uppercase tracking-wider">Artist</span>
                    <?php if (($scores['RankNum'] ?? 999) <= 100): ?>
                        <span class="px-2 py-0.5 bg-yellow-500/20 text-yellow-500 text-xs font-bold rounded uppercase tracking-wider">#<?= $scores['RankNum'] ?> Globally</span>
                    <?php endif; ?>
                </div>
                <h1 class="text-4xl lg:text-7xl font-black mb-4 tracking-tight"><?= htmlspecialchars($entity['name']) ?></h1>
                
                <div class="flex flex-wrap items-center justify-center md:justify-start gap-4 text-sm font-medium">
                    <?php if (!empty($entity['city'])): ?>
                        <span class="flex items-center gap-1 text-white/80"><i class="bi-geo-alt-fill text-brand"></i> <?= htmlspecialchars($entity['city']) ?></span>
                    <?php endif; ?>
                    
                    <span class="flex items-center gap-1 text-white/80"><i class="bi-music-note-beamed text-brand"></i> <?= number_format($entity['engagement_metrics']['total_songs'] ?? 0) ?> Tracks</span>
                    <span class="flex items-center gap-1 text-white/80"><i class="bi-people-fill text-brand"></i> <?= number_format($entity['engagement_metrics']['follower_count'] ?? 0) ?> Followers</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Bar -->
    <div class="bg-black/40 backdrop-blur-md border-b border-white/5 sticky top-0 z-40 px-6 lg:px-12 py-4 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <button onclick="handleFollow(<?= $entity['id'] ?>)" class="bg-brand hover:bg-brand-hover text-black font-bold py-3 px-8 rounded-full transition-all hover:scale-105 active:scale-95 shadow-lg shadow-brand/20">
                Follow
            </button>
            
            <button onclick="handleSpark(<?= $entity['id'] ?>)" class="w-12 h-12 rounded-full border border-white/20 flex items-center justify-center hover:border-white transition-colors group">
                <i class="bi-lightning-charge-fill text-xl group-hover:text-yellow-400"></i>
            </button>
            
            <div class="h-8 w-px bg-white/10 mx-2"></div>
            
            <!-- Socials -->
            <div class="flex items-center gap-3">
                <?php if (!empty($legacy['FacebookUrl'])): ?>
                    <a href="<?= htmlspecialchars($legacy['FacebookUrl']) ?>" target="_blank" class="text-white/60 hover:text-white transition-colors text-xl"><i class="bi-facebook"></i></a>
                <?php endif; ?>
                <?php if (!empty($legacy['InstagramUrl'])): ?>
                    <a href="<?= htmlspecialchars($legacy['InstagramUrl']) ?>" target="_blank" class="text-white/60 hover:text-white transition-colors text-xl"><i class="bi-instagram"></i></a>
                <?php endif; ?>
                <?php if (!empty($legacy['SpotifyUrl'])): ?>
                    <a href="<?= htmlspecialchars($legacy['SpotifyUrl']) ?>" target="_blank" class="text-white/60 hover:text-white transition-colors text-xl"><i class="bi-spotify"></i></a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="hidden md:flex items-center gap-6">
            <div class="text-right">
                <div class="text-[10px] uppercase text-white/40 font-bold tracking-widest">NGN Score</div>
                <div class="text-2xl font-black text-brand"><?= number_format((float)($scores['Score'] ?? 0), 1) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
    <!-- Main Column -->
    <div class="lg:col-span-8 space-y-12">
        
        <!-- Top Tracks -->
        <?php if (!empty($entity['all_songs'])): ?>
        <section>
            <h2 class="text-2xl font-bold mb-6">Popular Tracks</h2>
            <div class="space-y-1">
                <?php $idx = 1; foreach (array_slice($entity['all_songs'], 0, 10) as $song): ?>
                <div class="group flex items-center gap-4 p-3 rounded-lg hover:bg-white/5 transition-colors cursor-pointer">
                    <span class="w-4 text-center text-white/40 font-medium group-hover:text-white"><?= $idx++ ?></span>
                    <div class="w-10 h-10 rounded bg-white/10 flex items-center justify-center flex-shrink-0 overflow-hidden">
                        <i class="bi-play-fill text-xl opacity-0 group-hover:opacity-100 transition-opacity"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-bold truncate"><?= htmlspecialchars($song['title'] ?? $song['Title'] ?? 'Untitled') ?></div>
                        <div class="text-xs text-white/40 truncate"><?= htmlspecialchars($song['ReleaseName'] ?? 'Single') ?></div>
                    </div>
                    <div class="text-sm text-white/40"><?= ($song['duration_seconds'] ?? $song['Duration'] ?? 0) ? gmdate('i:s', $song['duration_seconds'] ?? $song['Duration'] ?? 0) : '--:--' ?></div>
                    <button class="text-white/40 hover:text-brand opacity-0 group-hover:opacity-100 transition-all"><i class="bi-plus-circle"></i></button>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Discography -->
        <?php if (!empty($entity['releases'])): ?>
        <section>
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold">Discography</h2>
                <a href="#" class="text-xs font-bold text-white/40 hover:text-white uppercase tracking-widest">Show All</a>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php foreach (array_slice($entity['releases'], 0, 4) as $release): ?>
                <?php $releaseImg = ($release['cover_url'] ?? $release['cover_image_url'] ?? '') ?: DEFAULT_AVATAR; ?>
                <a href="/release/<?= htmlspecialchars($release['slug'] ?? $release['Slug'] ?? '') ?>" class="group">
                    <div class="aspect-square rounded-xl overflow-hidden mb-4 shadow-lg group-hover:shadow-brand/10 transition-all border border-white/5">
                        <img src="<?= htmlspecialchars($releaseImg) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" alt="" onerror="this.src='<?= DEFAULT_AVATAR ?>'">
                    </div>
                    <div class="font-bold truncate group-hover:text-brand transition-colors"><?= htmlspecialchars($release['title'] ?? $release['Title'] ?? 'Untitled') ?></div>
                    <div class="text-sm text-white/40 uppercase tracking-tighter"><?= !empty($release['release_date']) ? date('Y', strtotime($release['release_date'])) : (isset($release['ReleaseDate']) ? date('Y', strtotime($release['ReleaseDate'])) : 'N/A') ?> • <?= ucfirst($release['type'] ?? $release['Type'] ?? 'Album') ?></div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- SMR Rankings -->
        <?php if (!empty($entity['smr_rankings'])): ?>
        <section>
            <h2 class="text-2xl font-bold mb-6">SMR Radio Rankings</h2>
            <div class="bg-white/5 border border-white/5 rounded-2xl overflow-hidden divide-y divide-white/5">
                <?php foreach ($entity['smr_rankings'] as $rank): ?>
                <div class="flex items-center justify-between p-4">
                    <div>
                        <div class="font-bold truncate max-w-xs"><?= htmlspecialchars($rank['Song']) ?></div>
                        <div class="text-xs text-white/40 uppercase tracking-widest"><?= date('M j, Y', strtotime($rank['chart_date'])) ?></div>
                    </div>
                    <div class="text-right">
                        <div class="font-bold text-brand">#<?= $rank['TWP'] ?></div>
                        <div class="text-[10px] text-white/40 uppercase font-black">Rank</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Videos -->
        <?php if (!empty($entity['all_videos'])): ?>
        <section>
            <h2 class="text-2xl font-bold mb-6">Featured Videos</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php foreach (array_slice($entity['all_videos'], 0, 4) as $video): ?>
                <div class="group bg-white/5 rounded-2xl overflow-hidden border border-white/5">
                    <div class="aspect-video relative">
                        <iframe
                            src="https://www.youtube.com/embed/<?= htmlspecialchars($video['video_id'] ?? $video['external_id'] ?? $video['VideoId'] ?? '') ?>?rel=0&modestbranding=1"
                            class="w-full h-full"
                            frameborder="0"
                            allowfullscreen
                            loading="lazy"
                        ></iframe>
                    </div>
                    <div class="p-4">
                        <div class="font-bold truncate"><?= htmlspecialchars($video['title'] ?? $video['Title'] ?? 'Untitled') ?></div>
                        <div class="text-xs text-white/40"><?= !empty($video['published_at']) ? date('M j, Y', strtotime($video['published_at'])) : '' ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Chart Rankings -->
        <?php if (!empty($entity['chart_rankings'])): ?>
        <section>
            <h2 class="text-2xl font-bold mb-6">NGN Chart History</h2>
            <div class="bg-white/5 border border-white/5 rounded-2xl overflow-hidden divide-y divide-white/5">
                <?php foreach (array_slice($entity['chart_rankings'], 0, 5) as $rank): ?>
                <div class="flex items-center justify-between p-4 hover:bg-white/5 transition-colors">
                    <div>
                        <div class="font-bold text-brand">#<?= $rank['RankNum'] ?></div>
                        <div class="text-xs text-white/40 uppercase tracking-widest"><?= ucfirst($rank['Interval']) ?> Ranking</div>
                    </div>
                    <div class="text-right">
                        <div class="font-bold"><?= number_format($rank['Score'], 1) ?></div>
                        <div class="text-xs text-white/40"><?= date('M j, Y', strtotime($rank['PeriodEnd'])) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Posts -->
        <?php if (!empty($entity['posts'])): ?>
        <section>
            <h2 class="text-2xl font-bold mb-6">Latest Posts</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach (array_slice($entity['posts'], 0, 3) as $post): ?>
                <a href="/post/<?= htmlspecialchars($post['slug'] ?? $post['id'] ?? '') ?>" class="group bg-white/5 border border-white/5 rounded-xl overflow-hidden hover:bg-white/10 transition-all">
                    <div class="p-4">
                        <div class="font-bold line-clamp-2 mb-2 group-hover:text-brand transition-colors"><?= htmlspecialchars($post['title'] ?? $post['Title'] ?? 'Untitled') ?></div>
                        <div class="text-xs text-white/40"><?= !empty($post['published_at']) ? date('M j, Y', strtotime($post['published_at'])) : '' ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Bio Section -->
        <?php if (!empty($entity['bio']) || !empty($legacy['Body'])): ?>
        <section class="bg-white/5 rounded-2xl p-8 border border-white/5">
            <h2 class="text-2xl font-bold mb-6">About</h2>
            <div class="prose prose-invert prose-brand max-w-none line-clamp-6 text-white/70 leading-relaxed">
                <?= $entity['bio'] ?: $legacy['Body'] ?>
            </div>
            <button class="mt-6 text-sm font-bold uppercase tracking-widest text-white/40 hover:text-white transition-colors">Read More</button>
        </section>
        <?php endif; ?>

    </div>

    <!-- Sidebar Column -->
    <div class="lg:col-span-4 space-y-8">
        
        <!-- Stats Card -->
        <div class="bg-gradient-to-br from-brand/20 to-transparent border border-brand/20 rounded-2xl p-6">
            <h3 class="text-sm font-bold uppercase tracking-widest mb-6 flex items-center gap-2">
                <i class="bi-activity text-brand"></i> Performance
            </h3>
            <div class="space-y-6">
                <div>
                    <div class="flex justify-between text-xs font-bold uppercase tracking-tighter text-white/40 mb-2">
                        <span>Rank Stability</span>
                        <span class="text-brand">Strong</span>
                    </div>
                    <div class="h-1.5 w-full bg-white/5 rounded-full overflow-hidden">
                        <div class="h-full bg-brand rounded-full" style="width: 85%"></div>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-black/20 rounded-xl p-4 border border-white/5 text-center">
                        <div class="text-2xl font-black"><?= number_format($entity['engagement_metrics']['total_posts'] ?? 0) ?></div>
                        <div class="text-[10px] font-bold text-white/40 uppercase">Posts</div>
                    </div>
                    <div class="bg-black/20 rounded-xl p-4 border border-white/5 text-center">
                        <div class="text-2xl font-black"><?= number_format($entity['engagement_metrics']['total_videos'] ?? 0) ?></div>
                        <div class="text-[10px] font-bold text-white/40 uppercase">Videos</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming Shows -->
        <?php if (!empty($entity['shows'])): ?>
        <div class="bg-white/5 rounded-2xl p-6 border border-white/5">
            <h3 class="text-sm font-bold uppercase tracking-widest mb-6 flex items-center gap-2">
                <i class="bi-calendar-event text-brand"></i> On Tour
            </h3>
            <div class="space-y-4">
                <?php foreach (array_slice($entity['shows'], 0, 3) as $show): ?>
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-lg bg-brand/10 text-brand flex flex-col items-center justify-center flex-shrink-0 border border-brand/20">
                        <span class="text-[10px] font-bold uppercase"><?= date('M', strtotime($show['ShowDate'])) ?></span>
                        <span class="text-lg font-black leading-none"><?= date('j', strtotime($show['ShowDate'])) ?></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-bold text-sm truncate"><?= htmlspecialchars($show['Venue'] ?? 'TBA') ?></div>
                        <div class="text-xs text-white/40 truncate"><?= htmlspecialchars($show['City'] ?? '') ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <a href="#" class="block w-full text-center mt-6 py-3 rounded-xl bg-white/5 hover:bg-white/10 text-xs font-bold uppercase tracking-widest transition-colors border border-white/5">View All Dates</a>
        </div>
        <?php endif; ?>

        <!-- Label Info -->
        <?php if (!empty($entity['labels'])): ?>
        <div class="bg-white/5 rounded-2xl p-6 border border-white/5">
            <h3 class="text-sm font-bold uppercase tracking-widest mb-6">Management</h3>
            <?php foreach ($entity['labels'] as $label): ?>
            <a href="/label/<?= htmlspecialchars($label['Slug']) ?>" class="flex items-center gap-4 group">
                <img src="<?= htmlspecialchars(!empty($label['Image']) ? user_image($label['Slug'], $label['Image']) : DEFAULT_AVATAR) ?>" class="w-12 h-12 rounded-full object-cover grayscale group-hover:grayscale-0 transition-all" alt="">
                <div class="flex-1 min-w-0">
                    <div class="font-bold text-sm truncate group-hover:text-brand transition-colors"><?= htmlspecialchars($label['Title']) ?></div>
                    <div class="text-xs text-white/40">Record Label</div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
function handleFollow(id) {
    fetch('/api/v1/me/follows/' + id, { method: 'POST' })
        .then(r => r.json())
        .then(d => {
            if (d.success) alert('Following!');
            else alert(d.message || 'Error');
        });
}

function handleSpark(id) {
    // Show spark modal
    alert('Spark system coming soon! Send support to your favorite artists.');
}
</script>
