/**
 * XP Progress Bar Component
 *
 * Displays user's level progression with visual feedback
 * Shows level-up animations and unlocked features
 */

export class XPBar {
  constructor(containerId, userId) {
    this.containerId = containerId;
    this.userId = userId;
    this.container = document.getElementById(containerId);
    this.xpData = null;
  }

  /**
   * Initialize the component
   */
  async init() {
    try {
      await this.fetchXPData();
      this.render();
      return this;
    } catch (error) {
      console.error('Error initializing XP bar:', error);
      this.renderError();
    }
  }

  /**
   * Fetch XP data from API
   */
  async fetchXPData() {
    const response = await fetch('/api/v1/retention/xp');
    if (!response.ok) {
      throw new Error('Failed to fetch XP data');
    }
    this.xpData = await response.json();
  }

  /**
   * Render the component
   */
  render() {
    if (!this.xpData) return;

    const {
      current_level,
      total_xp,
      xp_to_next_level,
      xp_progress_percent,
      xp_breakdown,
      last_level_up_at
    } = this.xpData;

    let html = `
      <div class="xp-bar-container">
        <div class="level-header">
          <div class="level-display">
            <span class="level-number">${current_level}</span>
            <span class="level-label">LEVEL</span>
          </div>

          <div class="xp-count">
            <span class="xp-current">${total_xp.toLocaleString()}</span>
            <span class="xp-total">/ ${xp_to_next_level.toLocaleString()}</span>
          </div>
        </div>

        <div class="progress-bar-wrapper">
          <div class="progress-bar-background">
            <div class="progress-bar-fill" style="width: ${xp_progress_percent}%"></div>
            <div class="progress-bar-label">${Math.round(xp_progress_percent)}%</div>
          </div>
        </div>

        <div class="xp-breakdown">
          ${this.renderXPSources(xp_breakdown)}
        </div>
    `;

    // Show last level up time if recent
    if (last_level_up_at) {
      const lastLevelUp = new Date(last_level_up_at);
      const hoursAgo = (Date.now() - lastLevelUp) / (1000 * 60 * 60);

      if (hoursAgo < 24) {
        html += `
          <div class="recent-level-up">
            🎉 Leveled up ${Math.round(hoursAgo)}h ago
          </div>
        `;
      }
    }

    html += `</div>`;

    this.container.innerHTML = html;

    // Animate progress bar
    this.animateProgressBar();
  }

  /**
   * Render XP source breakdown
   */
  renderXPSources(breakdown) {
    const sources = [
      { name: 'Listening', value: breakdown.listening, icon: '🎵' },
      { name: 'Engagement', value: breakdown.engagement, icon: '👍' },
      { name: 'Sparks', value: breakdown.sparks, icon: '✨' },
      { name: 'Social', value: breakdown.social, icon: '🤝' },
      { name: 'Achievements', value: breakdown.achievements, icon: '🏆' }
    ];

    return sources
      .filter(s => s.value > 0)
      .map(s => `
        <div class="xp-source">
          <span class="xp-icon">${s.icon}</span>
          <span class="xp-name">${s.name}</span>
          <span class="xp-amount">${s.value.toLocaleString()}</span>
        </div>
      `)
      .join('');
  }

  /**
   * Animate progress bar fill
   */
  animateProgressBar() {
    const progressFill = this.container.querySelector('.progress-bar-fill');
    if (progressFill) {
      const currentPercent = parseFloat(progressFill.style.width);
      progressFill.style.width = '0%';

      // Trigger animation
      setTimeout(() => {
        progressFill.style.transition = 'width 1.5s ease-out';
        progressFill.style.width = currentPercent + '%';
      }, 100);
    }
  }

  /**
   * Render error state
   */
  renderError() {
    this.container.innerHTML = `
      <div class="xp-bar-container error">
        <p>Unable to load XP data</p>
      </div>
    `;
  }
}

/**
 * CSS Styles for XP Bar
 */
export const xpBarStyles = `
  .xp-bar-container {
    padding: 1.5rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    color: white;
  }

  .xp-bar-container.error {
    background: #ff6b6b;
    text-align: center;
    padding: 2rem;
  }

  .level-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
  }

  .level-display {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .level-number {
    font-size: 2.5rem;
    font-weight: bold;
  }

  .level-label {
    font-size: 0.75rem;
    opacity: 0.8;
    letter-spacing: 1px;
  }

  .xp-count {
    font-size: 0.9rem;
    opacity: 0.9;
  }

  .xp-current {
    font-weight: bold;
  }

  .xp-total {
    opacity: 0.7;
  }

  .progress-bar-wrapper {
    margin-bottom: 1rem;
  }

  .progress-bar-background {
    position: relative;
    width: 100%;
    height: 24px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    overflow: hidden;
  }

  .progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #4facfe 0%, #00f2fe 100%);
    width: 0%;
    transition: width 0s ease;
  }

  .progress-bar-label {
    position: absolute;
    top: 50%;
    right: 0.5rem;
    transform: translateY(-50%);
    font-size: 0.75rem;
    font-weight: bold;
    color: white;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
  }

  .xp-breakdown {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
    gap: 0.75rem;
  }

  .xp-source {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0.75rem;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    text-align: center;
  }

  .xp-icon {
    font-size: 1.5rem;
    margin-bottom: 0.25rem;
  }

  .xp-name {
    font-size: 0.7rem;
    opacity: 0.8;
  }

  .xp-amount {
    font-size: 0.9rem;
    font-weight: bold;
    margin-top: 0.25rem;
  }

  .recent-level-up {
    margin-top: 1rem;
    padding: 0.75rem;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    text-align: center;
    font-weight: bold;
    animation: pulse 1s infinite;
  }
`;
