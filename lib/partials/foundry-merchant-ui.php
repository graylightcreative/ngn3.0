<?php
/**
 * Sovereign Foundry Merchant UI - NGN 3.0
 * Facilitates In-House DTF T-Shirt Listings
 */

use NGN\Lib\Services\Commerce\FoundryService;

$foundry = new FoundryService($config);
$quota = $foundry->validateListing($user['id']);
$mocks = $foundry->getGarmentMocks();
?>

<!-- Foundry Quota Status -->
<div class="mb-12">
    <div class="glass-panel p-8 rounded-3xl border-brand/20 relative overflow-hidden">
        <div class="absolute top-0 right-0 p-4">
            <i class="bi bi-fire text-brand/20 text-6xl"></i>
        </div>
        
        <div class="relative z-10">
            <h3 class="text-2xl font-black text-white italic uppercase tracking-tighter mb-2">Foundry_Status</h3>
            <?php if ($quota['method'] === 'subscription'): ?>
                <p class="text-zinc-400 text-sm font-medium mb-6">You have <span class="text-emerald-400 font-black"><?= $quota['remaining_slots'] ?></span> design slots remaining in your current cycle.</p>
            <?php else: ?>
                <p class="text-zinc-400 text-sm font-medium mb-6">Listing a new design requires a <span class="text-brand font-black"><?= $quota['cost_sparks'] ?> Spark</span> burn.</p>
            <?php endif; ?>

            <?php if ($quota['can_list']): ?>
                <button onclick="document.getElementById('foundry-listing-form').classList.remove('hidden')" class="px-8 py-3 bg-brand text-black font-black rounded-full uppercase tracking-widest hover:scale-105 transition-all shadow-[0_10px_30px_rgba(255,85,0,0.3)]">
                    Create New Design
                </button>
            <?php else: ?>
                <div class="flex items-center gap-4 text-rose-500 font-bold uppercase text-xs tracking-widest">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    Insufficient Sparks to List
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Foundry Listing Form -->
<div id="foundry-listing-form" class="hidden space-y-12">
    <div class="separator-slash"></div>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
        <!-- Step 1: Design -->
        <div class="space-y-8">
            <h4 class="text-xl font-black text-white uppercase italic tracking-tighter">Step 1: The Artwork</h4>
            
            <div class="sp-card border-dashed border-white/10 p-12 text-center group cursor-pointer hover:border-brand/50 transition-colors">
                <i class="bi bi-cloud-upload text-4xl text-zinc-700 group-hover:text-brand transition-colors mb-4 block"></i>
                <div class="text-white font-black uppercase text-sm tracking-widest mb-2">Upload 300DPI Artwork</div>
                <p class="text-zinc-500 text-[10px] uppercase font-bold tracking-widest leading-relaxed">
                    Requirement: PNG with Transparency<br>
                    Max Print Area: 12" x 16"
                </p>
                <input type="file" class="hidden" accept="image/png">
            </div>

            <div class="space-y-4">
                <label class="text-[10px] font-black text-zinc-500 uppercase tracking-widest">Product Name</label>
                <input type="text" placeholder="e.g. Official 2026 World Tour Tee" class="w-full bg-zinc-900 border border-white/5 p-4 rounded-xl text-white font-medium focus:border-brand outline-none transition-all">
            </div>
        </div>

        <!-- Step 2: Garment Selection -->
        <div class="space-y-8">
            <h4 class="text-xl font-black text-white uppercase italic tracking-tighter">Step 2: Garment Selection</h4>
            
            <div class="grid grid-cols-2 gap-4">
                <!-- Mens Mock -->
                <div class="relative group cursor-pointer rounded-2xl overflow-hidden border-2 border-transparent hover:border-brand transition-all">
                    <img src="<?= $mocks['mens'] ?>" class="w-full h-auto opacity-80 group-hover:opacity-100 transition-opacity">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent flex items-end p-4">
                        <span class="text-[10px] font-black text-white uppercase tracking-widest">Unisex 3001</span>
                    </div>
                </div>
                <!-- Womens Mock -->
                <div class="relative group cursor-pointer rounded-2xl overflow-hidden border-2 border-transparent hover:border-brand transition-all">
                    <img src="<?= $mocks['womens'] ?>" class="w-full h-auto opacity-80 group-hover:opacity-100 transition-opacity">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent flex items-end p-4">
                        <span class="text-[10px] font-black text-white uppercase tracking-widest">Womens 3001</span>
                    </div>
                </div>
            </div>

            <div class="bg-black/30 p-6 rounded-2xl border border-white/5 space-y-4">
                <div class="flex justify-between items-center text-xs font-bold uppercase tracking-widest">
                    <span class="text-zinc-500">Retail Price ($)</span>
                    <input type="number" value="25.00" step="0.01" class="w-24 bg-zinc-800 border-none rounded-lg p-2 text-right text-white font-mono">
                </div>
                <div class="h-px bg-white/5"></div>
                <div class="flex justify-between items-center text-[10px] font-bold uppercase tracking-widest text-zinc-500">
                    <span>Wholesale Cost</span>
                    <span class="font-mono text-white">$12.00</span>
                </div>
                <div class="flex justify-between items-center text-[10px] font-bold uppercase tracking-widest text-zinc-500">
                    <span>Board Member Rake (10%)</span>
                    <span class="font-mono text-white">$1.30</span>
                </div>
                <div class="h-px bg-white/5"></div>
                <div class="flex justify-between items-center text-xs font-black uppercase tracking-widest text-emerald-400">
                    <span>Your Profit</span>
                    <span class="font-mono text-lg">$11.70</span>
                </div>
            </div>

            <button class="w-full py-4 bg-white text-black font-black rounded-xl uppercase tracking-widest hover:bg-emerald-400 hover:scale-[1.02] transition-all">
                Publish to Shop
            </button>
        </div>
    </div>
</div>
