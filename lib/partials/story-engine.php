<?php
/**
 * Sovereign Story Engine v3.2.1
 * High-Velocity Immersive Visuals // Corrected Shellshock Positioning
 */
?>
<div class="relative rounded-3xl md:rounded-[3rem] overflow-hidden mb-12 md:mb-16 group border border-white/5 shadow-[0_50px_100px_-20px_rgba(0,0,0,0.5)] bg-[#050505] min-h-[550px] md:min-h-[650px] flex items-center">
    <!-- Background Layer Protocol -->
    <div class="absolute inset-0">
        <div class="absolute inset-0 bg-gradient-to-r from-black via-black/60 to-transparent z-10"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-black via-transparent to-transparent z-10"></div>
        
        <!-- Slide 1: CLARITY VST (High Fidelity Grid) -->
        <div class="absolute inset-0 transition-all duration-1000 opacity-100" data-story-slide="0">
            <div class="w-full h-full flex items-center justify-center relative">
                <!-- SVG Tech Grid -->
                <svg class="absolute inset-0 w-full h-full opacity-20" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                            <path d="M 40 0 L 0 0 0 40" fill="none" stroke="var(--primary)" stroke-width="0.5"/>
                        </pattern>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#grid)" />
                </svg>
                <div class="w-[80%] h-[80%] bg-brand/10 blur-[120px] rounded-full animate-pulse"></div>
                <!-- Central Icon -->
                <div class="relative z-0 opacity-10 scale-150">
                    <i class="bi bi-cpu text-[20vw] text-brand"></i>
                </div>
            </div>
        </div>

        <!-- Slide 2: SHREDDER ENGINE (Aggressive Distortion) -->
        <div class="absolute inset-0 transition-all duration-1000 opacity-0" data-story-slide="1">
            <div class="w-full h-full bg-[#0a0a0a] flex items-center justify-center relative">
                <div class="absolute w-full h-full bg-[linear-gradient(45deg,rgba(255,255,255,0.02)_25%,transparent_25%,transparent_50%,rgba(255,255,255,0.02)_50%,rgba(255,255,255,0.02)_75%,transparent_75%,transparent)] bg-[length:60px_60px]"></div>
                <div class="w-full h-full bg-gradient-to-br from-brand/20 via-transparent to-transparent"></div>
                <!-- SVG Waves -->
                <svg class="absolute bottom-0 left-0 w-full h-64 opacity-30" viewBox="0 0 1440 320">
                    <path fill="var(--primary)" fill-opacity="1" d="M0,160L48,176C96,192,192,208,288,186.7C384,165,480,107,576,106.7C672,107,768,165,864,181.3C960,197,1056,171,1152,144C1248,117,1344,85,1392,69.3L1440,53.3L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
                </svg>
            </div>
        </div>

        <!-- Slide 3: SHELLSHOCK (Drum Precision) -->
        <div class="absolute inset-0 transition-all duration-1000 opacity-0" data-story-slide="2">
            <div class="w-full h-full bg-[#050505] flex items-center justify-center">
                <div class="absolute inset-0 opacity-10" style="background-image: conic-gradient(from 180deg at 50% 50%, var(--primary) 0deg, transparent 120deg);"></div>
                <div class="w-full h-full bg-gradient-to-tr from-brand/10 via-transparent to-transparent"></div>
                <!-- SVG Circular Tech -->
                <svg class="absolute inset-0 w-full h-full opacity-10" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <pattern id="hexagons" width="50" height="43.4" patternUnits="userSpaceOnUse" patternTransform="scale(2)">
                            <path d="M25 0 L50 14.4 L50 43.4 L25 57.8 L0 43.4 L0 14.4 Z" fill="none" stroke="var(--primary)" stroke-width="1"/>
                        </pattern>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#hexagons)" />
                </svg>
                <div class="relative z-0 opacity-10 scale-150">
                    <i class="bi bi-reception-4 text-[20vw] text-brand"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Content Engine -->
    <div class="relative z-20 p-8 md:p-16 lg:p-24 w-full">
        <div class="max-w-4xl">
            <!-- Content 0: Clarity -->
            <div class="story-content transition-all duration-700 block" data-story-content="0">
                <div class="inline-flex items-center gap-3 px-4 py-1.5 bg-brand text-black font-black text-[10px] md:text-[11px] uppercase tracking-[0.2em] mb-8 rounded-full shadow-xl shadow-brand/20">
                    <i class="bi bi-cpu-fill"></i> Hardware_Agnostic
                </div>
                <h1 class="text-6xl md:text-8xl lg:text-9xl font-black mb-8 tracking-tighter leading-[0.85] uppercase break-words drop-shadow-2xl brand-gradient-text">Clarity<span class="text-brand">_VST</span></h1>
                <p class="text-lg md:text-2xl text-zinc-400 font-medium mb-12 max-w-2xl leading-relaxed">The source of truth for high-fidelity audio processing. Coming soon to the Alliance.</p>
                <div class="flex flex-col sm:flex-row gap-6">
                    <a href="https://clarity.nextgennoise.com" target="_blank" class="bg-brand text-black font-black px-12 py-5 uppercase text-xs tracking-widest hover:bg-white hover:scale-105 transition-all rounded-full shadow-2xl shadow-brand/30 text-center">Explore_Engine</a>
                    <a href="/register.php" class="bg-white/5 border border-white/10 text-white font-black px-12 py-5 uppercase text-xs tracking-widest hover:bg-white/10 hover:border-white/20 transition-all rounded-full text-center">Join_Waiting_List</a>
                </div>
            </div>

            <!-- Content 1: Shredder -->
            <div class="story-content transition-all duration-700 hidden" data-story-content="1">
                <div class="inline-flex items-center gap-3 px-4 py-1.5 bg-brand text-black font-black text-[10px] md:text-[11px] uppercase tracking-[0.2em] mb-8 rounded-full shadow-xl shadow-brand/20">
                    <i class="bi bi-layers-half"></i> Stem_Isolation_v1.0
                </div>
                <h1 class="text-6xl md:text-8xl lg:text-9xl font-black mb-8 tracking-tighter leading-[0.85] uppercase break-words drop-shadow-2xl brand-gradient-text">The<span class="text-brand">_Shredder</span></h1>
                <p class="text-lg md:text-2xl text-zinc-400 font-medium mb-12 max-w-2xl leading-relaxed">Real-time stem isolation and AI-driven remix architecture. Deconstruct the sound.</p>
                <div class="flex flex-col sm:flex-row gap-6">
                    <a href="/register.php" class="bg-brand text-black font-black px-12 py-5 uppercase text-xs tracking-widest hover:bg-white hover:scale-105 transition-all rounded-full shadow-2xl shadow-brand/30 text-center">Bunker_Access</a>
                </div>
            </div>

            <!-- Content 2: Shellshock -->
            <div class="story-content transition-all duration-700 hidden" data-story-content="2">
                <div class="inline-flex items-center gap-3 px-4 py-1.5 bg-brand text-black font-black text-[10px] md:text-[11px] uppercase tracking-[0.2em] mb-8 rounded-full shadow-xl shadow-brand/20">
                    <i class="bi bi-tools"></i> Precision_Engineering
                </div>
                <h1 class="text-6xl md:text-8xl lg:text-9xl font-black mb-8 tracking-tighter leading-[0.85] uppercase break-words drop-shadow-2xl brand-gradient-text">Shell<span class="text-brand">_Shock</span></h1>
                <p class="text-lg md:text-2xl text-zinc-400 font-medium mb-12 max-w-2xl leading-relaxed">The world's first autonomous drum tuning system. Perfect pitch, verified by the Rig.</p>
                <div class="flex flex-col sm:flex-row gap-6">
                    <a href="https://shellshock.nextgennoise.com" target="_blank" class="bg-brand text-black font-black px-12 py-5 uppercase text-xs tracking-widest hover:bg-white hover:scale-105 transition-all rounded-full shadow-2xl shadow-brand/30 text-center">Explore_Shellshock</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Dots: Modern Vertical Style -->
    <div class="absolute bottom-12 right-12 z-30 flex md:flex-col gap-6">
        <button onclick="window.storyEngine.to(0)" class="group flex flex-col md:flex-row items-center gap-4 text-right" data-story-nav="0">
            <span class="hidden md:block text-[10px] font-black uppercase tracking-[0.3em] text-zinc-500 group-hover:text-white transition-colors">Clarity</span>
            <div class="w-12 md:w-1 h-1 md:h-12 bg-white/10 group-hover:bg-brand transition-all duration-500 rounded-full shadow-brand/50"></div>
        </button>
        <button onclick="window.storyEngine.to(1)" class="group flex flex-col md:flex-row items-center gap-4 text-right" data-story-nav="1">
            <span class="hidden md:block text-[10px] font-black uppercase tracking-[0.3em] text-zinc-500 group-hover:text-white transition-colors">Shredder</span>
            <div class="w-12 md:w-1 h-1 md:h-12 bg-white/10 group-hover:bg-brand transition-all duration-500 rounded-full shadow-brand/50"></div>
        </button>
        <button onclick="window.storyEngine.to(2)" class="group flex flex-col md:flex-row items-center gap-4 text-right" data-story-nav="2">
            <span class="hidden md:block text-[10px] font-black uppercase tracking-[0.3em] text-zinc-500 group-hover:text-white transition-colors">Shellshock</span>
            <div class="w-12 md:w-1 h-1 md:h-12 bg-white/10 group-hover:bg-brand transition-all duration-500 rounded-full shadow-brand/50"></div>
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
                    s.style.transform = (i === index) ? 'scale(1)' : 'scale(1.05)';
                });
                document.querySelectorAll(".story-content").forEach((c, i) => {
                    c.classList.toggle('hidden', i !== index);
                    c.classList.toggle('block', i === index);
                    if (i === index) {
                        c.style.animation = 'fadeSlideUp 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
                    }
                });
                document.querySelectorAll("[data-story-nav] div").forEach((n, i) => {
                    n.classList.toggle('bg-white/10', i !== index);
                    n.classList.toggle('bg-brand', i === index);
                    n.classList.toggle('shadow-2xl', i === index);
                    if (window.innerWidth >= 768) {
                        n.style.height = (i === index) ? '64px' : '32px';
                        n.style.width = '2px';
                    } else {
                        n.style.width = (i === index) ? '48px' : '24px';
                        n.style.height = '4px';
                    }
                });
            },
            next: function() {
                this.to((this.current + 1) % this.total);
            },
            resetTimer: function() {
                clearInterval(this.timer);
                this.timer = setInterval(() => this.next(), 10000);
            }
        };
        window.storyEngine.resetTimer();
        window.storyEngine.to(0);
    }
</script>

<style>
@keyframes fadeSlideUp {
    from { opacity: 0; transform: translateY(30px); filter: blur(10px); }
    to { opacity: 1; transform: translateY(0); filter: blur(0); }
}
</style>
