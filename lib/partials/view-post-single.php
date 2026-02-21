<?php
/**
 * NGN Single Post View
 */
$post = $data['post'];
?>
<!-- SINGLE POST VIEW (Modern Editorial) -->
<div class="max-w-4xl mx-auto">
    <a href="/posts" class="inline-flex items-center gap-2 text-zinc-500 hover:text-white mb-8 font-black uppercase tracking-widest text-xs transition-colors">
        <i class="bi-arrow-left"></i> Back to News
    </a>

    <header class="mb-12">
        <div class="text-xs font-black text-brand uppercase tracking-[0.3em] mb-4">NGN Intelligence Report</div>
        <h1 class="text-4xl lg:text-7xl font-black tracking-tighter mb-8 leading-[0.9]"><?= htmlspecialchars($post['title'] ?? '') ?></h1>
        
        <div class="flex flex-wrap items-center gap-6 text-sm font-black uppercase tracking-widest text-zinc-500 mb-12 border-y border-white/5 py-6">
            <?php if (!empty($post['author_entity'])): ?>
                <a href="/artist/<?= htmlspecialchars($post['author_entity']['slug'] ?? '') ?>" class="flex items-center gap-3 text-white hover:text-brand transition-colors">
                    <img src="<?= htmlspecialchars(($post['author_entity']['image_url'] ?? null) ?: DEFAULT_AVATAR) ?>" class="w-10 h-10 rounded-full object-cover">
                    <span><?= htmlspecialchars($post['author_entity']['name'] ?? 'Staff') ?></span>
                </a>
            <?php endif; ?>
            <span class="w-1 h-1 bg-zinc-800 rounded-full"></span>
            <span><?= date('F j, Y', strtotime($post['published_at'] ?? 'now')) ?></span>
            <span class="w-1 h-1 bg-zinc-800 rounded-full"></span>
            <span class="text-zinc-600">5 min read</span>
        </div>

        <?php 
            $postImg = post_image($post['featured_image_url'] ?? '');
        ?>
        <?php if ($postImg): ?>
        <div class="rounded-3xl overflow-hidden shadow-2xl border border-white/5 aspect-[21/9]">
            <img src="<?= htmlspecialchars($postImg) ?>" class="w-full h-full object-cover" alt="">
        </div>
        <?php endif; ?>
    </header>

    <article class="prose prose-invert prose-lg max-w-none">
        <?php if ($post['is_locked']): ?>
            <div class="bg-zinc-900/80 backdrop-blur-xl border border-brand/20 p-12 rounded-3xl text-center">
                <i class="bi-shield-lock-fill text-6xl text-brand mb-6 block"></i>
                <h2 class="text-3xl font-black text-white mb-4">Premium Intelligence</h2>
                <p class="text-zinc-400 mb-8 max-w-md mx-auto">This report is restricted to NGN Pro members. Gain the competitive edge with full platform access.</p>
                <a href="/pricing" class="inline-block bg-brand text-black font-black py-4 px-10 rounded-full hover:scale-105 transition-all shadow-xl shadow-brand/20 uppercase tracking-widest text-xs">Upgrade to Pro</a>
            </div>
        <?php else: ?>
            <div class="text-zinc-300 font-medium leading-[1.8]">
                <?= $post['body'] ?? 'No content available.' ?>
            </div>
        <?php endif; ?>
    </article>
</div>
