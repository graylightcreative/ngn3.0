16. Multi-Platform Strategy

16.1 The Universal Platform Vision

NGN 2.0 is built as a Universal Platform. By utilizing a "Mobile-First" PWA architecture, we serve Web, iOS, and Android users from a single, high-performance codebase while maintaining the ability to wrap the app for official stores (via Capacitor) if strategic visibility requires it in the future.

16.2 Design Tokens & Mobile UX

16.2.1 Safe Area Insets (The "Notch")

To ensure the NGN 2.0 dashboard and persistent player are not obscured by device hardware (notches, dynamic islands, or home swipe bars), we utilize CSS environment variables.

Implementation: Tailwind utility classes or custom CSS must account for safe-area-inset.

/* Persistent Player positioning */
.player-container {
  padding-bottom: calc(1rem + env(safe-area-inset-bottom));
}

/* Header positioning */
.top-nav {
  padding-top: env(safe-area-inset-top);
}


16.2.2 Touch Target Sizing & Haptics

Touch Targets: All interactive elements (buttons, "Spark" icons, nav links) must be a minimum of 44x44 points per Apple’s Human Interface Guidelines to ensure high usability for mobile users.

Haptic Feedback: On supported Android devices, we utilize the navigator.vibrate() API to provide tactile confirmation when a user "Sparks" a post or successfully purchases a ticket.

Example: navigator.vibrate(50); for a successful tip.

16.3 Mobile Engagement & Deep Linking

To compete with native apps, NGN 2.0 must handle links as "Universal Actions" rather than just web URLs.

16.3.1 Universal Links (iOS) & App Links (Android)

We implement deep-linking configuration on the Liquid Web origin to ensure that NGN links (e.g., in a text message or Instagram bio) open directly in the PWA if it is "Installed" on the home screen.

Apple: Requires an apple-app-site-association (AASA) JSON file in the .well-known directory.

Android: Requires an assetlinks.json file.

Fallback: If the user hasn't added the app to their home screen, the link opens in the standard mobile browser with a "Download App" banner.

16.3.2 Smart QR Redirection

Our Dynamic QR system (Ch. 7.3) detects the user's OS and provides specific onboarding prompts:

iOS Detection: If on Safari, Niko prompts the user with a tooltip: "Tap the Share icon and 'Add to Home Screen' to unlock background playback."

Android Detection: Triggers the native "Add to Home Screen" prompt via the beforeinstallprompt event.

16.4 Push Notifications (The Engagement Loop)

Niko (Editor-in-Chief) relies on notifications to drive users back into the platform.

Web Push API: We utilize Service Workers to handle incoming push events from our PHP notification service.

iOS Support: As of iOS 16.4+, Apple supports Web Push for PWAs that have been saved to the home screen.

User Flow: 1.  User adds NGN to home screen.
2.  Niko prompts: "Want to know when [Artist Name] drops a new Riff?"
3.  User grants permission; the Service Worker registers the pushSubscription.

16.5 Native Bridge Strategy (Capacitor)

While our primary deployment is a PWA, we use Capacitor as our bridge for future "Official Store" presence.

The Wrapper: Capacitor allows us to wrap our Vite/Tailwind build in a native WebView.

Native Features: If we require deeper integration (e.g., access to the native Contacts list for "Find Friends" or the Bluetooth API for venue beacons), we toggle the Capacitor plugins without changing our core business logic.

16.6 Branding, Launch Icons & Splash Screens

To maintain the "Native Illusion," the PWA must display a splash screen during the initial load (bootstrapping the PHP/JS environment).

Icons: 192x192 and 512x512 "maskable" icons for Android to support different launcher shapes.

Launch Images: We utilize apple-touch-startup-image meta tags in the HTML head to provide a high-res NGN logo during the splash phase, matching the system theme (Light/Dark).

16.7 Storage & Performance Optimization

IndexedDB: Mobile browsers have varying storage limits. We use IndexedDB for large metadata caches (Artist Directories) and localStorage for bouncer ticket hashes.

Image Optimization: Fastly (Ch. 11) is configured to detect the device's screen density (Retina @2x/@3x) and serve the exact resolution required to save mobile data and battery life.