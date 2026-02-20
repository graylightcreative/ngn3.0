<?php
/**
 * NGN Institutional Footer
 * Foundry Standard: Deep Charcoal / Electric Orange
 * Bible Ref: Chapter 4 (Visual DNA)
 */

$isMaster = $_SESSION['is_master'] ?? false;
$baseUrl = $config->baseUrl();
?>

<!-- NGN PLAYER INTERFACE -->
<div id="ngn-player-container"></div>

<!-- INSTITUTIONAL FOOTER -->
<footer class="bg-charcoal border-t border-white/5 pt-24 pb-12 px-6 lg:px-12 selection:bg-brand selection:text-white relative overflow-hidden">
    <!-- Tactical Grid Overlay -->
    <div class="absolute inset-0 opacity-[0.02] pointer-events-none" style="background-image: linear-gradient(#FF5F1F 1px, transparent 1px), linear-gradient(90deg, #FF5F1F 1px, transparent 1px); background-size: 40px 40px;"></div>

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
                    <li><a href="/smr-charts" class="text-zinc-500 hover:text-brand transition-all flex items-center group"><span class="w-0 group-hover:w-2 h-px bg-brand transition-all mr-0 group-hover:mr-2"></span>SMR_Radio</a></li>
                </ul>
            </div>

            <!-- Column 3: Institutional -->
            <div>
                <h4 class="text-[10px] font-mono text-white uppercase tracking-[0.4em] mb-8 font-bold flex items-center">
                    <span class="w-2 h-px bg-brand mr-3"></span> Institutional
                </h4>
                <ul class="space-y-4 text-[11px] font-bold uppercase tracking-widest">
                    <li><a href="/?view=investors" class="text-zinc-500 hover:text-brand transition-all">Investor_Terminal</a></li>
                    <li><a href="https://boardroom.nextgennoise.com" class="text-zinc-500 hover:text-brand transition-all">The_Boardroom</a></li>
                    <li><a href="/beta" class="text-zinc-500 hover:text-brand transition-all">System_Manifest</a></li>
                    <li><a href="/docs/bible/00%20-%20Bible%20Index.md" class="text-zinc-500 hover:text-brand transition-all">Technical_Bible</a></li>
                    <li><a href="/agreement/artist-onboarding" class="text-zinc-500 hover:text-brand transition-all">Rights_Ledger</a></li>
                    <li><a href="/pricing" class="text-brand hover:text-white transition-all">Secure_Entry</a></li>
                </ul>
            </div>

            <!-- Column 4: Sovereign Nodes -->
            <div>
                <h4 class="text-[10px] font-mono text-white uppercase tracking-[0.4em] mb-8 font-bold flex items-center">
                    <span class="w-2 h-px bg-brand mr-3"></span> The Fleet
                </h4>
                <ul class="space-y-4 text-[10px] font-mono uppercase tracking-[0.2em]">
                    <li><a href="https://beacon.graylightcreative.com" class="text-zinc-600 hover:text-brand transition-all">Beacon_ID</a></li>
                    <li><a href="https://ledger.graylightcreative.com" class="text-zinc-600 hover:text-brand transition-all">Ledger_Moat</a></li>
                    <li><a href="https://vault.graylightcreative.com" class="text-zinc-600 hover:text-brand transition-all">Vault_Secure</a></li>
                    <li><a href="https://shredder.nextgennoise.com" class="text-zinc-600 hover:text-brand transition-all">Shredder_Node</a></li>
                    <li><a href="https://uplink.graylightcreative.com" class="text-zinc-600 hover:text-brand transition-all">Uplink_SMM</a></li>
                    <li><a href="https://nexus.graylightcreative.com" class="text-zinc-600 hover:text-brand transition-all">Nexus_Infra</a></li>
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
                <a href="/notes" class="hover:text-white transition-colors">Dev_Notes</a>
            </div>
        </div>
    </div>
</footer>

<!-- PWA & Navigation Modals -->
<?php include $root . 'lib/partials/dispute-modal.php'; ?>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="/lib/js/site.js?v=<?= time() ?>"></script>
<script src="/js/toast.js?v=<?= time() ?>"></script>

<!-- NGN Player Initialization -->
<script type="module" src="/public/js/player/player-init.js"></script>

<script>
    // System Handshake
    window.NGN = window.NGN || {};
    window.NGN.version = '2.1.0';
    window.NGN.environment = 'production';
</script>

</body>
</html>
