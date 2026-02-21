<?php
/**
 * NGN Releases List View
 */
?>
<!-- RELEASES LIST VIEW -->
<div class="mb-12">
    <h1 class="text-4xl font-black tracking-tighter mb-2 text-white">Latest Releases</h1>
    <p class="text-zinc-500 font-bold uppercase tracking-[0.2em] text-[10px]">New albums, EPs, and singles from the NGN network</p>
</div>

<?php $items = $data['releases'] ?? []; ?>
<?php if (!empty($items)): ?>
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-8">
    <?php foreach ($items as $release): ?>
    <?php 
        $releaseImg = release_image($release['cover_url'] ?? $release['cover_image_url'] ?? '', $release['artist_slug'] ?? null);
    ?>
    <a href="/release/<?= htmlspecialchars($release['slug'] ?? $release['id']) ?>" class="group flex flex-col sp-card border border-white/5">
    <div class="aspect-square rounded-xl overflow-hidden mb-4 relative shadow-2xl">
        <img src="<?= htmlspecialchars($releaseImg) ?>" alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 bg-zinc-800" onerror="this.onerror=null;this.src='<?= DEFAULT_AVATAR ?>'">
        <div class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity">
        <i class="bi bi-play-fill text-5xl text-white"></i>
        </div>
    </div>
    <div class="font-black text-white truncate"><?= htmlspecialchars($release['title']) ?></div>
    <div class="text-[10px] font-black text-zinc-500 uppercase tracking-widest mt-1"><?= htmlspecialchars($release['artist_name'] ?? 'Unknown Artist') ?></div>
    </a>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="text-center py-24 sp-card border border-dashed border-white/10">
    <i class="bi-vinyl text-4xl text-zinc-700 mb-4 block"></i>
    <h2 class="text-xl font-black">No releases found</h2>
    <p class="text-zinc-500">New music is being cataloged. Check back soon.</p>
</div>
<?php endif; ?>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="flex items-center justify-center gap-4 mt-16">
    <?php if ($page > 1): ?>
    <a href="/releases?page=<?= $page-1 ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="px-8 py-3 rounded-full bg-zinc-800 text-white font-black hover:bg-zinc-700 transition-all">Previous</a>
    <?php endif; ?>
    <?php if ($page < $totalPages): ?>
    <a href="/releases?page=<?= $page + 1 ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="px-8 py-3 rounded-full bg-white text-black font-black hover:scale-105 transition-all">Next</a>
    <?php endif; ?>
</div>
<?php endif; ?>
