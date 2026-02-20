/**
 * ShredderMixer - 4-Channel Stem Mixer for NGN Shredder UI
 * 
 * Handles multi-stream audio synchronization and mixing.
 * Bible Ref: Chapter 49 (Shredder Node)
 */

export class ShredderMixer {
  constructor(audioContext) {
    this.ctx = audioContext || new (window.AudioContext || window.webkitAudioContext)();
    
    // Stem nodes
    this.stems = {
      vocals: { url: null, element: null, source: null, gain: null, panner: null, status: 'idle' },
      drums:  { url: null, element: null, source: null, gain: null, panner: null, status: 'idle' },
      bass:   { url: null, element: null, source: null, gain: null, panner: null, status: 'idle' },
      other:  { url: null, element: null, source: null, gain: null, panner: null, status: 'idle' }
    };

    this.masterGain = this.ctx.createGain();
    this.masterGain.connect(this.ctx.destination);
    
    this.isInitialized = false;
    this.isPlaying = false;
  }

  /**
   * Load stem URLs into the mixer
   */
  async loadStems(urls) {
    console.log('[ShredderMixer] Loading stems:', urls);
    
    const loadPromises = Object.keys(this.stems).map(async (key) => {
      if (!urls[key]) return;

      const stem = this.stems[key];
      stem.url = urls[key];
      
      // Create HTML5 audio element for streaming (better than full buffer for long tracks)
      const audio = new Audio();
      audio.crossOrigin = 'anonymous';
      audio.src = stem.url;
      audio.load();

      stem.element = audio;
      stem.source = this.ctx.createMediaElementSource(audio);
      stem.gain = this.ctx.createGain();
      
      stem.source.connect(stem.gain);
      stem.gain.connect(this.masterGain);
      
      stem.status = 'ready';
    });

    await Promise.all(loadPromises);
    this.isInitialized = true;
  }

  /**
   * Synchronized Play
   */
  async play() {
    if (this.ctx.state === 'suspended') {
      await this.ctx.resume();
    }

    Object.values(this.stems).forEach(s => {
      if (s.element) s.element.play();
    });
    
    this.isPlaying = true;
  }

  /**
   * Synchronized Pause
   */
  pause() {
    Object.values(this.stems).forEach(s => {
      if (s.element) s.element.pause();
    });
    this.isPlaying = false;
  }

  /**
   * Set volume for a specific stem (0-1)
   */
  setStemVolume(key, value) {
    if (this.stems[key] && this.stems[key].gain) {
      this.stems[key].gain.gain.setTargetAtTime(value, this.ctx.currentTime, 0.05);
    }
  }

  /**
   * Sync all stems to a specific timestamp
   */
  seek(seconds) {
    Object.values(this.stems).forEach(s => {
      if (s.element) s.element.currentTime = seconds;
    });
  }

  /**
   * Get current playback time (from first valid stem)
   */
  getCurrentTime() {
    const first = Object.values(this.stems).find(s => s.element);
    return first ? first.element.currentTime : 0;
  }

  /**
   * Destroy and cleanup context
   */
  destroy() {
    this.pause();
    Object.values(this.stems).forEach(s => {
      if (s.element) {
        s.element.src = '';
        s.element = null;
      }
    });
    this.ctx.close();
  }
}
