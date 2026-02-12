<?php
/**
 * Venue Profile Partial - Modern 2.0
 */
$venue = $entity;
$venueName = $venue['name'] ?? 'Unknown Venue';
$venueSlug = $venue['slug'] ?? '';
$isClaimed = !empty($venue['user_id']);

// Robust Image Detection
$venueImg = DEFAULT_AVATAR;
if (!empty($venue['image_url'])) {
    if (str_starts_with($venue['image_url'], 'http') || str_starts_with($venue['image_url'], '/')) {
        $venueImg = $venue['image_url'];
    } else {
        $paths = [
            "/uploads/venues/{$venueSlug}/{$venue['image_url']}",
            "/uploads/users/{$venueSlug}/{$venue['image_url']}"
        ];
        foreach ($paths as $p) {
            if (file_exists(dirname(__DIR__, 3) . '/public' . $p)) {
                $venueImg = $p;
                break;
            }
        }
    }
}

$bio = $venue['bio'] ?? '';
$scores = $venue['scores'] ?? ['Score' => 0];
?>

<div class="venue-profile">
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
                    <?php if ($isClaimed): ?>
                        <span class="px-3 py-1 bg-purple-500 text-black text-[10px] font-black uppercase tracking-[0.2em] rounded-full">Verified Venue</span>
                        <button onclick="openDisputeModal('venue', <?= $venue['id'] ?>)" class="px-3 py-1 bg-white/5 text-zinc-500 text-[10px] font-black uppercase tracking-[0.2em] rounded-full border border-white/10 hover:bg-red-500 hover:text-white transition-all">Dispute Claim</button>
                    <?php else: ?>
                        <a href="/claim-profile.php?slug=<?= urlencode($venueSlug) ?>" class="px-3 py-1 bg-white/10 text-white text-[10px] font-black uppercase tracking-[0.2em] rounded-full border border-white/10 hover:bg-purple-500 hover:text-black transition-all">Claim Venue</a>
                    <?php endif; ?>
                </div>
                <h1 class="text-5xl lg:text-8xl font-black mb-6 tracking-tighter leading-none text-white drop-shadow-2xl"><?= htmlspecialchars($venueName) ?></h1>
                <div class="flex flex-wrap items-center justify-center md:justify-start gap-6 text-sm font-black uppercase tracking-widest text-zinc-400">
                    <div class="flex items-center gap-2"><i class="bi bi-lightning-charge-fill text-purple-400"></i> Score: <?= number_format($scores['Score'] ?? 0) ?></div>
                    <div class="flex items-center gap-2"><i class="bi-people-fill text-purple-400"></i> Cap: <?= number_format($venue['capacity'] ?? 0) ?></div>
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
                    <h2 class="text-3xl font-black tracking-tight text-white">Show Calendar</h2>
                </div>
                
                <?php if (!empty($venue['upcoming_shows'])): ?>
                    <div class="bg-zinc-900/30 rounded-3xl border border-white/5 overflow-hidden">
                        <div class="divide-y divide-white/5">
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
                                        <span>Capacity: <?= number_format($venue['capacity'] ?? 0) ?></span>
                                    </div>
                                </div>
                                <?php if (!empty($show['ticket_url'])): ?>
                                <a href="<?= htmlspecialchars($show['ticket_url']) ?>" target="_blank" class="px-8 py-3 rounded-full bg-white text-black font-black hover:scale-105 transition-all text-xs uppercase tracking-widest shadow-xl">Tickets</a>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <?php render_profile_upsell("Upcoming Events", "Schedule your shows and sell tickets directly through NGN. Automated payouts, fan notifications, and verified check-ins.", $isClaimed, $venueSlug); ?>
                <?php endif; ?>
            </section>

            <!-- Latest Posts -->
            <section>
                <h2 class="text-3xl font-black mb-8 tracking-tight">Venue Updates</h2>
                <?php if (!empty($entity['posts'])): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <?php foreach ($entity['posts'] as $post): ?>
                        <?php 
                            $postImg = $post['featured_image_url'] ?? DEFAULT_AVATAR;
                            if ($postImg && !str_starts_with($postImg, 'http') && !str_starts_with($postImg, '/')) {
                                $postImg = "/uploads/{$postImg}";
                            }
                        ?>
                        <a href="/post/<?= htmlspecialchars($post['slug'] ?? $post['id']) ?>" class="group flex flex-col bg-zinc-900/30 rounded-2xl overflow-hidden border border-white/5 hover:border-purple-500 transition-all">
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
                    <?php render_profile_upsell("Show Announcements", "Share gig lineups, venue rules, and local show news with your audience.", $isClaimed, $venueSlug); ?>
                <?php endif; ?>
            </section>

        </div>

        <!-- Sidebar Info -->
        <div class="lg:col-span-4 space-y-12">
            <!-- About -->
            <section class="sp-card border border-white/5 p-8">
                <h2 class="text-xs font-black text-zinc-500 uppercase tracking-[0.3em] mb-6">About</h2>
                <div class="prose prose-invert prose-sm text-zinc-400 font-medium leading-relaxed mb-8">
                    <?= !empty($bio) ? nl2br($bio) : 'A premier destination for live rock and metal. Experience the noise in its rawest form.' ?>
                </div>
                <div class="flex flex-wrap gap-4">
                    <?php foreach ($entity['social_links'] ?? [] as $provider => $url): ?>
                        <a href="<?= htmlspecialchars($url) ?>" target="_blank" class="w-10 h-10 rounded-full bg-white/5 border border-white/10 flex items-center justify-center text-white hover:bg-purple-500 hover:text-black transition-all">
                            <i class="bi bi-<?= $provider ?>"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </div>
</div>