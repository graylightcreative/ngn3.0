<?php
/**
 * Artist Profile Partial - Premium 2.0
 */

$artist = $entity;
$artistName = $artist['name'] ?? 'Unknown Artist';
$artistSlug = $artist['slug'] ?? '';
$isClaimed = !empty($artist['claimed']);

// Robust Image Detection
$artistImg = DEFAULT_AVATAR;
if (!empty($artist['image_url'])) {
    if (str_starts_with($artist['image_url'], 'http') || str_starts_with($artist['image_url'], '/')) {
        $artistImg = $artist['image_url'];
    } else {
        // Try multiple locations for migrated/legacy images
        $paths = [
            "/uploads/artists/{$artistSlug}/{$artist['image_url']}",
            "/uploads/users/{$artistSlug}/{$artist['image_url']}",
            "/uploads/posts/{$artist['image_url']}"
        ];
        foreach ($paths as $p) {
            if (file_exists(dirname(__DIR__, 3) . '/public' . $p)) {
                $artistImg = $p;
                break;
            }
        }
    }
}

$bio = $artist['bio'] ?? '';
$scores = $artist['scores'] ?? ['Score' => 0];
?>

<div class="artist-profile">
    <!-- HERO HEADER -->
    <div class="relative -mt-6 -mx-4 lg:-mx-8 mb-12 group h-[400px] flex items-end overflow-hidden">
        <!-- Immersive Background -->
        <div class="absolute inset-0 bg-gradient-to-br from-brand/40 to-black z-0 transition-transform duration-1000 group-hover:scale-105"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-transparent z-10"></div>
        
        <div class="relative z-20 p-8 lg:p-12 flex flex-col md:flex-row items-center md:items-end gap-10 w-full">
            <!-- Profile Image -->
            <div class="w-48 h-48 md:w-64 md:h-64 flex-shrink-0 shadow-[0_20px_50px_rgba(0,0,0,0.5)] rounded-2xl overflow-hidden bg-zinc-900 border-4 border-white/5 group-hover:scale-[1.02] transition-transform duration-500">
                <img src="<?= htmlspecialchars($artistImg) ?>" class="w-full h-full object-cover" alt="<?= htmlspecialchars($artistName) ?>" onerror="this.src='<?= DEFAULT_AVATAR ?>'">
            </div>
            
            <!-- Info -->
            <div class="flex-1 text-center md:text-left pb-4">
                <div class="flex items-center justify-center md:justify-start gap-2 mb-4">
                    <?php if ($isClaimed): ?>
                        <span class="px-3 py-1 bg-brand text-black text-[10px] font-black uppercase tracking-[0.2em] rounded-full">Verified Artist</span>
                        <button onclick="openDisputeModal('artist', <?= $artist['id'] ?>)" class="px-3 py-1 bg-white/5 text-zinc-500 text-[10px] font-black uppercase tracking-[0.2em] rounded-full border border-white/10 hover:bg-red-500 hover:text-white transition-all">Dispute Claim</button>
                    <?php else: ?>
                        <div class="inline-flex items-center gap-3 px-4 py-2 bg-brand/10 border border-brand/30 rounded-xl">
                            <i class="bi-shield-lock text-brand animate-pulse"></i>
                            <span class="text-white text-[10px] font-black uppercase tracking-widest">Ghost_Profile // Unclaimed</span>
                        </div>
                    <?php endif; ?>
                </div>
                <h1 class="text-5xl lg:text-8xl font-black text-white tracking-tighter mb-6 leading-none drop-shadow-2xl"><?= htmlspecialchars($artistName) ?></h1>
                
                <?php if (!$isClaimed): ?>
                <div class="mb-8">
                    <a href="/claim-profile.php?slug=<?= urlencode($artistSlug) ?>" class="inline-block px-10 py-4 bg-brand text-black font-black uppercase tracking-widest rounded-2xl hover:scale-105 transition-all shadow-2xl shadow-brand/40">
                        Claim Your Digital Moat
                    </a>
                    <p class="text-zinc-500 text-[10px] font-bold uppercase mt-3 italic">Verified artists keep 90% of all Sparks and Merch profit.</p>
                </div>
                <?php endif; ?>
                
                <div class="flex flex-wrap items-center justify-center md:justify-start gap-8 text-sm font-black text-white/80 uppercase tracking-widest">
                    <div class="flex flex-col">
                        <span class="text-brand text-xl leading-none mb-1"><?= number_format($scores['Score'] ?? 0) ?></span>
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
    </div>

    <!-- ACTION BAR (REVENUE ENGINE) -->
    <div class="sticky top-16 z-30 flex items-center justify-between py-6 mb-16 bg-black/50 backdrop-blur-xl -mx-4 px-4 lg:-mx-8 lg:px-8 border-b border-white/5">
        <div class="flex items-center gap-6">
            <button class="w-16 h-14 bg-brand text-black rounded-full flex items-center justify-center hover:scale-105 transition-all shadow-xl shadow-brand/20 group" data-play-all>
                <i class="bi bi-play-fill text-4xl ml-1 group-active:scale-90 transition-transform"></i>
            </button>
            <button class="px-8 py-3 rounded-full border-2 border-brand text-brand font-black text-xs uppercase tracking-widest hover:bg-brand hover:text-black transition-all shadow-[0_0_20px_rgba(255,95,31,0.2)]" onclick="tipArtist(<?= $artist['id'] ?>, 100)">
                <i class="bi bi-lightning-charge-fill mr-2"></i> Tip 100 Sparks
            </button>
            <a href="/shop/<?= $artistSlug ?>" class="px-8 py-3 rounded-full bg-white/10 text-white font-black text-xs uppercase tracking-widest hover:bg-white/20 border border-white/10 transition-all">
                <i class="bi bi-bag-check-fill mr-2"></i> Shop Merch
            </a>
        </div>
        
        <div class="flex items-center gap-4">
            <button class="hidden md:flex items-center gap-2 px-6 py-3 bg-white/5 hover:bg-white/10 rounded-full font-black text-xs uppercase tracking-widest border border-white/10 transition-all">Follow</button>
            <button class="w-12 h-12 rounded-full bg-white/5 border border-white/10 flex items-center justify-center text-white hover:bg-white/10"><i class="bi bi-share"></i></button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
        <!-- Main Content -->
        <div class="lg:col-span-8 space-y-20">
            
            <!-- Popular Tracks -->
            <section>
                <h2 class="text-3xl font-black mb-8 tracking-tight">Popular Tracks</h2>
                <?php if (!empty($entity['all_songs'])): ?>
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
                                <i class="bi bi-play-fill text-2xl opacity-0 group-hover:opacity-100 transition-opacity z-10"></i>
                                <img src="<?= htmlspecialchars($artistImg) ?>" class="absolute inset-0 w-full h-full object-cover opacity-40 group-hover:opacity-20 transition-opacity">
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-black text-white truncate"><?= htmlspecialchars($song['title']) ?></div>
                                <div class="text-[10px] text-zinc-500 font-bold uppercase tracking-widest mt-1"><?= htmlspecialchars($song['ReleaseName'] ?? 'Single') ?></div>
                            </div>
                            <div class="flex items-center gap-4">
                                <button class="px-3 py-1 bg-brand/10 hover:bg-brand text-brand hover:text-black text-[8px] font-black uppercase tracking-widest rounded-full border border-brand/20 transition-all" onclick="event.stopPropagation(); tipArtist(<?= $artist['id'] ?>, 10)">Tip 10</button>
                                <div class="text-sm font-mono text-zinc-500"><?= ($song['duration_seconds'] ?? 0) ? gmdate('i:s', $song['duration_seconds']) : '--:--' ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <?php render_profile_upsell("Music Library", "Upload your tracks to our global intelligence engine. Let fans listen, tip sparks, and boost your NGN score.", $isClaimed, $artistSlug); ?>
                <?php endif; ?>
            </section>

            <!-- Discography -->
            <section>
                <div class="flex items-center justify-between mb-8">
                    <h2 class="text-3xl font-black tracking-tight">Discography</h2>
                </div>
                <?php if (!empty($entity['releases'])): ?>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                        <?php foreach (array_slice($entity['releases'], 0, 4) as $release): ?>
                        <?php 
                            $releaseImg = ($release['cover_image_url'] ?? $release['cover_url'] ?? '') ?: DEFAULT_AVATAR; 
                            if ($releaseImg && !str_starts_with($releaseImg, 'http') && !str_starts_with($releaseImg, '/')) {
                                $releaseImg = "/uploads/releases/{$releaseImg}";
                            }
                        ?>
                        <div class="group">
                            <div class="aspect-square rounded-xl overflow-hidden mb-4 shadow-2xl relative border border-white/5">
                                <img src="<?= htmlspecialchars($releaseImg) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 bg-zinc-800" onerror="this.src='<?= DEFAULT_AVATAR ?>'">
                                <a href="/release/<?= htmlspecialchars($release['slug'] ?? $release['id']) ?>" class="absolute inset-0 z-10"></a>
                            </div>
                            <div class="font-black text-sm truncate text-white block"><?= htmlspecialchars($release['title']) ?></div>
                            <div class="text-[10px] font-black text-zinc-500 uppercase mt-1"><?= !empty($release['release_date']) ? date('Y', strtotime($release['release_date'])) : 'TBA' ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <?php render_profile_upsell("Releases", "Manage your albums, EPs, and singles. Link your merch and build your professional discography on NGN 2.0.", $isClaimed, $artistSlug); ?>
                <?php endif; ?>
            </section>

            <!-- Latest Posts -->
            <section>
                <h2 class="text-3xl font-black mb-8 tracking-tight">Latest Updates</h2>
                <?php if (!empty($entity['posts'])): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <?php foreach ($entity['posts'] as $post): ?>
                        <?php 
                            $postImg = $post['featured_image_url'] ?? DEFAULT_AVATAR;
                            if ($postImg && !str_starts_with($postImg, 'http') && !str_starts_with($postImg, '/')) {
                                $postImg = "/uploads/{$postImg}";
                            }
                        ?>
                        <a href="/post/<?= htmlspecialchars($post['slug'] ?? $post['id']) ?>" class="group flex flex-col bg-zinc-900/30 rounded-2xl overflow-hidden border border-white/5 hover:border-brand/50 transition-all">
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
                    <?php render_profile_upsell("Social Feed", "Connect with your fans directly. Share behind-the-scenes content, tour dates, and official announcements.", $isClaimed, $artistSlug); ?>
                <?php endif; ?>
            </section>

            <!-- Videos -->
            <section>
                <h2 class="text-3xl font-black mb-8 tracking-tight">Featured Videos</h2>
                <?php if (!empty($entity['all_videos'])): ?>
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
                <?php else: ?>
                    <?php render_profile_upsell("Video Vault", "Showcase your music videos, interviews, and live performances. Video engagement is a high-weight factor for NGN Rankings.", $isClaimed, $artistSlug); ?>
                <?php endif; ?>
            </section>

        </div>

        <!-- Sidebar Info -->
        <div class="lg:col-span-4 space-y-12">
            <!-- About -->
            <section class="sp-card border border-white/5 p-8">
                <h2 class="text-xs font-black text-zinc-500 uppercase tracking-[0.3em] mb-6">About</h2>
                <div class="prose prose-invert prose-sm text-zinc-400 font-medium leading-relaxed mb-8">
                    <?= !empty($bio) ? nl2br($bio) : 'No biography available for this artist.' ?>
                </div>
                <div class="flex flex-wrap gap-4">
                    <?php foreach ($entity['social_links'] ?? [] as $provider => $url): ?>
                        <a href="<?= htmlspecialchars($url) ?>" target="_blank" class="w-10 h-10 rounded-full bg-white/5 border border-white/10 flex items-center justify-center text-white hover:bg-brand hover:text-black transition-all">
                            <i class="bi bi-<?= $provider ?>"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Chart Performance -->
            <?php if (!empty($entity['chart_rankings'])): ?>
            <section class="sp-card border border-white/5 p-8">
                <h2 class="text-xs font-black text-zinc-500 uppercase tracking-[0.3em] mb-6">Chart History</h2>
                <div class="space-y-4">
                    <?php foreach (array_slice($entity['chart_rankings'], 0, 5) as $r): ?>
                    <div class="flex items-center gap-4">
                        <div class="w-8 font-black text-zinc-700 text-xs">#<?= $r['RankNum'] ?></div>
                        <div class="flex-1 h-1.5 bg-zinc-800 rounded-full overflow-hidden">
                            <div class="h-full bg-brand rounded-full" style="width: <?= min(100, ($r['Score'] ?? 0)/10) ?>%"></div>
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