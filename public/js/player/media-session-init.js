/**
 * Media Session Integration
 *
 * Connects the AudioPlayer to the existing media-session.js module
 * for lock screen controls (iOS, Android) and system media controls
 */

// Import existing media-session module (if available)
// Note: The media-session.js module expects a player object with:
// - .on(event, callback) event emitter
// - .play(), .pause(), .next(), .prev(), .seek() methods
// - Track metadata: title, artist_name, album_name, cover_*

/**
 * Setup Media Session with AudioPlayer
 */
export function setupMediaSession(player) {
  // Check if Media Session API is supported
  if (!('mediaSession' in navigator)) {
    console.warn('[Media Session] API not supported in this browser');
    return;
  }

  console.log('[Media Session] Initializing...');

  // Listen to player events and update Media Session metadata
  player.on('trackchange', (data) => {
    const track = data.track;
    if (!track) return;

    // Set metadata for lock screen display
    try {
      navigator.mediaSession.metadata = new MediaMetadata({
        title: track.title || 'Unknown',
        artist: track.artist_name || 'Unknown',
        album: track.album_name || 'NGN',
        artwork: getArtworkArray(track)
      });
    } catch (e) {
      console.warn('[Media Session] Failed to set metadata:', e);
    }
  });

  // Media Session action handlers
  try {
    // Play
    navigator.mediaSession.setActionHandler('play', () => {
      player.play();
    });

    // Pause
    navigator.mediaSession.setActionHandler('pause', () => {
      player.pause();
    });

    // Next track
    navigator.mediaSession.setActionHandler('nexttrack', () => {
      player.next();
    });

    // Previous track
    navigator.mediaSession.setActionHandler('previoustrack', () => {
      player.prev();
    });

    // Seek backward (10 seconds)
    navigator.mediaSession.setActionHandler('seekbackward', (details) => {
      const seekTime = details.seekOffset || 10;
      player.seek(Math.max(0, player.audio.currentTime - seekTime));
    });

    // Seek forward (10 seconds)
    navigator.mediaSession.setActionHandler('seekforward', (details) => {
      const seekTime = details.seekOffset || 10;
      player.seek(player.audio.currentTime + seekTime);
    });

    console.log('[Media Session] Actions registered');
  } catch (e) {
    console.warn('[Media Session] Failed to set action handlers:', e);
  }

  // Request wake lock to keep screen on during playback
  player.on('play', () => {
    if ('wakeLock' in navigator) {
      navigator.wakeLock.request('screen').catch(() => {
        // Wake lock not available or denied
      });
    }
  });

  // Update playback position for lock screen (every second)
  player.on('timeupdate', (data) => {
    if (data.duration && data.currentTime >= 0) {
      try {
        navigator.mediaSession.playbackState = player.state.isPlaying ? 'playing' : 'paused';
        navigator.mediaSession.setPositionState({
          duration: data.duration,
          playbackRate: 1.0,
          position: data.currentTime
        });
      } catch (e) {
        // Position state might not be supported
      }
    }
  });

  console.log('[Media Session] Setup complete');
}

/**
 * Get artwork array for MediaMetadata (supports multiple sizes)
 */
function getArtworkArray(track) {
  const artwork = [];

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

  // Fallback to default artwork if no covers provided
  if (artwork.length === 0) {
    artwork.push({
      src: '/lib/images/default-album-art.jpg',
      sizes: '256x256',
      type: 'image/jpeg'
    });
  }

  return artwork;
}

/**
 * Update playback position (can be called externally)
 * Useful for periodic updates
 */
export function updatePlaybackPosition(duration, currentTime) {
  if ('mediaSession' in navigator && navigator.mediaSession.setPositionState) {
    try {
      navigator.mediaSession.setPositionState({
        duration,
        playbackRate: 1.0,
        position: currentTime
      });
    } catch (e) {
      // Ignore - might not be supported
    }
  }
}

/**
 * Request notification permission for Media Session notifications
 */
export function requestNotificationPermission() {
  if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission();
  }
}

/**
 * Show notification on track change
 */
export function showTrackNotification(track) {
  if ('Notification' in window && Notification.permission === 'granted') {
    try {
      new Notification(track.title, {
        body: track.artist_name,
        icon: track.cover_sm || '/lib/images/default-album-art.jpg',
        tag: 'ngn-player-track'
      });
    } catch (e) {
      console.warn('[Media Session] Failed to show notification:', e);
    }
  }
}
