<?php
/**
 * Sovereign Ad_Signal Partial
 * A tactical, high-contrast ad placeholder that frames ads as signal fuel.
 */
$adType = $type ?? 'horizontal'; // horizontal, vertical, square
?>

<div class="ad-signal-container my-12 group">
    <div class="flex items-center gap-3 mb-3 px-2">
        <div class="w-1.5 h-1.5 bg-brand animate-pulse"></div>
        <span class="text-[9px] font-black text-white/30 uppercase tracking-[0.3em]">Commercial_Signal // Fueling_The_Empire</span>
    </div>

    <div class="glass-panel rounded-2xl overflow-hidden border border-white/5 group-hover:border-brand/20 transition-all flex items-center justify-center min-h-[120px] bg-zinc-900/50 relative">
        <!-- The Ad Content -->
        <div class="relative z-10 text-center p-8">
            <h5 class="text-[10px] font-black text-zinc-500 uppercase tracking-widest mb-2">Space_Reserved_For_Vanguard_Partners</h5>
            <p class="text-[8px] font-bold text-zinc-600 uppercase tracking-tighter max-w-[200px] mx-auto">Every impression accelerates our Road to Activation.</p>
            <a href="/advertisers" class="inline-block mt-4 text-[9px] font-black text-brand hover:text-white uppercase tracking-[0.2em] transition-colors border-b border-brand/30 pb-0.5">Become_A_Partner</a>
        </div>

        <!-- Geometric Accents -->
        <div class="absolute top-0 right-0 w-16 h-16 bg-brand/5 rotate-45 translate-x-8 -translate-y-8 pointer-events-none"></div>
        <div class="absolute bottom-0 left-0 w-12 h-12 bg-white/5 -rotate-12 -translate-x-4 translate-y-4 pointer-events-none"></div>
    </div>
</div>
