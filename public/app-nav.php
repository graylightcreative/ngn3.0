<nav class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-[500px] bg-black/80 backdrop-blur-2xl border-t border-white/5 h-20 z-[100] px-6 flex items-center justify-between pb-4">
    <a href="/" class="flex flex-col items-center gap-1 <?= ($view ?? 'home') === 'home' ? 'text-brand' : 'text-zinc-500' ?>">
        <i class="bi bi-house-fill text-xl"></i>
        <span class="text-[9px] font-black uppercase tracking-widest">Home</span>
    </a>
    <a href="/charts" class="flex flex-col items-center gap-1 <?= ($view ?? '') === 'charts' ? 'text-brand' : 'text-zinc-500' ?>">
        <i class="bi bi-bar-chart-fill text-xl"></i>
        <span class="text-[9px] font-black uppercase tracking-widest">Charts</span>
    </a>
    <a href="/releases" class="flex flex-col items-center gap-1 <?= ($view ?? '') === 'releases' ? 'text-brand' : 'text-zinc-500' ?>">
        <i class="bi bi-vinyl-fill text-xl"></i>
        <span class="text-[9px] font-black uppercase tracking-widest">Music</span>
    </a>
    <a href="/posts" class="flex flex-col items-center gap-1 <?= ($view ?? '') === 'posts' ? 'text-brand' : 'text-zinc-500' ?>">
        <i class="bi bi-newspaper text-xl"></i>
        <span class="text-[9px] font-black uppercase tracking-widest">News</span>
    </a>
    <a href="/artists" class="flex flex-col items-center gap-1 <?= ($view ?? '') === 'artists' ? 'text-brand' : 'text-zinc-500' ?>">
        <i class="bi bi-people-fill text-xl"></i>
        <span class="text-[9px] font-black uppercase tracking-widest">Artists</span>
    </a>
</nav>
