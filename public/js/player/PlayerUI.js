/**
 * PlayerUI - Player UI controller v3.1.0
 * Pure Sovereign implementation using Bootstrap Icons.
 */

import { ShredderMixer } from './ShredderMixer.js';

export class PlayerUI {
  constructor(player, container) {
    this.player = player;
    this.container = (typeof container === 'string') 
        ? (document.querySelector(container) || document.getElementById(container))
        : container;
    
    // Defer Mixer until needed (Web Audio interaction rules)
    this.mixer = null;

    if (!this.container) {
      console.warn('[PlayerUI] Container not found:', container);
      return;
    }

    try {
        this.render();
        this.attachPlayerListeners();
        this.attachUIListeners();
        console.log('[PlayerUI] Initialized successfully');
    } catch (err) {
        console.error('[PlayerUI] Initialization failed:', err);
    }
  }

  /**
   * Render player HTML
   */
  render() {
    this.container.innerHTML = `
      <div class="ngn-player">
        <!-- Track Info -->
        <div class="ngn-player-track-info">
          <img class="ngn-player-artwork" src="/lib/images/site/2026/default-avatar.png" alt="Album artwork">
          <div class="ngn-player-meta">
            <div class="ngn-player-title truncate font-black">Ready to play</div>
            <div class="ngn-player-artist truncate text-[10px] text-zinc-500 uppercase tracking-widest">NGN Sovereign Player</div>
          </div>
        </div>

        <!-- Main Controls -->
        <div class="ngn-player-controls">
          <button class="ngn-btn ngn-btn-prev" title="Previous track">
            <i class="bi bi-skip-start-fill text-xl"></i>
          </button>
          <button class="ngn-btn ngn-btn-play ngn-btn-primary" title="Play">
            <i class="bi bi-play-fill text-3xl"></i>
          </button>
          <button class="ngn-btn ngn-btn-next" title="Next track">
            <i class="bi bi-skip-end-fill text-xl"></i>
          </button>
        </div>

        <!-- Hidden elements for state compatibility -->
        <div class="hidden">
            <input type="range" class="ngn-player-seek" min="0" max="100" value="0">
            <input type="range" class="ngn-player-volume-slider" min="0" max="100" value="80">
            <span class="ngn-player-time-current">0:00</span>
            <span class="ngn-player-time-total">0:00</span>
            <button class="ngn-btn ngn-btn-shuffle"></button>
            <button class="ngn-btn ngn-btn-repeat" data-mode="none"></button>
            <button class="ngn-btn ngn-btn-mute"></button>
            <button class="ngn-btn ngn-btn-shredder"></button>
        </div>

        <!-- Queue Toggle -->
        <div class="ngn-player-right">
          <button class="ngn-btn ngn-btn-queue" title="Show queue">
            <i class="bi bi-list-ul text-xl"></i>
            <span class="ngn-queue-count text-[9px] font-black absolute -top-1 -right-1 bg-brand text-black w-4 h-4 rounded-full flex items-center justify-center">0</span>
          </button>
        </div>
      </div>

      <!-- Shredder Mixer Panel (Overlay) -->
      <div class="ngn-shredder-panel" style="display: none;" aria-hidden="true">
        <div class="ngn-shredder-header">
          <h3 class="font-black tracking-tighter uppercase text-brand">Shredder_Node // v1.0</h3>
          <button class="ngn-btn ngn-btn-close-shredder">&times;</button>
        </div>
        
        <div class="ngn-shredder-grid">
          <div class="ngn-stem-control" data-stem="vocals">
            <label>Vocals</label>
            <canvas class="ngn-stem-viz" width="100" height="40"></canvas>
            <input type="range" class="ngn-stem-fader" min="0" max="100" value="100">
            <div class="ngn-stem-btns">
              <button class="ngn-stem-mute">M</button>
              <button class="ngn-stem-solo">S</button>
            </div>
          </div>
          <div class="ngn-stem-control" data-stem="drums">
            <label>Drums</label>
            <canvas class="ngn-stem-viz" width="100" height="40"></canvas>
            <input type="range" class="ngn-stem-fader" min="0" max="100" value="100">
            <div class="ngn-stem-btns">
              <button class="ngn-stem-mute">M</button>
              <button class="ngn-stem-solo">S</button>
            </div>
          </div>
          <div class="ngn-stem-control" data-stem="bass">
            <label>Bass</label>
            <canvas class="ngn-stem-viz" width="100" height="40"></canvas>
            <input type="range" class="ngn-stem-fader" min="0" max="100" value="100">
            <div class="ngn-stem-btns">
              <button class="ngn-stem-mute">M</button>
              <button class="ngn-stem-solo">S</button>
            </div>
          </div>
          <div class="ngn-stem-control" data-stem="other">
            <label>Other</label>
            <canvas class="ngn-stem-viz" width="100" height="40"></canvas>
            <input type="range" class="ngn-stem-fader" min="0" max="100" value="100">
            <div class="ngn-stem-btns">
              <button class="ngn-stem-mute">M</button>
              <button class="ngn-stem-solo">S</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Queue Panel -->
      <div class="ngn-player-queue-panel" style="display: none;">
        <div class="ngn-queue-header">
          <h3>Up Next</h3>
          <button class="ngn-btn ngn-btn-close-queue">&times;</button>
        </div>
        <div class="ngn-queue-items"></div>
      </div>
    `;

    this.cacheElements();
  }

  /**
   * Cache DOM element references with safety
   */
  cacheElements() {
    const q = (sel) => this.container.querySelector(sel);
    
    this.player_element = q('.ngn-player');
    this.artwork = q('.ngn-player-artwork');
    this.title = q('.ngn-player-title');
    this.artist = q('.ngn-player-artist');
    this.btnPlay = q('.ngn-btn-play');
    this.btnPrev = q('.ngn-btn-prev');
    this.btnNext = q('.ngn-btn-next');
    this.btnShuffle = q('.ngn-btn-shuffle');
    this.btnRepeat = q('.ngn-btn-repeat');
    this.btnMute = q('.ngn-btn-mute');
    this.btnQueue = q('.ngn-btn-queue');
    this.seekBar = q('.ngn-player-seek');
    this.volumeSlider = q('.ngn-player-volume-slider');
    this.timeCurrent = q('.ngn-player-time-current');
    this.timeTotal = q('.ngn-player-time-total');
    this.queuePanel = q('.ngn-player-queue-panel');
    this.queueItems = q('.ngn-queue-items');
    this.queueCount = q('.ngn-queue-count');
    this.btnCloseQueue = q('.ngn-btn-close-queue');

    // Shredder Elements
    this.btnShredder = q('.ngn-btn-shredder');
    this.shredderPanel = q('.ngn-shredder-panel');
    this.btnCloseShredder = q('.ngn-btn-close-shredder');
    this.stemFaders = this.container.querySelectorAll('.ngn-stem-fader');
    this.stemCanvases = this.container.querySelectorAll('.ngn-stem-viz');
  }

  /**
   * Attach player event listeners
   */
  attachPlayerListeners() {
    this.player.on('trackchange', (data) => this.updateTrackInfo(data));
    this.player.on('play', () => this.updatePlayButton(true));
    this.player.on('pause', () => this.updatePlayButton(false));
    this.player.on('timeupdate', (data) => this.updateProgress(data));
    this.player.on('volumechange', (data) => this.updateVolumeDisplay(data));
    this.player.on('queueupdate', (data) => this.updateQueue(data));
    this.player.on('error', (data) => console.error('[PlayerUI] Error:', data));
  }

  /**
   * Attach UI event listeners
   */
  attachUIListeners() {
    if (this.btnPlay) this.btnPlay.addEventListener('click', () => this.player.togglePlay());
    if (this.btnPrev) this.btnPrev.addEventListener('click', () => this.player.prev());
    if (this.btnNext) this.btnNext.addEventListener('click', () => this.player.next());

    if (this.btnQueue) {
        this.btnQueue.addEventListener('click', () => {
            this.queuePanel.style.display = this.queuePanel.style.display === 'none' ? 'block' : 'none';
        });
    }

    if (this.btnCloseQueue) {
        this.btnCloseQueue.addEventListener('click', () => {
            this.queuePanel.style.display = 'none';
        });
    }

    // Global track triggers
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-play-track]');
      if (btn) {
        e.preventDefault();
        const track = {
          id: btn.dataset.trackId || 0,
          title: btn.dataset.trackTitle || 'Unknown Track',
          artist_name: btn.dataset.trackArtist || 'Unknown Artist',
          cover_md: btn.dataset.trackArt || null,
          mp3_url: btn.dataset.trackUrl
        };
        this.player.loadTrack(track, true);
      }
    });
  }

  updateTrackInfo(data) {
    const track = data.track;
    if (!track || !this.title) return;
    this.title.textContent = track.title || 'Unknown Track';
    this.artist.textContent = track.artist_name || 'Unknown Artist';
    this.artwork.src = track.cover_md || '/lib/images/site/2026/default-avatar.png';
  }

  updatePlayButton(isPlaying) {
    if (!this.btnPlay) return;
    this.btnPlay.innerHTML = isPlaying 
        ? '<i class="bi bi-pause-fill text-3xl"></i>' 
        : '<i class="bi bi-play-fill text-3xl"></i>';
  }

  updateProgress(data) {
    if (!this.seekBar) return;
    this.seekBar.value = data.progress || 0;
  }

  updateVolumeDisplay(data) {
    if (!this.volumeSlider) return;
    this.volumeSlider.value = (data.volume * 100) || 0;
  }

  updateQueue(data) {
    if (!this.queueCount) return;
    this.queueCount.textContent = data.queue.length;
  }

  formatTime(seconds) {
    if (isNaN(seconds)) return '0:00';
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  }
}
