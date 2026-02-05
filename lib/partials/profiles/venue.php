<?php
/**
 * Venue Profile Partial
 */
$legacy = $entity['legacy'] ?? [];
$scores = $entity['scores'] ?? [];

// Fetch venue ranking if missing
if (empty($entity['chart_rankings'])) {
    try {
        $rankingsPdo = \NGN\Lib\DB\ConnectionFactory::named($config, 'rankings2025');
        $stmt = $rankingsPdo->prepare('
            SELECT ri.rank AS RankNum, ri.score AS Score, rw.period_end AS PeriodEnd, rw.interval AS Interval
            FROM `ngn_rankings_2025`.`ranking_items` ri
            JOIN `ngn_rankings_2025`.`ranking_windows` rw ON ri.window_id = rw.id
            WHERE ri.entity_type = \'venue\' AND ri.entity_id = ?
            ORDER BY rw.period_end DESC LIMIT 5
        ');
        $stmt->execute([$entity['id']]);
        $entity['chart_rankings'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (\Throwable $e) {
        error_log("Error fetching venue rankings: " . $e->getMessage());
    }
}

$legacySlug = $legacy['Slug'] ?? $entity['slug'] ?? '';
$entityImg = !empty($legacy['Image']) ? user_image($legacySlug, $legacy['Image']) : (($entity['image_url'] ?? null) ?: DEFAULT_AVATAR);
?>

<div class="relative -mt-6 -mx-4 lg:-mx-6 mb-8">
    <div class="h-48 lg:h-64 w-full relative bg-gray-900 overflow-hidden">
        <div class="w-full h-full bg-gradient-to-r from-purple-900/40 to-black"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-black to-transparent"></div>
        <div class="absolute bottom-0 left-0 w-full p-6 lg:p-12 flex items-center gap-6">
            <div class="w-32 h-32 lg:w-40 lg:h-40 flex-shrink-0 rounded-lg overflow-hidden shadow-2xl border-2 border-purple-500/20 bg-black">
                <img src="<?= htmlspecialchars($entityImg) ?>" class="w-full h-full object-cover" alt="<?= htmlspecialchars($entity['name']) ?>" onerror="this.src='<?= DEFAULT_AVATAR ?>'">
            </div>
            <div>
                <span class="px-2 py-0.5 bg-purple-500/20 text-purple-400 text-xs font-bold rounded uppercase tracking-wider">Music Venue</span>
                <h1 class="text-3xl lg:text-5xl font-black mt-2 mb-2"><?= htmlspecialchars($entity['name']) ?></h1>
                <div class="flex gap-4 text-sm font-medium text-white/60">
                    <span><i class="bi-calendar-event text-purple-400"></i> <?= number_format(count($entity['upcoming_shows'] ?? [])) ?> Upcoming Shows</span>
                    <span><i class="bi-geo-alt-fill text-purple-400"></i> <?= htmlspecialchars($entity['city'] ?? 'TBA') ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
    <div class="lg:col-span-8 space-y-12">
        
        <!-- Upcoming Shows -->
        <section>
            <h2 class="text-2xl font-bold mb-6 flex items-center gap-2">
                <i class="bi-ticket-perforated text-purple-400"></i> Upcoming Events
            </h2>
            <div class="bg-white/5 border border-white/5 rounded-2xl overflow-hidden divide-y divide-white/5">
                <?php if (!empty($entity['upcoming_shows'])): ?>
                    <?php foreach ($entity['upcoming_shows'] as $show): 
                        $showDate = strtotime($show['starts_at']);
                    ?>
                    <div class="flex items-center gap-6 p-6 hover:bg-white/5 transition-colors group">
                        <div class="w-16 h-16 rounded-xl bg-purple-500/10 text-purple-400 flex flex-col items-center justify-center flex-shrink-0 border border-purple-500/20">
                            <span class="text-xs font-bold uppercase"><?= date('M', $showDate) ?></span>
                            <span class="text-2xl font-black leading-none"><?= date('j', $showDate) ?></span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-bold text-lg truncate group-hover:text-purple-400 transition-colors"><?= htmlspecialchars($show['title'] ?? $show['Title'] ?? 'Live Show') ?></div>
                            <div class="text-sm text-white/40"><?= date('g:i A', $showDate) ?> • <?= htmlspecialchars($show['venue_name'] ?? '') ?></div>
                        </div>
                        <?php if (!empty($show['ticket_url'])): ?>
                        <a href="<?= htmlspecialchars($show['ticket_url']) ?>" target="_blank" class="px-6 py-2 rounded-lg bg-brand text-black font-bold hover:scale-105 transition-all text-sm">TICKETS</a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-12 text-center">
                        <div class="text-white/20 text-4xl mb-4"><i class="bi-calendar-x"></i></div>
                        <div class="text-white/40">No upcoming shows scheduled.</div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Venue Chart History -->
        <?php if (!empty($entity['chart_rankings'])): ?>
        <section>
            <h2 class="text-2xl font-bold mb-6 flex items-center gap-2">
                <i class="bi-star text-purple-400"></i> Venue Popularity
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($entity['chart_rankings'] as $rank): ?>
                <div class="bg-white/5 p-4 rounded-xl border border-white/5 flex items-center justify-between">
                    <div>
                        <div class="text-xs text-white/40 uppercase"><?= date('M Y', strtotime($rank['PeriodEnd'])) ?></div>
                        <div class="font-black text-xl text-purple-400">#<?= $rank['RankNum'] ?></div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-white/40 uppercase">NGN Score</div>
                        <div class="font-bold"><?= number_format($rank['Score'], 1) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Venue About -->
        <?php if (!empty($entity['bio']) || !empty($legacy['Body'])): ?>
        <section class="bg-white/5 rounded-2xl p-8 border border-white/5">
            <h2 class="text-2xl font-bold mb-4">About the Venue</h2>
            <div class="text-white/70 leading-relaxed prose prose-invert max-w-none">
                <?= $entity['bio'] ?: $legacy['Body'] ?>
            </div>
        </section>
        <?php endif; ?>

    </div>

    <!-- Sidebar -->
    <div class="lg:col-span-4 space-y-8">
        <!-- Location Card -->
        <div class="bg-white/5 border border-white/5 rounded-2xl p-6">
            <h3 class="text-xs font-bold uppercase tracking-widest text-white/40 mb-6">Location & Info</h3>
            <div class="space-y-4 text-sm">
                <div class="flex gap-3">
                    <i class="bi-geo-alt text-purple-400"></i>
                    <div class="text-white/80">
                        <?= htmlspecialchars($entity['address'] ?? 'Address TBA') ?><br>
                        <?= htmlspecialchars(($entity['city'] ?? '') . ', ' . ($entity['region'] ?? '')) ?>
                    </div>
                </div>
                <?php if (!empty($entity['phone'])): ?>
                <div class="flex gap-3">
                    <i class="bi-telephone text-purple-400"></i>
                    <div class="text-white/80"><?= htmlspecialchars($entity['phone']) ?></div>
                </div>
                <?php endif; ?>
            </div>
            <button class="w-full mt-6 py-3 rounded-xl bg-purple-500/20 hover:bg-purple-500/30 text-purple-400 text-xs font-bold uppercase tracking-widest transition-all">Get Directions</button>
        </div>

        <!-- Venue Metrics -->
        <div class="bg-gradient-to-br from-purple-900/20 to-transparent border border-purple-500/20 rounded-2xl p-6">
            <div class="flex justify-between items-end mb-2">
                <span class="text-xs font-bold uppercase tracking-widest text-white/40">Popularity</span>
                <span class="text-xl font-black text-purple-400">Top 10%</span>
            </div>
            <div class="h-1.5 w-full bg-white/5 rounded-full overflow-hidden">
                <div class="h-full bg-purple-500 rounded-full" style="width: 92%"></div>
            </div>
        </div>
    </div>
</div>
