<?php
/**
 * NGN Posts List View (Newswire)
 */
?>
<!-- POSTS LIST VIEW -->
<div class="mb-12">
    <h1 class="text-4xl font-black tracking-tighter mb-2 text-white">NGN Newswire</h1>
    <p class="text-zinc-500 font-bold uppercase tracking-[0.2em] text-[10px]">Industry reports & daily music intelligence</p>
</div>

<?php $items = $data['posts'] ?? []; ?>
<?php if (!empty($items)): ?>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
    <?php foreach ($items as $post): ?>
    <?php 
        $postImg = post_image($post['featured_image_url'] ?? '');
    ?>
    <a href="/post/<?= htmlspecialchars(($post['slug'] ?? $post['id']) ?? '') ?>" class="group flex flex-col sp-card border border-white/5">
    <div class="aspect-video rounded-xl overflow-hidden mb-6 shadow-2xl">
        <img src="<?= htmlspecialchars($postImg) ?>" alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 bg-zinc-800" onerror="this.onerror=null;this.src='<?= DEFAULT_POST ?>'">
    </div>
    <div class="flex-1">
        <div class="text-[10px] font-black text-brand uppercase tracking-[0.2em] mb-3">Feature Article</div>
        <h3 class="text-xl font-black text-white line-clamp-2 leading-tight group-hover:text-brand transition-colors mb-4"><?= htmlspecialchars($post['title'] ?? 'Untitled Story') ?></h3>
        <?php if (!empty($post['excerpt'])): ?>
        <p class="text-zinc-400 text-sm line-clamp-3 leading-relaxed mb-6 font-medium"><?= htmlspecialchars($post['excerpt'] ?? '') ?></p>
        <?php endif; ?>
    </div>
    <div class="flex items-center justify-between pt-6 border-t border-white/5">
        <div class="flex items-center gap-3">
        <img src="/lib/images/site/2026/NGN-Emblem-Light.png" alt="NGN" class="w-8 h-8 rounded-full object-cover">
        <div class="text-[10px] font-black text-zinc-500 uppercase"><?= htmlspecialchars($post['author_name'] ?? 'Staff') ?></div>
        </div>
        <div class="text-[10px] font-black text-zinc-600 uppercase tracking-widest"><?= ($post['published_at'] ?? null) ? date('M j, Y', strtotime($post['published_at'])) : '' ?></div>
    </div>
    </a>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="text-center py-24 sp-card border border-dashed border-white/10">
    <i class="bi-newspaper text-4xl text-zinc-700 mb-4 block"></i>
    <h2 class="text-xl font-black">No posts available</h2>
    <p class="text-zinc-500">Check back later for fresh updates.</p>
</div>
<?php endif; ?>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="flex items-center justify-center gap-4 mt-16">
    <?php if ($page > 1): ?>
    <a href="/posts?page=<?= $page-1 ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="px-8 py-3 rounded-full bg-zinc-800 text-white font-black hover:bg-zinc-700 transition-all">Previous</a>
    <?php endif; ?>
    <?php if ($page < $totalPages): ?>
    <a href="/posts?page=<?= $page + 1 ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="px-8 py-3 rounded-full bg-white text-black font-black hover:scale-105 transition-all">Next</a>
    <?php endif; ?>
</div>
<?php endif; ?>
