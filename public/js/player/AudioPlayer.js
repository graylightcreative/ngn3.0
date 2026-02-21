/**
 * AudioPlayer - Modern ES6 player with queue management
 *
 * Features:
 * - Event-driven architecture (compatible with Media Session API)
 * - Full queue management (add, remove, shuffle, reorder)
 * - State persistence (localStorage)
 * - 30-second qualified listen tracking (for Phase 3 royalty triggers)
 * - HTTP Range support for seeking
 * - Secure streaming via signed tokens
 */

export class AudioPlayer {
  constructor(config = {}) {
    this.config = {
      apiBaseUrl: '/api/v1',
      autoplay: false,
      volume: 0.8,
      ...config
    };

    // Audio element setup
    this.audio = new Audio();
    this.audio.crossOrigin = 'anonymous';

    // Queue management
    this.queue = [];
    this.currentIndex = -1;
    this.currentTrack = null;
    this.qualified30sTriggered = false;

    // Player state
    this.state = {
      isPlaying: false,
      isMuted: false,
      isShuffled: false,
      repeatMode: 'none' // 'none', 'one', 'all'
    };

    // Event emitter (required by media-session.js)
    this.listeners = {}; // { 'event': [callback1, callback2, ...] }

    // Volume setup
    this.audio.volume = this.config.volume;

    // Setup audio event listeners
    this._setupAudioListeners();
    this._setupQualifiedListenTracking();
  }

  /**
   * Event emitter: register listener for event
   */
  on(event, callback) {
    if (!this.listeners[event]) {
      this.listeners[event] = [];
    }
    this.listeners[event].push(callback);
    return this; // Allow chaining
  }

  /**
   * Event emitter: remove listener
   */
  off(event, callback) {
    if (!this.listeners[event]) return;
    this.listeners[event] = this.listeners[event].filter(cb => cb !== callback);
  }

  /**
   * Event emitter: emit event to all listeners
   */
  emit(event, data) {
    if (!this.listeners[event]) return;
    this.listeners[event].forEach(callback => {
      try {
        callback(data);
      } catch (e) {
        console.error(`[AudioPlayer] Error in ${event} listener:`, e);
      }
    });
  }

  /**
   * Setup HTML5 audio element event listeners
   */
  _setupAudioListeners() {
    // Play event
    this.audio.addEventListener('play', () => {
      this.state.isPlaying = true;
      this.emit('play', { track: this.currentTrack });
    });

    // Pause event
    this.audio.addEventListener('pause', () => {
      this.state.isPlaying = false;
      this.emit('pause', { track: this.currentTrack });
    });

    // Time update (for progress bar and qualified listen tracking)
    this.audio.addEventListener('timeupdate', () => {
      this.emit('timeupdate', {
        currentTime: this.audio.currentTime,
        duration: this.audio.duration,
        progress: (this.audio.currentTime / this.audio.duration) * 100
      });
    });

    // Track ended
    this.audio.addEventListener('ended', () => {
      this.qualified30sTriggered = false;
      this.emit('ended', { track: this.currentTrack });
      this._handleTrackEnded();
    });

    // Error
    this.audio.addEventListener('error', (e) => {
      console.error('[AudioPlayer] Audio error:', e);
      this.emit('error', { error: e, track: this.currentTrack });
    });

    // Metadata loaded
    this.audio.addEventListener('loadedmetadata', () => {
      this.emit('loadedmetadata', {
        duration: this.audio.duration,
        track: this.currentTrack
      });
    });
  }

  /**
   * Track 30-second qualified listen (required by Phase 3)
   */
  _setupQualifiedListenTracking() {
    this.audio.addEventListener('timeupdate', () => {
      if (
        !this.qualified30sTriggered &&
        this.audio.currentTime >= 30 &&
        this.currentTrack
      ) {
        this.qualified30sTriggered = true;
        this.emit('qualified_listen', {
          track: this.currentTrack,
          playbackTime: this.audio.currentTime,
          eventTime: new Date().toISOString()
        });
      }
    });
  }

  /**
   * Handle track end (check repeat mode, play next, etc.)
   */
  _handleTrackEnded() {
    if (this.state.repeatMode === 'one') {
      // Repeat current track
      this.audio.currentTime = 0;
      this.play();
    } else if (this.queue.length > 0) {
      // Play next track
      if (this.currentIndex < this.queue.length - 1) {
        this.next();
      } else if (this.state.repeatMode === 'all') {
        // Loop back to start
        this.next();
      }
    }
  }

  /**
   * Load a track and prepare for playback
   */
  async loadTrack(track, autoplay = this.config.autoplay) {
    if (!track) {
      console.error('[AudioPlayer] Invalid track:', track);
      return;
    }

    try {
      // Update current track
      this.currentTrack = track;
      this.qualified30sTriggered = false;

      let streamUrl = track.mp3_url || null;

      // If no direct URL, fetch streaming token
      if (!streamUrl && track.id) {
        const response = await fetch(`${this.config.apiBaseUrl}/tracks/${track.id}/token`);
        if (!response.ok) {
          throw new Error(`Failed to get streaming token: ${response.statusText}`);
        }

        const data = await response.json();
        if (!data.success || !data.data.url) {
          throw new Error('No streaming URL returned');
        }
        streamUrl = data.data.url;
      }

      if (!streamUrl) throw new Error('No valid audio source found');

      // Set audio source
      this.audio.src = streamUrl;

      // Emit trackchange event (required by media-session.js)
      this.emit('trackchange', {
        track: this.currentTrack,
        index: this.currentIndex,
        total: this.queue.length
      });

      // Autoplay if requested
      if (autoplay) {
        this.play();
      }

    } catch (error) {
      console.error('[AudioPlayer] Error loading track:', error);
      this.emit('error', { error, track });
    }
  }

  /**
   * Start playback
   */
  async play() {
    // If no track loaded, load first in queue
    if (!this.currentTrack && this.queue.length > 0) {
      this.currentIndex = 0;
      await this.loadTrack(this.queue[0], true);
      return;
    }

    // Play current audio element
    try {
      await this.audio.play();
    } catch (error) {
      console.error('[AudioPlayer] Error playing:', error);
      this.emit('error', { error });
    }
  }

  /**
   * Pause playback
   */
  pause() {
    this.audio.pause();
  }

  /**
   * Toggle play/pause
   */
  async togglePlay() {
    if (this.state.isPlaying) {
      this.pause();
    } else {
      this.play();
    }
  }

  /**
   * Play next track
   */
  async next() {
    if (this.queue.length === 0) return;

    this.currentIndex = (this.currentIndex + 1) % this.queue.length;
    const nextTrack = this.queue[this.currentIndex];

    if (nextTrack) {
      await this.loadTrack(nextTrack, true);
    }
  }

  /**
   * Play previous track
   */
  async prev() {
    if (this.queue.length === 0) return;

    // If more than 3 seconds into track, restart it
    if (this.audio.currentTime > 3) {
      this.audio.currentTime = 0;
      return;
    }

    // Go to previous track
    this.currentIndex = (this.currentIndex - 1 + this.queue.length) % this.queue.length;
    const prevTrack = this.queue[this.currentIndex];

    if (prevTrack) {
      await this.loadTrack(prevTrack, true);
    }
  }

  /**
   * Seek to specific time
   */
  seek(seconds) {
    this.audio.currentTime = Math.max(0, Math.min(seconds, this.audio.duration || 0));
    this.emit('seek', {
      currentTime: this.audio.currentTime,
      duration: this.audio.duration
    });
  }

  /**
   * Set entire queue
   */
  setQueue(tracks, startIndex = 0) {
    this.queue = Array.isArray(tracks) ? tracks : [];
    this.currentIndex = Math.max(-1, Math.min(startIndex, this.queue.length - 1));

    this.emit('queueupdate', {
      queue: this.queue,
      currentIndex: this.currentIndex,
      total: this.queue.length
    });

    // Save to localStorage
    this._saveQueueState();

    // Load first track if available
    if (this.queue.length > 0 && this.currentIndex >= 0) {
      this.loadTrack(this.queue[this.currentIndex], false);
    }
  }

  /**
   * Add tracks to queue
   */
  addToQueue(tracks, playNow = false) {
    if (!Array.isArray(tracks)) {
      tracks = [tracks];
    }

    const startIndex = this.queue.length;
    this.queue.push(...tracks);

    if (playNow && startIndex >= 0) {
      this.currentIndex = startIndex;
      this.loadTrack(this.queue[this.currentIndex], true);
    }

    this.emit('queueupdate', {
      queue: this.queue,
      currentIndex: this.currentIndex,
      total: this.queue.length
    });

    this._saveQueueState();
  }

  /**
   * Remove track from queue
   */
  removeFromQueue(index) {
    if (index < 0 || index >= this.queue.length) return;

    this.queue.splice(index, 1);

    // Adjust currentIndex if needed
    if (index < this.currentIndex) {
      this.currentIndex--;
    } else if (index === this.currentIndex) {
      if (this.currentIndex >= this.queue.length) {
        this.currentIndex = Math.max(-1, this.queue.length - 1);
      }
      // Load next track if available
      if (this.currentIndex >= 0) {
        this.loadTrack(this.queue[this.currentIndex], this.state.isPlaying);
      }
    }

    this.emit('queueupdate', {
      queue: this.queue,
      currentIndex: this.currentIndex,
      total: this.queue.length
    });

    this._saveQueueState();
  }

  /**
   * Shuffle queue
   */
  shuffleQueue() {
    if (this.queue.length <= 1) return;

    // Keep current track at position 0
    const currentTrack = this.currentTrack;

    // Shuffle the rest
    const shuffled = [currentTrack, ...this.queue.filter(t => t.id !== currentTrack?.id)];
    for (let i = shuffled.length - 1; i > 1; i--) {
      const j = Math.floor(Math.random() * (i - 1)) + 1;
      [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
    }

    this.queue = shuffled;
    this.currentIndex = 0;
    this.state.isShuffled = true;

    this.emit('queueupdate', {
      queue: this.queue,
      currentIndex: this.currentIndex,
      total: this.queue.length
    });

    this._saveQueueState();
  }

  /**
   * Clear queue
   */
  clearQueue() {
    this.pause();
    this.queue = [];
    this.currentIndex = -1;
    this.currentTrack = null;
    this.audio.src = '';

    this.emit('queueupdate', {
      queue: [],
      currentIndex: -1,
      total: 0
    });

    this._saveQueueState();
  }

  /**
   * Set volume (0-1)
   */
  setVolume(level) {
    const volume = Math.max(0, Math.min(1, parseFloat(level)));
    this.audio.volume = volume;
    this.config.volume = volume;

    // Save to localStorage
    localStorage.setItem('ngn_player_volume', volume);

    this.emit('volumechange', { volume });
  }

  /**
   * Toggle mute
   */
  toggleMute() {
    this.state.isMuted = !this.state.isMuted;
    this.audio.muted = this.state.isMuted;
    this.emit('volumechange', { volume: this.audio.volume, muted: this.state.isMuted });
  }

  /**
   * Set repeat mode: 'none', 'one', 'all'
   */
  setRepeatMode(mode = 'none') {
    this.state.repeatMode = mode;
    this.emit('repeatmodechange', { repeatMode: mode });
  }

  /**
   * Get current state
   */
  getState() {
    return {
      ...this.state,
      currentTrack: this.currentTrack,
      currentIndex: this.currentIndex,
      duration: this.audio.duration,
      currentTime: this.audio.currentTime,
      volume: this.audio.volume,
      queue: this.queue
    };
  }

  /**
   * Save queue state to localStorage
   */
  _saveQueueState() {
    try {
      const state = {
        queue: this.queue,
        currentIndex: this.currentIndex,
        timestamp: Date.now()
      };
      localStorage.setItem('ngn_player_queue', JSON.stringify(state));
    } catch (e) {
      console.warn('[AudioPlayer] Failed to save queue state:', e);
    }
  }

  /**
   * Load queue state from localStorage
   */
  loadQueueState() {
    try {
      const data = localStorage.getItem('ngn_player_queue');
      if (!data) return null;

      const state = JSON.parse(data);

      // Expire after 24 hours
      if (Date.now() - state.timestamp > 86400000) {
        localStorage.removeItem('ngn_player_queue');
        return null;
      }

      return state;
    } catch (e) {
      console.warn('[AudioPlayer] Failed to load queue state:', e);
      return null;
    }
  }

  /**
   * Cleanup (for page unload)
   */
  destroy() {
    this.pause();
    this.audio.src = '';
    this.listeners = {};
  }
}
