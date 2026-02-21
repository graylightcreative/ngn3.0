<?php
/**
 * Sovereign Stats Ticker
 * Real-time fleet metrics
 */
?>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-16 separator-slash">
    <div class="glass-panel p-5 md:p-8 rounded-[2rem] group transition-all text-center md:text-left flex flex-col md:flex-row items-center gap-4">
        <div class="w-12 h-12 md:w-16 md:h-16 rounded-2xl vibrant-icon-wrapper flex items-center justify-center text-brand group-hover:scale-110 transition-transform">
            <i class="bi bi-mic-fill text-2xl md:text-3xl"></i>
        </div>
        <div>
            <div class="text-[9px] font-black text-zinc-400 uppercase tracking-[0.2em] mb-1 group-hover:text-white transition-colors">Artists_In_Fleet</div>
            <div class="text-3xl md:text-4xl font-black text-white leading-none tracking-tighter brand-gradient-text"><?= number_format($counts['artists'] ?? 0) ?></div>
        </div>
    </div>
    <div class="glass-panel p-5 md:p-8 rounded-[2rem] group transition-all text-center md:text-left flex flex-col md:flex-row items-center gap-4">
        <div class="w-12 h-12 md:w-16 md:h-16 rounded-2xl vibrant-icon-wrapper flex items-center justify-center text-brand group-hover:scale-110 transition-transform">
            <i class="bi bi-record-vinyl-fill text-2xl md:text-3xl"></i>
        </div>
        <div>
            <div class="text-[9px] font-black text-zinc-400 uppercase tracking-[0.2em] mb-1 group-hover:text-white transition-colors">Institutional_Labels</div>
            <div class="text-3xl md:text-4xl font-black text-white leading-none tracking-tighter brand-gradient-text"><?= number_format($counts['labels'] ?? 0) ?></div>
        </div>
    </div>
    <div class="glass-panel p-5 md:p-8 rounded-[2rem] group transition-all text-center md:text-left flex flex-col md:flex-row items-center gap-4">
        <div class="w-12 h-12 md:w-16 md:h-16 rounded-2xl vibrant-icon-wrapper flex items-center justify-center text-brand group-hover:scale-110 transition-transform">
            <i class="bi bi-broadcast text-2xl md:text-3xl"></i>
        </div>
        <div>
            <div class="text-[9px] font-black text-zinc-400 uppercase tracking-[0.2em] mb-1 group-hover:text-white transition-colors">Broadcasting_Stations</div>
            <div class="text-3xl md:text-4xl font-black text-white leading-none tracking-tighter brand-gradient-text"><?= number_format($counts['stations'] ?? 0) ?></div>
        </div>
    </div>
    <div class="glass-panel p-5 md:p-8 rounded-[2rem] group transition-all text-center md:text-left flex flex-col md:flex-row items-center gap-4">
        <div class="w-12 h-12 md:w-16 md:h-16 rounded-2xl vibrant-icon-wrapper flex items-center justify-center text-brand group-hover:scale-110 transition-transform">
            <i class="bi bi-geo-alt-fill text-2xl md:text-3xl"></i>
        </div>
        <div>
            <div class="text-[9px] font-black text-zinc-400 uppercase tracking-[0.2em] mb-1 group-hover:text-white transition-colors">Verified_Venues</div>
            <div class="text-3xl md:text-4xl font-black text-white leading-none tracking-tighter brand-gradient-text"><?= number_format($counts['venues'] ?? 0) ?></div>
        </div>
    </div>
</div>
