<?php
/**
 * NGN Advertiser Portal - Foundry Standard
 * Self-serve interface for high-margin programmatic inventory.
 * Bible Ref: Chapter 18.5
 */
?>

<div class="max-w-6xl mx-auto py-12">
    <div class="flex items-center justify-between mb-12">
        <div>
            <h1 class="text-4xl font-black uppercase tracking-tighter text-white">Advertiser_Portal</h1>
            <p class="text-zinc-500 font-bold uppercase tracking-widest text-[10px] mt-1 italic">Foundry Global Ad-Serving Engine</p>
        </div>
        <div class="px-4 py-2 rounded-full glass border-brand/20">
            <span class="text-[9px] font-black uppercase tracking-widest text-brand">Inventory_Live: 1.2M Impressions/mo</span>
        </div>
    </div>

    <!-- ADVERTISER STATS -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-16">
        <div class="glass p-8 rounded-[2rem] border-brand/10">
            <div class="text-[9px] font-black text-zinc-600 uppercase tracking-widest mb-2">Active_Campaigns</div>
            <div class="text-3xl font-black text-white">0</div>
        </div>
        <div class="glass p-8 rounded-[2rem] border-white/5">
            <div class="text-[9px] font-black text-zinc-600 uppercase tracking-widest mb-2">Total_Impressions</div>
            <div class="text-3xl font-black text-white">0</div>
        </div>
        <div class="glass p-8 rounded-[2rem] border-white/5">
            <div class="text-[9px] font-black text-zinc-600 uppercase tracking-widest mb-2">Ad_Spend_Total</div>
            <div class="text-3xl font-black text-brand">$0.00</div>
        </div>
    </div>

    <!-- LAUNCH CAMPAIGN CTA -->
    <div class="glass p-12 rounded-[3rem] border-brand/20 text-center relative overflow-hidden group">
        <div class="absolute inset-0 bg-brand/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
        <i class="bi-megaphone text-6xl text-brand/20 mb-8 block"></i>
        <h2 class="text-2xl font-black text-white uppercase mb-4">Target the Independent Music Monopoly</h2>
        <p class="text-zinc-500 max-w-xl mx-auto mb-10 text-sm leading-relaxed">
            Reach 15k+ artists and their fanbases with programmatic audio, video, and display placements.
            Zero fluff. Direct conversion.
        </p>
        <button onclick="openCampaignModal()" class="px-10 py-4 bg-brand text-black font-black uppercase tracking-widest rounded-2xl hover:scale-105 transition-all shadow-xl shadow-brand/20">
            Create_New_Campaign
        </button>
    </div>

    <!-- PLACEMENT PREVIEW -->
    <div class="mt-20">
        <h3 class="text-xs font-black text-zinc-600 uppercase tracking-[0.4em] mb-10 pl-2 font-mono">Available_Placements</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="glass p-6 rounded-2xl border-white/5 flex gap-6 items-center">
                <div class="w-24 h-24 bg-zinc-900 rounded-xl border border-dashed border-zinc-700 flex items-center justify-center">
                    <span class="text-[8px] font-bold text-zinc-600 uppercase">Sidebar_Box</span>
                </div>
                <div>
                    <h4 class="text-white font-black uppercase text-sm">Main_Sidebar_Billboard</h4>
                    <p class="text-[10px] text-zinc-500 mt-1">High-visibility sticky placement across all profiles.</p>
                    <div class="mt-3 text-brand font-mono text-[9px] font-bold">$12.00 CPM</div>
                </div>
            </div>
            <div class="glass p-6 rounded-2xl border-white/5 flex gap-6 items-center">
                <div class="w-24 h-24 bg-zinc-900 rounded-xl border border-dashed border-zinc-700 flex items-center justify-center">
                    <span class="text-[8px] font-bold text-zinc-600 uppercase">Player_Audio</span>
                </div>
                <div>
                    <h4 class="text-white font-black uppercase text-sm">Audio_Stream_Injection</h4>
                    <p class="text-[10px] text-zinc-500 mt-1">Non-skippable 15s pre-roll on station streams.</p>
                    <div class="mt-3 text-brand font-mono text-[9px] font-bold">$25.00 CPM</div>
                </div>
            </div>
        </div>
    </div>
</div>
