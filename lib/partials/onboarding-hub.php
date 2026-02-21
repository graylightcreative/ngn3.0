<?php
/**
 * NGN Onboarding Hub (Institutional B2B & Fan Acquisition)
 * Vibrant 2026 Design
 */
?>
<section class="mb-24 mt-32 relative">
    <!-- Badass Separator -->
    <div class="absolute -top-16 left-1/2 -translate-x-1/2 w-[80vw] max-w-4xl h-px bg-gradient-to-r from-transparent via-brand to-transparent opacity-50"></div>
    <div class="absolute -top-16 left-1/2 -translate-x-1/2 w-[40vw] max-w-xl h-[2px] bg-brand glow-primary"></div>

    <div class="text-center mb-16 relative z-10">
        <h2 class="text-5xl lg:text-7xl font-black tracking-tighter uppercase italic mb-4 brand-gradient-text drop-shadow-2xl">Join_The_Movement</h2>
        <p class="text-brand font-mono text-sm tracking-widest uppercase glow-primary">Select your role to deploy your infrastructure.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 max-w-7xl mx-auto relative z-10 px-4">
        <!-- Fan Acquisition (Aggressive Neon) -->
        <div class="neon-border rounded-[2.5rem] relative group">
            <div class="bg-black/80 backdrop-blur-3xl p-8 md:p-12 rounded-[2.5rem] flex flex-col justify-between h-full border border-brand/50 shadow-[0_0_50px_rgba(255,95,31,0.15)] overflow-hidden relative">
                <!-- Inner Mesh -->
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,var(--primary)_0%,transparent_50%)] opacity-20"></div>
                
                <div class="relative z-10">
                    <div class="w-20 h-20 bg-brand rounded-3xl flex items-center justify-center text-black mb-8 group-hover:scale-110 group-hover:rotate-6 transition-all shadow-[0_0_30px_var(--primary)]">
                        <i class="bi bi-person-heart text-4xl"></i>
                    </div>
                    <h3 class="text-5xl font-black text-white uppercase tracking-tighter mb-4 leading-none drop-shadow-lg">I_am_a_Fan</h3>
                    <p class="text-zinc-300 font-bold text-lg mb-8 leading-relaxed">Support your favorite artists directly. Access exclusive content, verified charts, and private community bunkers.</p>
                </div>
                <a href="/register.php" class="relative z-10 inline-block w-full py-6 bg-brand text-black text-center font-black uppercase tracking-[0.3em] text-sm rounded-full hover:bg-white transition-all shadow-[0_10px_30px_rgba(255,95,31,0.4)]">Sign_Up_As_Fan</a>
            </div>
        </div>

        <!-- Professional Roles -->
        <div class="grid grid-cols-1 gap-5">
            <!-- Artists -->
            <div class="glass-panel p-6 md:p-8 rounded-3xl flex flex-col sm:flex-row sm:items-center justify-between group gap-6">
                <div class="flex items-center gap-6">
                    <div class="w-14 h-14 rounded-xl vibrant-icon-wrapper flex items-center justify-center text-brand flex-shrink-0">
                        <i class="bi bi-mic-fill text-2xl"></i>
                    </div>
                    <div>
                        <h4 class="text-2xl font-black text-white uppercase tracking-tight mb-1 group-hover:text-brand transition-colors">Artists</h4>
                        <p class="text-xs text-zinc-400 font-bold uppercase tracking-widest">Claim profile & distribute content</p>
                    </div>
                </div>
                <a href="/help?role=artist" class="px-8 py-4 bg-white/5 border border-white/10 text-white text-[10px] font-black uppercase tracking-[0.2em] rounded-full hover:bg-brand hover:text-black hover:border-brand transition-all whitespace-nowrap text-center">Claim_Now</a>
            </div>
            <!-- Labels -->
            <div class="glass-panel p-6 md:p-8 rounded-3xl flex flex-col sm:flex-row sm:items-center justify-between group gap-6">
                <div class="flex items-center gap-6">
                    <div class="w-14 h-14 rounded-xl vibrant-icon-wrapper flex items-center justify-center text-brand flex-shrink-0">
                        <i class="bi bi-record-vinyl-fill text-2xl"></i>
                    </div>
                    <div>
                        <h4 class="text-2xl font-black text-white uppercase tracking-tight mb-1 group-hover:text-brand transition-colors">Labels</h4>
                        <p class="text-xs text-zinc-400 font-bold uppercase tracking-widest">Deploy institutional catalog</p>
                    </div>
                </div>
                <a href="/register.php?role=label" class="px-8 py-4 bg-white/5 border border-white/10 text-white text-[10px] font-black uppercase tracking-[0.2em] rounded-full hover:bg-brand hover:text-black hover:border-brand transition-all whitespace-nowrap text-center">Institutional_Signup</a>
            </div>
            <!-- Stations -->
            <div class="glass-panel p-6 md:p-8 rounded-3xl flex flex-col sm:flex-row sm:items-center justify-between group gap-6">
                <div class="flex items-center gap-6">
                    <div class="w-14 h-14 rounded-xl vibrant-icon-wrapper flex items-center justify-center text-brand flex-shrink-0">
                        <i class="bi bi-broadcast text-2xl"></i>
                    </div>
                    <div>
                        <h4 class="text-2xl font-black text-white uppercase tracking-tight mb-1 group-hover:text-brand transition-colors">Stations</h4>
                        <p class="text-xs text-zinc-400 font-bold uppercase tracking-widest">Broadcast & track radio spins</p>
                    </div>
                </div>
                <a href="/register.php?role=station" class="px-8 py-4 bg-white/5 border border-white/10 text-white text-[10px] font-black uppercase tracking-[0.2em] rounded-full hover:bg-brand hover:text-black hover:border-brand transition-all whitespace-nowrap text-center">Get_Airwaves</a>
            </div>
            <!-- Venues -->
            <div class="glass-panel p-6 md:p-8 rounded-3xl flex flex-col sm:flex-row sm:items-center justify-between group gap-6">
                <div class="flex items-center gap-6">
                    <div class="w-14 h-14 rounded-xl vibrant-icon-wrapper flex items-center justify-center text-brand flex-shrink-0">
                        <i class="bi bi-geo-alt-fill text-2xl"></i>
                    </div>
                    <div>
                        <h4 class="text-2xl font-black text-white uppercase tracking-tight mb-1 group-hover:text-brand transition-colors">Venues</h4>
                        <p class="text-xs text-zinc-400 font-bold uppercase tracking-widest">Secure show bookings & ticketing</p>
                    </div>
                </div>
                <a href="/help?role=venue" class="px-8 py-4 bg-white/5 border border-white/10 text-white text-[10px] font-black uppercase tracking-[0.2em] rounded-full hover:bg-brand hover:text-black hover:border-brand transition-all whitespace-nowrap text-center">Claim_Venue</a>
            </div>
        </div>
    </div>
</section>
