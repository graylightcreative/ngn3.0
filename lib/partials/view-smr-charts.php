<?php
/**
 * NGN 2.0.3 SMR Charts View
 */
$smrData = $data['smr_charts'] ?? [];
$smrDate = $data['smr_date'] ?? 'N/A';
?>
<div class="mb-12">
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-8 mb-12">
        <div>
            <div class="inline-block px-3 py-1 bg-[#FF5F1F] text-black font-black text-[10px] uppercase tracking-widest mb-4 rounded-sm">Radio_Intelligence</div>
            <h1 class="text-5xl lg:text-7xl font-black tracking-tighter text-white">SMR CHARTS</h1>
            <p class="text-zinc-500 font-mono text-sm mt-4 max-w-xl">Spins Music Radio global airplay tracking. Updated weekly.</p>
        </div>
        <div class="text-right">
            <div class="text-[10px] font-black text-zinc-500 uppercase tracking-[0.2em] mb-1">Chart Date</div>
            <div class="text-xl font-black text-white"><?= $smrDate ?></div>
        </div>
    </div>

    <?php if (!empty($smrData)): ?>
    <div class="bg-zinc-900/30 rounded-[2rem] border border-white/5 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-white/5">
                        <th class="px-8 py-6 text-[10px] font-black text-zinc-500 uppercase tracking-widest w-20">TW</th>
                        <th class="px-8 py-6 text-[10px] font-black text-zinc-500 uppercase tracking-widest w-20 text-center">LW</th>
                        <th class="px-8 py-6 text-[10px] font-black text-zinc-500 uppercase tracking-widest">Track / Artist</th>
                        <th class="px-8 py-6 text-[10px] font-black text-zinc-500 uppercase tracking-widest text-center">WOC</th>
                        <th class="px-8 py-6 text-[10px] font-black text-zinc-500 uppercase tracking-widest text-right">Spins</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($smrData as $row): ?>
                    <tr class="group hover:bg-white/5 transition-all cursor-pointer border-b border-white/5 last:border-0">
                        <td class="px-8 py-6">
                            <span class="text-2xl font-black <?= (int)$row['TWP'] <= 3 ? 'text-[#FF5F1F]' : 'text-zinc-400' ?>"><?= $row['TWP'] ?></span>
                        </td>
                        <td class="px-8 py-6 text-center">
                            <span class="text-xs font-black text-zinc-600"><?= $row['LWP'] ?></span>
                        </td>
                        <td class="px-8 py-6">
                            <div class="flex items-center gap-6">
                                <img src="<?= htmlspecialchars($row['artist']['image_url'] ?? DEFAULT_AVATAR) ?>" class="w-12 h-12 rounded-lg object-cover shadow-xl group-hover:scale-105 transition-transform" onerror="this.src='<?= DEFAULT_AVATAR ?>'">
                                <div>
                                    <div class="font-black text-white group-hover:text-[#FF5F1F] transition-colors"><?= htmlspecialchars($row['Song']) ?></div>
                                    <div class="text-[10px] font-black text-zinc-500 uppercase tracking-widest mt-1"><?= htmlspecialchars($row['artist']['name']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-8 py-6 text-center">
                            <span class="text-xs font-black text-zinc-400"><?= $row['WOC'] ?></span>
                        </td>
                        <td class="px-8 py-6 text-right">
                            <div class="text-lg font-black text-white"><?= number_format($row['TWS']) ?></div>
                            <div class="text-[10px] font-black text-brand uppercase tracking-widest">Spins</div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="text-center py-24 sp-card border border-dashed border-white/10">
        <i class="bi-broadcast text-4xl text-zinc-700 mb-4 block"></i>
        <h2 class="text-xl font-black text-white">No radio data found</h2>
        <p class="text-zinc-500 font-mono text-sm mt-2">Connecting to SMR broadcast satellites...</p>
    </div>
    <?php endif; ?>
</div>
