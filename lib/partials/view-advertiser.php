<?php
/**
 * NGN Advertiser Portal - Layman ROI Edition
 * Self-serve interface for results-oriented marketing.
 * Bible Ref: Chapter 18.5
 */
?>

<div class="max-w-6xl mx-auto py-12">
    <div class="flex items-center justify-between mb-12">
        <div>
            <h1 class="text-4xl font-black uppercase tracking-tighter text-white">Marketing_Hub</h1>
            <p class="text-zinc-500 font-bold uppercase tracking-widest text-[10px] mt-1 italic">Automated Results Infrastructure</p>
        </div>
        <div class="px-4 py-2 rounded-full glass border-brand/20">
            <span class="text-[9px] font-black uppercase tracking-widest text-brand" title="Total number of views available across our network each month.">Global Reach: 1.2M Monthly Views</span>
        </div>
    </div>

    <!-- ADVERTISER STATS -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-16">
        <div class="glass p-8 rounded-[2rem] border-brand/10">
            <div class="text-[9px] font-black text-zinc-600 uppercase tracking-widest mb-2">Active_Promotions</div>
            <div class="text-3xl font-black text-white">0</div>
        </div>
        <div class="glass p-8 rounded-[2rem] border-white/5">
            <div class="text-[9px] font-black text-zinc-600 uppercase tracking-widest mb-2">Total_Reach</div>
            <div class="text-3xl font-black text-white">0</div>
        </div>
        <div class="glass p-8 rounded-[2rem] border-white/5">
            <div class="text-[9px] font-black text-zinc-600 uppercase tracking-widest mb-2">Marketing_Investment</div>
            <div class="text-3xl font-black text-brand">$0.00</div>
        </div>
    </div>

    <!-- LAUNCH CAMPAIGN CTA -->
    <div class="glass p-12 rounded-[3rem] border-brand/20 text-center relative overflow-hidden group">
        <div class="absolute inset-0 bg-brand/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
        <i class="bi-megaphone text-6xl text-brand/20 mb-8 block"></i>
        <h2 class="text-2xl font-black text-white uppercase mb-4">Promote to the Independent Music Network</h2>
        <p class="text-zinc-500 max-w-xl mx-auto mb-10 text-sm leading-relaxed font-bold">
            Reach 15,000+ active partners and their global fanbases with automated audio and visual promotions. 
            Grow your brand with direct, high-impact visibility.
        </p>
        <button onclick="openCampaignModal()" class="px-10 py-4 bg-brand text-black font-black uppercase tracking-widest rounded-2xl hover:scale-105 transition-all shadow-xl shadow-brand/20">
            Launch_New_Promotion
        </button>
    </div>

    <!-- PLACEMENT PREVIEW -->
    <div class="mt-20">
        <h3 class="text-xs font-black text-zinc-600 uppercase tracking-[0.4em] mb-10 pl-2 font-mono">Available_Options</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="glass p-6 rounded-2xl border-white/5 flex gap-6 items-center cursor-help" title="High-visibility banner that appears on every artist and label profile.">
                <div class="w-24 h-24 bg-zinc-900 rounded-xl border border-dashed border-zinc-700 flex items-center justify-center">
                    <span class="text-[8px] font-bold text-zinc-600 uppercase">Banner_Ad</span>
                </div>
                <div>
                    <h4 class="text-white font-black uppercase text-sm">Site-Wide Promotion</h4>
                    <p class="text-[10px] text-zinc-500 mt-1">Maximum visibility across all partner profiles.</p>
                    <div class="mt-3 text-brand font-mono text-[9px] font-bold">$12.00 per 1k views</div>
                </div>
            </div>
            <div class="glass p-6 rounded-2xl border-white/5 flex gap-6 items-center cursor-help" title="A short audio promotion played before music starts on our production labs.">
                <div class="w-24 h-24 bg-zinc-900 rounded-xl border border-dashed border-zinc-700 flex items-center justify-center">
                    <span class="text-[8px] font-bold text-zinc-600 uppercase">Audio_Ad</span>
                </div>
                <div>
                    <h4 class="text-white font-black uppercase text-sm">Radio Promotion</h4>
                    <p class="text-[10px] text-zinc-500 mt-1">Speak directly to listeners on our production labs.</p>
                    <div class="mt-3 text-brand font-mono text-[9px] font-bold">$25.00 per 1k plays</div>
                </div>
            </div>
        </div>
    </div>
</div>
