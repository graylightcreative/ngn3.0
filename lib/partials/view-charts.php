<?php
/**
 * NGN 2.1.0 Charts View
 * Spotify-Killer Design
 */
$chartType = $data['chart_type'] ?? 'artists';
$rankings = ($chartType === 'labels') ? ($data['label_rankings'] ?? []) : ($data['artist_rankings'] ?? []);
?>
<div class="mb-12">
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-8 mb-12">
        <div>
            <div class="inline-block px-3 py-1 bg-[#FF5F1F] text-black font-black text-[10px] uppercase tracking-widest mb-4 rounded-sm">Institutional_Intelligence</div>
            <h1 class="text-5xl lg:text-7xl font-black tracking-tighter text-white">NGN CHARTS</h1>
            <p class="text-zinc-500 font-mono text-sm mt-4 max-w-xl">Real-time engagement signals processed through the NGN Story Engine.</p>
        </div>
        
        <div class="flex bg-zinc-900 p-1 rounded-2xl border border-white/5">
            <a href="/charts?type=artists" class="px-8 py-3 rounded-xl text-xs font-black uppercase tracking-widest transition-all <?= $chartType === 'artists' ? 'bg-[#FF5F1F] text-black shadow-xl shadow-[#FF5F1F]/20' : 'text-zinc-500 hover:text-white' ?>">Artists</a>
            <a href="/charts?type=labels" class="px-8 py-3 rounded-xl text-xs font-black uppercase tracking-widest transition-all <?= $chartType === 'labels' ? 'bg-[#FF5F1F] text-black shadow-xl shadow-[#FF5F1F]/20' : 'text-zinc-500 hover:text-white' ?>">Labels</a>
        </div>
    </div>

    <?php if (!empty($rankings)): ?>
    <div class="bg-zinc-900/30 rounded-[2rem] border border-white/5 overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-white/5">
                    <th class="px-8 py-6 text-[10px] font-black text-zinc-500 uppercase tracking-widest w-20">Rank</th>
                    <th class="px-8 py-6 text-[10px] font-black text-zinc-500 uppercase tracking-widest"><?= ucfirst($chartType) ?></th>
                    <th class="px-8 py-6 text-[10px] font-black text-zinc-500 uppercase tracking-widest text-right">Score</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rankings as $i => $item): ?>
                <tr class="group hover:bg-white/5 transition-all cursor-pointer border-b border-white/5 last:border-0" onclick="window.location='/<?= rtrim($chartType, 's') ?>/<?= $item['slug'] ?? $item['entity_id'] ?>'">
                    <td class="px-8 py-6">
                        <span class="text-2xl font-black <?= $i < 3 ? 'text-[#FF5F1F]' : 'text-zinc-700' ?>"><?= $i + 1 ?></span>
                    </td>
                    <td class="px-8 py-6">
                        <div class="flex items-center gap-6">
                            <?php 
                                $itemImg = user_image($item['slug'] ?? $item['Slug'] ?? '', $item['image_url'] ?? $item['Image'] ?? null);
                            ?>
                            <img src="<?= htmlspecialchars($itemImg) ?>" class="w-14 h-14 rounded-xl object-cover shadow-2xl group-hover:scale-105 transition-transform" onerror="this.src='<?= DEFAULT_AVATAR ?>'">
                            <div>
                                <div class="font-black text-white text-lg group-hover:text-[#FF5F1F] transition-colors"><?= htmlspecialchars($item['Name'] ?? $item['name'] ?? 'Unknown') ?></div>
                                <div class="text-[10px] font-black text-zinc-500 uppercase tracking-widest mt-1">Active Signaling</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-8 py-6 text-right">
                        <div class="text-xl font-black text-white"><?= number_format($item['Score'] ?? 0) ?></div>
                        <div class="text-[10px] font-black text-brand uppercase tracking-widest">Pressure_Index</div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="text-center py-24 sp-card border border-dashed border-white/10">
        <i class="bi-bar-chart text-4xl text-zinc-700 mb-4 block"></i>
        <h2 class="text-xl font-black text-white">No ranking data available</h2>
        <p class="text-zinc-500 font-mono text-sm mt-2">The moat is currently being pressurized. Check back shortly.</p>
    </div>
    <?php endif; ?>
</div>
