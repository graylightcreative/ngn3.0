<?php
/**
 * Label Profile Partial
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
            WHERE ri.entity_type = \'label\' AND ri.entity_id = ?
            ORDER BY rw.period_end DESC LIMIT 10
        ');
        $stmt->execute([$entity['id']]);
        $entity['chart_rankings'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (\Throwable $e) {
        error_log("Error fetching chart history: " . $e->getMessage());
    }
}

$legacySlug = $legacy['Slug'] ?? $entity['slug'] ?? '';
$entityImg = !empty($legacy['Image']) ? user_image($legacySlug, $legacy['Image']) : (($entity['image_url'] ?? null) ?: DEFAULT_AVATAR);
?>

<div class="relative -mt-6 -mx-4 lg:-mx-6 mb-8">
    <div class="h-48 lg:h-64 w-full relative bg-gray-900 overflow-hidden">
        <div class="w-full h-full bg-gradient-to-r from-blue-900/40 to-black"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-black to-transparent"></div>
        <div class="absolute bottom-0 left-0 w-full p-6 lg:p-12 flex items-center gap-6">
            <div class="w-32 h-32 lg:w-40 lg:h-40 flex-shrink-0 rounded-lg overflow-hidden shadow-2xl border-2 border-white/10 bg-black">
                <img src="<?= htmlspecialchars($entityImg) ?>" class="w-full h-full object-contain p-4" alt="<?= htmlspecialchars($entity['name']) ?>" onerror="this.src='<?= DEFAULT_AVATAR ?>'">
            </div>
            <div>
                <span class="px-2 py-0.5 bg-blue-500/20 text-blue-400 text-xs font-bold rounded uppercase tracking-wider">Record Label</span>
                <h1 class="text-3xl lg:text-5xl font-black mt-2 mb-2"><?= htmlspecialchars($entity['name']) ?></h1>
                <div class="flex gap-4 text-sm font-medium text-white/60">
                    <span><i class="bi-people-fill text-blue-400"></i> <?= number_format(count($entity['roster'] ?? [])) ?> Artists</span>
                    <span><i class="bi-disc-fill text-blue-400"></i> <?= number_format(count($entity['releases'] ?? [])) ?> Releases</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
    <div class="lg:col-span-8 space-y-12">
        
        <!-- Artist Roster -->
        <?php if (!empty($entity['roster'])): ?>
        <section>
            <h2 class="text-2xl font-bold mb-6 flex items-center gap-2">
                <i class="bi-people text-blue-400"></i> Artist Roster
            </h2>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <?php foreach ($entity['roster'] as $artist): ?>
                <?php $artistImg = user_image($artist['slug'] ?? $artist['Slug'], $artist['image_url'] ?? $artist['Image']); ?>
                <a href="/artist/<?= htmlspecialchars($artist['slug'] ?? $artist['Slug']) ?>" class="group bg-white/5 border border-white/5 rounded-xl p-4 hover:bg-white/10 transition-all text-center">
                    <img src="<?= htmlspecialchars($artistImg) ?>" class="w-20 h-20 mx-auto rounded-full object-cover mb-3 border-2 border-white/10 group-hover:border-blue-400 transition-colors" onerror="this.onerror=null;this.src='<?= DEFAULT_AVATAR ?>'">
                    <div class="font-bold text-sm truncate group-hover:text-blue-400 transition-colors"><?= htmlspecialchars($artist['name'] ?? $artist['Title']) ?></div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Label Releases -->
        <?php if (!empty($entity['releases'])): ?>
        <section>
            <h2 class="text-2xl font-bold mb-6 flex items-center gap-2">
                <i class="bi-vinyl text-blue-400"></i> New Releases
            </h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <?php foreach ($entity['releases'] as $release): ?>
                <?php $releaseImg = ($release['cover_url'] ?? $release['cover_image_url'] ?? '') ?: DEFAULT_AVATAR; ?>
                <a href="/release/<?= htmlspecialchars($release['slug'] ?? $release['Slug'] ?? '') ?>" class="group">
                    <div class="aspect-square rounded-xl overflow-hidden mb-3 border border-white/5 group-hover:border-blue-400 transition-colors shadow-lg">
                        <img src="<?= htmlspecialchars($releaseImg) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" onerror="this.src='<?= DEFAULT_AVATAR ?>'">
                    </div>
                    <div class="font-bold text-sm truncate group-hover:text-blue-400 transition-colors"><?= htmlspecialchars($release['title'] ?? $release['Title'] ?? 'Untitled') ?></div>
                    <div class="text-[10px] text-white/40 uppercase font-black"><?= htmlspecialchars($release['ArtistName'] ?? 'Various Artists') ?></div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Label Chart History -->
        <?php if (!empty($entity['chart_rankings'])): ?>
        <section>
            <h2 class="text-2xl font-bold mb-6 flex items-center gap-2">
                <i class="bi-graph-up text-blue-400"></i> Performance History
            </h2>
            <div class="bg-white/5 border border-white/5 rounded-2xl overflow-hidden divide-y divide-white/5">
                <?php foreach (array_slice($entity['chart_rankings'], 0, 5) as $rank): ?>
                <div class="flex items-center justify-between p-4">
                    <div>
                        <div class="font-bold text-blue-400">#<?= $rank['RankNum'] ?></div>
                        <div class="text-xs text-white/40 uppercase tracking-widest"><?= ucfirst($rank['Interval']) ?></div>
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

        <!-- Label Posts -->
        <?php if (!empty($entity['posts'])): ?>
        <section>
            <h2 class="text-2xl font-bold mb-6">Latest Announcements</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach (array_slice($entity['posts'], 0, 4) as $post): ?>
                <a href="/post/<?= htmlspecialchars($post['slug'] ?? $post['id'] ?? '') ?>" class="p-4 bg-white/5 rounded-xl border border-white/5 hover:bg-white/10 transition-all">
                    <div class="font-bold group-hover:text-blue-400 transition-colors"><?= htmlspecialchars($post['title'] ?? $post['Title'] ?? 'Untitled') ?></div>
                    <div class="text-xs text-white/40 mt-2"><?= !empty($post['published_at']) ? date('M j, Y', strtotime($post['published_at'])) : '' ?></div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- About Section -->
        <?php if (!empty($entity['bio']) || !empty($legacy['Body'])): ?>
        <section class="bg-white/5 rounded-2xl p-8 border border-white/5">
            <h2 class="text-2xl font-bold mb-4">About</h2>
            <div class="text-white/70 leading-relaxed prose prose-invert max-w-none">
                <?= $entity['bio'] ?: $legacy['Body'] ?>
            </div>
        </section>
        <?php endif; ?>

    </div>

    <!-- Sidebar -->
    <div class="lg:col-span-4 space-y-8">
        <!-- Label Stats -->
        <div class="bg-gradient-to-br from-blue-900/20 to-transparent border border-blue-500/20 rounded-2xl p-6">
            <h3 class="text-xs font-bold uppercase tracking-widest text-white/40 mb-6">Network Performance</h3>
            <div class="space-y-4">
                <div class="flex justify-between items-end border-b border-white/5 pb-4">
                    <span class="text-sm text-white/60">Combined Score</span>
                    <span class="text-2xl font-black text-blue-400"><?= number_format((float)($scores['Score'] ?? 0), 1) ?></span>
                </div>
                <div class="flex justify-between items-end border-b border-white/5 pb-4">
                    <span class="text-sm text-white/60">Active Campaigns</span>
                    <span class="text-xl font-bold text-white"><?= number_format($entity['engagement_metrics']['active_campaigns'] ?? 0) ?></span>
                </div>
            </div>
        </div>

        <!-- Social Presence -->
        <div class="bg-white/5 rounded-2xl p-6 border border-white/5">
            <h3 class="text-xs font-bold uppercase tracking-widest text-white/40 mb-6">Connect</h3>
            <div class="grid grid-cols-2 gap-3">
                <?php if (!empty($legacy['WebsiteUrl'])): ?>
                <a href="<?= htmlspecialchars($legacy['WebsiteUrl']) ?>" target="_blank" class="flex items-center gap-2 p-3 rounded-lg bg-white/5 hover:bg-blue-500/20 text-xs font-bold transition-all">
                    <i class="bi-globe"></i> Website
                </a>
                <?php endif; ?>
                <?php if (!empty($legacy['FacebookUrl'])): ?>
                <a href="<?= htmlspecialchars($legacy['FacebookUrl']) ?>" target="_blank" class="flex items-center gap-2 p-3 rounded-lg bg-white/5 hover:bg-blue-500/20 text-xs font-bold transition-all">
                    <i class="bi-facebook"></i> Facebook
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
