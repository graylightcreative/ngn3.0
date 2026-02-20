<?php
/**
 * NGN 2.1.0 Sovereign Navigation
 * Mobile-First "Spotify-Killer" Architecture
 * Electric Orange (#FF5F1F) Theme
 */
?>
<!-- Mobile Bottom Bar (Fixed) -->
<nav class="lg:hidden fixed bottom-0 left-0 right-0 h-[72px] bg-black/90 backdrop-blur-xl border-t border-white/5 z-[100] flex items-center justify-around px-2 pb-safe">
    <a href="/" class="flex flex-col items-center gap-1 <?= $view === 'home' ? 'text-[#FF5F1F]' : 'text-zinc-500' ?>">
        <i class="bi-house-door-fill text-2xl"></i>
        <span class="text-[10px] font-black uppercase tracking-widest">Home</span>
    </a>
    <a href="/charts" class="flex flex-col items-center gap-1 <?= $view === 'charts' ? 'text-[#FF5F1F]' : 'text-zinc-500' ?>">
        <i class="bi-bar-chart-fill text-2xl"></i>
        <span class="text-[10px] font-black uppercase tracking-widest">Charts</span>
    </a>
    <a href="/search" class="flex flex-col items-center gap-1 text-zinc-500">
        <i class="bi-search text-2xl"></i>
        <span class="text-[10px] font-black uppercase tracking-widest">Search</span>
    </a>
    <a href="/artists" class="flex flex-col items-center gap-1 <?= in_array($view, ['artists', 'labels', 'stations', 'venues']) ? 'text-[#FF5F1F]' : 'text-zinc-500' ?>">
        <i class="bi-collection-play-fill text-2xl"></i>
        <span class="text-[10px] font-black uppercase tracking-widest">Library</span>
    </a>
</nav>

<!-- Desktop Sidebar (Fixed) -->
<aside class="hidden lg:flex flex-col w-[280px] bg-black fixed inset-y-0 left-0 z-50 border-r border-white/5">
    <div class="p-8">
        <a href="/" class="block mb-10">
            <img src="/lib/images/site/2026/NGN-Logo-Full-Light.png" alt="Next Generation Noise" class="h-10 object-contain">
        </a>

        <nav class="space-y-6">
            <a href="/" class="flex items-center gap-4 font-black text-sm uppercase tracking-widest transition-all <?= $view === 'home' ? 'text-white' : 'text-zinc-500 hover:text-white' ?>">
                <i class="bi-house-door-fill text-2xl <?= $view === 'home' ? 'text-[#FF5F1F]' : '' ?>"></i> Home
            </a>
            <a href="/charts" class="flex items-center gap-4 font-black text-sm uppercase tracking-widest transition-all <?= $view === 'charts' ? 'text-white' : 'text-zinc-500 hover:text-white' ?>">
                <i class="bi-bar-chart-fill text-2xl <?= $view === 'charts' ? 'text-[#FF5F1F]' : '' ?>"></i> NGN Charts
            </a>
            <a href="/smr-charts" class="flex items-center gap-4 font-black text-sm uppercase tracking-widest transition-all <?= $view === 'smr-charts' ? 'text-white' : 'text-zinc-500 hover:text-white' ?>">
                <i class="bi-graph-up text-2xl <?= $view === 'smr-charts' ? 'text-[#FF5F1F]' : '' ?>"></i> SMR Charts
            </a>
            <a href="/videos" class="flex items-center gap-4 font-black text-sm uppercase tracking-widest transition-all <?= $view === 'videos' ? 'text-white' : 'text-zinc-500 hover:text-white' ?>">
                <i class="bi-play-circle-fill text-2xl <?= $view === 'videos' ? 'text-[#FF5F1F]' : '' ?>"></i> Video Vault
            </a>
            <a href="/releases" class="flex items-center gap-4 font-black text-sm uppercase tracking-widest transition-all <?= $view === 'releases' ? 'text-white' : 'text-zinc-500 hover:text-white' ?>">
                <i class="bi-vinyl-fill text-2xl <?= $view === 'releases' ? 'text-[#FF5F1F]' : '' ?>"></i> Releases
            </a>
            <a href="/posts" class="flex items-center gap-4 font-black text-sm uppercase tracking-widest transition-all <?= $view === 'posts' ? 'text-white' : 'text-zinc-500 hover:text-white' ?>">
                <i class="bi-newspaper text-2xl <?= $view === 'posts' ? 'text-[#FF5F1F]' : '' ?>"></i> Intelligence
            </a>
        </nav>
    </div>

    <!-- Your Library Section -->
    <div class="flex-1 px-4 overflow-hidden flex flex-col">
        <div class="bg-zinc-900/50 rounded-2xl flex-1 flex flex-col border border-white/5">
            <div class="p-6 flex items-center justify-between border-b border-white/5">
                <span class="font-black text-[10px] uppercase tracking-[0.2em] text-zinc-500">Your_Library</span>
                <i class="bi-plus-lg text-zinc-500 hover:text-white cursor-pointer"></i>
            </div>
            
            <div class="flex-1 overflow-y-auto p-4 space-y-2 custom-scrollbar">
                <a href="/artists" class="flex items-center gap-3 p-3 rounded-xl hover:bg-white/5 transition-all group">
                    <div class="w-10 h-10 rounded-full bg-zinc-800 flex items-center justify-center group-hover:bg-[#FF5F1F]/20">
                        <i class="bi-person-fill text-zinc-500 group-hover:text-[#FF5F1F]"></i>
                    </div>
                    <span class="font-bold text-sm text-zinc-400 group-hover:text-white">Artists</span>
                </a>
                <a href="/labels" class="flex items-center gap-3 p-3 rounded-xl hover:bg-white/5 transition-all group">
                    <div class="w-10 h-10 rounded-lg bg-zinc-800 flex items-center justify-center group-hover:bg-[#FF5F1F]/20">
                        <i class="bi-record-circle-fill text-zinc-500 group-hover:text-[#FF5F1F]"></i>
                    </div>
                    <span class="font-bold text-sm text-zinc-400 group-hover:text-white">Labels</span>
                </a>
                <a href="/stations" class="flex items-center gap-3 p-3 rounded-xl hover:bg-white/5 transition-all group">
                    <div class="w-10 h-10 rounded-lg bg-zinc-800 flex items-center justify-center group-hover:bg-[#FF5F1F]/20">
                        <i class="bi-broadcast text-zinc-500 group-hover:text-[#FF5F1F]"></i>
                    </div>
                    <span class="font-bold text-sm text-zinc-400 group-hover:text-white">Stations</span>
                </a>
                <a href="/venues" class="flex items-center gap-3 p-3 rounded-xl hover:bg-white/5 transition-all group">
                    <div class="w-10 h-10 rounded-lg bg-zinc-800 flex items-center justify-center group-hover:bg-[#FF5F1F]/20">
                        <i class="bi-geo-alt-fill text-zinc-500 group-hover:text-[#FF5F1F]"></i>
                    </div>
                    <span class="font-bold text-sm text-zinc-400 group-hover:text-white">Venues</span>
                </a>
            </div>
        </div>
    </div>

    <!-- User Section -->
    <div class="p-6 border-t border-white/5">
        <?php if ($isLoggedIn): ?>
            <a href="/dashboard/" class="flex items-center gap-4 p-3 rounded-2xl hover:bg-white/5 transition-all group">
                <img src="<?= htmlspecialchars(user_image($currentUser['Slug'] ?? '', $currentUser['Image'] ?? null)) ?>" class="w-10 h-10 rounded-full object-cover ring-2 ring-white/5 group-hover:ring-[#FF5F1F]/50 transition-all">
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-black text-white truncate"><?= htmlspecialchars($currentUser['display_name'] ?? 'User') ?></div>
                    <div class="text-[10px] font-black text-zinc-500 uppercase tracking-widest">Dashboard</div>
                </div>
            </a>
        <?php else: ?>
            <div class="space-y-3">
                <a href="/login.php" class="block w-full py-4 text-center font-black text-sm uppercase tracking-widest text-white border border-white/10 rounded-2xl hover:bg-white/5 transition-all">Log_In</a>
                <a href="/register.php" class="block w-full py-4 text-center font-black text-sm uppercase tracking-widest bg-[#FF5F1F] text-black rounded-2xl hover:scale-[1.02] transition-all">Join_The_Fleet</a>
            </div>
        <?php endif; ?>
    </div>
</aside>

<style>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #1a1a1a; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #222; }
</style>
