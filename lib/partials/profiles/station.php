<?php
/**
 * Station Profile Partial
 */
$legacy = $entity['legacy'] ?? [];
$scores = $entity['scores'] ?? [];

// Fetch recent spins if missing
if (empty($entity['smr_rankings'])) {
    try {
        $smrPdo = \NGN\Lib\DB\ConnectionFactory::named($config, 'smr2025');
        $stmt = $smrPdo->prepare('
            SELECT sc.track AS Song, sc.artist AS Artist, sc.window_date AS chart_date
            FROM `ngn_smr_2025`.`smr_chart` sc
            WHERE sc.station_id = ?
            ORDER BY sc.window_date DESC LIMIT 10
        ');
        $stmt->execute([$entity['id']]);
        $entity['smr_rankings'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (\Throwable $e) {
        // Might not have station_id mapping in sc yet, or table structure varies
        error_log("Error fetching station spins: " . $e->getMessage());
    }
}

$legacySlug = $legacy['Slug'] ?? $entity['slug'] ?? '';
$entityImg = !empty($legacy['Image']) ? user_image($legacySlug, $legacy['Image']) : (($entity['image_url'] ?? null) ?: DEFAULT_AVATAR);
?>

<div class="relative -mt-6 -mx-4 lg:-mx-6 mb-8">
    <div class="h-48 lg:h-64 w-full relative bg-gray-900 overflow-hidden">
        <div class="w-full h-full bg-gradient-to-r from-emerald-900/40 to-black"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-black to-transparent"></div>
        <div class="absolute bottom-0 left-0 w-full p-6 lg:p-12 flex items-center gap-6">
            <div class="w-32 h-32 lg:w-40 lg:h-40 flex-shrink-0 rounded-full overflow-hidden shadow-2xl border-4 border-emerald-500/20 bg-black">
                <img src="<?= htmlspecialchars($entityImg) ?>" class="w-full h-full object-cover" alt="<?= htmlspecialchars($entity['name']) ?>" onerror="this.src='<?= DEFAULT_AVATAR ?>'">
            </div>
            <div>
                <span class="px-2 py-0.5 bg-emerald-500/20 text-emerald-400 text-xs font-bold rounded uppercase tracking-wider">Radio Station</span>
                <h1 class="text-3xl lg:text-5xl font-black mt-2 mb-2"><?= htmlspecialchars($entity['name']) ?></h1>
                <div class="flex gap-4 text-sm font-medium text-white/60">
                    <span><i class="bi-broadcast text-emerald-400"></i> <?= number_format($entity['engagement_metrics']['total_spins'] ?? 0) ?> Total Spins</span>
                    <span><i class="bi-geo-alt-fill text-emerald-400"></i> <?= htmlspecialchars($entity['region'] ?? 'Global') ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
    <div class="lg:col-span-8 space-y-12">
        
        <!-- Live Spins / Recent History -->
        <section>
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold flex items-center gap-2">
                    <span class="w-3 h-3 bg-red-500 rounded-full animate-pulse"></span>
                    Recent Spins
                </h2>
                <button class="bg-white/10 hover:bg-white/20 px-4 py-2 rounded-lg text-xs font-bold transition-all">LISTEN LIVE</button>
            </div>
            <div class="bg-white/5 border border-white/5 rounded-2xl overflow-hidden divide-y divide-white/5">
                <?php if (!empty($entity['smr_rankings'])): ?>
                    <?php foreach (array_slice($entity['smr_rankings'], 0, 10) as $spin): ?>
                    <div class="flex items-center gap-4 p-4 hover:bg-white/5 transition-colors">
                        <div class="w-12 h-12 rounded bg-white/10 flex-shrink-0 flex items-center justify-center">
                            <i class="bi-music-note text-emerald-400"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-bold truncate"><?= htmlspecialchars($spin['Song'] ?? 'Unknown Track') ?></div>
                            <div class="text-sm text-white/40 truncate"><?= htmlspecialchars($spin['Artist'] ?? 'Unknown Artist') ?></div>
                        </div>
                        <div class="text-xs text-white/40 font-mono"><?= !empty($spin['chart_date']) ? date('H:i', strtotime($spin['chart_date'])) : '--:--' ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-12 text-center">
                        <div class="text-emerald-500/20 text-4xl mb-4"><i class="bi-broadcast-pin"></i></div>
                        <div class="text-white/40">No live spin data reported yet.</div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Station Posts -->
        <?php if (!empty($entity['posts'])): ?>
        <section>
            <h2 class="text-2xl font-bold mb-6">Station News</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach (array_slice($entity['posts'], 0, 4) as $post): ?>
                <a href="/post/<?= htmlspecialchars($post['slug'] ?? $post['id'] ?? '') ?>" class="p-6 bg-white/5 rounded-2xl border border-white/5 hover:bg-white/10 transition-all group">
                    <div class="font-bold group-hover:text-emerald-400 transition-colors mb-2"><?= htmlspecialchars($post['title'] ?? $post['Title'] ?? 'Untitled') ?></div>
                    <div class="text-xs text-white/40"><?= !empty($post['published_at']) ? date('F j, Y', strtotime($post['published_at'])) : '' ?></div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- About -->
        <?php if (!empty($entity['bio']) || !empty($legacy['Body'])): ?>
        <section class="bg-white/5 rounded-2xl p-8 border border-white/5">
            <h2 class="text-2xl font-bold mb-4">About</h2>
            <div class="text-white/70 leading-relaxed">
                <?= $entity['bio'] ?: $legacy['Body'] ?>
            </div>
        </section>
        <?php endif; ?>

    </div>

    <!-- Sidebar -->
    <div class="lg:col-span-4 space-y-8">
        <!-- Frequency / Info -->
        <div class="bg-gradient-to-br from-emerald-900/20 to-transparent border border-emerald-500/20 rounded-2xl p-6 text-center">
            <div class="text-xs font-bold uppercase tracking-widest text-white/40 mb-2">Station Frequency</div>
            <div class="text-4xl font-black text-emerald-400 mb-4"><?= htmlspecialchars($entity['call_sign'] ?? 'NGN') ?></div>
            <div class="text-sm font-medium text-white/60 italic"><?= htmlspecialchars($entity['format'] ?? 'Rock / Metal') ?></div>
        </div>

        <!-- Station Metrics -->
        <div class="grid grid-cols-2 gap-4">
            <div class="bg-white/5 p-4 rounded-xl border border-white/5 text-center">
                <div class="text-xl font-black text-white"><?= number_format((float)($scores['Score'] ?? 0), 1) ?></div>
                <div class="text-[10px] font-bold text-white/40 uppercase">Station Rank</div>
            </div>
            <div class="bg-white/5 p-4 rounded-xl border border-white/5 text-center">
                <div class="text-xl font-black text-white"><?= number_format($entity['engagement_metrics']['total_posts'] ?? 0) ?></div>
                <div class="text-[10px] font-bold text-white/40 uppercase">Announcements</div>
            </div>
        </div>
    </div>
</div>
