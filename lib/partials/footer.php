<?php
/**
 * NGN Institutional Footer v3.0.0
 * Foundry Standard: Deep Charcoal / Electric Orange
 * Bible Ref: Chapter 4 (Visual DNA)
 */

// Auth state for email popup
$isLoggedIn = !empty($_SESSION['LoggedIn']) && $_SESSION['LoggedIn'] === 1;
?>

<!-- Email Capture Popup -->
<?php if (!$isLoggedIn): ?>
<div class="popup fixed inset-0 flex items-center justify-center bg-black/80 backdrop-blur-sm z-[300]" id="contactJoinPopup" style="display:none;">
  <div class="bg-[#0a0a0a] rounded-[2rem] p-10 max-w-md w-full mx-4 relative border border-white/10 shadow-2xl">
    <button class="close-popup absolute top-6 right-6 text-zinc-500 hover:text-white text-2xl leading-none" aria-label="Close">
      <i class="bi bi-x-lg"></i>
    </button>

    <div class="mb-8">
      <div class="w-16 h-16 bg-brand rounded-2xl flex items-center justify-center text-black mb-6">
        <i class="bi bi-lightning-fill text-3xl"></i>
      </div>
      <h2 class="text-3xl font-black text-white mb-4 tracking-tighter uppercase italic">Join_The_Movement</h2>
      <p class="text-zinc-400 text-sm leading-relaxed font-medium">
        Deploy your credentials to the Sovereign Alliance. Get exclusive intelligence, verified charts, and private community access.
      </p>
    </div>

    <form action='' method='post' novalidate class='newsletterSignup space-y-4'>
      <div class='grid grid-cols-1 gap-4'>
          <input type='text' class='newsletterFirstName w-full bg-white/5 border border-white/10 rounded-xl px-5 py-4 text-white placeholder-zinc-600 focus:border-brand focus:outline-none transition-all font-bold' placeholder='First_Name' required>
          <input type='email' class='newsletterEmail w-full bg-white/5 border border-white/10 rounded-xl px-5 py-4 text-white placeholder-zinc-600 focus:border-brand focus:outline-none transition-all font-bold' placeholder='Email_Address' required>
      </div>

      <button type='submit' class='w-full bg-brand hover:bg-white text-black font-black py-5 rounded-full transition-all mt-6 uppercase tracking-widest text-xs shadow-xl shadow-brand/20'>Secure_Access</button>
      <button type='button' class='w-full text-zinc-500 hover:text-white font-black py-2 rounded-lg transition-all mt-2 dismiss-popup uppercase tracking-widest text-[10px]'>Decline_Entry</button>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- NGN PLAYER INTERFACE -->
<div id="ngn-player-container"></div>

<!-- INSTITUTIONAL FOOTER -->
<footer class="bg-[#050505] border-t border-white/5 pt-24 pb-12 px-6 lg:px-12 relative overflow-hidden mt-24">
    <!-- Tactical Grid Overlay -->
    <div class="absolute inset-0 opacity-[0.02] pointer-events-none" style="background-image: linear-gradient(var(--primary) 1px, transparent 1px), linear-gradient(90deg, var(--primary) 1px, transparent 1px); background-size: 40px 40px;"></div>

    <div class="max-w-7xl mx-auto relative z-10">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-16 mb-24">
            
            <!-- Column 1: The Core -->
            <div class="lg:col-span-2 space-y-8">
                <a href="/" class="block group">
                    <img src="/lib/images/site/2026/NGN-Logo-Full-Light.png" alt="NGN" class="h-10 opacity-90 group-hover:opacity-100 transition-opacity">
                </a>
                <div class="border-l-2 border-brand/40 pl-8 py-2">
                    <h4 class="text-[10px] font-mono text-brand uppercase tracking-[0.6em] mb-4 font-black">Foundry_Mandate</h4>
                    <p class="text-sm font-medium leading-relaxed text-zinc-500 max-w-sm">
                        Building the infrastructure of sovereignty. We deploy autonomous engines that kill the dependency on the manual service model. Sound ownership, verified by the Rig.
                    </p>
                </div>
                <div class="flex gap-6 text-zinc-600">
                    <a href="#" class="hover:text-brand transition-colors"><i class="bi-twitter-x"></i></a>
                    <a href="#" class="hover:text-brand transition-colors"><i class="bi-instagram"></i></a>
                    <a href="#" class="hover:text-brand transition-colors"><i class="bi-github"></i></a>
                    <a href="#" class="hover:text-brand transition-colors"><i class="bi-discord"></i></a>
                </div>
            </div>

            <!-- Column 2: Platform -->
            <div>
                <h4 class="text-[10px] font-mono text-white uppercase tracking-[0.4em] mb-8 font-bold flex items-center">
                    <span class="w-2 h-px bg-brand mr-3"></span> Platform
                </h4>
                <ul class="space-y-4 text-[11px] font-bold uppercase tracking-widest">
                    <li><a href="/artists" class="text-zinc-500 hover:text-brand transition-all flex items-center group"><span class="w-0 group-hover:w-2 h-px bg-brand transition-all mr-0 group-hover:mr-2"></span>Artists</a></li>
                    <li><a href="/labels" class="text-zinc-500 hover:text-brand transition-all flex items-center group"><span class="w-0 group-hover:w-2 h-px bg-brand transition-all mr-0 group-hover:mr-2"></span>Labels</a></li>
                    <li><a href="/stations" class="text-zinc-500 hover:text-brand transition-all flex items-center group"><span class="w-0 group-hover:w-2 h-px bg-brand transition-all mr-0 group-hover:mr-2"></span>Stations</a></li>
                    <li><a href="/venues" class="text-zinc-500 hover:text-brand transition-all flex items-center group"><span class="w-0 group-hover:w-2 h-px bg-brand transition-all mr-0 group-hover:mr-2"></span>Venues</a></li>
                    <li><a href="/charts" class="text-zinc-500 hover:text-brand transition-all flex items-center group"><span class="w-0 group-hover:w-2 h-px bg-brand transition-all mr-0 group-hover:mr-2"></span>Charts</a></li>
                </ul>
            </div>

            <!-- Column 3: Institutional -->
            <div>
                <h4 class="text-[10px] font-mono text-white uppercase tracking-[0.4em] mb-8 font-bold flex items-center">
                    <span class="w-2 h-px bg-brand mr-3"></span> Institutional
                </h4>
                <ul class="space-y-4 text-[11px] font-bold uppercase tracking-widest">
                    <li><a href="https://investors.nextgennoise.com" class="text-zinc-500 hover:text-brand transition-all">Investor_Terminal</a></li>
                    <li><a href="https://boardroom.nextgennoise.com" class="text-zinc-500 hover:text-brand transition-all">The_Boardroom</a></li>
                    <li><a href="/forge" class="text-zinc-500 hover:text-brand transition-all">System_Manifest</a></li>
                    <li><a href="/pricing" class="text-brand hover:text-white transition-all">Secure_Entry</a></li>
                </ul>
            </div>

            <!-- Column 4: Sovereign Nodes -->
            <div>
                <h4 class="text-[10px] font-mono text-white uppercase tracking-[0.4em] mb-8 font-bold flex items-center">
                    <span class="w-2 h-px bg-brand mr-3"></span> Sovereign Nodes
                </h4>
                <ul class="space-y-4 text-[10px] font-mono uppercase tracking-[0.2em]">
                    <li><a href="https://beacon.graylightcreative.com" class="text-zinc-600 hover:text-brand transition-all">Beacon_ID</a></li>
                    <li><a href="https://ledger.graylightcreative.com" class="text-zinc-600 hover:text-brand transition-all">Ledger_Moat</a></li>
                    <li><a href="https://vault.graylightcreative.com" class="text-zinc-600 hover:text-brand transition-all">Vault_Secure</a></li>
                    <li><a href="https://shredder.nextgennoise.com" class="text-zinc-600 hover:text-brand transition-all">Shredder_Node</a></li>
                </ul>
            </div>
        </div>

        <!-- System Footnote -->
        <div class="pt-12 border-t border-white/5 flex flex-col md:flex-row justify-between items-center gap-8">
            <div class="space-y-2 text-center md:text-left">
                <p class="text-[9px] font-mono text-zinc-700 uppercase tracking-[0.5em]">&copy; 2026 Graylight Creative // NGN Sovereign Platform</p>
                <p class="text-[9px] font-mono text-brand/40 uppercase tracking-[0.3em]">SHA-256 INTEGRITY VERIFIED // PRESSURIZED FOR EXIT</p>
            </div>
            <div class="flex flex-wrap justify-center gap-8 text-[9px] font-mono text-zinc-700 uppercase tracking-[0.5em]">
                <a href="/privacy-policy" class="hover:text-white transition-colors">Privacy_Protocol</a>
                <a href="/terms-of-service" class="hover:text-white transition-colors">Terms_of_Service</a>
                <a href="/agreement/dmca-policy" class="hover:text-white transition-colors">DMCA_Safe_Harbor</a>
            </div>
        </div>
    </div>
</footer>

<!-- PWA & Navigation Modals -->
<?php include $root . 'lib/partials/dispute-modal.php'; ?>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="/lib/js/newsletter-signup.js?v=<?= time() ?>"></script>
<script src="/lib/js/site.js?v=<?= time() ?>"></script>

<!-- NGN Player Initialization -->
<script type="module" src="/js/player/player-init.js?v=<?= time() ?>"></script>

<script>
    window.NGN = window.NGN || {};
    window.NGN.version = '3.0.0';
    window.NGN.environment = 'production';
</script>
