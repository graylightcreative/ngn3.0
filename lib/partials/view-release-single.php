<?php
/**
 * NGN Single Release View
 */
$release = $data['release'] ?? null;
if (!$release) {
    echo '<div class="p-20 text-center text-zinc-500 uppercase font-black tracking-widest">Sovereign Data Missing / Entry Void</div>';
    return;
}
?>
<!-- SINGLE RELEASE VIEW (Premium Immersion) -->
<div class="max-w-6xl mx-auto">
    <a href="/artist/<?= htmlspecialchars($release['artist']['slug'] ?? '') ?>" class="inline-flex items-center gap-2 text-zinc-500 hover:text-white mb-12 font-black uppercase tracking-widest text-xs transition-colors">
        <i class="bi-arrow-left"></i> Back to Artist
    </a>

    <div class="flex flex-col md:flex-row gap-12 mb-16 items-end">
        <div class="w-full md:w-80 flex-shrink-0 shadow-[0_20px_50px_rgba(0,0,0,0.5)]">
            <?php 
                $relImg = release_image($release['cover_url'] ?? $release['cover_image_url'] ?? '', $release['artist']['slug'] ?? null);
            ?>
            <img src="<?= htmlspecialchars($relImg) ?>" 
                    class="w-full aspect-square object-cover rounded-2xl border border-white/5 group-hover:scale-[1.02] transition-transform duration-1000" alt=""
                    onerror="this.src='<?= DEFAULT_AVATAR ?>'">
        </div>
        <div class="flex-1">
            <span class="text-xs font-black uppercase tracking-[0.3em] text-brand mb-4 block"><?= ucfirst($release['type'] ?? 'Album') ?></span>
            <h1 class="text-5xl lg:text-8xl font-black mb-6 tracking-tighter leading-none"><?= htmlspecialchars($release['title'] ?? 'Untitled Release') ?></h1>
            <div class="flex items-center gap-4 text-sm font-black text-zinc-400">
                <a href="/artist/<?= htmlspecialchars($release['artist']['slug'] ?? '') ?>" class="text-white hover:text-brand transition-colors"><?= htmlspecialchars($release['artist']['name'] ?? 'Unknown Artist') ?></a>
                <span class="w-1 h-1 bg-zinc-800 rounded-full"></span>
                <span><?= !empty($release['release_date']) ? date('Y', strtotime($release['release_date'])) : 'N/A' ?></span>
                <span class="w-1 h-1 bg-zinc-800 rounded-full"></span>
                <span><?= count($release['tracks'] ?? []) ?> Tracks</span>
            </div>
        </div>
    </div>

    <!-- Tracklist (Immersion Grid) -->
    <div class="bg-zinc-900/30 rounded-3xl border border-white/5 overflow-hidden mb-16">
        <div class="p-8 border-b border-white/5 flex items-center justify-between bg-white/5">
            <h2 class="text-2xl font-black tracking-tight">Tracklist</h2>
            <button class="bg-brand text-black w-14 h-14 rounded-full flex items-center justify-center hover:scale-105 transition-all shadow-2xl shadow-brand/20">
                <i class="bi-play-fill text-3xl ml-1"></i>
            </button>
        </div>
        <div class="divide-y divide-white/5">
            <?php if (!empty($release['tracks'])): ?>
                <?php foreach ($release['tracks'] as $i => $track): ?>
                <div class="flex items-center gap-6 p-6 hover:bg-white/5 transition-all group">
                    <span class="w-8 text-center text-zinc-600 font-black group-hover:text-white"><?= $i + 1 ?></span>
                    <div class="flex-1 min-w-0">
                        <div class="font-black text-lg truncate text-white group-hover:text-brand transition-colors"><?= htmlspecialchars($track['title']) ?></div>
                        <div class="text-[10px] font-black text-zinc-500 uppercase tracking-widest mt-1"><?= htmlspecialchars($release['artist']['name'] ?? '') ?></div>
                    </div>
                    <div class="text-xs font-mono text-zinc-500 font-bold"><?= ($track['duration_seconds'] ?? 0) ? gmdate('i:s', $track['duration_seconds']) : '--:--' ?></div>
                    <div class="flex gap-4 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button class="text-zinc-500 hover:text-white"><i class="bi-plus-circle text-xl"></i></button>
                        <button class="text-zinc-500 hover:text-white"><i class="bi-three-dots text-xl"></i></button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="p-20 text-center text-zinc-600 font-black uppercase tracking-widest">No Tracks Indexed</div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($release['description'])): ?>
    <div class="sp-card border border-white/5 p-12 mb-16">
        <h3 class="text-sm font-black uppercase tracking-[0.3em] text-zinc-500 mb-6">About this release</h3>
        <div class="prose prose-invert max-w-none text-zinc-400 font-medium leading-relaxed">
            <?= nl2br($release['description']) ?>
        </div>
    </div>
    <?php endif; ?>
</div>
