<?php
/**
 * NGN Sovereign Home View
 * Refactored into specialized high-velocity partials
 */
$featuredPosts = get_ngn_posts($pdo, '', 1, 4);
?>

<?php include $root . 'lib/partials/story-engine.php'; ?>
<?php include $root . 'lib/partials/stats-ticker.php'; ?>

<!-- Pressurized Discovery -->
<section class="mb-16">
    <div class="flex items-center justify-between mb-8">
    <h2 class="text-3xl font-black tracking-tight text-white uppercase italic">Sovereign_Discovery</h2>
    <div class="h-px flex-1 bg-white/5 mx-8"></div>
    <a href="/releases" class="text-[10px] font-black text-zinc-500 hover:text-white uppercase tracking-widest transition-colors">View_Archive</a>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
    <!-- Featured Station: THE RAGE -->
    <div class="relative aspect-square rounded-[2rem] overflow-hidden group cursor-pointer border border-brand/20"
            data-play-track
            data-track-url="https://ice1.somafm.com/groovesalad-256-mp3"
            data-track-title="The Rage Online"
            data-track-artist="Sovereign Broadcast"
            data-track-art="/lib/images/users/the-rage-online/The Rage Slash Bitly.png">
        <img src="/lib/images/users/the-rage-online/The Rage Slash Bitly.png" class="absolute inset-0 w-full h-full object-cover transition-transform duration-1000 group-hover:scale-110">
        <div class="absolute inset-0 bg-gradient-to-t from-black via-black/20 to-transparent"></div>
        <div class="absolute inset-0 p-8 flex flex-col justify-end">
            <div class="flex items-center gap-2 mb-3">
                <span class="w-2 h-2 bg-brand rounded-full animate-pulse"></span>
                <span class="text-[10px] font-black text-brand uppercase tracking-widest">Live_Signal</span>
            </div>
            <h3 class="text-3xl font-black text-white uppercase tracking-tighter mb-4">The Rage<br>Online</h3>
            <div class="flex items-center justify-between">
                <span class="text-[9px] font-black text-white/40 uppercase tracking-[0.3em]">Institutional Radio</span>
                <div class="w-12 h-12 bg-brand text-black rounded-full flex items-center justify-center shadow-2xl scale-90 group-hover:scale-100 transition-all">
                    <i class="bi bi-play-fill text-2xl ml-1"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- New Release: Heroes and Villains -->
    <div class="relative aspect-square rounded-[2rem] overflow-hidden group cursor-pointer border border-white/5"
            data-play-track
            data-track-id="1"
            data-track-title="Evermore"
            data-track-artist="Heroes and Villains"
            data-track-art="/lib/images/releases/heroes-and-villains/evermore-single-cover-1200x1200.jpg">
        <img src="/lib/images/releases/heroes-and-villains/evermore-single-cover-1200x1200.jpg" class="absolute inset-0 w-full h-full object-cover transition-transform duration-1000 group-hover:scale-110">
        <div class="absolute inset-0 bg-gradient-to-t from-black via-black/20 to-transparent"></div>
        <div class="absolute inset-0 p-8 flex flex-col justify-end">
            <div class="text-[10px] font-black text-zinc-400 uppercase tracking-widest mb-3">New_Drop</div>
            <h3 class="text-3xl font-black text-white uppercase tracking-tighter mb-4">Evermore</h3>
            <div class="flex items-center justify-between">
                <span class="text-[9px] font-black text-white/40 uppercase tracking-[0.3em]">Heroes and Villains</span>
                <div class="w-12 h-12 bg-white text-black rounded-full flex items-center justify-center shadow-2xl scale-90 group-hover:scale-100 transition-all">
                    <i class="bi bi-play-fill text-2xl ml-1"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- New Release: Heroes and Villains #2 -->
    <div class="relative aspect-square rounded-[2rem] overflow-hidden group cursor-pointer border border-white/5"
            data-play-track
            data-track-id="2"
            data-track-title="Alone Together"
            data-track-artist="Heroes and Villains"
            data-track-art="/lib/images/posts/heroes-and-villains/from-the-wastelands-heart-a-new-anthem-alone-together-arrives-november-29th.jpg">
        <img src="/lib/images/posts/heroes-and-villains/from-the-wastelands-heart-a-new-anthem-alone-together-arrives-november-29th.jpg" class="absolute inset-0 w-full h-full object-cover transition-transform duration-1000 group-hover:scale-110">
        <div class="absolute inset-0 bg-gradient-to-t from-black via-black/20 to-transparent"></div>
        <div class="absolute inset-0 p-8 flex flex-col justify-end">
            <div class="text-[10px] font-black text-zinc-400 uppercase tracking-widest mb-3">Trending_Now</div>
            <h3 class="text-3xl font-black text-white uppercase tracking-tighter mb-4">Alone Together</h3>
            <div class="flex items-center justify-between">
                <span class="text-[9px] font-black text-white/40 uppercase tracking-[0.3em]">Heroes and Villains</span>
                <div class="w-12 h-12 bg-white text-black rounded-full flex items-center justify-center shadow-2xl scale-90 group-hover:scale-100 transition-all">
                    <i class="bi bi-play-fill text-2xl ml-1"></i>
                </div>
            </div>
        </div>
    </div>
    </div>
</section>

<!-- Trending Artists -->
<?php if (!empty($data['trending_artists'])): ?>
<section class="mb-12">
    <div class="flex items-center justify-between mb-6">
    <h2 class="text-2xl font-black tracking-tight text-white">Trending Artists</h2>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-6">
    <?php foreach ($data['trending_artists'] as $artist): ?>
    <?php 
        $artSlug = $artist['slug'] ?? $artist['Slug'] ?? '';
        $artImg  = $artist['image_url'] ?? $artist['Image'] ?? '';
        $artistImg = user_image($artSlug, $artImg);
    ?>
    <a href="/artist/<?= htmlspecialchars($artSlug ?: $artist['id']) ?>" class="group sp-card border border-white/5 flex flex-col">
        <div class="relative aspect-square mb-4 shadow-2xl">
        <img src="<?= htmlspecialchars($artistImg) ?>" alt="" class="w-full h-full object-cover rounded-xl bg-zinc-800 shadow-xl group-hover:scale-[1.02] transition-transform duration-500" onerror="this.onerror=null;this.src='<?= DEFAULT_AVATAR ?>'">
        <button class="absolute bottom-3 right-3 w-12 h-12 bg-brand text-black rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 translate-y-2 group-hover:translate-y-0 transition-all shadow-xl shadow-black/40">
            <i class="bi bi-play-fill text-2xl"></i>
        </button>
        </div>
        <div class="font-black truncate text-white"><?= htmlspecialchars($artist['name'] ?? $artist['Name'] ?? 'Unknown Artist') ?></div>
        <div class="text-xs font-bold text-zinc-500 uppercase tracking-tighter mt-1"><?= htmlspecialchars($artist['engagement_count'] ?? '0') ?> signals</div>
    </a>
    <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php include $root . 'lib/partials/onboarding-hub.php'; ?>

<!-- Latest News -->
<?php if (!empty($data['posts'])): ?>
<section class="mb-12">
    <div class="flex items-center justify-between mb-6">
    <h2 class="text-2xl font-black tracking-tight text-white">Latest News</h2>
    <a href="/posts" class="text-sm font-black text-zinc-500 hover:text-white uppercase tracking-widest">Show All</a>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
    <?php foreach ($data['posts'] as $post): ?>
    <?php 
        $postImg = post_image($post['featured_image_url'] ?? '');
    ?>
    <a href="/post/<?= htmlspecialchars($post['slug'] ?? $post['id']) ?>" class="group flex flex-col">
        <div class="aspect-video rounded-xl overflow-hidden mb-4 border border-white/5">
        <img src="<?= htmlspecialchars($postImg) ?>" alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 bg-zinc-800" onerror="this.onerror=null;this.src='<?= DEFAULT_AVATAR ?>'">
        </div>
        <div class="font-black text-sm text-white line-clamp-2 leading-tight group-hover:text-brand transition-colors"><?= htmlspecialchars($post['title'] ?? 'Untitled Story') ?></div>
        <div class="text-[10px] font-black text-zinc-500 uppercase tracking-widest mt-2"><?= ($post['published_at'] ?? null) ? date('M j, Y', strtotime($post['published_at'])) : '' ?></div>
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
        <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-black uppercase tracking-tighter text-white">Top Artists</h2>
        <a href="/charts" class="text-brand text-[10px] font-black uppercase tracking-widest hover:underline">View All →</a>
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
        <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-black uppercase tracking-tighter text-white">Top Labels</h2>
        <a href="/charts?type=labels" class="text-brand text-[10px] font-black uppercase tracking-widest hover:underline">View All →</a>
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
