/**
 * Streak Widget Component
 *
 * Displays user's daily engagement streak with loss aversion hooks
 * Shows countdown to next check-in deadline
 * Displays warning state when < 6 hours remaining
 */

export class StreakWidget {
  constructor(containerId, userId) {
    this.containerId = containerId;
    this.userId = userId;
    this.container = document.getElementById(containerId);
    this.streakData = null;
    this.updateInterval = null;
  }

  /**
   * Initialize the widget
   */
  async init() {
    try {
      await this.fetchStreakData();
      this.render();
      this.startAutoUpdate();
      return this;
    } catch (error) {
      console.error('Error initializing streak widget:', error);
      this.renderError();
    }
  }

  /**
   * Fetch streak data from API
   */
  async fetchStreakData() {
    const response = await fetch('/api/v1/retention/streaks');
    if (!response.ok) {
      throw new Error('Failed to fetch streak data');
    }
    this.streakData = await response.json();
  }

  /**
   * Render the widget
   */
  render() {
    if (!this.streakData) return;

    const {
      current_streak,
      longest_streak,
      hours_remaining,
      grace_period_active,
      last_broken_at,
      last_broken_streak_length
    } = this.streakData;

    // Determine warning state
    const isWarning = hours_remaining > 0 && hours_remaining < 6;
    const isCritical = hours_remaining <= 0 && !grace_period_active;
    const isBroken = current_streak === 0;

    // Build HTML
    let html = `
      <div class="streak-widget ${isCritical ? 'critical' : ''} ${isWarning ? 'warning' : ''}">
        <div class="streak-flame">
          ${this.getFlameEmoji(current_streak, hours_remaining)}
        </div>

        <div class="streak-content">
          <div class="streak-count">
            ${current_streak > 0 ? current_streak : 'Start'} day${current_streak !== 1 ? 's' : ''}
          </div>

          <div class="streak-progress">
            ${this.renderProgressBar(hours_remaining)}
          </div>

          <div class="streak-info">
            ${current_streak > 0
              ? `Check in in ${this.formatHours(hours_remaining)} to maintain streak`
              : `Best streak: ${longest_streak} days`
            }
          </div>
    `;

    // Show broken streak regret message if applicable
    if (grace_period_active && last_broken_streak_length > 0) {
      html += `
        <div class="streak-regret">
          <span class="regret-icon">😢</span>
          <span class="regret-text">
            You had a ${last_broken_streak_length}-day streak. Recover it in the next 48 hours!
          </span>
        </div>
      `;
    }

    // Show check-in button if needed
    if (current_streak === 0 || hours_remaining <= 0) {
      html += `
        <button class="streak-checkin-btn" data-streak-type="listening">
          Check In Now
        </button>
      `;
    }

    html += `</div></div>`;

    this.container.innerHTML = html;

    // Attach event listeners
    const checkinBtn = this.container.querySelector('.streak-checkin-btn');
    if (checkinBtn) {
      checkinBtn.addEventListener('click', () => this.recordCheckIn());
    }
  }

  /**
   * Get appropriate flame emoji based on streak
   */
  getFlameEmoji(streak, hoursRemaining) {
    if (streak === 0) {
      return '🔲'; // Empty when streak is broken
    }

    if (hoursRemaining <= 0) {
      return '⚠️'; // Warning
    }

    if (hoursRemaining < 6) {
      return '🔥'; // Hot (critical)
    }

    if (streak >= 30) {
      return '🌟'; // Legendary
    }

    if (streak >= 7) {
      return '🔥'; // Hot
    }

    return '✨'; // Mild
  }

  /**
   * Render progress bar to next deadline
   */
  renderProgressBar(hoursRemaining) {
    const total = 24;
    const elapsed = Math.max(0, total - hoursRemaining);
    const percent = Math.min((elapsed / total) * 100, 100);

    return `
      <div class="progress-bar-container">
        <div class="progress-bar" style="width: ${percent}%"></div>
        <div class="progress-text">${Math.round(percent)}%</div>
      </div>
    `;
  }

  /**
   * Format hours remaining as readable text
   */
  formatHours(hours) {
    if (hours <= 0) {
      return 'NOW';
    }

    if (hours < 1) {
      const minutes = Math.round(hours * 60);
      return `${minutes}m`;
    }

    if (hours < 24) {
      return `${Math.round(hours)}h`;
    }

    const days = Math.round(hours / 24);
    return `${days}d`;
  }

  /**
   * Record check-in for user
   */
  async recordCheckIn() {
    try {
      const response = await fetch('/api/v1/retention/streaks/checkin', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          streak_type: 'listening'
        })
      });

      if (!response.ok) {
        throw new Error('Failed to record check-in');
      }

      const result = await response.json();
      this.streakData = result.streak;

      // Show success feedback
      this.showCheckInSuccess();
      this.render();
    } catch (error) {
      console.error('Error recording check-in:', error);
      alert('Failed to check in. Please try again.');
    }
  }

  /**
   * Show success animation
   */
  showCheckInSuccess() {
    const element = this.container.querySelector('.streak-flame');
    if (element) {
      element.classList.add('success-animation');
      setTimeout(() => element.classList.remove('success-animation'), 600);
    }
  }

  /**
   * Start auto-update interval
   */
  startAutoUpdate() {
    this.updateInterval = setInterval(() => {
      this.fetchStreakData().then(() => {
        // Only re-render if time-critical (warning state)
        const { hours_remaining } = this.streakData;
        if (hours_remaining < 6) {
          this.render();
        }
      });
    }, 60000); // Update every minute
  }

  /**
   * Render error state
   */
  renderError() {
    this.container.innerHTML = `
      <div class="streak-widget error">
        <p>Unable to load streak data</p>
      </div>
    `;
  }

  /**
   * Destroy the widget
   */
  destroy() {
    if (this.updateInterval) {
      clearInterval(this.updateInterval);
    }
  }
}

/**
 * CSS Styles for Streak Widget
 */
export const streakWidgetStyles = `
  .streak-widget {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    color: white;
    min-width: 300px;
  }

  .streak-widget.warning {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    animation: pulse 1s infinite;
  }

  .streak-widget.critical {
    background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    animation: pulse 0.5s infinite;
  }

  .streak-flame {
    font-size: 3rem;
    animation: float 3s ease-in-out infinite;
  }

  .streak-flame.success-animation {
    animation: bounce 0.6s ease-out;
  }

  .streak-content {
    flex: 1;
  }

  .streak-count {
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
  }

  .progress-bar-container {
    position: relative;
    width: 100%;
    height: 8px;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 0.5rem;
  }

  .progress-bar {
    height: 100%;
    background: rgba(255, 255, 255, 0.9);
    transition: width 0.5s ease;
  }

  .progress-text {
    font-size: 0.75rem;
    position: absolute;
    right: 0.5rem;
    top: -1.2rem;
  }

  .streak-info {
    font-size: 0.9rem;
    opacity: 0.9;
  }

  .streak-regret {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.75rem;
    padding: 0.75rem;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 6px;
    font-size: 0.85rem;
  }

  .regret-icon {
    font-size: 1.2rem;
  }

  .streak-checkin-btn {
    margin-top: 0.75rem;
    padding: 0.5rem 1rem;
    background: white;
    color: #667eea;
    border: none;
    border-radius: 6px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.2s;
  }

  .streak-checkin-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
  }

  @keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
  }

  @keyframes bounce {
    0% { transform: scale(1); }
    50% { transform: scale(1.2); }
    100% { transform: scale(1); }
  }

  @keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
  }
`;
