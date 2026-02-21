<?php
/**
 * NGN Onboarding Hub (Institutional B2B & Fan Acquisition)
 */
?>
<section class="mb-24 mt-24">
    <div class="text-center mb-16">
        <h2 class="text-4xl lg:text-6xl font-black tracking-tighter text-white uppercase italic mb-4">Join_The_Movement</h2>
        <p class="text-zinc-500 font-mono text-sm">Select your role to deploy your infrastructure.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 max-w-6xl mx-auto">
        <!-- Fan Acquisition -->
        <div class="bg-brand p-8 md:p-12 rounded-[2.5rem] flex flex-col justify-between group shadow-2xl shadow-brand/10">
            <div>
                <div class="w-16 h-16 bg-black rounded-2xl flex items-center justify-center text-brand mb-8 group-hover:scale-110 transition-transform">
                    <i class="bi bi-person-heart text-3xl"></i>
                </div>
                <h3 class="text-4xl font-black text-black uppercase tracking-tighter mb-4 leading-none">I_am_a_Fan</h3>
                <p class="text-black/70 font-bold text-lg mb-8 leading-tight">Support your favorite artists directly. Access exclusive content, verified charts, and private community bunkers.</p>
            </div>
            <a href="/register.php" class="inline-block w-full py-5 bg-black text-white text-center font-black uppercase tracking-widest text-xs rounded-full hover:scale-[1.02] transition-all">Sign_Up_As_Fan</a>
        </div>

        <!-- Professional Roles -->
        <div class="grid grid-cols-1 gap-4">
            <!-- Artists -->
            <div class="bg-zinc-900/50 border border-white/5 p-8 rounded-3xl flex items-center justify-between group hover:border-brand/30 transition-all">
                <div>
                    <h4 class="text-xl font-black text-white uppercase tracking-tight mb-1">Artists</h4>
                    <p class="text-xs text-zinc-500 font-bold uppercase tracking-widest">Claim profile & distribute content</p>
                </div>
                <a href="/help?role=artist" class="px-6 py-3 bg-white/5 border border-white/10 text-white text-[10px] font-black uppercase tracking-widest rounded-full hover:bg-brand hover:text-black hover:border-brand transition-all">Claim_Now</a>
            </div>
            <!-- Labels -->
            <div class="bg-zinc-900/50 border border-white/5 p-8 rounded-3xl flex items-center justify-between group hover:border-brand/30 transition-all">
                <div>
                    <h4 class="text-xl font-black text-white uppercase tracking-tight mb-1">Labels</h4>
                    <p class="text-xs text-zinc-500 font-bold uppercase tracking-widest">Deploy institutional catalog</p>
                </div>
                <a href="/register.php?role=label" class="px-6 py-3 bg-white/5 border border-white/10 text-white text-[10px] font-black uppercase tracking-widest rounded-full hover:bg-brand hover:text-black hover:border-brand transition-all">Institutional_Signup</a>
            </div>
            <!-- Stations -->
            <div class="bg-zinc-900/50 border border-white/5 p-8 rounded-3xl flex items-center justify-between group hover:border-brand/30 transition-all">
                <div>
                    <h4 class="text-xl font-black text-white uppercase tracking-tight mb-1">Stations</h4>
                    <p class="text-xs text-zinc-500 font-bold uppercase tracking-widest">Broadcast & track radio spins</p>
                </div>
                <a href="/register.php?role=station" class="px-6 py-3 bg-white/5 border border-white/10 text-white text-[10px] font-black uppercase tracking-widest rounded-full hover:bg-brand hover:text-black hover:border-brand transition-all">Get_Airwaves</a>
            </div>
            <!-- Venues -->
            <div class="bg-zinc-900/50 border border-white/5 p-8 rounded-3xl flex items-center justify-between group hover:border-brand/30 transition-all">
                <div>
                    <h4 class="text-xl font-black text-white uppercase tracking-tight mb-1">Venues</h4>
                    <p class="text-xs text-zinc-500 font-bold uppercase tracking-widest">Secure show bookings & ticketing</p>
                </div>
                <a href="/help?role=venue" class="px-6 py-3 bg-white/5 border border-white/10 text-white text-[10px] font-black uppercase tracking-widest rounded-full hover:bg-brand hover:text-black hover:border-brand transition-all">Claim_Venue</a>
            </div>
        </div>
    </div>
</section>
