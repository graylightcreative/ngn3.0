<!-- Sovereign Drawer: Full Screen Navigation -->
<div id="sovereign-menu" class="fixed inset-0 z-[200] hidden bg-black/95 backdrop-blur-2xl transition-all duration-500 overflow-y-auto">
    <div class="max-w-xl mx-auto px-8 py-12">
        <!-- Close Header -->
        <div class="flex items-center justify-between mb-16">
            <img src="/lib/images/site/2026/NGN-Logo-Full-Light.png" class="h-8 w-auto" alt="NGN">
            <button onclick="toggleSovereignMenu()" class="w-12 h-12 rounded-full bg-white/5 flex items-center justify-center text-white text-2xl hover:bg-white/10 transition-all">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <!-- Menu Grid -->
        <nav class="grid grid-cols-2 gap-6 mb-16">
            <a href="/" class="p-6 rounded-3xl bg-white/5 border border-white/5 flex flex-col gap-4 hover:bg-brand/10 hover:border-brand/20 transition-all group">
                <i class="bi bi-house-fill text-3xl text-zinc-500 group-hover:text-brand transition-colors"></i>
                <span class="font-black uppercase tracking-widest text-sm text-white">Home</span>
            </a>
            <a href="/charts" class="p-6 rounded-3xl bg-white/5 border border-white/5 flex flex-col gap-4 hover:bg-brand/10 hover:border-brand/20 transition-all group">
                <i class="bi bi-bar-chart-fill text-3xl text-zinc-500 group-hover:text-brand transition-colors"></i>
                <span class="font-black uppercase tracking-widest text-sm text-white">Charts</span>
            </a>
            <a href="/releases" class="p-6 rounded-3xl bg-white/5 border border-white/5 flex flex-col gap-4 hover:bg-brand/10 hover:border-brand/20 transition-all group">
                <i class="bi bi-vinyl-fill text-3xl text-zinc-500 group-hover:text-brand transition-colors"></i>
                <span class="font-black uppercase tracking-widest text-sm text-white">Music</span>
            </a>
            <a href="/posts" class="p-6 rounded-3xl bg-white/5 border border-white/5 flex flex-col gap-4 hover:bg-brand/10 hover:border-brand/20 transition-all group">
                <i class="bi bi-newspaper text-3xl text-zinc-500 group-hover:text-brand transition-colors"></i>
                <span class="font-black uppercase tracking-widest text-sm text-white">News</span>
            </a>
            <a href="/artists" class="p-6 rounded-3xl bg-white/5 border border-white/5 flex flex-col gap-4 hover:bg-brand/10 hover:border-brand/20 transition-all group">
                <i class="bi bi-people-fill text-3xl text-zinc-500 group-hover:text-brand transition-colors"></i>
                <span class="font-black uppercase tracking-widest text-sm text-white">Artists</span>
            </a>
            <a href="/labels" class="p-6 rounded-3xl bg-white/5 border border-white/5 flex flex-col gap-4 hover:bg-brand/10 hover:border-brand/20 transition-all group">
                <i class="bi bi-record-circle-fill text-3xl text-zinc-500 group-hover:text-brand transition-colors"></i>
                <span class="font-black uppercase tracking-widest text-sm text-white">Labels</span>
            </a>
            <a href="/stations" class="p-6 rounded-3xl bg-white/5 border border-white/5 flex flex-col gap-4 hover:bg-brand/10 hover:border-brand/20 transition-all group">
                <i class="bi bi-broadcast-pin text-3xl text-zinc-500 group-hover:text-brand transition-colors"></i>
                <span class="font-black uppercase tracking-widest text-sm text-white">Stations</span>
            </a>
            <a href="/venues" class="p-6 rounded-3xl bg-white/5 border border-white/5 flex flex-col gap-4 hover:bg-brand/10 hover:border-brand/20 transition-all group">
                <i class="bi bi-geo-alt-fill text-3xl text-zinc-500 group-hover:text-brand transition-colors"></i>
                <span class="font-black uppercase tracking-widest text-sm text-white">Venues</span>
            </a>
            <a href="/pricing" class="p-6 rounded-3xl bg-white/5 border border-white/5 flex flex-col gap-4 hover:bg-brand/10 hover:border-brand/20 transition-all group">
                <i class="bi bi-star-fill text-3xl text-zinc-500 group-hover:text-brand transition-colors"></i>
                <span class="font-black uppercase tracking-widest text-sm text-white">Premium</span>
            </a>
            <a href="/shop" class="p-6 rounded-3xl bg-white/5 border border-white/5 flex flex-col gap-4 hover:bg-brand/10 hover:border-brand/20 transition-all group">
                <i class="bi bi-bag-check-fill text-3xl text-zinc-500 group-hover:text-brand transition-colors"></i>
                <span class="font-black uppercase tracking-widest text-sm text-white">Merch</span>
            </a>
        </nav>

        <!-- Extra Links -->
        <div class="space-y-4 mb-16">
            <a href="/advertisers" class="flex items-center justify-between p-4 rounded-2xl hover:bg-white/5 transition-all group">
                <span class="font-black uppercase tracking-widest text-xs text-zinc-400 group-hover:text-white">Advertiser Portal</span>
                <i class="bi bi-chevron-right text-zinc-700"></i>
            </a>
            <a href="/legal" class="flex items-center justify-between p-4 rounded-2xl hover:bg-white/5 transition-all group">
                <span class="font-black uppercase tracking-widest text-xs text-zinc-400 group-hover:text-white">Legal & IP Intelligence</span>
                <i class="bi bi-chevron-right text-zinc-700"></i>
            </a>
            <a href="/help" class="flex items-center justify-between p-4 rounded-2xl hover:bg-white/5 transition-all group">
                <span class="font-black uppercase tracking-widest text-xs text-zinc-400 group-hover:text-white">Operator Support</span>
                <i class="bi bi-chevron-right text-zinc-700"></i>
            </a>
        </div>

        <!-- Logout / Profile -->
        <?php if ($isLoggedIn): ?>
            <a href="/logout.php" class="block w-full py-5 rounded-full bg-red-500/10 border border-red-500/20 text-red-500 font-black uppercase tracking-widest text-center text-xs hover:bg-red-500 hover:text-black transition-all">
                Terminate Session
            </a>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleSovereignMenu() {
    const menu = document.getElementById('sovereign-menu');
    if (menu.classList.contains('hidden')) {
        menu.classList.remove('hidden');
        document.body.style.overflow = 'hidden'; // Prevent scroll
    } else {
        menu.classList.add('hidden');
        document.body.style.overflow = '';
    }
}
</script>
