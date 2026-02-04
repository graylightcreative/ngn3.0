/**
 * Playback Tracker - Qualified Listen Event Integration
 *
 * Listens to AudioPlayer 'qualified_listen' event and POSTs to:
 * POST /api/v1/playback/events
 *
 * Features:
 * - Automatic qualified listen tracking at 30 seconds
 * - Exponential backoff retry logic (3 attempts)
 * - Session-based deduplication
 * - Error logging and handling
 */

export class PlaybackTracker {
  constructor(player, apiBaseUrl = '/api/v1') {
    this.player = player;
    this.apiBaseUrl = apiBaseUrl;
    this.sessionId = this.generateSessionId();
    this.retryAttempts = new Map(); // trackId -> attempt count

    this.setupListeners();
  }

  /**
   * Generate unique session ID (UUID v4 format)
   * Used for deduplication and tracking across page loads
   */
  generateSessionId() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
      const r = Math.random() * 16 | 0;
      const v = c === 'x' ? r : (r & 0x3 | 0x8);
      return v.toString(16);
    });
  }

  /**
   * Setup player event listeners
   * Listen for 'qualified_listen' event emitted by AudioPlayer at 30 seconds
   */
  setupListeners() {
    this.player.on('qualified_listen', (data) => {
      this.trackQualifiedListen(data);
    });

    console.log('[PlaybackTracker] Initialized with session:', this.sessionId);
  }

  /**
   * Track qualified listen event
   * Called when player emits 'qualified_listen' at 30+ seconds
   *
   * @param {Object} data - Event data from player
   * @param {Object} data.track - Track metadata (id, title, artist_name, album_name)
   * @param {number} data.playbackTime - Current playback time in seconds
   * @param {string} data.eventTime - ISO timestamp when event fired
   */
  async trackQualifiedListen(data) {
    const { track, playbackTime, eventTime } = data;

    // Validate track data
    if (!track || !track.id) {
      console.warn('[PlaybackTracker] Invalid track data:', data);
      return;
    }

    // Build payload for API
    const payload = {
      track_id: track.id,
      session_id: this.sessionId,
      duration_seconds: Math.floor(playbackTime),
      source_type: 'on_demand',
      timestamp: eventTime || new Date().toISOString(),
      metadata: {
        title: track.title,
        artist: track.artist_name,
        album: track.album_name
      }
    };

    console.log('[PlaybackTracker] Sending qualified listen:', payload);

    try {
      await this.sendEvent(payload, track.id);
      this.retryAttempts.delete(track.id);
    } catch (error) {
      console.error('[PlaybackTracker] Failed to track event:', error);
      this.retryEvent(payload, track.id);
    }
  }

  /**
   * Send event to API
   * POST /api/v1/playback/events
   *
   * @param {Object} payload - Event data
   * @param {number} trackId - Track ID (for retry tracking)
   * @returns {Promise<Object>} API response
   * @throws {Error} If request fails
   */
  async sendEvent(payload, trackId) {
    const response = await fetch(`${this.apiBaseUrl}/playback/events`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      credentials: 'same-origin', // Include session cookies for authenticated users
      body: JSON.stringify(payload)
    });

    if (!response.ok) {
      const error = await response.json().catch(() => ({ error: 'Unknown error' }));
      throw new Error(error.error || `HTTP ${response.status}`);
    }

    const result = await response.json();
    console.log('[PlaybackTracker] Event recorded:', result);
    return result;
  }

  /**
   * Retry failed event with exponential backoff
   * Retries up to 3 times: 1s, 2s, 4s delays
   *
   * @param {Object} payload - Event data
   * @param {number} trackId - Track ID
   */
  retryEvent(payload, trackId) {
    const attempts = this.retryAttempts.get(trackId) || 0;

    if (attempts >= 3) {
      console.error('[PlaybackTracker] Max retries exceeded for track:', trackId);
      this.retryAttempts.delete(trackId);
      return;
    }

    // Exponential backoff: 1s, 2s, 4s
    const delay = Math.pow(2, attempts) * 1000;
    this.retryAttempts.set(trackId, attempts + 1);

    console.log(`[PlaybackTracker] Retrying in ${delay}ms (attempt ${attempts + 1}/3)`);

    setTimeout(() => {
      this.sendEvent(payload, trackId)
        .then(() => this.retryAttempts.delete(trackId))
        .catch(() => this.retryEvent(payload, trackId));
    }, delay);
  }
}
