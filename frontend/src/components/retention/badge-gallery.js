/**
 * Badge Gallery Component
 *
 * Displays user's unlocked badges and progress towards new badges
 * Shows "First!" badges with clout values
 * Displays progress bars for near-unlock badges
 */

export class BadgeGallery {
  constructor(containerId, userId) {
    this.containerId = containerId;
    this.userId = userId;
    this.container = document.getElementById(containerId);
    this.badgeData = null;
  }

  /**
   * Initialize the component
   */
  async init() {
    try {
      await this.fetchBadgeData();
      this.render();
      return this;
    } catch (error) {
      console.error('Error initializing badge gallery:', error);
      this.renderError();
    }
  }

  /**
   * Fetch badge data from API
   */
  async fetchBadgeData() {
    const response = await fetch('/api/v1/retention/badges');
    if (!response.ok) {
      throw new Error('Failed to fetch badge data');
    }
    this.badgeData = await response.json();
  }

  /**
   * Render the component
   */
  render() {
    if (!this.badgeData) return;

    const { user_badges, progress, total_badges } = this.badgeData;

    let html = `
      <div class="badge-gallery">
        <div class="gallery-header">
          <h3>Your Badges</h3>
          <span class="badge-count">${total_badges}</span>
        </div>

        <div class="badges-grid">
    `;

    // Render unlocked badges
    if (user_badges && user_badges.length > 0) {
      user_badges.forEach(badge => {
        html += this.renderBadge(badge, 'unlocked');
      });
    } else {
      html += '<p class="no-badges">Badges you unlock will appear here</p>';
    }

    html += `</div>`;

    // Render progress section
    if (progress && progress.length > 0) {
      html += `
        <div class="badge-progress-section">
          <h4>On Your Way</h4>
          <div class="progress-list">
      `;

      progress.forEach(badge => {
        html += this.renderProgressBadge(badge);
      });

      html += `
          </div>
        </div>
      `;
    }

    html += `</div>`;

    this.container.innerHTML = html;

    // Attach hover listeners for tooltips
    this.attachTooltips();
  }

  /**
   * Render a single unlocked badge
   */
  renderBadge(badge, state = 'unlocked') {
    const isFirstBadge = badge.clout_value && badge.clout_value > 0;

    let html = `
      <div class="badge-item ${state}" data-badge-key="${badge.badge_key}">
        <div class="badge-icon">
    `;

    // Show icon or placeholder
    if (badge.badge_icon_url) {
      html += `<img src="${badge.badge_icon_url}" alt="${badge.badge_name}">`;
    } else {
      html += `<span class="badge-emoji">${this.getBadgeEmoji(badge.badge_key)}</span>`;
    }

    if (isFirstBadge) {
      html += `<span class="first-badge-marker">🥇</span>`;
    }

    html += `</div>`;

    // Rarity indicator
    const rarityClass = badge.rarity || 'common';
    html += `<div class="rarity rarity-${rarityClass}"></div>`;

    // Tooltip
    html += `
      <div class="badge-tooltip">
        <div class="tooltip-name">${badge.badge_name}</div>
        ${badge.badge_description ? `<div class="tooltip-desc">${badge.badge_description}</div>` : ''}
        ${isFirstBadge ? `<div class="clout-value">Clout: ${badge.clout_value.toFixed(1)}%</div>` : ''}
        <div class="unlock-date">${this.formatDate(badge.unlocked_at)}</div>
      </div>
    `;

    html += `</div>`;

    return html;
  }

  /**
   * Render a badge in progress
   */
  renderProgressBadge(badge) {
    const percent = badge.progress_percent;
    const isClose = percent >= 75;

    return `
      <div class="progress-badge ${isClose ? 'almost-unlocked' : ''}">
        <div class="progress-info">
          <span class="badge-name">${badge.badge_name}</span>
          <span class="progress-text">${Math.round(percent)}%</span>
        </div>
        <div class="progress-bar-bg">
          <div class="progress-bar-fill" style="width: ${percent}%">
            ${isClose ? '<span class="almost-label">Almost!</span>' : ''}
          </div>
        </div>
        <div class="distance">${badge.distance} to unlock</div>
      </div>
    `;
  }

  /**
   * Get badge emoji based on key
   */
  getBadgeEmoji(badgeKey) {
    const emojiMap = {
      'first_adopter': '🥇',
      'silver_merchant': '🪙',
      'gold_merchant': '💰',
      'diamond_merchant': '💎',
      'streak_warrior_7': '🔥',
      'streak_warrior_30': '🌟',
      'streak_warrior_100': '👑',
      'chart_topper': '🎵',
      'top_10': '🏆',
      'super_fan': '❤️'
    };

    return emojiMap[badgeKey] || '🎖️';
  }

  /**
   * Format unlock date
   */
  formatDate(dateStr) {
    const date = new Date(dateStr);
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);

    if (date.toDateString() === today.toDateString()) {
      return 'Today';
    }

    if (date.toDateString() === yesterday.toDateString()) {
      return 'Yesterday';
    }

    const daysAgo = Math.floor((today - date) / (1000 * 60 * 60 * 24));
    if (daysAgo < 7) {
      return `${daysAgo} days ago`;
    }

    return date.toLocaleDateString();
  }

  /**
   * Attach tooltip listeners
   */
  attachTooltips() {
    const badgeItems = this.container.querySelectorAll('.badge-item');

    badgeItems.forEach(item => {
      item.addEventListener('mouseenter', () => {
        const tooltip = item.querySelector('.badge-tooltip');
        if (tooltip) {
          tooltip.style.display = 'block';
        }
      });

      item.addEventListener('mouseleave', () => {
        const tooltip = item.querySelector('.badge-tooltip');
        if (tooltip) {
          tooltip.style.display = 'none';
        }
      });
    });
  }

  /**
   * Render error state
   */
  renderError() {
    this.container.innerHTML = `
      <div class="badge-gallery error">
        <p>Unable to load badges</p>
      </div>
    `;
  }
}

/**
 * CSS Styles for Badge Gallery
 */
export const badgeGalleryStyles = `
  .badge-gallery {
    padding: 1.5rem;
    background: white;
    border-radius: 12px;
  }

  .gallery-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
  }

  .gallery-header h3 {
    margin: 0;
    font-size: 1.2rem;
  }

  .badge-count {
    background: #667eea;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: bold;
  }

  .badges-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
  }

  .badge-item {
    position: relative;
    cursor: pointer;
    transition: transform 0.2s;
  }

  .badge-item:hover {
    transform: scale(1.1);
  }

  .badge-icon {
    position: relative;
    width: 80px;
    height: 80px;
    margin: 0 auto 0.5rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
  }

  .badge-icon img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .badge-emoji {
    font-size: 2rem;
  }

  .first-badge-marker {
    position: absolute;
    top: -5px;
    right: -5px;
    font-size: 1.5rem;
    background: white;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
  }

  .rarity {
    width: 100%;
    height: 4px;
    border-radius: 2px;
    margin-bottom: 0.5rem;
  }

  .rarity-common {
    background: #999;
  }

  .rarity-uncommon {
    background: #4facfe;
  }

  .rarity-rare {
    background: #a78bfa;
  }

  .rarity-epic {
    background: #f59e0b;
  }

  .rarity-legendary {
    background: linear-gradient(90deg, #f59e0b 0%, #ec4899 100%);
  }

  .badge-tooltip {
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: #222;
    color: white;
    padding: 0.75rem;
    border-radius: 8px;
    width: 150px;
    font-size: 0.8rem;
    z-index: 100;
    display: none;
    margin-bottom: 0.5rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
  }

  .tooltip-name {
    font-weight: bold;
    margin-bottom: 0.25rem;
  }

  .tooltip-desc {
    font-size: 0.75rem;
    opacity: 0.8;
    margin-bottom: 0.25rem;
  }

  .clout-value {
    color: #fbbf24;
    font-weight: bold;
    margin-bottom: 0.25rem;
  }

  .unlock-date {
    font-size: 0.7rem;
    opacity: 0.6;
  }

  .no-badges {
    text-align: center;
    color: #999;
    padding: 2rem 1rem;
  }

  .badge-progress-section {
    border-top: 1px solid #ddd;
    padding-top: 1.5rem;
  }

  .badge-progress-section h4 {
    margin: 0 0 1rem 0;
    font-size: 1rem;
  }

  .progress-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }

  .progress-badge {
    padding: 1rem;
    background: #f5f5f5;
    border-radius: 8px;
    transition: all 0.2s;
  }

  .progress-badge.almost-unlocked {
    background: linear-gradient(135deg, #fef08a 0%, #fef08a 100%);
    border: 2px solid #f59e0b;
  }

  .progress-info {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
  }

  .badge-name {
    font-weight: bold;
  }

  .progress-text {
    font-weight: bold;
    color: #667eea;
  }

  .progress-bar-bg {
    width: 100%;
    height: 12px;
    background: #ddd;
    border-radius: 6px;
    overflow: hidden;
    margin-bottom: 0.5rem;
  }

  .progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
  }

  .almost-label {
    color: white;
    font-size: 0.7rem;
    font-weight: bold;
  }

  .distance {
    font-size: 0.8rem;
    color: #666;
  }

  .badge-gallery.error {
    text-align: center;
    color: #ff6b6b;
    padding: 2rem;
  }
`;
