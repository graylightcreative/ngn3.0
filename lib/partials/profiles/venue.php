<?php
/**
 * Venue Profile Partial - Modern 2.0
 */
$venue = $entity;
$venueName = $venue['name'] ?? 'Unknown Venue';
$venueImg = user_image($venue['slug'] ?? '', $venue['image_url'] ?? null);
$bio = $venue['bio'] ?? '';
$scores = $venue['scores'] ?? [];

// Unified Chart History Fetching
if (empty($venue['chart_rankings'])) {
    try {
        $rankingsPdo = \NGN\Lib\DB\ConnectionFactory::named($config, 'rankings2025');
        $stmt = $rankingsPdo->prepare('
            SELECT ri.rank AS RankNum, ri.score AS Score, rw.period_end AS PeriodEnd, rw.interval AS Interval
            FROM `ngn_rankings_2025`.`ranking_items` ri
            JOIN `ngn_rankings_2025`.`ranking_windows` rw ON ri.window_id = rw.id
            WHERE ri.entity_type = \'venue\' AND ri.entity_id = ?
            ORDER BY rw.period_end DESC LIMIT 10
        ');
        $stmt->execute([$venue['id']]);
        $venue['chart_rankings'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (\Throwable $e) {}
}
?>

<!-- HERO HEADER -->
<div class="relative -mt-8 -mx-8 mb-12 h-[450px] flex items-end overflow-hidden group">
    <!-- Immersive Background -->
    <div class="absolute inset-0 bg-gradient-to-br from-purple-600/40 to-black z-0 transition-transform duration-1000 group-hover:scale-105"></div>
    <div class="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-transparent z-10"></div>
    
    <div class="relative z-20 p-12 flex flex-col md:flex-row items-center md:items-end gap-10 w-full">
        <!-- Venue Image -->
        <div class="w-48 h-48 md:w-64 md:h-64 flex-shrink-0 shadow-[0_20px_50px_rgba(0,0,0,0.5)] rounded-3xl overflow-hidden bg-zinc-900 border-4 border-white/5 group-hover:scale-[1.02] transition-transform duration-500 relative">
            <img src="<?= htmlspecialchars($venueImg) ?>" class="w-full h-full object-cover" alt="<?= htmlspecialchars($venueName) ?>" onerror="this.src='<?= DEFAULT_AVATAR ?>'">
        </div>
        
        <!-- Info -->
        <div class="flex-1 text-center md:text-left">
            <div class="flex items-center justify-center md:justify-start gap-2 mb-4">
                <span class="px-3 py-1 bg-purple-500 text-black text-[10px] font-black uppercase tracking-[0.2em] rounded-full">Verified Venue</span>
                <?php if (!empty($scores['Score'])): ?>
                    <span class="px-3 py-1 bg-white/10 text-white text-[10px] font-black uppercase tracking-[0.2em] rounded-full border border-white/10">
                        Rank #<?= number_format($scores['Score'], 0) ?>
                    </span>
                <?php endif; ?>
            </div>
            <h1 class="text-5xl lg:text-8xl font-black mb-6 tracking-tighter leading-none text-white drop-shadow-2xl"><?= htmlspecialchars($venueName) ?></h1>
            <div class="flex flex-wrap items-center justify-center md:justify-start gap-6 text-sm font-black uppercase tracking-widest text-zinc-400">
                <div class="flex items-center gap-2"><i class="bi-calendar-event-fill text-purple-400"></i> <?= count($venue['upcoming_shows'] ?? []) ?> Upcoming Events</div>
                <div class="flex items-center gap-2"><i class="bi-geo-alt-fill text-purple-400"></i> <?= htmlspecialchars($venue['city'] ?? 'TBA') ?></div>
            </div>
        </div>
    </div>
</div>

<!-- ACTION BAR -->
<div class="flex flex-wrap items-center gap-6 mb-16 px-4">
    <button class="px-10 py-4 bg-purple-500 text-black font-black rounded-full hover:scale-105 transition-all shadow-xl shadow-purple-500/20 uppercase tracking-widest text-xs">Book Venue</button>
    <button class="w-14 h-14 rounded-full border-2 border-zinc-800 text-white flex items-center justify-center hover:border-white transition-all text-xl"><i class="bi-heart"></i></button>
    <button class="w-14 h-14 rounded-full border-2 border-zinc-800 text-white flex items-center justify-center hover:border-white transition-all text-xl"><i class="bi-share"></i></button>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-12 px-4">
    <div class="lg:col-span-8 space-y-20">
        
        <!-- Upcoming Events -->
        <section>
            <div class="flex items-center justify-between mb-8">
                <h2 class="text-3xl font-black tracking-tight text-white">Upcoming Events</h2>
            </div>
            
            <div class="bg-zinc-900/30 rounded-3xl border border-white/5 overflow-hidden">
                <div class="divide-y divide-white/5">
                    <?php if (!empty($venue['upcoming_shows'])): ?>
                        <?php foreach ($venue['upcoming_shows'] as $show): 
                            $showDate = strtotime($show['starts_at']);
                        ?>
                        <div class="flex items-center gap-8 p-8 hover:bg-white/5 transition-all group">
                            <div class="w-20 h-20 rounded-2xl bg-purple-500/10 text-purple-400 flex flex-col items-center justify-center flex-shrink-0 border border-purple-500/20 shadow-lg">
                                <span class="text-xs font-black uppercase"><?= date('M', $showDate) ?></span>
                                <span class="text-3xl font-black leading-none"><?= date('j', $showDate) ?></span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-black text-2xl truncate text-white group-hover:text-purple-400 transition-colors"><?= htmlspecialchars($show['title'] ?? 'Live Show') ?></div>
                                <div class="flex items-center gap-4 text-[10px] font-black text-zinc-500 uppercase tracking-[0.2em] mt-2">
                                    <span><?= date('g:i A', $showDate) ?></span>
                                    <span class="w-1 h-1 bg-zinc-800 rounded-full"></span>
                                    <span><?= htmlspecialchars($show['venue_name'] ?? $venueName) ?></span>
                                </div>
                            </div>
                            <?php if (!empty($show['ticket_url'])): ?>
                            <a href="<?= htmlspecialchars($show['ticket_url']) ?>" target="_blank" class="px-8 py-3 rounded-full bg-white text-black font-black hover:scale-105 transition-all text-xs uppercase tracking-widest shadow-xl">Tickets</a>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-24 text-center">
                            <i class="bi-calendar-x text-6xl text-zinc-800 mb-6 block"></i>
                            <div class="text-zinc-500 font-black uppercase tracking-widest text-xs">No Upcoming Events Scheduled</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- About / Story -->
        <?php if (!empty($bio)): ?>
        <section class="sp-card border border-white/5 p-12 relative overflow-hidden">
            <h2 class="text-sm font-black uppercase tracking-[0.3em] text-purple-400 mb-8">About the Space</h2>
            <div class="prose prose-invert max-w-none text-zinc-400 font-medium leading-[1.8] text-lg">
                <?= $bio ?>
            </div>
        </section>
        <?php endif; ?>

    </div>

    <!-- SIDEBAR -->
    <div class="lg:col-span-4 space-y-8">
        
        <!-- Intelligence -->
        <?php if ((new \NGN\Lib\Config())->featureAiEnabled()): ?>
        <div class="bg-zinc-900/50 rounded-3xl border border-white/5 p-8">
            <h3 class="text-xs font-black uppercase tracking-[0.2em] text-zinc-500 mb-8">Intelligence</h3>
            <div class="space-y-6">
                <div class="flex justify-between items-center">
                    <span class="text-zinc-400 font-bold">Network Rank</span>
                    <span class="text-3xl font-black text-purple-400"><?= number_format((float)($scores['Score'] ?? 0), 1) ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-zinc-400 font-bold">Engagement Score</span>
                    <span class="text-xl font-black text-white"><?= number_format($venue['engagement_metrics']['total_posts'] ?? 0) ?></span>
                </div>
                
                <div class="pt-6 border-t border-white/5">
                    <div class="text-[10px] font-black text-zinc-600 uppercase tracking-widest mb-4">Location</div>
                    <div class="flex items-start gap-3 text-white font-bold">
                        <i class="bi-geo-alt-fill text-purple-500 mt-1"></i>
                        <div class="text-sm">
                            <?= htmlspecialchars($venue['address'] ?? 'Address TBA') ?><br>
                            <?= htmlspecialchars($venue['city'] ?? '') ?><?= !empty($venue['region']) ? ', ' . htmlspecialchars($venue['region']) : '' ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Official Links -->
        <div class="bg-zinc-900/50 rounded-3xl border border-white/5 p-8">
            <h3 class="text-xs font-black uppercase tracking-[0.2em] text-zinc-500 mb-8">Official Links</h3>
            <div class="flex flex-col gap-3">
                <?php if (!empty($venue['website_url'])): ?>
                <a href="<?= htmlspecialchars($venue['website_url']) ?>" target="_blank" class="w-full py-4 rounded-xl bg-white/5 hover:bg-white/10 border border-white/5 font-black text-xs uppercase tracking-[0.2em] transition-all text-center">
                    <i class="bi-globe mr-2"></i> Website
                </a>
                <?php endif; ?>
                <?php if (!empty($venue['social_links']['facebook'])): ?>
                <a href="<?= htmlspecialchars($venue['social_links']['facebook']) ?>" target="_blank" class="w-full py-4 rounded-xl bg-white/5 hover:bg-white/10 border border-white/5 font-black text-xs uppercase tracking-[0.2em] transition-all text-center">
                    <i class="bi-facebook mr-2"></i> Facebook
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

