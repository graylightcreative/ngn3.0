<?php
/**
 * NGN Sovereign Integrations Dashboard
 * Foundry Standard: Deep Charcoal / Electric Orange
 */
?>

<div class="max-w-6xl mx-auto py-12">
    <div class="flex items-center justify-between mb-12">
        <div>
            <h1 class="text-4xl font-black uppercase tracking-tighter text-white">Sovereign_Integrations</h1>
            <p class="text-zinc-500 font-bold uppercase tracking-widest text-[10px] mt-1 italic">Fleet Ecosystem Handshake Status</p>
        </div>
        <div class="px-4 py-2 rounded-full glass border-brand/20">
            <span class="w-2 h-2 rounded-full bg-brand inline-block mr-2 animate-pulse"></span>
            <span class="text-[9px] font-black uppercase tracking-widest text-brand">System_Online</span>
        </div>
    </div>

    <!-- CORE FLEET NODES -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-16">
        <div class="glass p-8 rounded-[2rem] border-brand/10 relative overflow-hidden group">
            <div class="absolute -top-4 -right-4 w-20 h-20 bg-brand/5 rounded-full blur-2xl"></div>
            <div class="flex items-center justify-between mb-6">
                <div class="w-12 h-12 bg-zinc-900 rounded-xl flex items-center justify-center border border-white/5">
                    <i class="bi-shield-check text-xl text-brand"></i>
                </div>
                <div class="text-[9px] font-black text-green-500 uppercase tracking-widest font-mono">Connected</div>
            </div>
            <h3 class="text-lg font-black text-white uppercase mb-2">Beacon_ID</h3>
            <p class="text-xs text-zinc-500 font-medium leading-relaxed">Centralized identity and SSO handshake for the Graylight Fleet.</p>
        </div>

        <div class="glass p-8 rounded-[2rem] border-white/5 group">
            <div class="flex items-center justify-between mb-6">
                <div class="w-12 h-12 bg-zinc-900 rounded-xl flex items-center justify-center border border-white/5">
                    <i class="bi-safe text-xl text-zinc-500"></i>
                </div>
                <div class="text-[9px] font-black text-zinc-600 uppercase tracking-widest font-mono">Syncing</div>
            </div>
            <h3 class="text-lg font-black text-white uppercase mb-2">Ledger_Moat</h3>
            <p class="text-xs text-zinc-500 font-medium leading-relaxed">Cryptographic revenue split and royalty tracking engine.</p>
        </div>

        <div class="glass p-8 rounded-[2rem] border-white/5 group">
            <div class="flex items-center justify-between mb-6">
                <div class="w-12 h-12 bg-zinc-900 rounded-xl flex items-center justify-center border border-white/5">
                    <i class="bi-cpu text-xl text-zinc-500"></i>
                </div>
                <div class="text-[9px] font-black text-zinc-600 uppercase tracking-widest font-mono">Standby</div>
            </div>
            <h3 class="text-lg font-black text-white uppercase mb-2">Shredder_AI</h3>
            <p class="text-xs text-zinc-500 font-medium leading-relaxed">Stem isolation and mastery tools for interactive practice.</p>
        </div>
    </div>

    <!-- EXTERNAL PLATFORMS -->
    <h2 class="text-xs font-black text-zinc-600 uppercase tracking-[0.4em] mb-10 pl-2 font-mono">External_API_Routes</h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
        <div class="glass p-6 rounded-2xl border-white/5 hover:border-brand/20 transition-all cursor-pointer">
            <div class="flex items-center gap-4">
                <i class="bi-spotify text-2xl text-zinc-700"></i>
                <div class="text-[10px] font-bold uppercase tracking-widest text-zinc-500">Spotify</div>
            </div>
        </div>
        <div class="glass p-6 rounded-2xl border-white/5 hover:border-brand/20 transition-all cursor-pointer">
            <div class="flex items-center gap-4">
                <i class="bi-apple text-2xl text-zinc-700"></i>
                <div class="text-[10px] font-bold uppercase tracking-widest text-zinc-500">Apple_Music</div>
            </div>
        </div>
        <div class="glass p-6 rounded-2xl border-white/5 hover:border-brand/20 transition-all cursor-pointer">
            <div class="flex items-center gap-4">
                <i class="bi-youtube text-2xl text-zinc-700"></i>
                <div class="text-[10px] font-bold uppercase tracking-widest text-zinc-500">YouTube</div>
            </div>
        </div>
        <div class="glass p-6 rounded-2xl border-white/5 hover:border-brand/20 transition-all cursor-pointer">
            <div class="flex items-center gap-4">
                <i class="bi-facebook text-2xl text-zinc-700"></i>
                <div class="text-[10px] font-bold uppercase tracking-widest text-zinc-500">Meta_Social</div>
            </div>
        </div>
    </div>
</div>
