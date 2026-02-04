/**
 * StationPlayer - Continuous radio stream player
 *
 * Simplified player for station streaming:
 * - No queue management (single continuous stream)
 * - Buffering state handling
 * - Auto-reconnect on errors
 * - Session tracking with heartbeats
 * - Now-playing metadata polling
 * - Media Session API integration
 */

export class StationPlayer {
  constructor(stationId, apiBaseUrl = '/api/v1') {
    this.stationId = stationId;
    this.apiBaseUrl = apiBaseUrl;
    this.audio = new Audio();
    this.audio.crossOrigin = 'anonymous';
    this.sessionId = this.generateSessionId();
    this.listeners = {};
    this.pollingInterval = null;
    this.heartbeatInterval = null;
    this.metadata = null;

    this.state = {
      isPlaying: false,
      isBuffering: false,
      isMuted: false,
      volume: 0.7
    };

    this.audio.volume = this.state.volume;
    this.setupAudioListeners();
  }

  /**
   * Generate unique session ID
   */
  generateSessionId() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
      const r = Math.random() * 16 | 0;
      const v = c === 'x' ? r : (r & 0x3 | 0x8);
      return v.toString(16);
    });
  }

  /**
   * Setup audio element event listeners
   */
  setupAudioListeners() {
    this.audio.addEventListener('play', () => {
      this.state.isPlaying = true;
      this.state.isBuffering = false;
      this.emit('play');
    });

    this.audio.addEventListener('pause', () => {
      this.state.isPlaying = false;
      this.emit('pause');
    });

    this.audio.addEventListener('waiting', () => {
      this.state.isBuffering = true;
      this.emit('buffering', { isBuffering: true });
    });

    this.audio.addEventListener('playing', () => {
      this.state.isBuffering = false;
      this.emit('buffering', { isBuffering: false });
    });

    this.audio.addEventListener('error', (e) => {
      console.error('[StationPlayer] Stream error:', e);
      this.emit('error', { error: e });
      this.attemptReconnect();
    });

    this.audio.addEventListener('volumechange', () => {
      this.emit('volumechange', {
        volume: this.audio.volume,
        muted: this.audio.muted
      });
    });
  }

  /**
   * Load and play station stream
   */
  async play() {
    try {
      // Fetch stream token
      const response = await fetch(`${this.apiBaseUrl}/stations/${this.stationId}/stream`);
      if (!response.ok) {
        throw new Error(`Failed to get stream URL: ${response.statusText}`);
      }

      const data = await response.json();
      if (!data.success || !data.data.url) {
        throw new Error('No stream URL returned');
      }

      // Set stream URL
      this.audio.src = data.data.url;

      // Start playback
      await this.audio.play();

      // Start session tracking
      await this.startSession();

      // Start polling for now-playing metadata
      this.startMetadataPolling();

      // Start heartbeat
      this.startHeartbeat();

      console.log('[StationPlayer] Stream started:', data.data.station.name);

    } catch (error) {
      console.error('[StationPlayer] Play error:', error);
      this.emit('error', { error });
    }
  }

  /**
   * Pause playback
   */
  pause() {
    this.audio.pause();
    this.stopPolling();
    this.stopHeartbeat();
    this.endSession();
  }

  /**
   * Toggle play/pause
   */
  async togglePlay() {
    if (this.state.isPlaying) {
      this.pause();
    } else {
      await this.play();
    }
  }

  /**
   * Set volume (0-1)
   */
  setVolume(level) {
    const volume = Math.max(0, Math.min(1, parseFloat(level)));
    this.audio.volume = volume;
    this.state.volume = volume;
  }

  /**
   * Toggle mute
   */
  toggleMute() {
    this.state.isMuted = !this.state.isMuted;
    this.audio.muted = this.state.isMuted;
  }

  /**
   * Start session tracking
   */
  async startSession() {
    try {
      const response = await fetch(
        `${this.apiBaseUrl}/stations/${this.stationId}/session/start`,
        {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ session_id: this.sessionId })
        }
      );

      const data = await response.json();
      if (data.success) {
        console.log('[StationPlayer] Session started:', this.sessionId);
      }
    } catch (error) {
      console.error('[StationPlayer] Session start error:', error);
    }
  }

  /**
   * Send heartbeat to keep session alive
   */
  async sendHeartbeat() {
    try {
      await fetch(
        `${this.apiBaseUrl}/stations/${this.stationId}/session/heartbeat`,
        {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ session_id: this.sessionId })
        }
      );
    } catch (error) {
      console.error('[StationPlayer] Heartbeat error:', error);
    }
  }

  /**
   * End session
   */
  async endSession() {
    try {
      await fetch(
        `${this.apiBaseUrl}/stations/${this.stationId}/session/end`,
        {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ session_id: this.sessionId })
        }
      );
      console.log('[StationPlayer] Session ended');
    } catch (error) {
      console.error('[StationPlayer] Session end error:', error);
    }
  }

  /**
   * Start heartbeat interval (every 60 seconds)
   */
  startHeartbeat() {
    this.stopHeartbeat(); // Clear any existing interval
    this.heartbeatInterval = setInterval(() => {
      this.sendHeartbeat();
    }, 60000); // 60 seconds
  }

  /**
   * Stop heartbeat
   */
  stopHeartbeat() {
    if (this.heartbeatInterval) {
      clearInterval(this.heartbeatInterval);
      this.heartbeatInterval = null;
    }
  }

  /**
   * Start polling for now-playing metadata
   */
  startMetadataPolling() {
    this.stopPolling(); // Clear any existing interval

    // Immediate fetch
    this.fetchNowPlaying();

    // Poll every 30 seconds
    this.pollingInterval = setInterval(() => {
      this.fetchNowPlaying();
    }, 30000);
  }

  /**
   * Stop metadata polling
   */
  stopPolling() {
    if (this.pollingInterval) {
      clearInterval(this.pollingInterval);
      this.pollingInterval = null;
    }
  }

  /**
   * Fetch now-playing metadata
   */
  async fetchNowPlaying() {
    try {
      const response = await fetch(
        `${this.apiBaseUrl}/stations/${this.stationId}/now-playing`
      );

      const data = await response.json();
      if (data.success && data.data) {
        this.metadata = data.data;
        this.emit('metadata', data.data);
        this.updateMediaSession(data.data);
      }
    } catch (error) {
      console.error('[StationPlayer] Metadata fetch error:', error);
    }
  }

  /**
   * Update Media Session API metadata
   */
  updateMediaSession(metadata) {
    if (!('mediaSession' in navigator)) return;

    try {
      navigator.mediaSession.metadata = new MediaMetadata({
        title: metadata.title || 'Unknown Track',
        artist: metadata.artist || 'Unknown Artist',
        album: metadata.album || 'Station Stream',
        artwork: metadata.artwork
          ? [{ src: metadata.artwork, sizes: '512x512', type: 'image/jpeg' }]
          : []
      });
    } catch (e) {
      console.warn('[StationPlayer] MediaSession update failed:', e);
    }
  }

  /**
   * Attempt to reconnect on error
   */
  attemptReconnect() {
    console.log('[StationPlayer] Attempting reconnect in 5 seconds...');
    setTimeout(() => {
      if (!this.state.isPlaying) {
        this.play().catch(err => {
          console.error('[StationPlayer] Reconnect failed:', err);
        });
      }
    }, 5000);
  }

  /**
   * Event emitter methods
   */
  on(event, callback) {
    if (!this.listeners[event]) {
      this.listeners[event] = [];
    }
    this.listeners[event].push(callback);
  }

  emit(event, data) {
    if (!this.listeners[event]) return;
    this.listeners[event].forEach(callback => {
      try {
        callback(data);
      } catch (e) {
        console.error(`[StationPlayer] Error in ${event} listener:`, e);
      }
    });
  }

  /**
   * Cleanup
   */
  destroy() {
    this.pause();
    this.stopPolling();
    this.stopHeartbeat();
    this.audio.src = '';
    this.listeners = {};
  }
}
