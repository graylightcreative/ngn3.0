<?php
/**
 * Sovereign Story Engine v3.1.0
 * Mobile-First Responsive Showcase
 */
?>
<div class="relative rounded-2xl md:rounded-[2rem] overflow-hidden mb-12 md:mb-16 group border border-white/5 shadow-2xl bg-[#050505] min-h-[500px] md:min-h-[600px] flex items-center">
    <div class="absolute inset-0">
        <div class="absolute inset-0 bg-gradient-to-r from-black via-black/60 to-transparent z-10"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-black via-transparent to-transparent z-10"></div>
        
        <!-- Slide 1: CLARITY VST -->
        <div class="absolute inset-0 transition-all duration-1000 opacity-100" data-story-slide="0">
            <div class="w-full h-full flex items-center justify-center relative">
                <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle, #FF5F1F 1.5px, transparent 1px); background-size: 30px 30px;"></div>
                <div class="w-[80%] h-[80%] bg-brand/5 blur-[80px] md:blur-[120px] rounded-full animate-pulse"></div>
            </div>
        </div>
        <!-- Slide 2: SHREDDER ENGINE -->
        <div class="absolute inset-0 transition-all duration-1000 opacity-0" data-story-slide="1">
            <div class="w-full h-full bg-[#0a0a0a] flex items-center justify-center">
                <div class="absolute w-full h-full bg-[linear-gradient(45deg,rgba(255,95,31,0.03)_25%,transparent_25%,transparent_50%,rgba(255,95,31,0.03)_50%,rgba(255,95,31,0.03)_75%,transparent_75%,transparent)] bg-[length:60px_60px] md:bg-[length:80px_80px]"></div>
                <div class="w-full h-full bg-gradient-to-br from-brand/10 via-transparent to-transparent"></div>
            </div>
        </div>
        <!-- Slide 3: SHELLSHOCK CMS -->
        <div class="absolute inset-0 transition-all duration-1000 opacity-0" data-story-slide="2">
            <div class="w-full h-full bg-[#050505] flex items-center justify-center">
                <div class="absolute inset-0 opacity-10" style="background-image: conic-gradient(from 180deg at 50% 50%, #FF5F1F 0deg, transparent 120deg);"></div>
                <div class="w-full h-full bg-gradient-to-tr from-brand/5 via-transparent to-transparent"></div>
            </div>
        </div>
    </div>

    <div class="relative z-20 p-6 md:p-12 lg:p-24 w-full">
        <div class="max-w-4xl">
            <!-- Content 0: Clarity -->
            <div class="story-content transition-all duration-700 block" data-story-content="0">
                <div class="inline-flex items-center gap-3 px-3 py-1 bg-brand text-black font-black text-[9px] md:text-[10px] uppercase tracking-widest mb-6 md:mb-8 rounded-sm">
                    <i class="bi bi-cpu-fill"></i> Hardware_Agnostic
                </div>
                <h1 class="text-5xl md:text-7xl lg:text-9xl font-black mb-6 md:mb-8 tracking-tighter leading-[0.9] text-white uppercase">Clarity<span class="text-brand">_VST</span></h1>
                <p class="text-base md:text-xl text-zinc-400 font-medium mb-8 md:mb-12 max-w-2xl leading-relaxed">The source of truth for high-fidelity audio processing. Coming soon.</p>
                <div class="flex flex-col sm:flex-row gap-4 md:gap-6">
                    <a href="https://clarity.nextgennoise.com" target="_blank" class="bg-brand text-black font-black px-8 md:px-12 py-4 md:py-5 uppercase text-[10px] md:text-xs tracking-widest hover:bg-white transition-all rounded-full shadow-2xl shadow-brand/20 text-center">Learn_More</a>
                    <a href="/register.php" class="bg-white/5 border border-white/10 text-white font-black px-8 md:px-12 py-4 md:py-5 uppercase text-[10px] md:text-xs tracking-widest hover:bg-white/10 transition-all rounded-full text-center">Join_Waiting_List</a>
                </div>
            </div>

            <!-- Content 1: Shredder -->
            <div class="story-content transition-all duration-700 hidden" data-story-content="1">
                <div class="inline-flex items-center gap-3 px-3 py-1 bg-brand text-black font-black text-[9px] md:text-[10px] uppercase tracking-widest mb-6 md:mb-8 rounded-sm">
                    <i class="bi bi-layers-half"></i> Stem_Isolation_v1.0
                </div>
                <h1 class="text-5xl md:text-7xl lg:text-9xl font-black mb-6 md:mb-8 tracking-tighter leading-[0.9] text-white uppercase">The<span class="text-brand">_Shredder</span></h1>
                <p class="text-base md:text-xl text-zinc-400 font-medium mb-8 md:mb-12 max-w-2xl leading-relaxed">Real-time stem isolation and AI-driven remix architecture. Deconstruct sound.</p>
                <div class="flex flex-col sm:flex-row gap-4 md:gap-6">
                    <a href="/register.php" class="bg-brand text-black font-black px-8 md:px-12 py-4 md:py-5 uppercase text-[10px] md:text-xs tracking-widest hover:bg-white transition-all rounded-full shadow-2xl shadow-brand/20 text-center">Bunker_Access</a>
                </div>
            </div>

            <!-- Content 2: Shellshock -->
            <div class="story-content transition-all duration-700 hidden" data-story-content="2">
                <div class="inline-flex items-center gap-3 px-3 py-1 bg-brand text-black font-black text-[9px] md:text-[10px] uppercase tracking-widest mb-6 md:mb-8 rounded-sm">
                    <i class="bi bi-shield-lock-fill"></i> Sovereign_CMS
                </div>
                <h1 class="text-5xl md:text-7xl lg:text-9xl font-black mb-6 md:mb-8 tracking-tighter leading-[0.9] text-white uppercase">Shell<span class="text-brand">_Shock</span></h1>
                <p class="text-base md:text-xl text-zinc-400 font-medium mb-8 md:mb-12 max-w-2xl leading-relaxed">The administrative core of the Empire. Deploy stores in seconds.</p>
                <div class="flex flex-col sm:flex-row gap-4 md:gap-6">
                    <a href="https://shellshock.nextgennoise.com" target="_blank" class="bg-brand text-black font-black px-8 md:px-12 py-4 md:py-5 uppercase text-[10px] md:text-xs tracking-widest hover:bg-white transition-all rounded-full shadow-2xl shadow-brand/20 text-center">Explore_Console</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Slide Indicators: Repositioned for Mobile -->
    <div class="absolute bottom-6 md:bottom-12 left-6 right-6 md:left-auto md:right-12 z-30 flex md:flex-col justify-center md:justify-end gap-4">
        <button onclick="window.storyEngine.to(0)" class="group flex items-center gap-4 text-right" data-story-nav="0">
            <span class="hidden md:block text-[10px] font-black uppercase tracking-widest text-zinc-500 group-hover:text-white transition-colors">Clarity</span>
            <div class="w-8 md:w-12 h-1 bg-white/10 group-hover:bg-brand transition-all"></div>
        </button>
        <button onclick="window.storyEngine.to(1)" class="group flex items-center gap-4 text-right" data-story-nav="1">
            <span class="hidden md:block text-[10px] font-black uppercase tracking-widest text-zinc-500 group-hover:text-white transition-colors">Shredder</span>
            <div class="w-8 md:w-12 h-1 bg-white/10 group-hover:bg-brand transition-all"></div>
        </button>
        <button onclick="window.storyEngine.to(2)" class="group flex items-center gap-4 text-right" data-story-nav="2">
            <span class="hidden md:block text-[10px] font-black uppercase tracking-widest text-zinc-500 group-hover:text-white transition-colors">Shellshock</span>
            <div class="w-8 md:w-12 h-1 bg-white/10 group-hover:bg-brand transition-all"></div>
        </button>
    </div>
</div>

<script>
    if (!window.storyEngine) {
        window.storyEngine = {
            current: 0,
            total: 3,
            timer: null,
            to: function(index) {
                this.current = index;
                this.resetTimer();
                document.querySelectorAll("[data-story-slide]").forEach((s, i) => {
                    s.style.opacity = (i === index) ? '1' : '0';
                });
                document.querySelectorAll(".story-content").forEach((c, i) => {
                    c.classList.toggle('hidden', i !== index);
                    c.classList.toggle('block', i === index);
                });
                document.querySelectorAll("[data-story-nav] div").forEach((n, i) => {
                    n.classList.toggle('bg-white/10', i !== index);
                    n.classList.toggle('bg-brand', i === index);
                    // Match the widths from CSS
                    const isMobile = window.innerWidth < 768;
                    const activeW = isMobile ? '32px' : '48px';
                    const inactiveW = isMobile ? '16px' : '24px';
                    n.style.width = (i === index) ? activeW : inactiveW;
                });
            },
            next: function() {
                this.to((this.current + 1) % this.total);
            },
            resetTimer: function() {
                clearInterval(this.timer);
                this.timer = setInterval(() => this.next(), 8000);
            }
        };
        window.storyEngine.resetTimer();
        window.storyEngine.to(0);
    }
</script>
