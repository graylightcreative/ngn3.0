# NGN 2.0 PWA - Quick Reference Guide

## Using URL Helper Functions

```php
<?php
// Include the URL helpers
require_once 'lib/helpers/url.php';
use NGN\Lib\Helpers as URL;

// Generate entity URLs
echo URL\artist_url('coldward');           // /artist/coldward
echo URL\label_url('metal-blade');         // /label/metal-blade
echo URL\station_url('wumr');              // /station/wumr
echo URL\venue_url('the-metal-club');      // /venue/the-metal-club
echo URL\post_url('new-review');           // /post/new-review
echo URL\video_url('live-show');           // /video/live-show

// Generate full URLs
echo URL\artist_full_url($artist);         // https://nextgennoise.com/artist/name

// Generate listing URLs
echo URL\listing_url('artists');           // /artists
echo URL\listing_url('artists', ['page' => 2]); // /artists?page=2
echo URL\listing_url('posts', ['q' => 'metal']); // /posts?q=metal

// Special pages
echo URL\page_url('charts');               // /charts
echo URL\page_url('pricing');              // /pricing

// User profiles
echo URL\user_profile_url('brock');        // /@brock

// Current page
echo URL\current_url();                    // https://nextgennoise.com/current/path

// Validate and sanitize
echo URL\sanitize_slug('My Cool Artist');  // my-cool-artist
if (URL\is_valid_slug('my-artist')) { }   // true
?>
```

## Adding Media Session to Audio Player

```javascript
// Import the module
import { 
  initMediaSession, 
  updatePlaybackPosition,
  requestWakeLock 
} from '/js/media-session.js';

// Initialize when player starts
const player = {
  title: 'Track Name',
  artist_name: 'Artist Name',
  album_name: 'Album Name',
  cover_sm: '/path/to/image-96.jpg',
  cover_md: '/path/to/image-256.jpg',
  cover_lg: '/path/to/image-512.jpg',
  
  play() { /* ... */ },
  pause() { /* ... */ },
  prev() { /* ... */ },
  next() { /* ... */ },
  seek(seconds) { /* ... */ },
  
  on(event, handler) { /* ... */ }
};

initMediaSession(player);

// Update position every second during playback
setInterval(() => {
  updatePlaybackPosition(duration, currentTime);
}, 1000);

// Request wake lock to keep screen on
requestWakeLock();
```

## Service Worker Testing

**Check if registered:**
```javascript
navigator.serviceWorker.ready.then(registration => {
  console.log('Service Worker ready:', registration);
});
```

**Manual update check:**
```javascript
navigator.serviceWorker.controller?.postMessage({
  type: 'CHECK_UPDATES'
});
```

**View cache contents:**
```javascript
// In browser console
caches.keys().then(names => {
  names.forEach(name => {
    caches.open(name).then(cache => {
      cache.keys().then(keys => {
        console.log(name, keys);
      });
    });
  });
});
```

## Clean URL Routing Reference

**Profile URLs** (routed to profile.php):
- `/artist/{slug}` → `artist-profile.php?slug={slug}`
- `/label/{slug}` → `label-profile.php?slug={slug}`
- `/station/{slug}` → `station-profile.php?slug={slug}`
- `/venue/{slug}` → `venue-profile.php?slug={slug}`
- `/video/{slug}` → `video.php?slug={slug}`

**Content URLs** (routed to index.php):
- `/post/{slug}` → `index.php?view=post&slug={slug}`
- `/show/{slug}` → `index.php?view=show&slug={slug}`
- `/release/{slug}` → `index.php?view=release&slug={slug}`
- `/product/{slug}` → `index.php?view=product&slug={slug}`
- `/@{username}` → `index.php?view=user&username={username}`

**Listing URLs** (routed to index.php):
- `/artists` → `index.php?view=artists`
- `/labels` → `index.php?view=labels`
- `/stations` → `index.php?view=stations`
- `/venues` → `index.php?view=venues`
- `/posts` → `index.php?view=posts`
- `/videos` → `videos.php` (or `index.php?view=videos`)
- `/shows` → `index.php?view=shows`
- `/shop` → `index.php?view=shop`

**Special URLs**:
- `/charts` → `index.php?view=charts`
- `/smr-charts` → `index.php?view=smr-charts`
- `/pricing` → `index.php?view=pricing`

**Backwards Compatibility:**
All old query parameter URLs still work:
- `/?view=artists` still redirects to `/artists`
- `/?view=artist&slug=x` still redirects to `/artist/x`

---

## PWA Global API

Exposed as `window.NGN.PWA`:

```javascript
// Check if app is installed
if (window.NGN.PWA.isInstalled()) {
  console.log('App is installed');
}

// Get installed related apps
window.NGN.PWA.getInstalledApps().then(apps => {
  console.log('Installed apps:', apps);
});

// Manually trigger update check
window.NGN.PWA.checkForUpdates();
```

---

## Cache Strategy Quick Reference

**API requests (`/api/*`):**
- Strategy: Network only
- No caching

**Static assets (images, fonts, etc.):**
- Strategy: Cache-first
- Cache duration: 1 year (immutable)

**HTML pages:**
- Strategy: Network-first
- Cache duration: 1 hour (must-revalidate)

**CSS/JS with version params:**
- Strategy: Cache-first
- Cache duration: 1 year (immutable)

---

## SEO & Open Graph

All pages automatically set:
- `<title>` - Page title
- `<meta name="description">` - Page description
- `<meta property="og:title">` - Social title
- `<meta property="og:description">` - Social description
- `<meta property="og:image">` - Social image
- `<meta property="og:url">` - Canonical URL
- `<link rel="canonical">` - Canonical link

Profile pages include schema.org structured data:
- Artist: `MusicGroup` schema
- Label: `Organization` schema
- Venue: `Place` schema
- Video: `VideoObject` schema

---

## Manifest & Favicon Locations

**Manifest:**
- Path: `/lib/images/site/site.webmanifest`
- Start URL: `/`
- Display: `standalone`
- Theme color: `#0b1020`

**Favicons:**
- `/lib/images/site/favicon.ico` (16x16)
- `/lib/images/site/favicon-32x32.png` (32x32)
- `/lib/images/site/favicon-16x16.png` (16x16)
- `/lib/images/site/apple-touch-icon.png` (180x180)
- `/lib/images/site/android-chrome-192x192.png` (192x192, maskable)
- `/lib/images/site/android-chrome-512x512.png` (512x512, maskable)

---

## Common Tasks

### Displaying artist with link
```php
<?php
require_once 'lib/helpers/url.php';
$artist = ['name' => 'Coldward', 'slug' => 'coldward'];
?>
<a href="<?= \NGN\Lib\Helpers\artist_url($artist['slug']) ?>">
  <?= htmlspecialchars($artist['name']) ?>
</a>
```

### Pagination with query params
```php
<?php
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$next = $page + 1;
?>
<a href="/artists?page=<?= $next ?>">Next Page</a>
```

### Mobile install button
```html
<button id="install-pwa">Install App</button>

<!-- Script automatically shows/hides this based on PWA state -->
<script src="/js/pwa-setup.js"></script>
```

### Analytics tracking
```javascript
// Track PWA installation
if (window.gtag) {
  gtag('event', 'app_installed', {
    event_category: 'engagement',
    event_label: 'PWA Install'
  });
}
```

---

## Troubleshooting

**Service worker not registering?**
- Check browser console for errors
- Verify `/public/service-worker.js` exists and is accessible
- Check .htaccess has correct Service-Worker-Allowed header
- Service worker only works over HTTPS

**PWA not installing?**
- Check manifest.json is valid JSON
- Verify icons exist at specified paths
- Check theme color format
- Some browsers require specific criteria (HTTPS, service worker, manifest)

**Cache not updating?**
- Service worker checks every 24 hours by default
- Manually trigger with `window.NGN.PWA.checkForUpdates()`
- Clear browser cache if stuck
- Check .htaccess headers for service-worker.js

**Media Session not working?**
- Only works on supported platforms (Android, iOS 15.1+, Chrome 73+)
- Requires initialization before audio plays
- Lock screen only updates on track changes
- Check `navigator.mediaSession` exists before calling

---

## Performance Tips

1. **Compress images** - Use WebP with fallbacks
2. **Version static assets** - Use v=123 query params
3. **Minimize JS** - Keep scripts small and lazy-load
4. **Cache aggressively** - Static assets use 1-year cache
5. **Monitor service worker** - Check cache size and performance

---

## Resources

- [Web Dev PWA Guide](https://web.dev/progressive-web-apps/)
- [Service Worker API](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API)
- [Media Session API](https://developer.mozilla.org/en-US/docs/Web/API/MediaSession)
- [Deep Linking](https://developer.apple.com/documentation/safariservices/supporting_associated_domains)

