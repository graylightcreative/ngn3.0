/**
 * NGN 2.0 PWA Setup
 * Handles service worker registration and install prompt
 */

// ============================================================================
// SERVICE WORKER REGISTRATION
// ============================================================================

function registerServiceWorker() {
  if (!('serviceWorker' in navigator)) {
    console.log('[PWA] Service Workers not supported in this browser');
    return;
  }

  window.addEventListener('load', () => {
    navigator.serviceWorker
      .register('/service-worker.js')
      .then((registration) => {
        console.log('[PWA] Service Worker registered successfully:', registration);

        // Check for updates every 24 hours
        setInterval(() => {
          console.log('[PWA] Checking for Service Worker updates...');
          registration.update();
        }, 86400000); // 24 hours

        // Listen for updates
        registration.addEventListener('updatefound', () => {
          const newWorker = registration.installing;

          newWorker.addEventListener('statechange', () => {
            if (
              newWorker.state === 'installed' &&
              navigator.serviceWorker.controller
            ) {
              // New SW available, show update prompt
              showUpdatePrompt(newWorker);
            }
          });
        });
      })
      .catch((err) => {
        console.error('[PWA] Service Worker registration failed:', err);
      });
  });
}

/**
 * Show update prompt when new service worker is available
 */
function showUpdatePrompt(newWorker) {
  console.log('[PWA] New Service Worker version available');

  // Check if update notification already shown
  if (sessionStorage.getItem('ngn_update_shown')) {
    return;
  }

  // Create update notification
  const notification = document.createElement('div');
  notification.id = 'ngn-update-notification';
  notification.innerHTML = `
    <div style="
      position: fixed;
      bottom: 20px;
      right: 20px;
      background: #1DB954;
      color: #000;
      padding: 16px 20px;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.3);
      font-weight: 600;
      z-index: 999999;
      max-width: 300px;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    ">
      <div style="margin-bottom: 12px;">NGN App updated!</div>
      <div style="display: flex; gap: 12px;">
        <button id="ngn-update-btn" style="
          background: #000;
          color: #1DB954;
          border: none;
          padding: 8px 16px;
          border-radius: 4px;
          cursor: pointer;
          font-weight: 600;
          font-size: 14px;
        ">Reload</button>
        <button id="ngn-dismiss-btn" style="
          background: rgba(0,0,0,0.2);
          color: #000;
          border: none;
          padding: 8px 16px;
          border-radius: 4px;
          cursor: pointer;
          font-weight: 600;
          font-size: 14px;
        ">Later</button>
      </div>
    </div>
  `;

  document.body.appendChild(notification);
  sessionStorage.setItem('ngn_update_shown', 'true');

  // Reload on update
  document.getElementById('ngn-update-btn').addEventListener('click', () => {
    newWorker.postMessage({ type: 'SKIP_WAITING' });
    notification.remove();
    window.location.reload();
  });

  // Dismiss
  document.getElementById('ngn-dismiss-btn').addEventListener('click', () => {
    notification.remove();
  });

  // Auto-hide after 10 seconds
  setTimeout(() => {
    if (notification.parentNode) {
      notification.style.opacity = '0';
      notification.style.transition = 'opacity 0.3s ease-out';
      setTimeout(() => {
        if (notification.parentNode) {
          notification.remove();
        }
      }, 300);
    }
  }, 10000);
}

// ============================================================================
// INSTALL PROMPT HANDLING
// ============================================================================

let deferredPrompt = null;

window.addEventListener('beforeinstallprompt', (e) => {
  console.log('[PWA] beforeinstallprompt event fired');
  e.preventDefault();
  deferredPrompt = e;

  // Show custom install button
  showInstallPrompt();
});

/**
 * Show custom install prompt
 */
function showInstallPrompt() {
  // Look for install button in page (must have id="install-pwa")
  const installButton = document.getElementById('install-pwa');
  if (!installButton) {
    console.log('[PWA] No install button found in page (id="install-pwa")');
    return;
  }

  // Make button visible
  installButton.style.display = 'block';
  installButton.addEventListener('click', handleInstallClick);

  console.log('[PWA] Install prompt ready');
}

/**
 * Handle install button click
 */
function handleInstallClick(e) {
  e.preventDefault();

  if (!deferredPrompt) {
    console.log('[PWA] Install prompt not available');
    return;
  }

  // Show the install dialog
  deferredPrompt.prompt();

  // Wait for user response
  deferredPrompt.userChoice.then((choiceResult) => {
    if (choiceResult.outcome === 'accepted') {
      console.log('[PWA] User accepted install prompt');
      // Track installation
      if (window.gtag) {
        gtag('event', 'app_installed', {
          event_category: 'engagement',
          event_label: 'PWA Install'
        });
      }
    } else {
      console.log('[PWA] User dismissed install prompt');
    }

    deferredPrompt = null;
    const button = document.getElementById('install-pwa');
    if (button) {
      button.style.display = 'none';
    }
  });
}

/**
 * Listen for app installed event
 */
window.addEventListener('appinstalled', () => {
  console.log('[PWA] App successfully installed');
  deferredPrompt = null;

  const button = document.getElementById('install-pwa');
  if (button) {
    button.style.display = 'none';
  }

  // Track installation
  if (window.gtag) {
    gtag('event', 'app_installed', {
      event_category: 'engagement',
      event_label: 'PWA Install'
    });
  }
});

/**
 * Detect if app is already installed
 */
function isAppInstalled() {
  // Check for display mode standalone
  if (window.matchMedia('(display-mode: standalone)').matches) {
    return true;
  }

  // Check for iOS
  if (navigator.standalone) {
    return true;
  }

  return false;
}

/**
 * Get app installation status
 */
function getAppInstallationStatus() {
  if (!('getInstalledRelatedApps' in navigator)) {
    return Promise.resolve([]);
  }

  return navigator.getInstalledRelatedApps();
}

// ============================================================================
// INITIALIZATION
// ============================================================================

// Register service worker
registerServiceWorker();

// Log PWA status
window.addEventListener('load', () => {
  console.log('[PWA] App installed:', isAppInstalled());
  console.log('[PWA] Display mode:', window.matchMedia('(display-mode: standalone)').matches ? 'standalone' : 'browser');

  // Get installed related apps
  getAppInstallationStatus().then((apps) => {
    if (apps.length > 0) {
      console.log('[PWA] Installed related apps:', apps);
    }
  });
});

// ============================================================================
// EXPORTS FOR USE IN OTHER SCRIPTS
// ============================================================================

window.NGN = window.NGN || {};
window.NGN.PWA = {
  isInstalled: isAppInstalled,
  getInstalledApps: getAppInstallationStatus,
  checkForUpdates: () => {
    if (navigator.serviceWorker.controller) {
      navigator.serviceWorker.controller.postMessage({
        type: 'CHECK_UPDATES'
      });
    }
  }
};
