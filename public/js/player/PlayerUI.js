/**
 * PlayerUI - Player UI controller
 *
 * Renders and manages the player interface:
 * - Track info display
 * - Playback controls
 * - Progress bar with seek
 * - Volume control
 * - Queue display
 */

export class PlayerUI {
  constructor(player, container) {
    this.player = player;
    this.container = document.querySelector(container) || document.getElementById(container);

    if (!this.container) {
      console.warn('[PlayerUI] Container not found:', container);
      return;
    }

    this.render();
    this.attachPlayerListeners();
    this.attachUIListeners();

    console.log('[PlayerUI] Initialized');
  }

  /**
   * Render player HTML
   */
  render() {
    this.container.innerHTML = `
      <div class="ngn-player">
        <!-- Track Info -->
        <div class="ngn-player-track-info">
          <img class="ngn-player-artwork" src="/lib/images/default-album-art.jpg" alt="Album artwork">
          <div class="ngn-player-meta">
            <div class="ngn-player-title">Ready to play</div>
            <div class="ngn-player-artist">NGN Player</div>
          </div>
        </div>

        <!-- Main Controls -->
        <div class="ngn-player-controls">
          <button class="ngn-btn ngn-btn-shuffle" title="Shuffle queue">
            <i class="fa fa-random"></i>
          </button>
          <button class="ngn-btn ngn-btn-prev" title="Previous track">
            <i class="fa fa-step-backward"></i>
          </button>
          <button class="ngn-btn ngn-btn-play ngn-btn-primary" title="Play">
            <i class="fa fa-play"></i>
          </button>
          <button class="ngn-btn ngn-btn-next" title="Next track">
            <i class="fa fa-step-forward"></i>
          </button>
          <button class="ngn-btn ngn-btn-repeat" data-mode="none" title="Repeat mode">
            <i class="fa fa-repeat"></i>
          </button>
        </div>

        <!-- Progress Bar -->
        <div class="ngn-player-progress">
          <span class="ngn-player-time-current">0:00</span>
          <input type="range" class="ngn-player-seek" min="0" max="100" value="0" title="Seek track">
          <span class="ngn-player-time-total">0:00</span>
        </div>

        <!-- Volume Control -->
        <div class="ngn-player-volume">
          <button class="ngn-btn ngn-btn-mute" title="Mute">
            <i class="fa fa-volume-up"></i>
          </button>
          <input type="range" class="ngn-player-volume-slider" min="0" max="100" value="80" title="Volume">
        </div>

        <!-- Queue Toggle -->
        <div class="ngn-player-right">
          <button class="ngn-btn ngn-btn-queue" title="Show queue">
            <i class="fa fa-list"></i>
            <span class="ngn-queue-count">0</span>
          </button>
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
   * Cache DOM element references
   */
  cacheElements() {
    this.player_element = this.container.querySelector('.ngn-player');
    this.artwork = this.container.querySelector('.ngn-player-artwork');
    this.title = this.container.querySelector('.ngn-player-title');
    this.artist = this.container.querySelector('.ngn-player-artist');
    this.btnPlay = this.container.querySelector('.ngn-btn-play');
    this.btnPrev = this.container.querySelector('.ngn-btn-prev');
    this.btnNext = this.container.querySelector('.ngn-btn-next');
    this.btnShuffle = this.container.querySelector('.ngn-btn-shuffle');
    this.btnRepeat = this.container.querySelector('.ngn-btn-repeat');
    this.btnMute = this.container.querySelector('.ngn-btn-mute');
    this.btnQueue = this.container.querySelector('.ngn-btn-queue');
    this.seekBar = this.container.querySelector('.ngn-player-seek');
    this.volumeSlider = this.container.querySelector('.ngn-player-volume-slider');
    this.timeCurrent = this.container.querySelector('.ngn-player-time-current');
    this.timeTotal = this.container.querySelector('.ngn-player-time-total');
    this.queuePanel = this.container.querySelector('.ngn-player-queue-panel');
    this.queueItems = this.container.querySelector('.ngn-queue-items');
    this.queueCount = this.container.querySelector('.ngn-queue-count');
    this.btnCloseQueue = this.container.querySelector('.ngn-btn-close-queue');
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
    this.player.on('ended', () => {});
    this.player.on('error', (data) => this.showError(data));
  }

  /**
   * Attach UI event listeners
   */
  attachUIListeners() {
    // Play button
    this.btnPlay.addEventListener('click', () => this.player.togglePlay());

    // Previous button
    this.btnPrev.addEventListener('click', () => this.player.prev());

    // Next button
    this.btnNext.addEventListener('click', () => this.player.next());

    // Shuffle button
    this.btnShuffle.addEventListener('click', () => {
      this.player.shuffleQueue();
      this.btnShuffle.classList.toggle('active');
    });

    // Repeat button
    this.btnRepeat.addEventListener('click', () => {
      const modes = ['none', 'one', 'all'];
      const current = this.btnRepeat.dataset.mode || 'none';
      const nextMode = modes[(modes.indexOf(current) + 1) % modes.length];
      this.player.setRepeatMode(nextMode);
      this.btnRepeat.dataset.mode = nextMode;
      this.btnRepeat.classList.toggle('active', nextMode !== 'none');
      this.btnRepeat.classList.toggle('repeat-one', nextMode === 'one');
    });

    // Seek bar
    this.seekBar.addEventListener('input', (e) => {
      const duration = this.player.audio.duration;
      const position = (e.target.value / 100) * duration;
      this.player.seek(position);
    });

    // Mute button
    this.btnMute.addEventListener('click', () => {
      this.player.toggleMute();
      this.btnMute.classList.toggle('muted');
    });

    // Volume slider
    this.volumeSlider.addEventListener('input', (e) => {
      const volume = e.target.value / 100;
      this.player.setVolume(volume);
    });

    // Queue button
    this.btnQueue.addEventListener('click', () => {
      this.queuePanel.style.display = this.queuePanel.style.display === 'none' ? 'block' : 'none';
    });

    // Close queue panel
    this.btnCloseQueue.addEventListener('click', () => {
      this.queuePanel.style.display = 'none';
    });

    // Prevent seeking while loading
    this.seekBar.addEventListener('mousedown', (e) => {
      e.preventDefault();
    });
  }

  /**
   * Update track info display
   */
  updateTrackInfo(data) {
    const track = data.track;
    if (!track) return;

    this.title.textContent = track.title || 'Unknown Track';
    this.artist.textContent = track.artist_name || 'Unknown Artist';

    // Update artwork
    const artwork = track.cover_md || track.cover_lg || '/lib/images/default-album-art.jpg';
    this.artwork.src = artwork;
    this.artwork.alt = `${track.title} by ${track.artist_name}`;
  }

  /**
   * Update play button state
   */
  updatePlayButton(isPlaying) {
    if (isPlaying) {
      this.btnPlay.innerHTML = '<i class="fa fa-pause"></i>';
      this.btnPlay.title = 'Pause';
      this.player_element.classList.add('playing');
    } else {
      this.btnPlay.innerHTML = '<i class="fa fa-play"></i>';
      this.btnPlay.title = 'Play';
      this.player_element.classList.remove('playing');
    }
  }

  /**
   * Update progress bar and time display
   */
  updateProgress(data) {
    const { currentTime, duration, progress } = data;

    // Update seek bar
    this.seekBar.value = progress || 0;

    // Update time displays
    this.timeCurrent.textContent = this.formatTime(currentTime);
    this.timeTotal.textContent = this.formatTime(duration);
  }

  /**
   * Update volume display
   */
  updateVolumeDisplay(data) {
    const { volume, muted } = data;
    this.volumeSlider.value = (volume * 100) || 0;

    // Update mute button
    if (muted) {
      this.btnMute.innerHTML = '<i class="fa fa-volume-off"></i>';
      this.btnMute.classList.add('muted');
    } else {
      this.btnMute.innerHTML = '<i class="fa fa-volume-up"></i>';
      this.btnMute.classList.remove('muted');
    }
  }

  /**
   * Update queue display
   */
  updateQueue(data) {
    const { queue, currentIndex, total } = data;

    // Update queue count
    this.queueCount.textContent = total;

    // Update queue items
    this.queueItems.innerHTML = '';

    if (queue.length === 0) {
      this.queueItems.innerHTML = '<div class="ngn-queue-empty">Queue is empty</div>';
      return;
    }

    queue.forEach((track, index) => {
      const isCurrentClass = index === currentIndex ? 'current' : '';
      const itemHTML = `
        <div class="ngn-queue-item ${isCurrentClass}" data-index="${index}">
          <span class="ngn-queue-item-index">${index + 1}</span>
          <div class="ngn-queue-item-info">
            <div class="ngn-queue-item-title">${track.title || 'Unknown'}</div>
            <div class="ngn-queue-item-artist">${track.artist_name || 'Unknown'}</div>
          </div>
          <button class="ngn-btn ngn-btn-remove-queue" data-index="${index}">
            <i class="fa fa-times"></i>
          </button>
        </div>
      `;
      this.queueItems.innerHTML += itemHTML;
    });

    // Attach remove buttons
    this.queueItems.querySelectorAll('.ngn-btn-remove-queue').forEach(btn => {
      btn.addEventListener('click', (e) => {
        const index = parseInt(e.target.closest('button').dataset.index);
        this.player.removeFromQueue(index);
      });
    });
  }

  /**
   * Show error message
   */
  showError(data) {
    console.error('[PlayerUI] Error:', data.error);
    this.title.textContent = 'Error';
    this.artist.textContent = 'Failed to load track';
    this.artist.style.color = '#ff4444';

    setTimeout(() => {
      this.artist.style.color = '';
    }, 3000);
  }

  /**
   * Format time in MM:SS
   */
  formatTime(seconds) {
    if (!seconds || isNaN(seconds)) return '0:00';

    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs < 10 ? '0' : ''}${secs}`;
  }

  /**
   * Destroy UI
   */
  destroy() {
    this.container.innerHTML = '';
  }
}
