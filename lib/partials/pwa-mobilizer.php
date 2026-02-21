<?php
/**
 * NGN PWA Mobilizer Banner
 * High-yield CTA for mobile app installation.
 */
?>
<div id="pwa-mobilizer" class="hidden fixed top-0 left-0 right-0 z-[200] bg-brand text-black px-6 py-4 items-center justify-between shadow-2xl animate-slide-down">
    <div class="flex items-center gap-4">
        <div class="w-10 h-10 bg-black rounded-lg flex items-center justify-center text-brand">
            <i class="bi-phone-fill text-2xl"></i>
        </div>
        <div>
            <h4 class="text-xs font-black uppercase tracking-tighter leading-none">Install_Sovereign_App</h4>
            <p class="text-[9px] font-bold uppercase tracking-widest mt-1 opacity-80">Bypass the App Store Tax // Lock-Screen Active</p>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <button onclick="window.NGN_PWA.install()" class="px-4 py-2 bg-black text-white text-[10px] font-black uppercase tracking-widest rounded-lg shadow-lg active:scale-95 transition-transform">
            Install_Now
        </button>
        <button onclick="document.getElementById('pwa-mobilizer').classList.add('hidden')" class="text-black/40 hover:text-black">
            <i class="bi-x-lg"></i>
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
</style>
