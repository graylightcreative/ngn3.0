/**
 * Queue - Standalone queue management logic
 *
 * Handles:
 * - Track addition/removal
 * - Reordering (drag and drop)
 * - Shuffling (Fisher-Yates algorithm)
 * - Navigation (next, prev, jump to index)
 */

export class Queue {
  constructor() {
    this.tracks = [];
    this.currentIndex = -1;
    this.history = []; // For "prev" button intelligence
  }

  /**
   * Add tracks to queue
   * @param {Array|Object} tracks - Single track or array of tracks
   * @param {string} position - 'end' (default), 'next', or numeric index
   */
  add(tracks, position = 'end') {
    if (!Array.isArray(tracks)) {
      tracks = [tracks];
    }

    if (position === 'end') {
      this.tracks.push(...tracks);
    } else if (position === 'next' && this.currentIndex >= 0) {
      this.tracks.splice(this.currentIndex + 1, 0, ...tracks);
    } else if (typeof position === 'number') {
      this.tracks.splice(position, 0, ...tracks);
    }

    return this;
  }

  /**
   * Remove track at index
   */
  remove(index) {
    if (index < 0 || index >= this.tracks.length) return this;

    this.tracks.splice(index, 1);

    // Adjust currentIndex
    if (index < this.currentIndex) {
      this.currentIndex--;
    } else if (index === this.currentIndex && this.currentIndex >= this.tracks.length) {
      this.currentIndex = Math.max(-1, this.tracks.length - 1);
    }

    return this;
  }

  /**
   * Move track from one position to another
   * @param {number} fromIndex - Current index
   * @param {number} toIndex - New index
   */
  move(fromIndex, toIndex) {
    if (fromIndex < 0 || fromIndex >= this.tracks.length) return this;
    if (toIndex < 0 || toIndex >= this.tracks.length) return this;

    const [track] = this.tracks.splice(fromIndex, 1);
    this.tracks.splice(toIndex, 0, track);

    // Update currentIndex if it was moved
    if (this.currentIndex === fromIndex) {
      this.currentIndex = toIndex;
    } else if (fromIndex < this.currentIndex && toIndex >= this.currentIndex) {
      this.currentIndex--;
    } else if (fromIndex > this.currentIndex && toIndex <= this.currentIndex) {
      this.currentIndex++;
    }

    return this;
  }

  /**
   * Shuffle queue using Fisher-Yates algorithm
   */
  shuffle() {
    if (this.tracks.length <= 1) return this;

    // Keep current track at position 0
    const currentTrack = this.getCurrent();
    const shuffled = currentTrack
      ? [currentTrack, ...this.tracks.filter(t => t.id !== currentTrack.id)]
      : [...this.tracks];

    // Fisher-Yates shuffle for remaining tracks
    for (let i = shuffled.length - 1; i > 1; i--) {
      const j = Math.floor(Math.random() * (i - 1)) + 1;
      [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
    }

    this.tracks = shuffled;
    this.currentIndex = currentTrack ? 0 : -1;

    return this;
  }

  /**
   * Clear entire queue
   */
  clear() {
    this.tracks = [];
    this.currentIndex = -1;
    this.history = [];
    return this;
  }

  /**
   * Get current track
   */
  getCurrent() {
    return this.currentIndex >= 0 ? this.tracks[this.currentIndex] : null;
  }

  /**
   * Get next track without advancing
   */
  getNext() {
    const nextIndex = this.currentIndex + 1;
    return nextIndex < this.tracks.length ? this.tracks[nextIndex] : null;
  }

  /**
   * Get previous track without advancing
   */
  getPrev() {
    const prevIndex = this.currentIndex - 1;
    return prevIndex >= 0 ? this.tracks[prevIndex] : null;
  }

  /**
   * Jump to specific index
   */
  jumpTo(index) {
    if (index < 0 || index >= this.tracks.length) return null;
    this.currentIndex = index;
    this.history.push(index);
    return this.getCurrent();
  }

  /**
   * Get track at index
   */
  getAt(index) {
    return this.tracks[index] || null;
  }

  /**
   * Get all tracks
   */
  getAll() {
    return [...this.tracks]; // Return copy to prevent external modification
  }

  /**
   * Get queue length
   */
  getLength() {
    return this.tracks.length;
  }

  /**
   * Get current index
   */
  getCurrentIndex() {
    return this.currentIndex;
  }

  /**
   * Find track index by ID
   */
  findByTrackId(trackId) {
    return this.tracks.findIndex(t => t.id === trackId);
  }

  /**
   * Check if queue has next track
   */
  hasNext() {
    return this.currentIndex + 1 < this.tracks.length;
  }

  /**
   * Check if queue has previous track
   */
  hasPrev() {
    return this.currentIndex > 0;
  }

  /**
   * Get queue info
   */
  getInfo() {
    return {
      total: this.tracks.length,
      currentIndex: this.currentIndex,
      currentTrack: this.getCurrent(),
      hasNext: this.hasNext(),
      hasPrev: this.hasPrev()
    };
  }
}
