<?php
/**
 * NGN PWA Mobilizer Banner v3.1.0
 * High-yield CTA for mobile app installation.
 */
?>
<div id="pwa-mobilizer" class="hidden fixed top-0 left-0 right-0 z-[200] bg-[#0a0a0a] border-b border-brand/30 text-white px-4 md:px-6 py-4 items-center justify-between shadow-2xl animate-slide-down w-full overflow-hidden">
    <div class="flex items-center gap-3 md:gap-4 flex-1 min-w-0">
        <div class="w-10 h-10 md:w-12 md:h-12 bg-brand rounded-xl flex items-center justify-center text-black flex-shrink-0">
            <i class="bi bi-phone-fill text-2xl"></i>
        </div>
        <div class="flex-1 min-w-0">
            <h4 class="text-[11px] md:text-xs font-black uppercase tracking-tighter leading-none text-white truncate">Install_Sovereign_App</h4>
            <p class="text-[8px] md:text-[9px] font-bold uppercase tracking-widest mt-1 text-zinc-500 truncate">Bypass App Store Tax // Full Immersion</p>
        </div>
    </div>
    <div class="flex items-center gap-2 md:gap-3 ml-2 flex-shrink-0">
        <button onclick="window.NGN_PWA.install()" class="px-3 md:px-4 py-2 bg-brand text-black text-[9px] md:text-[10px] font-black uppercase tracking-widest rounded-lg shadow-lg active:scale-95 transition-transform">
            INSTALL
        </button>
        <button onclick="document.getElementById('pwa-mobilizer').classList.add('hidden')" class="w-8 h-8 flex items-center justify-center text-zinc-600 hover:text-white transition-colors">
            <i class="bi bi-x-lg text-lg"></i>
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
/* Force the app frame down when banner is active */
#pwa-mobilizer:not(.hidden) ~ .app-frame {
    margin-top: 72px !important;
}
</style>
