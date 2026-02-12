<?php
$heroCode = "
        <!-- THE NGN STORY ENGINE -->
        <div class='relative rounded-[2rem] overflow-hidden mb-16 group border border-white/5 shadow-2xl bg-black'>
            <div class='absolute inset-0'>
                <div class='absolute inset-0 bg-gradient-to-r from-black via-black/40 to-transparent z-10'></div>
                <div class='absolute inset-0 bg-gradient-to-t from-black via-transparent to-transparent z-10'></div>
                
                <!-- Slide 1: THE FUSION -->
                <div class='absolute inset-0 transition-all duration-1000 opacity-100' data-story-slide='0'>
                    <div class='w-full h-full bg-[#0b0b0b] flex items-center justify-center'>
                        <div class='absolute w-full h-full opacity-20' style='background-image: radial-gradient(circle, #FF5F1F 1px, transparent 1px); background-size: 40px 40px;'></div>
                    </div>
                </div>
                <!-- Slide 2: THE SOVEREIGNTY -->
                <div class='absolute inset-0 transition-all duration-1000 opacity-0' data-story-slide='1'>
                    <div class='w-full h-full bg-[#111] flex items-center justify-center'>
                        <div class='absolute w-full h-full bg-gradient-to-br from-[#FF5F1F]/5 to-transparent'></div>
                    </div>
                </div>
                <!-- Slide 3: THE REVENUE -->
                <div class='absolute inset-0 transition-all duration-1000 opacity-0' data-story-slide='2'>
                    <div class='w-full h-full bg-[#070707] flex items-center justify-center'>
                        <div class='absolute w-full h-full bg-gradient-to-tl from-[#FF5F1F]/5 to-transparent'></div>
                    </div>
                </div>
            </div>

            <div class='relative z-20 p-12 lg:p-24 min-h-[600px] flex items-center'>
                <div class='max-w-3xl'>
                    <div class='story-content transition-all duration-700 block' data-story-content='0'>
                        <div class='inline-block px-3 py-1 bg-[#FF5F1F] text-black font-black text-[10px] uppercase tracking-widest mb-6 rounded-sm'>The_Fusion</div>
                        <h1 class='text-6xl lg:text-8xl font-black mb-8 tracking-tighter leading-none'>MUSIC MEETS <br><span class='text-[#FF5F1F] italic'>TERMINAL</span> VELOCITY</h1>
                        <p class='text-xl text-gray-400 font-mono mb-12 max-w-xl'>NextGenNoise 2.0.3 is the definitive fusion of underground sound and pressurized tech.</p>
                        <div class='flex gap-6'>
                            <a href='/register.php' class='bg-[#FF5F1F] text-black font-black px-12 py-5 uppercase text-xs tracking-widest hover:bg-white transition-all rounded-sm'>Join_The_Fleet</a>
                        </div>
                    </div>
                    <div class='story-content transition-all duration-700 hidden' data-story-content='1'>
                        <div class='inline-block px-3 py-1 bg-[#FF5F1F] text-black font-black text-[10px] uppercase tracking-widest mb-6 rounded-sm'>Sovereignty</div>
                        <h1 class='text-6xl lg:text-8xl font-black mb-8 tracking-tighter leading-none'>OWN YOUR <br><span class='text-[#FF5F1F] italic'>INFRASTRUCTURE</span></h1>
                        <p class='text-xl text-gray-400 font-mono mb-12 max-w-xl'>No more rented platforms. NGN 2.0.3 provides absolute sovereignty for artists and labels.</p>
                        <div class='flex gap-6'>
                            <a href='/register.php' class='bg-[#FF5F1F] text-black font-black px-12 py-5 uppercase text-xs tracking-widest hover:bg-white transition-all rounded-sm'>Get_A_Bunker</a>
                        </div>
                    </div>
                    <div class='story-content transition-all duration-700 hidden' data-story-content='2'>
                        <div class='inline-block px-3 py-1 bg-[#FF5F1F] text-black font-black text-[10px] uppercase tracking-widest mb-6 rounded-sm'>Revenue</div>
                        <h1 class='text-6xl lg:text-8xl font-black mb-8 tracking-tighter leading-none'>ARTIST-FIRST <br><span class='text-[#FF5F1F] italic'>REVENUE</span> ENGINES</h1>
                        <p class='text-xl text-gray-400 font-mono mb-12 max-w-xl'>Autonomous marketing loops and institutional invoicing. We build commerce.</p>
                        <div class='flex gap-6'>
                            <a href='/register.php' class='bg-[#FF5F1F] text-black font-black px-12 py-5 uppercase text-xs tracking-widest hover:bg-white transition-all rounded-sm'>Start_Earning</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class='absolute bottom-12 right-12 z-30 flex gap-3'>
                <button onclick='window.storyEngine.to(0)' class='w-12 h-1 bg-white/20 transition-all hover:bg-[#FF5F1F]' data-story-nav='0'></button>
                <button onclick='window.storyEngine.to(1)' class='w-12 h-1 bg-white/20 transition-all hover:bg-[#FF5F1F]' data-story-nav='1'></button>
                <button onclick='window.storyEngine.to(2)' class='w-12 h-1 bg-white/20 transition-all hover:bg-[#FF5F1F]' data-story-nav='2'></button>
            </div>
        </div>

        <script>
            window.storyEngine = {
                current: 0,
                total: 3,
                to: function(index) {
                    this.current = index;
                    document.querySelectorAll(\"[data-story-slide]\").forEach((s, i) => {
                        s.classList.toggle('opacity-100', i === index);
                        s.classList.toggle('opacity-0', i !== index);
                    });
                    document.querySelectorAll(\".story-content\").forEach((c, i) => {
                        c.classList.toggle('block', i === index);
                        c.classList.toggle('hidden', i !== index);
                    });
                    document.querySelectorAll(\"[data-story-nav]\").forEach((n, i) => {
                        n.classList.toggle('bg-[#FF5F1F]', i === index);
                        n.classList.toggle('bg-white/20', i !== index);
                    });
                },
                next: function() { this.to((this.current + 1) % this.total); }
            };
            setInterval(() => window.storyEngine.next(), 8000);
        </script>
";
file_put_contents('../ngn_202/public/hero_new.php', $heroCode);
PATCH;
