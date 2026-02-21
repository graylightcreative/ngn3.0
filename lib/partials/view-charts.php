<?php
/**
 * NGN 3.2.0 Market Rankings View
 * Spotify-Killer Design // Layman ROI Overhaul
 */
$chartType = $data['chart_type'] ?? 'artists';
$rankings = ($chartType === 'labels') ? ($data['label_rankings'] ?? []) : ($data['partner_rankings'] ?? []);
?>
<div class="mb-12">
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-8 mb-12">
        <div>
            <div class="inline-block px-3 py-1 bg-brand text-black font-black text-[10px] uppercase tracking-widest mb-4 rounded-sm">Global_Performance</div>
            <h1 class="text-5xl lg:text-7xl font-black tracking-tighter text-white uppercase italic">Market Rankings</h1>
            <p class="text-zinc-500 font-bold text-sm mt-4 max-w-xl">Real-time market performance and engagement signals across the entire alliance.</p>
        </div>
        
        <div class="flex bg-zinc-900 p-1 rounded-2xl border border-white/5 backdrop-blur-xl">
            <a href="/charts/artists" class="px-8 py-3 rounded-xl text-xs font-black uppercase tracking-widest transition-all <?= $chartType === 'artists' ? 'bg-brand text-black shadow-xl shadow-brand/20' : 'text-zinc-500 hover:text-white' ?>">Partners</a>
            <a href="/charts/labels" class="px-8 py-3 rounded-xl text-xs font-black uppercase tracking-widest transition-all <?= $chartType === 'labels' ? 'bg-brand text-black shadow-xl shadow-brand/20' : 'text-zinc-500 hover:text-white' ?>">Capital Groups</a>
        </div>
    </div>

    <?php if (!empty($rankings)): ?>
    <div class="bg-zinc-900/30 rounded-[2.5rem] border border-white/5 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-white/5">
                        <th class="px-8 py-6 text-[10px] font-black text-zinc-500 uppercase tracking-widest w-20">Rank</th>
                        <th class="px-8 py-6 text-[10px] font-black text-zinc-500 uppercase tracking-widest"><?= $chartType === 'labels' ? 'Capital Group' : 'Partner' ?></th>
                        <th class="px-8 py-6 text-[10px] font-black text-zinc-500 uppercase tracking-widest text-right">Growth Score</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rankings as $i => $item): ?>
                    <tr class="group hover:bg-white/5 transition-all cursor-pointer border-b border-white/5 last:border-0" onclick="window.location='/<?= rtrim(($chartType === 'artists' ? 'partner' : $chartType), 's') ?>/<?= $item['slug'] ?? $item['entity_id'] ?>'">
                        <td class="px-8 py-6">
                            <span class="text-2xl font-black <?= $i < 3 ? 'text-brand' : 'text-zinc-700' ?>"><?= $i + 1 ?></span>
                        </td>
                        <td class="px-8 py-6">
                            <div class="flex items-center gap-6">
                                <?php 
                                    $itemImg = user_image($item['slug'] ?? $item['Slug'] ?? '', $item['image_url'] ?? $item['Image'] ?? null);
                                ?>
                                <div class="relative">
                                    <img src="<?= htmlspecialchars($itemImg) ?>" class="w-14 h-14 rounded-xl object-cover shadow-2xl group-hover:scale-105 transition-transform" onerror="this.src='<?= DEFAULT_AVATAR ?>'">
                                    <?php if ($i < 3): ?>
                                        <div class="absolute -top-2 -right-2 w-6 h-6 bg-brand text-black rounded-full flex items-center justify-center text-[10px] font-black shadow-lg">
                                            <i class="bi bi-star-fill"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="font-black text-white text-lg group-hover:text-brand transition-colors"><?= htmlspecialchars($item['Name'] ?? $item['name'] ?? 'Unknown') ?></div>
                                    <div class="text-[10px] font-black text-zinc-500 uppercase tracking-widest mt-1">Verified Performance</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-8 py-6 text-right">
                            <div class="text-xl font-black text-white"><?= number_format($item['Score'] ?? 0) ?></div>
                            <div class="text-[10px] font-black text-brand uppercase tracking-widest">Growth_Index</div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="text-center py-24 sp-card border border-dashed border-white/10 rounded-[2.5rem]">
        <i class="bi bi-bar-chart text-4xl text-zinc-700 mb-4 block"></i>
        <h2 class="text-xl font-black text-white uppercase italic">Rankings_Processing</h2>
        <p class="text-zinc-500 font-bold text-sm mt-2">Market data is currently being aggregated. Check back shortly.</p>
    </div>
    <?php endif; ?>
</div>
