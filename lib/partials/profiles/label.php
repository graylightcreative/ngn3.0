<?php
/**
 * Label Profile Partial - Modern 2.0
 */
$label = $entity;
$labelName = $label['name'] ?? 'Unknown Label';
$labelImg = user_image($label['slug'] ?? '', $label['image_url'] ?? null);
$bio = $label['bio'] ?? '';
$scores = $label['scores'] ?? [];

// Unified Chart History Fetching
if (empty($label['chart_rankings'])) {
    try {
        $rankingsPdo = \NGN\Lib\DB\ConnectionFactory::named($config, 'rankings2025');
        $stmt = $rankingsPdo->prepare('
            SELECT ri.rank AS RankNum, ri.score AS Score, rw.period_end AS PeriodEnd, rw.interval AS Interval
            FROM `ngn_rankings_2025`.`ranking_items` ri
            JOIN `ngn_rankings_2025`.`ranking_windows` rw ON ri.window_id = rw.id
            WHERE ri.entity_type = \'label\' AND ri.entity_id = ?
            ORDER BY rw.period_end DESC LIMIT 10
        ');
        $stmt->execute([$label['id']]);
        $label['chart_rankings'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (\Throwable $e) {}
}
?>

<!-- HERO HEADER -->
<div class="relative -mt-8 -mx-8 mb-12 h-[400px] flex items-end overflow-hidden group">
    <!-- Dynamic Background -->
    <div class="absolute inset-0 bg-gradient-to-br from-blue-600/40 to-black z-0 transition-transform duration-1000 group-hover:scale-105"></div>
    <div class="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-transparent z-10"></div>
    
    <div class="relative z-20 p-12 flex flex-col md:flex-row items-center md:items-end gap-10 w-full">
        <!-- Profile Image -->
        <div class="w-48 h-48 md:w-64 md:h-64 flex-shrink-0 shadow-[0_20px_50px_rgba(0,0,0,0.5)] rounded-full overflow-hidden bg-zinc-900 border-4 border-white/5 group-hover:scale-[1.02] transition-transform duration-500">
            <img src="<?= htmlspecialchars($labelImg) ?>" class="w-full h-full object-cover" alt="<?= htmlspecialchars($labelName) ?>" onerror="this.src='<?= DEFAULT_AVATAR ?>'">
        </div>
        
        <!-- Info -->
        <div class="flex-1 text-center md:text-left">
            <div class="flex items-center justify-center md:justify-start gap-2 mb-4">
                <span class="px-3 py-1 bg-blue-500 text-black text-[10px] font-black uppercase tracking-[0.2em] rounded-full">Verified Label</span>
                <?php if (!empty($scores['Score'])): ?>
                    <span class="px-3 py-1 bg-white/10 text-white text-[10px] font-black uppercase tracking-[0.2em] rounded-full border border-white/10">
                        <i class="bi-lightning-charge-fill text-blue-400 mr-1"></i> Rank #<?= number_format($scores['Score'], 0) ?>
                    </span>
                <?php endif; ?>
            </div>
            <h1 class="text-5xl lg:text-8xl font-black mb-6 tracking-tighter leading-none text-white drop-shadow-2xl"><?= htmlspecialchars($labelName) ?></h1>
            <div class="flex flex-wrap items-center justify-center md:justify-start gap-6 text-sm font-black uppercase tracking-widest text-zinc-400">
                <div class="flex items-center gap-2"><i class="bi-people-fill text-blue-400"></i> <?= count($label['roster'] ?? []) ?> Artists</div>
                <div class="flex items-center gap-2"><i class="bi-disc-fill text-blue-400"></i> <?= count($label['releases'] ?? []) ?> Releases</div>
                <?php if (!empty($label['city'])): ?>
                    <div class="flex items-center gap-2"><i class="bi-geo-alt-fill text-zinc-500"></i> <?= htmlspecialchars($label['city']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ACTION BAR -->
<div class="flex flex-wrap items-center gap-6 mb-16 px-4">
    <button class="px-10 py-4 bg-blue-500 text-black font-black rounded-full hover:scale-105 transition-all shadow-xl shadow-blue-500/20 uppercase tracking-widest text-xs">Partner</button>
    <button class="w-14 h-14 rounded-full border-2 border-zinc-800 text-white flex items-center justify-center hover:border-white transition-all text-xl"><i class="bi-heart"></i></button>
    <button class="w-14 h-14 rounded-full border-2 border-zinc-800 text-white flex items-center justify-center hover:border-white transition-all text-xl"><i class="bi-three-dots"></i></button>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-12 px-4">
    <div class="lg:col-span-8 space-y-20">
        
        <!-- Artist Roster -->
        <?php if (!empty($label['roster'])): ?>
        <section>
            <div class="flex items-center justify-between mb-8">
                <h2 class="text-3xl font-black tracking-tight text-white">The Roster</h2>
                <a href="#" class="text-xs font-black text-zinc-500 hover:text-white uppercase tracking-widest">See All</a>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <?php foreach ($label['roster'] as $artist): ?>
                <?php $artistImg = user_image($artist['slug'] ?? '', $artist['image_url'] ?? null); ?>
                <a href="/artist/<?= htmlspecialchars($artist['slug'] ?? '') ?>" class="group sp-card border border-white/5">
                    <div class="aspect-square rounded-full overflow-hidden mb-4 border-2 border-zinc-800 group-hover:border-blue-500 transition-all shadow-xl relative">
                        <img src="<?= htmlspecialchars($artistImg) ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" onerror="this.src='<?= DEFAULT_AVATAR ?>'">
                        <div class="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    </div>
                    <div class="font-black text-sm truncate text-white text-center group-hover:text-blue-400 transition-colors"><?= htmlspecialchars($artist['name'] ?? 'Artist') ?></div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- New Releases -->
        <?php if (!empty($label['releases'])): ?>
        <section>
            <div class="flex items-center justify-between mb-8">
                <h2 class="text-3xl font-black tracking-tight text-white">Latest Drops</h2>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                <?php foreach ($label['releases'] as $release): ?>
                <?php $releaseImg = ($release['cover_url'] ?? $release['cover_image_url'] ?? '') ?: DEFAULT_AVATAR; ?>
                <a href="/release/<?= htmlspecialchars($release['slug'] ?? '') ?>" class="group">
                    <div class="aspect-square rounded-2xl overflow-hidden mb-4 shadow-2xl relative border border-white/5 group-hover:border-blue-500 transition-colors">
                        <img src="<?= htmlspecialchars($releaseImg) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" onerror="this.src='<?= DEFAULT_AVATAR ?>'">
                        <button class="absolute bottom-3 right-3 w-12 h-12 bg-blue-500 text-black rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 translate-y-2 group-hover:translate-y-0 transition-all shadow-xl">
                            <i class="bi-play-fill text-2xl"></i>
                        </button>
                    </div>
                    <div class="font-black text-sm truncate text-white group-hover:text-blue-400 transition-colors"><?= htmlspecialchars($release['title'] ?? 'Untitled') ?></div>
                    <div class="text-[10px] font-black text-zinc-500 uppercase tracking-widest mt-1"><?= htmlspecialchars($release['ArtistName'] ?? 'Various Artists') ?></div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Bio -->
        <?php if (!empty($bio)): ?>
        <section class="sp-card border border-white/5 p-12 relative overflow-hidden">
            <div class="absolute top-0 right-0 p-8 opacity-10">
                <i class="bi-quote text-8xl"></i>
            </div>
            <h2 class="text-sm font-black uppercase tracking-[0.3em] text-blue-400 mb-8">The Story</h2>
            <div class="prose prose-invert max-w-none text-zinc-400 font-medium leading-[1.8] text-lg">
                <?= $bio ?>
            </div>
        </section>
        <?php endif; ?>

    </div>

    <!-- SIDEBAR -->
    <div class="lg:col-span-4 space-y-8">
        
        <!-- Market Performance -->
        <?php if ((new \NGN\Lib\Config())->featureAiEnabled()): ?>
        <div class="bg-zinc-900/50 rounded-3xl border border-white/5 p-8">
            <h3 class="text-xs font-black uppercase tracking-[0.2em] text-zinc-500 mb-8">Intelligence</h3>
            <div class="space-y-6">
                <div class="flex justify-between items-center">
                    <span class="text-zinc-400 font-bold">Network Score</span>
                    <span class="text-3xl font-black text-blue-400"><?= number_format((float)($scores['Score'] ?? 0), 1) ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-zinc-400 font-bold">Artist Signal</span>
                    <span class="text-xl font-black text-white"><?= number_format(count($label['roster'] ?? [])) ?></span>
                </div>
                <div class="pt-6 border-t border-white/5">
                    <div class="text-[10px] font-black text-zinc-600 uppercase tracking-widest mb-4 text-center">Chart Performance</div>
                    <?php if (!empty($label['chart_rankings'])): ?>
                        <div class="space-y-3">
                            <?php foreach (array_slice($label['chart_rankings'], 0, 3) as $rank): ?>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-zinc-500 font-bold"><?= date('M j', strtotime($rank['PeriodEnd'])) ?></span>
                                <span class="font-black text-white bg-blue-500/10 px-2 py-1 rounded text-blue-400">#<?= $rank['RankNum'] ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4 text-zinc-700 font-black italic text-xs">No Recent Rankings</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Social Presence -->
        <div class="bg-zinc-900/50 rounded-3xl border border-white/5 p-8 text-center">
            <h3 class="text-xs font-black uppercase tracking-[0.2em] text-zinc-500 mb-8">Official Links</h3>
            <div class="flex flex-col gap-3">
                <?php if (!empty($label['social_links']['website'])): ?>
                <a href="<?= htmlspecialchars($label['social_links']['website']) ?>" target="_blank" class="w-full py-4 rounded-xl bg-white/5 hover:bg-white/10 border border-white/5 font-black text-xs uppercase tracking-[0.2em] transition-all">
                    <i class="bi-globe mr-2"></i> Website
                </a>
                <?php endif; ?>
                <?php if (!empty($label['social_links']['facebook'])): ?>
                <a href="<?= htmlspecialchars($label['social_links']['facebook']) ?>" target="_blank" class="w-full py-4 rounded-xl bg-white/5 hover:bg-white/10 border border-white/5 font-black text-xs uppercase tracking-[0.2em] transition-all">
                    <i class="bi-facebook mr-2"></i> Facebook
                </a>
                <?php endif; ?>
                <?php if (!empty($label['social_links']['instagram'])): ?>
                <a href="<?= htmlspecialchars($label['social_links']['instagram']) ?>" target="_blank" class="w-full py-4 rounded-xl bg-white/5 hover:bg-white/10 border border-white/5 font-black text-xs uppercase tracking-[0.2em] transition-all">
                    <i class="bi-instagram mr-2"></i> Instagram
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

