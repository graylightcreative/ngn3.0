/**
 * PlayerStorage - LocalStorage wrapper for player state
 *
 * Handles:
 * - Volume persistence
 * - Queue persistence (with 24-hour expiry)
 * - Playback position persistence
 * - State restoration
 */

export class PlayerStorage {
  static KEYS = {
    VOLUME: 'ngn_player_volume',
    QUEUE: 'ngn_player_queue',
    POSITION: 'ngn_player_position',
    STATE: 'ngn_player_state',
    MUTED: 'ngn_player_muted'
  };

  /**
   * Save volume to localStorage
   */
  static saveVolume(volume) {
    try {
      localStorage.setItem(this.KEYS.VOLUME, parseFloat(volume));
    } catch (e) {
      console.warn('[PlayerStorage] Failed to save volume:', e);
    }
  }

  /**
   * Load volume from localStorage
   */
  static loadVolume() {
    try {
      const volume = localStorage.getItem(this.KEYS.VOLUME);
      return volume ? parseFloat(volume) : 0.8;
    } catch (e) {
      console.warn('[PlayerStorage] Failed to load volume:', e);
      return 0.8;
    }
  }

  /**
   * Save queue to localStorage
   * @param {Array} queue - Array of track objects
   * @param {number} currentIndex - Current playing track index
   */
  static saveQueue(queue, currentIndex) {
    try {
      const state = {
        queue: queue || [],
        currentIndex: currentIndex || 0,
        timestamp: Date.now()
      };
      localStorage.setItem(this.KEYS.QUEUE, JSON.stringify(state));
    } catch (e) {
      console.warn('[PlayerStorage] Failed to save queue:', e);
    }
  }

  /**
   * Load queue from localStorage
   * Returns null if queue is expired (>24 hours old) or invalid
   */
  static loadQueue() {
    try {
      const data = localStorage.getItem(this.KEYS.QUEUE);
      if (!data) return null;

      const state = JSON.parse(data);

      // Validate structure
      if (!state.queue || !Array.isArray(state.queue)) {
        return null;
      }

      // Check expiry (24 hours = 86400000 milliseconds)
      if (Date.now() - state.timestamp > 86400000) {
        // Expired, remove it
        this.clearQueue();
        return null;
      }

      return state;
    } catch (e) {
      console.warn('[PlayerStorage] Failed to load queue:', e);
      return null;
    }
  }

  /**
   * Save playback position for a track
   */
  static savePosition(trackId, position) {
    try {
      const positions = this._loadPositions();
      positions[trackId] = {
        position,
        timestamp: Date.now()
      };
      localStorage.setItem(this.KEYS.POSITION, JSON.stringify(positions));
    } catch (e) {
      console.warn('[PlayerStorage] Failed to save position:', e);
    }
  }

  /**
   * Load playback position for a track
   */
  static loadPosition(trackId) {
    try {
      const positions = this._loadPositions();
      const data = positions[trackId];

      if (!data) return null;

      // Only restore if within last 7 days
      if (Date.now() - data.timestamp > 604800000) {
        delete positions[trackId];
        localStorage.setItem(this.KEYS.POSITION, JSON.stringify(positions));
        return null;
      }

      return data.position;
    } catch (e) {
      console.warn('[PlayerStorage] Failed to load position:', e);
      return null;
    }
  }

  /**
   * Save muted state
   */
  static saveMutedState(isMuted) {
    try {
      localStorage.setItem(this.KEYS.MUTED, isMuted ? '1' : '0');
    } catch (e) {
      console.warn('[PlayerStorage] Failed to save muted state:', e);
    }
  }

  /**
   * Load muted state
   */
  static loadMutedState() {
    try {
      const value = localStorage.getItem(this.KEYS.MUTED);
      return value === '1';
    } catch (e) {
      console.warn('[PlayerStorage] Failed to load muted state:', e);
      return false;
    }
  }

  /**
   * Clear queue from localStorage
   */
  static clearQueue() {
    try {
      localStorage.removeItem(this.KEYS.QUEUE);
    } catch (e) {
      console.warn('[PlayerStorage] Failed to clear queue:', e);
    }
  }

  /**
   * Clear all player data
   */
  static clearAll() {
    try {
      Object.values(this.KEYS).forEach(key => {
        localStorage.removeItem(key);
      });
    } catch (e) {
      console.warn('[PlayerStorage] Failed to clear all:', e);
    }
  }

  /**
   * Internal: Load all positions
   */
  static _loadPositions() {
    try {
      const data = localStorage.getItem(this.KEYS.POSITION);
      return data ? JSON.parse(data) : {};
    } catch (e) {
      return {};
    }
  }

  /**
   * Get storage usage info
   */
  static getStorageInfo() {
    try {
      return {
        volume: this.loadVolume(),
        hasQueue: !!localStorage.getItem(this.KEYS.QUEUE),
        hasMutedState: !!localStorage.getItem(this.KEYS.MUTED),
        queueTimestamp: this.loadQueue()?.timestamp
      };
    } catch (e) {
      console.warn('[PlayerStorage] Failed to get info:', e);
      return null;
    }
  }
}
