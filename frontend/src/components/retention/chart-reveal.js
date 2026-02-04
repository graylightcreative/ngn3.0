/**
 * Chart Drop Live Reveal Component
 *
 * Real-time chart reveal UI for Monday 06:00 UTC
 * Shows progressive rank reveal (100→1) with animations
 * Displays live viewer count and rank movement indicators
 */

export class ChartReveal {
  constructor(containerId) {
    this.containerId = containerId;
    this.container = document.getElementById(containerId);
    this.eventSource = null;
    this.chartData = null;
    this.isLive = false;
  }

  /**
   * Initialize the component
   */
  async init() {
    try {
      // Fetch initial status
      await this.fetchChartStatus();

      if (this.chartData && this.chartData.status !== 'completed') {
        this.render();

        if (this.chartData.status === 'revealing') {
          this.startLiveStream();
        }
      } else {
        this.renderNoActiveEvent();
      }

      return this;
    } catch (error) {
      console.error('Error initializing chart reveal:', error);
      this.renderError();
    }
  }

  /**
   * Fetch chart drop status
   */
  async fetchChartStatus() {
    const response = await fetch('/api/v1/retention/chart-drop/current');
    if (!response.ok) {
      throw new Error('Failed to fetch chart status');
    }
    this.chartData = await response.json();
  }

  /**
   * Start Server-Sent Events stream for live updates
   */
  startLiveStream() {
    this.isLive = true;
    this.eventSource = new EventSource('/api/v1/retention/chart-drop/live');

    this.eventSource.onmessage = (event) => {
      try {
        this.chartData = JSON.parse(event.data);
        this.renderLiveReveal();

        if (this.chartData.status === 'completed') {
          this.showRevealComplete();
          this.eventSource.close();
        }
      } catch (error) {
        console.error('Error processing SSE:', error);
      }
    };

    this.eventSource.onerror = (error) => {
      console.error('SSE connection error:', error);
      this.eventSource.close();
      this.isLive = false;
    };
  }

  /**
   * Render the component
   */
  render() {
    if (!this.chartData) return;

    const {
      event_date,
      status,
      current_rank_revealed,
      reveal_progress_percent,
      live_viewers
    } = this.chartData;

    let html = `
      <div class="chart-reveal-container">
        <div class="chart-header">
          <h2>🎵 Chart Drop</h2>
          <div class="chart-date">${this.formatDate(event_date)}</div>
        </div>

        <div class="chart-status">
          <div class="status-badge ${status}">
            ${status === 'revealing' ? '🔴 Live' : status.toUpperCase()}
          </div>
          <div class="live-viewers">
            <span class="viewer-icon">👥</span>
            <span class="viewer-count">${live_viewers.toLocaleString()}</span>
          </div>
        </div>

        <div class="reveal-progress">
          <div class="progress-label">
            <span>Reveal Progress</span>
            <span class="progress-percent">${Math.round(reveal_progress_percent)}%</span>
          </div>
          <div class="progress-bar-bg">
            <div class="progress-bar-fill" style="width: ${reveal_progress_percent}%"></div>
          </div>
        </div>

        <div class="rank-reveal">
          <div class="current-rank">
            <span class="rank-label">Current Rank</span>
            <span class="rank-number">#${current_rank_revealed}</span>
          </div>
        </div>
    `;

    if (status === 'scheduled') {
      html += `<div class="countdown-timer">${this.renderCountdown()}</div>`;
    }

    html += `</div>`;

    this.container.innerHTML = html;
  }

  /**
   * Render live reveal update
   */
  renderLiveReveal() {
    const rankElement = this.container.querySelector('.rank-number');
    if (rankElement) {
      const newRank = this.chartData.current_rank_revealed;

      // Animate rank change
      rankElement.classList.add('rank-change-animation');
      rankElement.textContent = `#${newRank}`;

      setTimeout(() => {
        rankElement.classList.remove('rank-change-animation');
      }, 600);
    }

    // Update progress bar
    const progressBar = this.container.querySelector('.progress-bar-fill');
    if (progressBar) {
      progressBar.style.width = this.chartData.reveal_progress_percent + '%';
    }

    // Update progress percent
    const progressPercent = this.container.querySelector('.progress-percent');
    if (progressPercent) {
      progressPercent.textContent = Math.round(this.chartData.reveal_progress_percent) + '%';
    }

    // Update live viewers
    const viewerCount = this.container.querySelector('.viewer-count');
    if (viewerCount) {
      viewerCount.textContent = this.chartData.live_viewers.toLocaleString();
    }
  }

  /**
   * Show reveal complete animation
   */
  showRevealComplete() {
    const container = this.container.querySelector('.chart-reveal-container');
    if (container) {
      container.classList.add('reveal-complete');

      // Show confetti effect
      this.triggerConfetti();

      // Display completion message
      const rankReveal = this.container.querySelector('.rank-reveal');
      if (rankReveal) {
        rankReveal.insertAdjacentHTML('afterend', `
          <div class="reveal-complete-message">
            <h3>🎉 Chart Drop Complete!</h3>
            <p>Check your ranking and celebrate with others</p>
          </div>
        `);
      }
    }
  }

  /**
   * Trigger confetti animation
   */
  triggerConfetti() {
    const colors = ['#667eea', '#764ba2', '#f093fb', '#f5576c'];

    for (let i = 0; i < 50; i++) {
      const confetti = document.createElement('div');
      confetti.className = 'confetti';
      confetti.style.left = Math.random() * 100 + '%';
      confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
      confetti.style.animationDelay = Math.random() * 0.5 + 's';

      this.container.appendChild(confetti);

      setTimeout(() => confetti.remove(), 3000);
    }
  }

  /**
   * Render countdown timer
   */
  renderCountdown() {
    const eventTime = new Date(this.chartData.event_datetime);
    const now = new Date();
    const diff = eventTime - now;

    if (diff <= 0) {
      return 'Starting now...';
    }

    const hours = Math.floor(diff / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((diff % (1000 * 60)) / 1000);

    return `
      <div class="countdown">
        <div class="time-unit">
          <span class="time-value">${String(hours).padStart(2, '0')}</span>
          <span class="time-label">Hours</span>
        </div>
        <div class="time-separator">:</div>
        <div class="time-unit">
          <span class="time-value">${String(minutes).padStart(2, '0')}</span>
          <span class="time-label">Minutes</span>
        </div>
        <div class="time-separator">:</div>
        <div class="time-unit">
          <span class="time-value">${String(seconds).padStart(2, '0')}</span>
          <span class="time-label">Seconds</span>
        </div>
      </div>
    `;
  }

  /**
   * Format event date
   */
  formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' });
  }

  /**
   * Render no active event message
   */
  renderNoActiveEvent() {
    this.container.innerHTML = `
      <div class="chart-reveal-container no-event">
        <h2>🎵 Chart Drop</h2>
        <p>No active chart drop event. Check back Monday at 6 AM UTC!</p>
      </div>
    `;
  }

  /**
   * Render error state
   */
  renderError() {
    this.container.innerHTML = `
      <div class="chart-reveal-container error">
        <p>Unable to load chart drop data</p>
      </div>
    `;
  }

  /**
   * Destroy the component
   */
  destroy() {
    if (this.eventSource) {
      this.eventSource.close();
    }
  }
}

/**
 * CSS Styles for Chart Reveal
 */
export const chartRevealStyles = `
  .chart-reveal-container {
    padding: 2rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    color: white;
    transition: all 0.3s;
  }

  .chart-reveal-container.reveal-complete {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    animation: pulse-success 0.6s ease-out;
  }

  .chart-header {
    text-align: center;
    margin-bottom: 1.5rem;
  }

  .chart-header h2 {
    margin: 0 0 0.5rem 0;
    font-size: 2rem;
  }

  .chart-date {
    font-size: 0.9rem;
    opacity: 0.8;
  }

  .chart-status {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 8px;
  }

  .status-badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: bold;
    background: rgba(255, 255, 255, 0.2);
  }

  .status-badge.revealing {
    background: #ef4444;
    animation: pulse 1s infinite;
  }

  .live-viewers {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: bold;
  }

  .viewer-icon {
    font-size: 1.2rem;
  }

  .reveal-progress {
    margin-bottom: 2rem;
  }

  .progress-label {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.75rem;
    font-size: 0.9rem;
  }

  .progress-percent {
    font-weight: bold;
  }

  .progress-bar-bg {
    width: 100%;
    height: 16px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    overflow: hidden;
  }

  .progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #4facfe 0%, #00f2fe 100%);
    transition: width 1s ease-out;
  }

  .rank-reveal {
    text-align: center;
    padding: 2rem;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    margin-bottom: 1.5rem;
  }

  .rank-label {
    display: block;
    font-size: 0.9rem;
    opacity: 0.8;
    margin-bottom: 0.5rem;
  }

  .rank-number {
    display: block;
    font-size: 3rem;
    font-weight: bold;
    transition: all 0.6s;
  }

  .rank-number.rank-change-animation {
    animation: rank-pop 0.6s ease-out;
  }

  .countdown {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 1rem;
    margin-top: 1.5rem;
    font-size: 1.2rem;
  }

  .time-unit {
    display: flex;
    flex-direction: column;
    align-items: center;
    background: rgba(255, 255, 255, 0.1);
    padding: 0.75rem 1rem;
    border-radius: 8px;
  }

  .time-value {
    font-size: 1.5rem;
    font-weight: bold;
    font-variant-numeric: tabular-nums;
  }

  .time-label {
    font-size: 0.75rem;
    opacity: 0.7;
  }

  .time-separator {
    animation: blink 1s infinite;
  }

  .reveal-complete-message {
    text-align: center;
    padding: 1.5rem;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    margin-top: 1.5rem;
    animation: slideUp 0.5s ease-out;
  }

  .reveal-complete-message h3 {
    margin: 0 0 0.5rem 0;
  }

  .reveal-complete-message p {
    margin: 0;
  }

  .confetti {
    position: fixed;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    pointer-events: none;
    animation: confetti-fall 3s linear forwards;
  }

  .no-event,
  .error {
    text-align: center;
    padding: 3rem 2rem;
  }

  @keyframes rank-pop {
    0% { transform: scale(0.5); opacity: 0; }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); opacity: 1; }
  }

  @keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
  }

  @keyframes pulse-success {
    0% { transform: scale(0.95); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
  }

  @keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0; }
  }

  @keyframes slideUp {
    from { transform: translateY(20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
  }

  @keyframes confetti-fall {
    to {
      transform: translateY(100vh) rotate(360deg);
      opacity: 0;
    }
  }
`;
