15. PWA Capabilities & Media Playback

15.1 Overview

To compete with native streaming apps while bypassing the "App Store Tax," NGN 2.0 must deliver a seamless background audio experience. This chapter defines the technical implementation of the Media Session API and Service Workers to ensure continuous playback, lock-screen integration, and "app-like" behavior on iOS and Android.

15.2 The Media Session API (Lock Screen Integration)

The Media Session API is the bridge between the browser and the device's hardware media controls. It allows NGN 2.0 to display metadata and respond to physical buttons (play, pause, skip) on the lock screen and control center.

15.2.1 Metadata Implementation

Whenever a track starts, the frontend must update the global navigator.mediaSession object:

if ('mediaSession' in navigator) {
  navigator.mediaSession.metadata = new MediaMetadata({
    title: currentTrack.title,
    artist: currentTrack.artist_name,
    album: currentTrack.album_name || 'NGN Radio',
    artwork: [
      { src: currentTrack.cover_sm, sizes: '96x96', type: 'image/jpeg' },
      { src: currentTrack.cover_lg, sizes: '512x512', type: 'image/jpeg' }
    ]
  });
}


15.2.2 Transport Control Handlers

We must map hardware button presses to our internal player logic to ensure the "Native" feel:

const actionHandlers = [
  ['play', () => player.play()],
  ['pause', () => player.pause()],
  ['previoustrack', () => player.prev()],
  ['nexttrack', () => player.next()],
  ['seekbackward', (details) => player.seek(details.seekOffset || -10)],
  ['seekforward', (details) => player.seek(details.seekOffset || 10)]
];

for (const [action, handler] of actionHandlers) {
  try {
    navigator.mediaSession.setActionHandler(action, handler);
  } catch (error) {
    console.warn(`The media session action "${action}" is not supported.`);
  }
}


15.3 Background Audio Strategy

Browsers aggressively throttle background tabs to save battery. To prevent playback from cutting out:

User Initiation: Playback must begin with an explicit user gesture (click/tap). The browser will block any attempt to "auto-play" audio into the background.

Audio Thread Persistence: As long as an <audio> or AudioContext is actively playing, iOS/Android will treat the browser tab as "Active," preventing the OS from killing the process.

Silence Padding: If there is a delay between tracks, we play a 0.1-second clip of digital silence to keep the audio thread "warm" and prevent the OS from reclaiming resources.

15.4 Service Workers & Offline Capability

We use Service Workers (via Workbox) to manage the "Offline-First" experience.

15.4.1 Caching Tiers

Tier 1 (App Shell): UI icons, CSS, and JS are cached on the first visit, allowing the app to open instantly even without a connection.

Tier 2 (Audio Buffering): The Service Worker intercepts audio requests and caches the next 2 tracks in the playlist to ensure gapless transitions in low-signal areas (e.g., subways).

Tier 3 (Favorites): If a user marks a track as a "Favorite," the PWA prompts to "Save for Offline," downloading the full file to the browser's CacheStorage.

15.5 Mobile UX Optimization (The "Native" Feel)

15.5.1 Safe Area Insets (The Notch)

To prevent the UI from being covered by the iPhone notch or the home bar, we utilize CSS environment variables:

body {
  padding-top: env(safe-area-inset-top);
  padding-bottom: env(safe-area-inset-bottom);
}

.persistent-player {
  /* Ensure the player stays above the iOS home swipe bar */
  bottom: env(safe-area-inset-bottom);
}


15.5.2 PWA Manifest Configuration

The manifest.json ensures NGN 2.0 appears as a standalone app:

display: "standalone" (Removes browser address bar).

orientation: "portrait" (Locks orientation for the music player).

theme_color: Matches our dark-mode "Spotify-style" branding.

15.6 Constraints & Solutions

A. The iOS "Silence Switch"

On iPhones, the physical mute switch can sometimes silence web audio.

Solution: We implement a "Sound Check" notification if the user hits play but no audio is detected, prompting them to check their physical switch.

B. Audio Interruptions

If a user receives a phone call, the Media Session API handles the pause automatically.

Logic: Our player listens for the onpause event triggered by the OS and maintains the "Current Position" so music resumes perfectly once the call ends.

C. Storage Quotas

Browsers limit how much data a PWA can store (typically 10-20% of disk space).

Management: NGN 2.0 includes a "Storage Manager" in the settings where users can clear their audio cache if it exceeds 500MB.