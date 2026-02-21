<?php
/**
 * Sovereign Stats Ticker
 * Real-time fleet metrics
 */
?>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-16">
    <div class="bg-white/5 border border-white/5 p-4 md:p-6 rounded-2xl group hover:border-brand/30 transition-all text-center md:text-left">
        <div class="text-[9px] font-black text-zinc-500 uppercase tracking-[0.2em] mb-2 group-hover:text-brand transition-colors">Artists_In_Fleet</div>
        <div class="text-3xl font-black text-white leading-none"><?= number_format($counts['artists'] ?? 0) ?></div>
    </div>
    <div class="bg-white/5 border border-white/5 p-4 md:p-6 rounded-2xl group hover:border-brand/30 transition-all text-center md:text-left">
        <div class="text-[9px] font-black text-zinc-500 uppercase tracking-[0.2em] mb-2 group-hover:text-brand transition-colors">Institutional_Labels</div>
        <div class="text-3xl font-black text-white leading-none"><?= number_format($counts['labels'] ?? 0) ?></div>
    </div>
    <div class="bg-white/5 border border-white/5 p-4 md:p-6 rounded-2xl group hover:border-brand/30 transition-all text-center md:text-left">
        <div class="text-[9px] font-black text-zinc-500 uppercase tracking-[0.2em] mb-2 group-hover:text-brand transition-colors">Broadcasting_Stations</div>
        <div class="text-3xl font-black text-white leading-none"><?= number_format($counts['stations'] ?? 0) ?></div>
    </div>
    <div class="bg-white/5 border border-white/5 p-4 md:p-6 rounded-2xl group hover:border-brand/30 transition-all text-center md:text-left">
        <div class="text-[9px] font-black text-zinc-500 uppercase tracking-[0.2em] mb-2 group-hover:text-brand transition-colors">Verified_Venues</div>
        <div class="text-3xl font-black text-white leading-none"><?= number_format($counts['venues'] ?? 0) ?></div>
    </div>
</div>
