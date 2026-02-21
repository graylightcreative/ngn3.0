/**
 * Player Initialization Entry Point
 *
 * Initializes all player modules and exposes to global scope:
 * - AudioPlayer: Main player instance
 * - PlayerUI: UI controller
 * - Media Session: Lock screen controls
 * - Storage: State persistence
 *
 * Global API: window.NGNPlayer
 */

import { AudioPlayer } from './AudioPlayer.js';
import { setupMediaSession } from './media-session-init.js';
import { PlayerStorage } from './Storage.js';
import { PlaybackTracker } from './playback-tracker.js';

// Initialize player with restored volume
const player = new AudioPlayer({
  apiBaseUrl: '/api/v1',
  autoplay: false,
  volume: PlayerStorage.loadVolume()
});

console.log('[NGN Player] Initialized with volume:', PlayerStorage.loadVolume());

// Restore previous queue if it exists and hasn't expired
const savedQueueState = PlayerStorage.loadQueue();
if (savedQueueState && savedQueueState.queue && savedQueueState.queue.length > 0) {
  console.log('[NGN Player] Restoring queue with', savedQueueState.queue.length, 'tracks');
  player.setQueue(savedQueueState.queue, savedQueueState.currentIndex || 0);
}

// Initialize UI if player container exists on page
const playerContainer = document.getElementById('ngn-player-container');
if (playerContainer) {
  console.log('[NGN Player] Initializing UI...');
  // Use absolute web path for dynamic import
  import(`/js/player/PlayerUI.js?v=${Date.now()}`).then(module => {
    new module.PlayerUI(player, playerContainer);
  }).catch(err => {
    console.error('[NGN Player] Failed to load PlayerUI:', err);
  });
} else {
  console.warn('[NGN Player] Container #ngn-player-container not found');
}

// Setup Media Session API for lock screen controls (iOS, Android)
if ('mediaSession' in navigator) {
  console.log('[NGN Player] Media Session API supported - initializing lock screen controls');
  setupMediaSession(player);
} else {
  console.log('[NGN Player] Media Session API not supported in this browser');
}

// Setup Playback Tracker for qualified listen tracking
const tracker = new PlaybackTracker(player, '/api/v1');

// Handle playlist data embedded in page
// Pattern: <script type="application/json" id="playlistData">{"tracks": [...]}</script>
document.addEventListener('DOMContentLoaded', () => {
  const playlistData = document.getElementById('playlistData');
  if (playlistData) {
    try {
      const data = JSON.parse(playlistData.textContent);
      if (data.tracks && Array.isArray(data.tracks) && data.tracks.length > 0) {
        console.log('[NGN Player] Loading playlist with', data.tracks.length, 'tracks');
        player.setQueue(data.tracks, 0);
      }
    } catch (e) {
      console.error('[NGN Player] Failed to parse playlist data:', e);
    }
  }
});

// Save queue state when page unloads
window.addEventListener('beforeunload', () => {
  PlayerStorage.saveVolume(player.audio.volume);
  PlayerStorage.saveQueue(player.queue, player.currentIndex);
});

// Expose player to global scope for backward compatibility
window.NGNPlayer = player;

console.log('[NGN Player] Ready - Available as window.NGNPlayer');
