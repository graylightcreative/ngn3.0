<?php
/**
 * NGN 3.2.1 Rankings View
 * Categorized Intelligence: Artists, Labels, SMR, Stations, Venues
 */
$chartType = $data['chart_type'] ?? 'artists';
$rankings = ($chartType === 'labels') ? ($data['label_rankings'] ?? []) : ($data['partner_rankings'] ?? []);
?>
<div class="mb-12">
    <div class="flex flex-col xl:flex-row xl:items-end justify-between gap-8 mb-12">
        <div>
            <div class="inline-block px-3 py-1 bg-brand text-black font-black text-[10px] uppercase tracking-widest mb-4 rounded-sm">Market_Intelligence</div>
            <h1 class="text-5xl lg:text-7xl font-black tracking-tighter text-white uppercase italic">Rankings</h1>
            <p class="text-zinc-500 font-bold text-sm mt-4 max-w-xl">Verified performance metrics and engagement signals across the alliance.</p>
        </div>
        
        <!-- Tab Navigation -->
        <div class="flex flex-wrap bg-zinc-900 p-1.5 rounded-2xl border border-white/5 backdrop-blur-xl gap-1">
            <a href="/charts/artists" class="px-6 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all <?= $chartType === 'artists' ? 'bg-brand text-black shadow-lg shadow-brand/20' : 'text-zinc-500 hover:text-white' ?>">Artists</a>
            <a href="/charts/labels" class="px-6 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all <?= $chartType === 'labels' ? 'bg-brand text-black shadow-lg shadow-brand/20' : 'text-zinc-500 hover:text-white' ?>">Labels</a>
            <a href="/charts/smr" class="px-6 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all text-zinc-500 hover:text-white">SMR</a>
            
            <div class="px-6 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest text-zinc-700 cursor-not-allowed flex items-center gap-2">
                Stations <span class="text-[8px] bg-white/5 px-1.5 py-0.5 rounded text-zinc-500">Soon</span>
            </div>
            <div class="px-6 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest text-zinc-700 cursor-not-allowed flex items-center gap-2">
                Venues <span class="text-[8px] bg-white/5 px-1.5 py-0.5 rounded text-zinc-500">Soon</span>
            </div>
        </div>
    </div>

    <?php if (!empty($rankings)): ?>
    <div class="bg-zinc-900/30 rounded-[2.5rem] border border-white/5 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-white/5">
                        <th class="px-8 py-6 text-[10px] font-black text-zinc-500 uppercase tracking-widest w-20">Rank</th>
                        <th class="px-8 py-6 text-[10px] font-black text-zinc-500 uppercase tracking-widest"><?= ucfirst(rtrim($chartType, 's')) ?></th>
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
        <i class="bi-bar-chart text-4xl text-zinc-700 mb-4 block"></i>
        <h2 class="text-xl font-black text-white uppercase italic">Rankings_Processing</h2>
        <p class="text-zinc-500 font-bold text-sm mt-2">Market data is currently being aggregated. Check back shortly.</p>
    </div>
    <?php endif; ?>
</div>
