<?php
/**
 * NGN Sovereign Home View v3.1.1
 * Mobile-Optimized Discovery & Onboarding
 */
?>

<?php include $root . 'lib/partials/story-engine.php'; ?>
<?php include $root . 'lib/partials/stats-ticker.php'; ?>

<!-- Sovereign Stations: Institutional Radio -->
<section class="mb-16">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
        <div>
            <h2 class="text-2xl md:text-3xl font-black tracking-tight text-white uppercase italic">Institutional_Stations</h2>
            <p class="text-zinc-500 font-mono text-[9px] md:text-[10px] uppercase tracking-widest mt-1">Live Global Airplay Tracking</p>
        </div>
        <div class="hidden md:block h-px flex-1 bg-white/5 mx-8"></div>
        <a href="/stations" class="text-[10px] font-black text-zinc-500 hover:text-white uppercase tracking-widest transition-colors shrink-0">Tune_In</a>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8">
        <!-- THE RAGE ONLINE -->
        <div class="relative h-56 md:h-64 rounded-3xl overflow-hidden group cursor-pointer border border-brand/20 bg-zinc-900"
             data-play-track
             data-track-url="https://ice1.somafm.com/groovesalad-256-mp3"
             data-track-title="The Rage Online"
             data-track-artist="Sovereign Broadcast"
             data-track-art="/lib/images/users/the-rage-online/The Rage Slash Bitly.png">
            <img src="/lib/images/users/the-rage-online/The Rage Slash Bitly.png" class="absolute inset-0 w-full h-full object-cover transition-transform duration-[2000ms] group-hover:scale-110 opacity-60">
            <div class="absolute inset-0 bg-gradient-to-r from-black via-black/40 to-transparent"></div>
            <div class="absolute inset-0 p-6 md:p-10 flex flex-col justify-center">
                <div class="flex items-center gap-2 mb-3 md:mb-4">
                    <span class="w-2 h-2 bg-brand rounded-full animate-pulse"></span>
                    <span class="text-[9px] md:text-[10px] font-black text-brand uppercase tracking-[0.2em]">Live_Signal</span>
                </div>
                <h3 class="text-2xl md:text-4xl font-black text-white uppercase tracking-tighter mb-2">The Rage Online</h3>
                <p class="hidden sm:block text-sm text-zinc-400 font-medium max-w-xs mb-6">The heartbeat of independent rock. Zero gatekeepers. Pure sound.</p>
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 md:w-12 md:h-12 bg-brand text-black rounded-full flex items-center justify-center shadow-2xl shadow-brand/40 group-hover:scale-110 transition-transform">
                        <i class="bi bi-play-fill text-xl md:text-2xl ml-1"></i>
                    </div>
                    <span class="text-[8px] md:text-[9px] font-black text-white/40 uppercase tracking-[0.3em]">Institutional Radio</span>
                </div>
            </div>
        </div>

        <!-- THE SOUND 228 -->
        <div class="relative h-56 md:h-64 rounded-3xl overflow-hidden group cursor-pointer border border-white/5 bg-zinc-900"
             data-play-track
             data-track-url="https://ice1.somafm.com/dronezone-256-mp3"
             data-track-title="The Sound 228"
             data-track-artist="Verified Station"
             data-track-art="/lib/images/users/the-sound-228/IMG_0136.jpeg">
            <img src="/lib/images/users/the-sound-228/IMG_0136.jpeg" class="absolute inset-0 w-full h-full object-cover transition-transform duration-[2000ms] group-hover:scale-110 opacity-60">
            <div class="absolute inset-0 bg-gradient-to-r from-black via-black/40 to-transparent"></div>
            <div class="absolute inset-0 p-6 md:p-10 flex flex-col justify-center">
                <div class="flex items-center gap-2 mb-3 md:mb-4 text-emerald-500">
                    <i class="bi bi-broadcast text-lg"></i>
                    <span class="text-[9px] md:text-[10px] font-black uppercase tracking-[0.2em]">Verified_Network</span>
                </div>
                <h3 class="text-2xl md:text-4xl font-black text-white uppercase tracking-tighter mb-2">The Sound 228</h3>
                <p class="hidden sm:block text-sm text-zinc-400 font-medium max-w-xs mb-6">Global spins. Real-time metrics. The future of terrestrial FM.</p>
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 md:w-12 md:h-12 bg-white text-black rounded-full flex items-center justify-center shadow-2xl group-hover:scale-110 transition-transform">
                        <i class="bi bi-play-fill text-xl md:text-2xl ml-1"></i>
                    </div>
                    <span class="text-[8px] md:text-[9px] font-black text-white/40 uppercase tracking-[0.3em]">Mississippi_Operator</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Sovereign Release Radar: New Music Drops -->
<section class="mb-16">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
        <div>
            <h2 class="text-2xl md:text-3xl font-black tracking-tight text-white uppercase italic">Release_Radar</h2>
            <p class="text-zinc-500 font-mono text-[9px] md:text-[10px] uppercase tracking-widest mt-1">Institutional Music Distribution</p>
        </div>
        <div class="hidden md:block h-px flex-1 bg-white/5 mx-8"></div>
        <a href="/releases" class="text-[10px] font-black text-zinc-500 hover:text-white uppercase tracking-widest transition-colors shrink-0">View_Drops</a>
    </div>
    
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
        <!-- Heroes and Villains: Evermore -->
        <div class="group cursor-pointer" 
             data-play-track
             data-track-id="1"
             data-track-title="Evermore"
             data-track-artist="Heroes and Villains"
             data-track-art="/lib/images/releases/heroes-and-villains/evermore-single-cover-1200x1200.jpg">
            <div class="relative aspect-square rounded-2xl overflow-hidden mb-4 border border-white/5 shadow-2xl">
                <img src="/lib/images/releases/heroes-and-villains/evermore-single-cover-1200x1200.jpg" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                    <div class="w-12 h-12 md:w-16 md:h-16 bg-brand text-black rounded-full flex items-center justify-center shadow-2xl">
                        <i class="bi bi-play-fill text-3xl md:text-4xl ml-1"></i>
                    </div>
                </div>
            </div>
            <div class="font-black text-white text-sm md:text-base truncate px-1">Evermore</div>
            <div class="text-[9px] md:text-[10px] font-black text-zinc-500 uppercase tracking-widest px-1 mt-1">Heroes and Villains</div>
        </div>

        <!-- Heroes and Villains: Alone Together -->
        <div class="group cursor-pointer" 
             data-play-track
             data-track-id="2"
             data-track-title="Alone Together"
             data-track-artist="Heroes and Villains"
             data-track-art="/lib/images/posts/heroes-and-villains/from-the-wastelands-heart-a-new-anthem-alone-together-arrives-november-29th.jpg">
            <div class="relative aspect-square rounded-2xl overflow-hidden mb-4 border border-white/5 shadow-2xl">
                <img src="/lib/images/posts/heroes-and-villains/from-the-wastelands-heart-a-new-anthem-alone-together-arrives-november-29th.jpg" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                    <div class="w-12 h-12 md:w-16 md:h-16 bg-brand text-black rounded-full flex items-center justify-center shadow-2xl">
                        <i class="bi bi-play-fill text-3xl md:text-4xl ml-1"></i>
                    </div>
                </div>
            </div>
            <div class="font-black text-white text-sm md:text-base truncate px-1">Alone Together</div>
            <div class="text-[9px] md:text-[10px] font-black text-zinc-500 uppercase tracking-widest px-1 mt-1">Heroes and Villains</div>
        </div>

        <!-- Coldwards: Sunflower -->
        <div class="group cursor-pointer" 
             data-play-track
             data-track-id="3"
             data-track-title="Sunflower"
             data-track-artist="Coldwards"
             data-track-art="/lib/images/releases/coldwards/coldwards-sunflower-cover.jpg">
            <div class="relative aspect-square rounded-2xl overflow-hidden mb-4 border border-white/5 shadow-2xl">
                <img src="/lib/images/releases/coldwards/coldwards-sunflower-cover.jpg" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                    <div class="w-12 h-12 md:w-16 md:h-16 bg-brand text-black rounded-full flex items-center justify-center shadow-2xl">
                        <i class="bi bi-play-fill text-3xl md:text-4xl ml-1"></i>
                    </div>
                </div>
            </div>
            <div class="font-black text-white text-sm md:text-base truncate px-1">Sunflower</div>
            <div class="text-[9px] md:text-[10px] font-black text-zinc-500 uppercase tracking-widest px-1 mt-1">Coldwards</div>
        </div>

        <!-- Heroes and Villains: Times Up -->
        <div class="group cursor-pointer" 
             data-play-track
             data-track-id="4"
             data-track-title="Times Up"
             data-track-artist="Heroes and Villains"
             data-track-art="/lib/images/releases/heroes-and-villains/times-up-featuring-tacboy-mike-mexas-brock-starr.jpg">
            <div class="relative aspect-square rounded-2xl overflow-hidden mb-4 border border-white/5 shadow-2xl">
                <img src="/lib/images/releases/heroes-and-villains/times-up-featuring-tacboy-mike-mexas-brock-starr.jpg" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                    <div class="w-12 h-12 md:w-16 md:h-16 bg-brand text-black rounded-full flex items-center justify-center shadow-2xl">
                        <i class="bi bi-play-fill text-3xl md:text-4xl ml-1"></i>
                    </div>
                </div>
            </div>
            <div class="font-black text-white text-sm md:text-base truncate px-1">Times Up</div>
            <div class="text-[9px] md:text-[10px] font-black text-zinc-500 uppercase tracking-widest px-1 mt-1">Heroes and Villains</div>
        </div>
    </div>
</section>

<!-- Trending Artists -->
<?php if (!empty($data['trending_artists'])): ?>
<section class="mb-16">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
        <div>
            <h2 class="text-2xl md:text-3xl font-black tracking-tight text-white uppercase italic">Trending_Artists</h2>
            <p class="text-zinc-500 font-mono text-[9px] md:text-[10px] uppercase tracking-widest mt-1">Institutional Engagement Monitoring</p>
        </div>
        <div class="hidden md:block h-px flex-1 bg-white/5 mx-8"></div>
        <a href="/artists" class="text-[10px] font-black text-zinc-500 hover:text-white uppercase tracking-widest transition-colors shrink-0">View_Fleet</a>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 md:gap-6">
    <?php foreach ($data['trending_artists'] as $artist): ?>
    <?php 
        $artSlug = $artist['slug'] ?? $artist['Slug'] ?? '';
        $artImg  = $artist['image_url'] ?? $artist['Image'] ?? '';
        $artistImg = user_image($artSlug, $artImg);
    ?>
    <a href="/artist/<?= htmlspecialchars($artSlug ?: $artist['id']) ?>" class="group sp-card border border-white/5 flex flex-col">
        <div class="relative aspect-square mb-4 shadow-2xl">
        <img src="<?= htmlspecialchars($artistImg) ?>" alt="" class="w-full h-full object-cover rounded-xl bg-zinc-800 shadow-xl group-hover:scale-[1.02] transition-transform duration-500" onerror="this.onerror=null;this.src='<?= DEFAULT_AVATAR ?>'">
        <button class="absolute bottom-3 right-3 w-10 h-10 md:w-12 md:h-12 bg-brand text-black rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 translate-y-2 group-hover:translate-y-0 transition-all shadow-xl shadow-black/40">
            <i class="bi bi-play-fill text-xl md:text-2xl"></i>
        </button>
        </div>
        <div class="font-black truncate text-white text-sm md:text-base"><?= htmlspecialchars($artist['name'] ?? $artist['Name'] ?? 'Unknown Artist') ?></div>
        <div class="text-[9px] md:text-[10px] font-bold text-zinc-500 uppercase tracking-tighter mt-1"><?= htmlspecialchars($artist['engagement_count'] ?? '0') ?> signals</div>
    </a>
    <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php include $root . 'lib/partials/onboarding-hub.php'; ?>

<!-- Latest News -->
<?php if (!empty($data['posts'])): ?>
<section class="mb-16">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
        <div>
            <h2 class="text-2xl md:text-3xl font-black tracking-tight text-white uppercase italic">Intelligence_Newswire</h2>
            <p class="text-zinc-500 font-mono text-[9px] md:text-[10px] uppercase tracking-widest mt-1">Real-time Industry Reporting</p>
        </div>
        <div class="hidden md:block h-px flex-1 bg-white/5 mx-8"></div>
        <a href="/posts" class="text-[10px] font-black text-zinc-500 hover:text-white uppercase tracking-widest transition-colors shrink-0">Show_All</a>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
    <?php foreach ($data['posts'] as $post): ?>
    <?php 
        $postImg = post_image($post['featured_image_url'] ?? '');
    ?>
    <a href="/post/<?= htmlspecialchars($post['slug'] ?? $post['id']) ?>" class="group flex flex-col">
        <div class="aspect-video rounded-xl overflow-hidden mb-4 border border-white/5 bg-zinc-900 shadow-2xl">
        <img src="<?= htmlspecialchars($postImg) ?>" alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 bg-zinc-800" onerror="this.onerror=null;this.src='<?= DEFAULT_AVATAR ?>'">
        </div>
        <div class="font-black text-sm text-white line-clamp-2 leading-tight group-hover:text-brand transition-colors"><?= htmlspecialchars($post['title'] ?? 'Untitled Story') ?></div>
        <div class="text-[9px] font-black text-zinc-500 uppercase tracking-widest mt-2"><?= ($post['published_at'] ?? null) ? date('M j, Y', strtotime($post['published_at'])) : '' ?></div>
    </a>
    <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- NGN Rankings Quick Charts -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-12 mb-16">
    <!-- Artists Chart -->
    <?php if (!empty($data['artist_rankings'])): ?>
    <div>
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h2 class="text-xl font-black uppercase tracking-tighter text-white">Top Artists</h2>
        <a href="/charts" class="text-brand text-[10px] font-black uppercase tracking-widest hover:underline shrink-0">View All →</a>
        </div>
        <div class="bg-white/5 rounded-2xl border border-white/5 overflow-hidden">
        <table class="w-full">
            <tbody>
                <?php foreach (array_slice($data['artist_rankings'], 0, 5) as $i => $ranking): ?>
                <tr class="border-b border-white/5 last:border-0 hover:bg-white/5 transition-colors">
                <td class="px-6 py-4 text-xs font-black text-zinc-500"><?= $i + 1 ?></td>
                <td class="px-6 py-4">
                    <a href="/artist/<?= htmlspecialchars($ranking['slug'] ?? '') ?>" class="text-white hover:text-brand font-black text-sm">
                    <?= htmlspecialchars($ranking['Name'] ?? 'Unknown') ?>
                    </a>
                </td>
                <td class="px-6 py-4 text-right text-xs font-black text-zinc-400"><?= number_format($ranking['Score'] ?? 0, 0) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Labels Chart -->
    <?php if (!empty($data['label_rankings'])): ?>
    <div>
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h2 class="text-xl font-black uppercase tracking-tighter text-white">Top Labels</h2>
        <a href="/charts?type=labels" class="text-brand text-[10px] font-black uppercase tracking-widest hover:underline shrink-0">View All →</a>
        </div>
        <div class="bg-white/5 rounded-2xl border border-white/5 overflow-hidden">
        <table class="w-full">
            <tbody>
                <?php foreach (array_slice($data['label_rankings'], 0, 5) as $i => $label): ?>
                <tr class="border-b border-white/5 last:border-0 hover:bg-white/5 transition-colors">
                <td class="px-6 py-4 text-xs font-black text-zinc-500"><?= $i + 1 ?></td>
                <td class="px-6 py-4">
                    <a href="/label/<?= htmlspecialchars($label['slug'] ?? '') ?>" class="text-white hover:text-brand font-black text-sm">
                    <?= htmlspecialchars($label['Name'] ?? 'Unknown') ?>
                    </a>
                </td>
                <td class="px-6 py-4 text-right text-xs font-black text-zinc-400"><?= number_format($label['Score'] ?? 0, 0) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>
</div>
