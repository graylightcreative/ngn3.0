<?php
/**
 * Sovereign Stats Ticker
 * Real-time Investment Impact metrics
 */
$counts = $data['counts'] ?? [];
?>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-16 separator-slash">
    <!-- PARTNERS -->
    <div class="glass-panel p-5 md:p-8 rounded-[2rem] group transition-all text-center md:text-left flex flex-col md:flex-row items-center gap-4 cursor-help" title="Number of creators and artists actively generating revenue on the platform.">
        <div class="w-12 h-12 md:w-16 md:h-16 rounded-2xl vibrant-icon-wrapper flex items-center justify-center text-brand group-hover:scale-110 transition-transform">
            <i class="bi bi-person-check-fill text-2xl md:text-3xl"></i>
        </div>
        <div>
            <div class="text-[9px] font-black text-zinc-400 uppercase tracking-[0.2em] mb-1 group-hover:text-white transition-colors">Active Partners</div>
            <div class="text-3xl md:text-4xl font-black text-white leading-none tracking-tighter brand-gradient-text"><?= number_format($counts['active_partners'] ?? 0) ?></div>
        </div>
    </div>
    
    <!-- LABS -->
    <div class="glass-panel p-5 md:p-8 rounded-[2rem] group transition-all text-center md:text-left flex flex-col md:flex-row items-center gap-4 cursor-help" title="High-tech production centers automating content distribution and data tracking.">
        <div class="w-12 h-12 md:w-16 md:h-16 rounded-2xl vibrant-icon-wrapper flex items-center justify-center text-brand group-hover:scale-110 transition-transform">
            <i class="bi bi-buildings-fill text-2xl md:text-3xl"></i>
        </div>
        <div>
            <div class="text-[9px] font-black text-zinc-400 uppercase tracking-[0.2em] mb-1 group-hover:text-white transition-colors">Production Labs</div>
            <div class="text-3xl md:text-4xl font-black text-white leading-none tracking-tighter brand-gradient-text"><?= number_format($counts['production_labs'] ?? 0) ?></div>
        </div>
    </div>

    <!-- GROWTH -->
    <div class="glass-panel p-5 md:p-8 rounded-[2rem] group transition-all text-center md:text-left flex flex-col md:flex-row items-center gap-4 cursor-help" title="Current performance benchmark across the entire network.">
        <div class="w-12 h-12 md:w-16 md:h-16 rounded-2xl vibrant-icon-wrapper flex items-center justify-center text-brand group-hover:scale-110 transition-transform">
            <i class="bi bi-graph-up-arrow text-2xl md:text-3xl"></i>
        </div>
        <div>
            <div class="text-[9px] font-black text-zinc-400 uppercase tracking-[0.2em] mb-1 group-hover:text-white transition-colors">Growth Score</div>
            <div class="text-3xl md:text-4xl font-black text-white leading-none tracking-tighter brand-gradient-text"><?= number_format($counts['growth_score'] ?? 0) ?>%</div>
        </div>
    </div>

    <!-- IMPACT -->
    <div class="glass-panel p-5 md:p-8 rounded-[2rem] group transition-all text-center md:text-left flex flex-col md:flex-row items-center gap-4 cursor-help" title="The total calculated value created for our partners.">
        <div class="w-12 h-12 md:w-16 md:h-16 rounded-2xl vibrant-icon-wrapper flex items-center justify-center text-brand group-hover:scale-110 transition-transform">
            <i class="bi bi-currency-dollar text-2xl md:text-3xl"></i>
        </div>
        <div>
            <div class="text-[9px] font-black text-zinc-400 uppercase tracking-[0.2em] mb-1 group-hover:text-white transition-colors">Investment Impact</div>
            <div class="text-3xl md:text-4xl font-black text-white leading-none tracking-tighter brand-gradient-text">$<?= number_format(($counts['goal_impact'] ?? 0)/1000) ?>k</div>
        </div>
    </div>
</div>