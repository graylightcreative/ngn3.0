<?php
/**
 * NGN Single Track View
 */
$track = $data['track'];
?>
<!-- SINGLE TRACK VIEW (Immersion) -->
<div class="max-w-4xl mx-auto py-24 text-center">
    <div class="mb-12 relative inline-block">
        <div class="w-64 h-64 mx-auto rounded-3xl bg-zinc-800 flex items-center justify-center shadow-[0_30px_60px_rgba(0,0,0,0.6)] border border-white/5">
            <i class="bi-music-note-beamed text-zinc-700 text-8xl"></i>
        </div>
        <button class="absolute -bottom-6 -right-6 w-20 h-20 bg-brand text-black rounded-full flex items-center justify-center shadow-2xl hover:scale-110 transition-all">
            <i class="bi-play-fill text-5xl ml-1"></i>
        </button>
    </div>
    
    <h1 class="text-5xl lg:text-7xl font-black mb-4 tracking-tighter leading-none"><?= htmlspecialchars($track['title'] ?? 'Untitled Song') ?></h1>
    <p class="text-zinc-500 mb-12 font-black uppercase tracking-[0.3em] text-xs"><?= htmlspecialchars($track['artist_name'] ?? 'High Fidelity Stream') ?></p>
    
    <div class="flex flex-wrap justify-center gap-6">
        <button class="px-12 py-4 bg-brand text-black font-black rounded-full hover:scale-105 transition-all shadow-2xl shadow-brand/20 uppercase tracking-widest text-sm">
            Listen Now
        </button>
        <button class="px-12 py-4 bg-white/5 text-white font-black rounded-full hover:bg-white/10 transition-all border border-white/10 uppercase tracking-widest text-sm">
            Add to Collection
        </button>
    </div>
</div>
