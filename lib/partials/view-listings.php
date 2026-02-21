<?php
/**
 * NGN Entity List View (Artists, Labels, Stations, Venues)
 */
?>
<!-- ENTITY LIST VIEW -->
<div class="flex items-center justify-between mb-8">
    <div>
    <h1 class="text-4xl font-black capitalize tracking-tighter text-white"><?= $view ?></h1>
    <p class="text-zinc-500 font-bold uppercase tracking-widest text-[10px] mt-1">Discover the NGN network</p>
    </div>
    <div class="text-right">
    <div class="text-3xl font-black text-brand"><?= number_format($total) ?></div>
    <div class="text-[10px] font-black text-zinc-500 uppercase tracking-widest">Active</div>
    </div>
</div>

<?php $items = $data[$view] ?? []; ?>
<?php if (!empty($items)): ?>
<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-6">
    <?php foreach ($items as $item): ?>
    <?php
    $itemSlug = $item['slug'] ?? $item['Slug'] ?? '';
    $itemImg  = $item['image_url'] ?? $item['Image'] ?? null;
    $imgUrl   = user_image($itemSlug, $itemImg);
    ?>
    <a href="/<?= rtrim($view, 's') ?>/<?= htmlspecialchars($itemSlug ?: ($item['id'] ?? '')) ?>" class="group sp-card border border-white/5">
    <div class="aspect-square mb-4 shadow-2xl relative">
        <img src="<?= htmlspecialchars($imgUrl) ?>" alt="" class="w-full h-full object-cover <?= $view === 'labels' ? 'rounded-full' : 'rounded-xl' ?> bg-zinc-800 shadow-xl group-hover:scale-[1.02] transition-transform duration-500" onerror="this.onerror=null;this.src='<?= DEFAULT_AVATAR ?>'">
        <button class="absolute bottom-2 right-2 w-10 h-10 bg-brand text-black rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 translate-y-2 group-hover:translate-y-0 transition-all shadow-lg">
            <i class="bi-play-fill text-xl"></i>
        </button>
    </div>
    <div class="font-black text-sm truncate text-white"><?= htmlspecialchars($item['name'] ?? $item['Name'] ?? $item['title'] ?? 'Unknown') ?></div>
    <div class="text-[10px] font-black text-zinc-500 uppercase tracking-widest mt-1"><?= htmlspecialchars($item['city'] ?? $item['City'] ?? 'Active') ?></div>
    </a>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="text-center py-24 sp-card border border-dashed border-white/10">
    <i class="bi-search text-4xl text-zinc-700 mb-4 block"></i>
    <h2 class="text-xl font-black">No results found</h2>
    <p class="text-zinc-500">Try adjusting your search filters.</p>
</div>
<?php endif; ?>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="flex items-center justify-center gap-4 mt-16">
    <?php if ($page > 1): ?>
    <a href="/<?= $view ?>?page=<?= $page-1 ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="px-8 py-3 rounded-full bg-zinc-800 text-white font-black hover:bg-zinc-700 transition-all">Previous</a>
    <?php endif; ?>
    <?php if ($page < $totalPages): ?>
    <a href="/<?= $view ?>?page=<?= $page + 1 ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="px-8 py-3 rounded-full bg-white text-black font-black hover:scale-105 transition-all">Next</a>
    <?php endif; ?>
</div>
<?php endif; ?>
