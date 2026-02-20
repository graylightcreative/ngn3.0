<?php
/**
 * NGN 2.1.0 Hero Section
 * Raw CSS patterns & Electric Orange theme
 * No external images to avoid CORS flood
 */
?>
<!-- THE NGN STORY ENGINE -->
<div class="relative rounded-[2rem] overflow-hidden mb-16 group border border-white/5 shadow-2xl bg-black">
    <div class="absolute inset-0">
        <div class="absolute inset-0 bg-gradient-to-r from-black via-black/40 to-transparent z-10"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-black via-transparent to-transparent z-10"></div>
        
        <!-- Slide 1: THE FUSION -->
        <div class="absolute inset-0 transition-all duration-1000 opacity-100" data-story-slide="0">
            <div class="w-full h-full bg-[#0b0b0b] flex items-center justify-center">
                <div class="absolute w-full h-full opacity-20" style="background-image: radial-gradient(circle, #FF5F1F 1px, transparent 1px); background-size: 40px 40px;"></div>
                <div class="absolute w-full h-full bg-gradient-to-br from-[#FF5F1F]/5 to-transparent"></div>
            </div>
        </div>
        <!-- Slide 2: THE SOVEREIGNTY -->
        <div class="absolute inset-0 transition-all duration-1000 opacity-0" data-story-slide="1">
            <div class="w-full h-full bg-[#111] flex items-center justify-center">
                <div class="absolute w-full h-full bg-[linear-gradient(45deg,rgba(255,95,31,0.05)_25%,transparent_25%,transparent_50%,rgba(255,95,31,0.05)_50%,rgba(255,95,31,0.05)_75%,transparent_75%,transparent)] bg-[length:60px_60px]"></div>
                <div class="absolute inset-0 bg-gradient-to-tr from-[#FF5F1F]/10 via-transparent to-transparent"></div>
            </div>
        </div>
        <!-- Slide 3: THE REVENUE -->
        <div class="absolute inset-0 transition-all duration-1000 opacity-0" data-story-slide="2">
            <div class="w-full h-full bg-[#070707] flex items-center justify-center">
                <div class="absolute w-full h-full opacity-10" style="background-image: conic-gradient(from 0deg at 50% 50%, #FF5F1F 0deg, transparent 90deg);"></div>
                <div class="absolute inset-0 bg-gradient-to-bl from-[#FF5F1F]/5 via-transparent to-transparent"></div>
            </div>
        </div>
    </div>

    <div class="relative z-20 p-12 lg:p-24 min-h-[600px] flex items-center">
        <div class="max-w-3xl">
            <!-- Content 0 -->
            <div class="story-content transition-all duration-700 block" data-story-content="0">
                <div class="inline-block px-3 py-1 bg-[#FF5F1F] text-black font-black text-[10px] uppercase tracking-widest mb-6 rounded-sm">The_Fusion</div>
                <h1 class="text-6xl lg:text-8xl font-black mb-8 tracking-tighter leading-none text-white">MUSIC MEETS <br><span class="text-[#FF5F1F] italic">TERMINAL</span> VELOCITY</h1>
                <p class="text-xl text-gray-400 font-mono mb-12 max-w-xl">NextGenNoise 2.1.0 is the definitive fusion of underground sound and pressurized tech.</p>
                <div class="flex gap-6">
                    <a href="/register.php" class="bg-[#FF5F1F] text-black font-black px-12 py-5 uppercase text-xs tracking-widest hover:bg-white transition-all rounded-sm">Join_The_Fleet</a>
                </div>
            </div>
            <!-- Content 1 -->
            <div class="story-content transition-all duration-700 hidden" data-story-content="1">
                <div class="inline-block px-3 py-1 bg-[#FF5F1F] text-black font-black text-[10px] uppercase tracking-widest mb-6 rounded-sm">Sovereignty</div>
                <h1 class="text-6xl lg:text-8xl font-black mb-8 tracking-tighter leading-none text-white">OWN YOUR <br><span class="text-[#FF5F1F] italic">INFRASTRUCTURE</span></h1>
                <p class="text-xl text-gray-400 font-mono mb-12 max-w-xl">No more rented platforms. NGN 2.1.0 provides absolute sovereignty for artists and labels.</p>
                <div class="flex gap-6">
                    <a href="/register.php" class="bg-[#FF5F1F] text-black font-black px-12 py-5 uppercase text-xs tracking-widest hover:bg-white transition-all rounded-sm">Get_A_Bunker</a>
                </div>
            </div>
            <!-- Content 2 -->
            <div class="story-content transition-all duration-700 hidden" data-story-content="2">
                <div class="inline-block px-3 py-1 bg-[#FF5F1F] text-black font-black text-[10px] uppercase tracking-widest mb-6 rounded-sm">Revenue</div>
                <h1 class="text-6xl lg:text-8xl font-black mb-8 tracking-tighter leading-none text-white">ARTIST-FIRST <br><span class="text-[#FF5F1F] italic">REVENUE</span> ENGINES</h1>
                <p class="text-xl text-gray-400 font-mono mb-12 max-w-xl">Autonomous marketing loops and institutional invoicing. We build commerce.</p>
                <div class="flex gap-6">
                    <a href="/register.php" class="bg-[#FF5F1F] text-black font-black px-12 py-5 uppercase text-xs tracking-widest hover:bg-white transition-all rounded-sm">Start_Earning</a>
                </div>
            </div>
        </div>
    </div>

    <div class="absolute bottom-12 right-12 z-30 flex gap-3">
        <button onclick="window.storyEngine.to(0)" class="w-12 h-1 bg-white/20 transition-all hover:bg-[#FF5F1F]" data-story-nav="0"></button>
        <button onclick="window.storyEngine.to(1)" class="w-12 h-1 bg-white/20 transition-all hover:bg-[#FF5F1F]" data-story-nav="1"></button>
        <button onclick="window.storyEngine.to(2)" class="w-12 h-1 bg-white/20 transition-all hover:bg-[#FF5F1F]" data-story-nav="2"></button>
    </div>
</div>

<script>
    window.storyEngine = {
        current: 0,
        total: 3,
        to: function(index) {
            this.current = index;
            document.querySelectorAll("[data-story-slide]").forEach((s, i) => {
                if (i === index) {
                    s.classList.remove('opacity-0');
                    s.classList.add('opacity-100');
                } else {
                    s.classList.remove('opacity-100');
                    s.classList.add('opacity-0');
                }
            });
            document.querySelectorAll(".story-content").forEach((c, i) => {
                if (i === index) {
                    c.classList.remove('hidden');
                    c.classList.add('block');
                } else {
                    c.classList.remove('block');
                    c.classList.add('hidden');
                }
            });
            document.querySelectorAll("[data-story-nav]").forEach((n, i) => {
                if (i === index) {
                    n.classList.remove('bg-white/20');
                    n.classList.add('bg-[#FF5F1F]');
                } else {
                    n.classList.remove('bg-[#FF5F1F]');
                    n.classList.add('bg-white/20');
                }
            });
        },
        next: function() {
            this.to((this.current + 1) % this.total);
        }
    };
    setInterval(() => window.storyEngine.next(), 8000);
</script>
