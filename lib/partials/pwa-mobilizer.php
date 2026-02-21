<?php
/**
 * NGN PWA Mobilizer Banner
 * High-yield CTA for mobile app installation.
 */
?>
<div id="pwa-mobilizer" class="hidden fixed top-0 left-0 right-0 z-[200] bg-zinc-900 border-b border-brand/30 text-white px-6 py-4 items-center justify-between shadow-2xl animate-slide-down">
    <div class="flex items-center gap-4">
        <div class="w-10 h-10 bg-brand rounded-lg flex items-center justify-center text-black">
            <i class="bi-phone-fill text-2xl"></i>
        </div>
        <div class="flex-1 min-w-0">
            <h4 class="text-xs font-black uppercase tracking-tighter leading-none text-white">Install_Sovereign_App</h4>
            <p class="text-[9px] font-bold uppercase tracking-widest mt-1 text-zinc-400 truncate">Bypass App Store Tax // Full Immersion</p>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <button onclick="window.NGN_PWA.install()" class="px-4 py-2 bg-brand text-black text-[10px] font-black uppercase tracking-widest rounded-lg shadow-lg active:scale-95 transition-transform">
            INSTALL
        </button>
        <button onclick="document.getElementById('pwa-mobilizer').classList.add('hidden')" class="w-8 h-8 flex items-center justify-center text-zinc-500 hover:text-white">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
</div>

<style>
@keyframes slide-down {
    from { transform: translateY(-100%); }
    to { transform: translateY(0); }
}
.animate-slide-down {
    animation: slide-down 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}
/* Ensure the app frame is pushed down when the mobilizer is visible */
#pwa-mobilizer:not(.hidden) ~ .app-frame {
    margin-top: 72px;
}
</style>
