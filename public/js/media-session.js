/**
 * NGN 2.0 Media Session API Integration
 * Provides lock screen controls and background audio playback support
 * Enables play/pause/skip controls on iOS, Android, and desktop
 */

/**
 * Initialize Media Session API with a player instance
 *
 * Player object should emit events and expose methods:
 * Events: 'play', 'pause', 'trackchange'
 * Methods: play(), pause(), prev(), next(), seek(seconds)
 * Track object should have: title, artist_name, album_name, cover_sm, cover_md, cover_lg
 *
 * @param {Object} player - The audio player instance
 * @returns {void}
 */
export function initMediaSession(player) {
  // Check if Media Session API is supported
  if (!('mediaSession' in navigator)) {
    console.warn('[MediaSession] Media Session API not supported in this browser');
    return;
  }

  console.log('[MediaSession] Initializing Media Session API');

  // ========================================================================
  // UPDATE METADATA - Call this when track changes
  // ========================================================================
  if (player.on) {
    player.on('trackchange', (track) => {
      updateMediaMetadata(track);
    });
  }

  // ========================================================================
  // SET ACTION HANDLERS
  // ========================================================================
  setActionHandlers(player);

  // ========================================================================
  // UPDATE PLAYBACK STATE
  // ========================================================================
  if (player.on) {
    player.on('play', () => {
      navigator.mediaSession.playbackState = 'playing';
    });

    player.on('pause', () => {
      navigator.mediaSession.playbackState = 'paused';
    });
  }

  console.log('[MediaSession] Media Session API initialized');
}

/**
 * Update the current track metadata displayed on lock screen
 *
 * @param {Object} track - Track object with metadata
 */
function updateMediaMetadata(track) {
  if (!track) {
    navigator.mediaSession.metadata = null;
    return;
  }

  const artwork = [];

  // Add artwork at different sizes
  if (track.cover_sm) {
    artwork.push({
      src: track.cover_sm,
      sizes: '96x96',
      type: 'image/jpeg'
    });
  }

  if (track.cover_md) {
    artwork.push({
      src: track.cover_md,
      sizes: '256x256',
      type: 'image/jpeg'
    });
  }

  if (track.cover_lg) {
    artwork.push({
      src: track.cover_lg,
      sizes: '512x512',
      type: 'image/jpeg'
    });
  }

  // Fallback if no cover specified
  if (artwork.length === 0) {
    artwork.push({
      src: '/lib/images/site/android-chrome-192x192.png',
      sizes: '192x192',
      type: 'image/png'
    });
  }

  try {
    navigator.mediaSession.metadata = new MediaMetadata({
      title: track.title || 'Unknown Track',
      artist: track.artist_name || 'Unknown Artist',
      album: track.album_name || 'NGN Radio',
      artwork: artwork
    });

    console.log('[MediaSession] Metadata updated:', {
      title: track.title,
      artist: track.artist_name,
      album: track.album_name
    });
  } catch (error) {
    console.warn('[MediaSession] Failed to update metadata:', error);
  }
}

/**
 * Set up action handlers for media controls
 * Handles: play, pause, previous track, next track, seek
 *
 * @param {Object} player - The player instance
 */
function setActionHandlers(player) {
  // Define all supported actions
  const actions = [
    {
      name: 'play',
      handler: () => {
        if (player.play && typeof player.play === 'function') {
          player.play();
        }
      }
    },
    {
      name: 'pause',
      handler: () => {
        if (player.pause && typeof player.pause === 'function') {
          player.pause();
        }
      }
    },
    {
      name: 'previoustrack',
      handler: () => {
        if (player.prev && typeof player.prev === 'function') {
          player.prev();
        }
      }
    },
    {
      name: 'nexttrack',
      handler: () => {
        if (player.next && typeof player.next === 'function') {
          player.next();
        }
      }
    },
    {
      name: 'seekbackward',
      handler: (details) => {
        if (player.seek && typeof player.seek === 'function') {
          const seekTime = details.seekOffset || 10;
          player.seek(-seekTime);
        }
      }
    },
    {
      name: 'seekforward',
      handler: (details) => {
        if (player.seek && typeof player.seek === 'function') {
          const seekTime = details.seekOffset || 10;
          player.seek(seekTime);
        }
      }
    },
    {
      name: 'skipad',
      handler: () => {
        console.log('[MediaSession] Skip ad requested');
        if (player.skipAd && typeof player.skipAd === 'function') {
          player.skipAd();
        }
      }
    }
  ];

  // Register each action handler
  for (const action of actions) {
    try {
      navigator.mediaSession.setActionHandler(action.name, action.handler);
      console.log(`[MediaSession] Action handler registered: ${action.name}`);
    } catch (error) {
      // Some actions may not be supported on all devices
      console.warn(`[MediaSession] Action "${action.name}" not supported:`, error.message);
    }
  }
}

/**
 * Update playback position on lock screen
 * Call this periodically during playback to show accurate position
 *
 * @param {number} duration - Total track duration in seconds
 * @param {number} position - Current playback position in seconds
 */
export function updatePlaybackPosition(duration, position) {
  if (!('mediaSession' in navigator)) {
    return;
  }

  try {
    navigator.mediaSession.setPositionState({
      duration: duration || 0,
      playbackRate: 1,
      position: position || 0
    });
  } catch (error) {
    console.warn('[MediaSession] Failed to update position:', error);
  }
}

/**
 * Request Media Session wake lock (keeps screen on during playback)
 * This is useful for audio playback
 */
export async function requestWakeLock() {
  try {
    if ('wakeLock' in navigator) {
      const wakeLock = await navigator.wakeLock.request('screen');
      console.log('[MediaSession] Wake lock acquired');

      // Release wake lock on visibility change
      document.addEventListener('visibilitychange', async () => {
        if (document.hidden) {
          wakeLock.release();
        } else {
          try {
            await navigator.wakeLock.request('screen');
          } catch (err) {
            console.warn('[MediaSession] Failed to re-acquire wake lock:', err);
          }
        }
      });

      return wakeLock;
    }
  } catch (error) {
    console.warn('[MediaSession] Failed to acquire wake lock:', error);
  }
}

/**
 * Set playback rate on Media Session
 *
 * @param {number} rate - Playback rate (1 = normal speed)
 */
export function setPlaybackRate(rate) {
  if (!('mediaSession' in navigator)) {
    return;
  }

  try {
    if (navigator.mediaSession.playbackRate !== rate) {
      navigator.mediaSession.playbackRate = rate;
      console.log('[MediaSession] Playback rate updated:', rate);
    }
  } catch (error) {
    console.warn('[MediaSession] Failed to set playback rate:', error);
  }
}

/**
 * Set audio sink (output device) if available
 * Useful for selecting between speakers, headphones, etc.
 *
 * @param {string} sinkId - Audio device ID
 */
export async function setAudioSink(sinkId) {
  try {
    if (navigator.mediaDevices && navigator.mediaDevices.selectAudioOutput) {
      await navigator.mediaDevices.selectAudioOutput({ deviceId: sinkId });
      console.log('[MediaSession] Audio sink updated:', sinkId);
    }
  } catch (error) {
    console.warn('[MediaSession] Failed to set audio sink:', error);
  }
}

/**
 * Get available audio output devices
 *
 * @returns {Promise<Array>} Array of audio device objects
 */
export async function getAudioOutputDevices() {
  try {
    if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
      return [];
    }

    const devices = await navigator.mediaDevices.enumerateDevices();
    return devices.filter((device) => device.kind === 'audiooutput');
  } catch (error) {
    console.warn('[MediaSession] Failed to enumerate audio devices:', error);
    return [];
  }
}

/**
 * Request Notification permission for push notifications
 *
 * @returns {Promise<boolean>} True if permission granted
 */
export async function requestNotificationPermission() {
  if (!('Notification' in window)) {
    console.warn('[MediaSession] Notifications not supported');
    return false;
  }

  if (Notification.permission === 'granted') {
    return true;
  }

  if (Notification.permission !== 'denied') {
    try {
      const permission = await Notification.requestPermission();
      return permission === 'granted';
    } catch (error) {
      console.warn('[MediaSession] Failed to request notification permission:', error);
      return false;
    }
  }

  return false;
}

// Export for CommonJS/Node if needed
if (typeof module !== 'undefined' && module.exports) {
  module.exports = {
    initMediaSession,
    updatePlaybackPosition,
    requestWakeLock,
    setPlaybackRate,
    setAudioSink,
    getAudioOutputDevices,
    requestNotificationPermission
  };
}

console.log('[MediaSession] Media Session module loaded');
