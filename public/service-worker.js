/**
 * NGN 2.0 Progressive Web App Service Worker
 * Provides offline support, caching strategies, and push notifications
 */

const CACHE_VERSION = 'ngn-v1.0.0';
const CACHE_ASSETS = [
  '/',
  '/lib/images/site/site.webmanifest',
  '/lib/images/site/favicon.ico',
  '/lib/images/site/favicon-32x32.png',
  '/lib/images/site/favicon-16x16.png',
  '/lib/images/site/apple-touch-icon.png',
  '/lib/images/site/android-chrome-192x192.png',
  '/lib/images/site/android-chrome-512x512.png'
];

// ============================================================================
// INSTALL EVENT - Cache critical app shell on first install
// ============================================================================
self.addEventListener('install', (event) => {
  console.log('[SW] Install event');

  event.waitUntil(
    caches.open(CACHE_VERSION).then((cache) => {
      console.log('[SW] Caching app shell');
      return cache.addAll(CACHE_ASSETS).catch((err) => {
        console.warn('[SW] Some assets failed to cache:', err);
        // Don't fail installation if some assets don't cache
        return Promise.resolve();
      });
    })
  );

  // Force new service worker to activate immediately
  self.skipWaiting();
});

// ============================================================================
// ACTIVATE EVENT - Clean up old cache versions
// ============================================================================
self.addEventListener('activate', (event) => {
  console.log('[SW] Activate event');

  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames
          .filter((name) => name !== CACHE_VERSION)
          .map((name) => {
            console.log('[SW] Deleting old cache:', name);
            return caches.delete(name);
          })
      );
    })
  );

  // Claim clients immediately
  self.clients.claim();
});

// ============================================================================
// FETCH EVENT - Network first with cache fallback
// Strategy:
//   - API requests: Network only (always fresh data)
//   - Static assets: Cache first (fast, with network update)
//   - HTML pages: Network first (fresh content, fallback to cache)
// ============================================================================
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Skip non-GET requests (POST, PUT, DELETE, etc.)
  if (request.method !== 'GET') {
    return;
  }

  // Skip API requests - always go to network
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(
      fetch(request).catch((err) => {
        console.warn('[SW] API request failed:', err);
        return new Response(
          JSON.stringify({ error: 'Offline - API unavailable' }),
          {
            status: 503,
            headers: { 'Content-Type': 'application/json' }
          }
        );
      })
    );
    return;
  }

  // Skip admin and auth routes
  if (url.pathname.startsWith('/admin/') ||
      url.pathname.startsWith('/auth/') ||
      url.pathname.startsWith('/dashboard/')) {
    return;
  }

  // Static assets: Cache first strategy
  if (isStaticAsset(url.pathname)) {
    event.respondWith(
      caches.match(request).then((cachedResponse) => {
        if (cachedResponse) {
          return cachedResponse;
        }

        return fetch(request).then((response) => {
          // Cache successful responses
          if (response && response.status === 200) {
            const responseClone = response.clone();
            caches.open(CACHE_VERSION).then((cache) => {
              cache.put(request, responseClone);
            });
          }
          return response;
        });
      })
    );
    return;
  }

  // HTML pages and dynamic content: Network first strategy
  event.respondWith(
    fetch(request)
      .then((response) => {
        // Cache successful HTML responses
        if (response && response.status === 200) {
          const responseClone = response.clone();
          caches.open(CACHE_VERSION).then((cache) => {
            cache.put(request, responseClone);
          });
        }
        return response;
      })
      .catch((err) => {
        // Network failed, try cache
        console.warn('[SW] Network request failed:', err);
        return caches.match(request).then((cachedResponse) => {
          if (cachedResponse) {
            return cachedResponse;
          }

          // If not cached, return offline page for navigation requests
          if (request.mode === 'navigate') {
            return caches.match('/') || createOfflinePage();
          }

          // Return error response for other requests
          return new Response('Offline - page not cached', { status: 503 });
        });
      })
  );
});

// ============================================================================
// PUSH NOTIFICATION EVENT - Handle push messages and show notifications
// ============================================================================
self.addEventListener('push', (event) => {
  console.log('[SW] Push event received');

  let notificationData = {
    title: 'Next Gen Noise',
    body: 'You have a new notification',
    icon: '/lib/images/site/android-chrome-192x192.png',
    badge: '/lib/images/site/favicon-32x32.png',
    vibrate: [200, 100, 200],
    data: { url: '/' }
  };

  // Parse push event data if available
  if (event.data) {
    try {
      const pushData = event.data.json();
      notificationData = { ...notificationData, ...pushData };
    } catch (e) {
      // Push data is plain text, not JSON
      notificationData.body = event.data.text();
    }
  }

  event.waitUntil(
    self.registration.showNotification(notificationData.title, {
      body: notificationData.body,
      icon: notificationData.icon,
      badge: notificationData.badge,
      vibrate: notificationData.vibrate,
      data: notificationData.data,
      actions: [
        { action: 'open', title: 'Open' },
        { action: 'close', title: 'Close' }
      ],
      tag: notificationData.tag || 'ngn-notification',
      requireInteraction: false
    })
  );
});

// ============================================================================
// NOTIFICATION CLICK EVENT - Handle notification interactions
// ============================================================================
self.addEventListener('notificationclick', (event) => {
  console.log('[SW] Notification clicked:', event.action);

  event.notification.close();

  if (event.action === 'close') {
    return;
  }

  // Open the URL from notification data
  const url = event.notification.data?.url || '/';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      // Check if app is already open
      for (const client of clientList) {
        if (client.url === url && 'focus' in client) {
          return client.focus();
        }
      }

      // If not open, open new window
      if (clients.openWindow) {
        return clients.openWindow(url);
      }
    })
  );
});

// ============================================================================
// MESSAGE EVENT - Handle messages from client pages
// ============================================================================
self.addEventListener('message', (event) => {
  console.log('[SW] Message received:', event.data);

  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

// ============================================================================
// BACKGROUND SYNC EVENT - Sync user actions when back online
// ============================================================================
self.addEventListener('sync', (event) => {
  console.log('[SW] Background sync:', event.tag);

  if (event.tag === 'sync-favorites') {
    event.waitUntil(syncFavorites());
  }

  if (event.tag === 'sync-ratings') {
    event.waitUntil(syncRatings());
  }
});

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Determine if a URL path is a static asset
 */
function isStaticAsset(pathname) {
  const staticPatterns = [
    /\.(js|css|png|jpg|jpeg|gif|svg|webp|ico|woff|woff2|ttf|eot|mp3|mp4)$/i,
    /^\/assets\//,
    /^\/lib\/images\//,
    /^\/uploads\//
  ];

  return staticPatterns.some((pattern) => pattern.test(pathname));
}

/**
 * Create a simple offline page
 */
function createOfflinePage() {
  const html = `
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Offline - Next Gen Noise</title>
      <style>
        body {
          font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
          background: #0b1020;
          color: #f8fafc;
          display: flex;
          align-items: center;
          justify-content: center;
          min-height: 100vh;
          margin: 0;
          padding: 20px;
        }
        .container {
          text-align: center;
          max-width: 500px;
        }
        h1 {
          font-size: 32px;
          margin-bottom: 16px;
        }
        p {
          font-size: 18px;
          color: #94a3b8;
          margin-bottom: 32px;
          line-height: 1.6;
        }
        a {
          display: inline-block;
          padding: 12px 24px;
          background: #1DB954;
          color: #000;
          text-decoration: none;
          border-radius: 8px;
          font-weight: 600;
          transition: background 0.2s;
        }
        a:hover {
          background: #169c45;
        }
      </style>
    </head>
    <body>
      <div class="container">
        <h1>🔌 You're Offline</h1>
        <p>Check your internet connection and try again. Previously loaded pages are available.</p>
        <a href="/">Go to Homepage</a>
      </div>
    </body>
    </html>
  `;

  return new Response(html, {
    status: 503,
    statusText: 'Service Unavailable',
    headers: { 'Content-Type': 'text/html; charset=utf-8' }
  });
}

/**
 * Sync favorites with server when back online
 */
async function syncFavorites() {
  try {
    const db = await openIndexedDB();
    const favs = await getAllFromDB(db, 'favorites');

    if (favs.length === 0) {
      return;
    }

    const response = await fetch('/api/v1/favorites/sync', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ favorites: favs })
    });

    if (response.ok) {
      await clearDB('favorites');
      console.log('[SW] Favorites synced successfully');
    }
  } catch (err) {
    console.warn('[SW] Failed to sync favorites:', err);
    throw err; // Retry
  }
}

/**
 * Sync ratings with server when back online
 */
async function syncRatings() {
  try {
    const db = await openIndexedDB();
    const ratings = await getAllFromDB(db, 'ratings');

    if (ratings.length === 0) {
      return;
    }

    const response = await fetch('/api/v1/ratings/sync', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ratings: ratings })
    });

    if (response.ok) {
      await clearDB('ratings');
      console.log('[SW] Ratings synced successfully');
    }
  } catch (err) {
    console.warn('[SW] Failed to sync ratings:', err);
    throw err; // Retry
  }
}

/**
 * Open IndexedDB for offline data storage
 */
function openIndexedDB() {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open('NGN', 1);

    req.onerror = () => reject(req.error);
    req.onsuccess = () => resolve(req.result);

    req.onupgradeneeded = (e) => {
      const db = e.target.result;
      if (!db.objectStoreNames.contains('favorites')) {
        db.createObjectStore('favorites', { keyPath: 'id', autoIncrement: true });
      }
      if (!db.objectStoreNames.contains('ratings')) {
        db.createObjectStore('ratings', { keyPath: 'id', autoIncrement: true });
      }
    };
  });
}

/**
 * Get all items from an IndexedDB store
 */
function getAllFromDB(db, storeName) {
  return new Promise((resolve, reject) => {
    const txn = db.transaction([storeName], 'readonly');
    const store = txn.objectStore(storeName);
    const req = store.getAll();

    req.onerror = () => reject(req.error);
    req.onsuccess = () => resolve(req.result);
  });
}

/**
 * Clear an IndexedDB store
 */
function clearDB(storeName) {
  return openIndexedDB().then((db) => {
    return new Promise((resolve, reject) => {
      const txn = db.transaction([storeName], 'readwrite');
      const store = txn.objectStore(storeName);
      const req = store.clear();

      req.onerror = () => reject(req.error);
      req.onsuccess = () => resolve();
    });
  });
}

console.log('[SW] Service Worker loaded and ready');
