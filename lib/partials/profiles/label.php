<?php
/**
 * Label Profile Partial - Modern 2.0
 */
$label = $entity;
$labelName = $label['name'] ?? 'Unknown Label';
$labelSlug = $label['slug'] ?? '';
$isClaimed = !empty($label['claimed']);

// Use Authoritative Image Helper
$labelImg = user_image($labelSlug, $label['image_url'] ?? null);

$bio = $label['bio'] ?? '';
$scores = $label['scores'] ?? ['Score' => 0];
?>

<div class="label-profile">
    <!-- HERO HEADER -->
    <div class="relative -mt-8 -mx-8 mb-12 h-[400px] flex items-end overflow-hidden group">
        <!-- Immersive Background -->
        <div class="absolute inset-0 bg-gradient-to-br from-blue-600/40 to-black z-0 transition-transform duration-1000 group-hover:scale-105"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-transparent z-10"></div>
        
        <div class="relative z-20 p-12 flex flex-col md:flex-row items-center md:items-end gap-10 w-full">
            <!-- Profile Image -->
            <div class="w-48 h-48 md:w-64 md:h-64 flex-shrink-0 shadow-[0_20px_50px_rgba(0,0,0,0.5)] rounded-full overflow-hidden bg-zinc-900 border-4 border-white/5 group-hover:scale-[1.02] transition-transform duration-500 relative">
                <img src="<?= htmlspecialchars($labelImg) ?>" class="w-full h-full object-cover" alt="<?= htmlspecialchars($labelName) ?>" onerror="this.src='<?= DEFAULT_AVATAR ?>'">
            </div>
            
            <!-- Info -->
            <div class="flex-1 text-center md:text-left">
                <div class="flex items-center justify-center md:justify-start gap-2 mb-4">
                    <?php if ($isClaimed): ?>
                        <span class="px-3 py-1 bg-blue-500 text-black text-[10px] font-black uppercase tracking-[0.2em] rounded-full">Verified Label</span>
                        <button onclick="openDisputeModal('label', <?= $label['id'] ?>)" class="px-3 py-1 bg-white/5 text-zinc-500 text-[10px] font-black uppercase tracking-[0.2em] rounded-full border border-white/10 hover:bg-red-500 hover:text-white transition-all">Dispute Claim</button>
                    <?php else: ?>
                        <a href="/claim-profile.php?slug=<?= urlencode($labelSlug) ?>" class="px-3 py-1 bg-white/10 text-white text-[10px] font-black uppercase tracking-[0.2em] rounded-full border border-white/10 hover:bg-blue-500 hover:text-black transition-all">Claim Profile</a>
                    <?php endif; ?>
                </div>
                <h1 class="text-5xl lg:text-8xl font-black mb-6 tracking-tighter leading-none text-white drop-shadow-2xl"><?= htmlspecialchars($labelName) ?></h1>
                <div class="flex flex-wrap items-center justify-center md:justify-start gap-6 text-sm font-black uppercase tracking-widest text-zinc-400">
                    <div class="flex items-center gap-2"><i class="bi bi-lightning-charge-fill text-blue-400"></i> Score: <?= number_format($scores['Score'] ?? 0) ?></div>
                    <div class="flex items-center gap-2"><i class="bi-people-fill text-blue-400"></i> <?= count($label['roster'] ?? []) ?> Artists</div>
                    <div class="flex items-center gap-2"><i class="bi-disc-fill text-blue-400"></i> <?= count($label['releases'] ?? []) ?> Releases</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ACTION BAR -->
    <div class="flex flex-wrap items-center gap-6 mb-16 px-4">
        <button class="px-10 py-4 bg-blue-500 text-black font-black rounded-full hover:scale-105 transition-all shadow-xl shadow-blue-500/20 uppercase tracking-widest text-xs">Partner</button>
        <button class="w-14 h-14 rounded-full border-2 border-zinc-800 text-white flex items-center justify-center hover:border-white transition-all text-xl"><i class="bi-heart"></i></button>
        <button class="w-14 h-14 rounded-full border-2 border-zinc-800 text-white flex items-center justify-center hover:border-white transition-all text-xl"><i class="bi-share"></i></button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 px-4">
        <div class="lg:col-span-8 space-y-20">
            
            <!-- Artist Roster -->
            <section>
                <div class="flex items-center justify-between mb-8">
                    <h2 class="text-3xl font-black tracking-tight text-white">The Roster</h2>
                </div>
                <?php if (!empty($label['roster'])): ?>
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
                <?php else: ?>
                    <?php render_profile_upsell("Artist Roster", "Unify your roster on the Sovereign fleet. Aggregate your artists' scores, monitor collective reach, and optimize institutional growth.", $isClaimed, $labelSlug); ?>
                <?php endif; ?>
            </section>

            <!-- Label Releases -->
            <section>
                <div class="flex items-center justify-between mb-8">
                    <h2 class="text-3xl font-black tracking-tight text-white">Latest Drops</h2>
                </div>
                <?php if (!empty($label['releases'])): ?>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                        <?php foreach ($label['releases'] as $release): ?>
                        <?php 
                            $releaseImg = ($release['cover_image_url'] ?? $release['cover_url'] ?? '') ?: DEFAULT_AVATAR; 
                            if ($releaseImg && !str_starts_with($releaseImg, 'http') && !str_starts_with($releaseImg, '/')) {
                                $releaseImg = "/uploads/releases/{$releaseImg}";
                            }
                        ?>
                        <div class="group">
                            <div class="aspect-square rounded-2xl overflow-hidden mb-4 shadow-2xl relative border border-white/5 group-hover:border-blue-500 transition-colors">
                                <img src="<?= htmlspecialchars($releaseImg) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" onerror="this.src='<?= DEFAULT_AVATAR ?>'">
                                <a href="/release/<?= htmlspecialchars($release['slug'] ?? $release['id']) ?>" class="absolute inset-0 z-10"></a>
                            </div>
                            <div class="font-black text-sm truncate text-white block group-hover:text-blue-400 transition-colors"><?= htmlspecialchars($release['title'] ?? 'Untitled') ?></div>
                            <div class="text-[10px] font-black text-zinc-500 uppercase tracking-widest mt-1"><?= htmlspecialchars($release['ArtistName'] ?? 'Various Artists') ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <?php render_profile_upsell("Catalog Management", "Showcase your entire catalog. Link merchandise, track royalties, and use our AI Coach to find the perfect release timing.", $isClaimed, $labelSlug); ?>
                <?php endif; ?>
            </section>

            <!-- Latest Posts -->
            <section>
                <h2 class="text-3xl font-black mb-8 tracking-tight">Press & News</h2>
                <?php if (!empty($entity['posts'])): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <?php foreach ($entity['posts'] as $post): ?>
                        <?php 
                            $postImg = $post['featured_image_url'] ?? DEFAULT_AVATAR;
                            if ($postImg && !str_starts_with($postImg, 'http') && !str_starts_with($postImg, '/')) {
                                $postImg = "/uploads/{$postImg}";
                            }
                        ?>
                        <a href="/post/<?= htmlspecialchars($post['slug'] ?? $post['id']) ?>" class="group flex flex-col bg-zinc-900/30 rounded-2xl overflow-hidden border border-white/5 hover:border-blue-500 transition-all">
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
                    <?php render_profile_upsell("Label News Feed", "Publish label updates, signing announcements, and tour press releases directly to your followers.", $isClaimed, $labelSlug); ?>
                <?php endif; ?>
            </section>

        </div>

        <!-- Sidebar Info -->
        <div class="lg:col-span-4 space-y-12">
            <!-- About -->
            <section class="sp-card border border-white/5 p-8">
                <h2 class="text-xs font-black text-zinc-500 uppercase tracking-[0.3em] mb-6">About</h2>
                <div class="prose prose-invert prose-sm text-zinc-400 font-medium leading-relaxed mb-8">
                    <?= !empty($bio) ? nl2br($bio) : 'No description available for this label.' ?>
                </div>
                <div class="flex flex-wrap gap-4">
                    <?php foreach ($entity['social_links'] ?? [] as $provider => $url): ?>
                        <a href="<?= htmlspecialchars($url) ?>" target="_blank" class="w-10 h-10 rounded-full bg-white/5 border border-white/10 flex items-center justify-center text-white hover:bg-blue-500 hover:text-black transition-all">
                            <i class="bi bi-<?= $provider ?>"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </div>
</div>