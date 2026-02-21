/**
 * NGN Sovereign App Installer
 * Handles the "Spotify-Killer" Mobile Mobilization logic.
 * Bible Ref: Chapter 15 (PWA Capabilities)
 */

window.NGN_PWA = {
    deferredPrompt: null,

    init() {
        window.addEventListener('beforeinstallprompt', (e) => {
            // Prevent Chrome 67 and earlier from automatically showing the prompt
            e.preventDefault();
            // Stash the event so it can be triggered later.
            this.deferredPrompt = e;
            
            // Show our custom mobilizer banner
            const banner = document.getElementById('pwa-mobilizer');
            if (banner) {
                banner.classList.remove('hidden');
                banner.classList.add('flex');
            }
        });

        window.addEventListener('appinstalled', (evt) => {
            window.NGN_Toast.success('Sovereign App Deployed Successfully.');
            // Hide banner if visible
            const banner = document.getElementById('pwa-mobilizer');
            if (banner) banner.classList.add('hidden');
        });
    },

    async install() {
        if (!this.deferredPrompt) {
            window.NGN_Toast.info('Open your browser menu and select "Add to Home Screen"');
            return;
        }
        
        // Show the browser's native install prompt
        this.deferredPrompt.prompt();
        
        // Wait for the user to respond to the prompt
        const { outcome } = await this.deferredPrompt.userChoice;
        console.log(`[PWA] User response to install: ${outcome}`);
        
        // We've used the prompt, and can't use it again
        this.deferredPrompt = null;
        
        // Hide our banner
        const banner = document.getElementById('pwa-mobilizer');
        if (banner) banner.classList.add('hidden');
    }
};

document.addEventListener('DOMContentLoaded', () => window.NGN_PWA.init());
