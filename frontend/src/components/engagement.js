/**
 * NGN Engagement Component
 * Handles likes, shares, comments, and sparks for any entity
 *
 * Usage:
 *   const engagement = new NGNEngagement({
 *     entityType: 'artist',
 *     entityId: 123,
 *     apiBase: '/api/v1'
 *   });
 *   engagement.init();
 */

class NGNEngagement {
  constructor(config) {
    this.entityType = config.entityType;
    this.entityId = config.entityId;
    this.apiBase = config.apiBase || '/api/v1';
    this.currentUser = null;
    this.hasLiked = false;
    this.hasShared = false;
    this.comments = [];
  }

  /**
   * Initialize the engagement component
   */
  async init() {
    this.checkAuth();
    await this.loadCounts();
    await this.loadComments();
    if (this.currentUser) {
      await this.checkUserEngagement();
    }
    this.attachEventListeners();
  }

  /**
   * Check if user is authenticated
   */
  checkAuth() {
    const token = localStorage.getItem('auth_token');
    if (!token) {
      this.currentUser = null;
      return false;
    }

    try {
      const payload = JSON.parse(atob(token.split('.')[1]));
      const exp = payload.exp * 1000;
      if (Date.now() >= exp) {
        localStorage.removeItem('auth_token');
        this.currentUser = null;
        return false;
      }
      this.currentUser = {
        id: parseInt(payload.sub),
        email: payload.email,
        role: payload.role
      };
      return true;
    } catch (e) {
      console.error('Invalid token:', e);
      this.currentUser = null;
      return false;
    }
  }

  /**
   * Load engagement counts
   */
  async loadCounts() {
    try {
      const response = await fetch(
        `${this.apiBase}/engagements/counts/${this.entityType}/${this.entityId}`
      );
      const data = await response.json();

      if (data.success && data.data) {
        this.updateCountUI('like', data.data.like_count || 0);
        this.updateCountUI('share', data.data.share_count || 0);
        this.updateCountUI('comment', data.data.comment_count || 0);
        this.updateCountUI('spark', data.data.spark_count || 0);
      }
    } catch (e) {
      console.error('Failed to load counts:', e);
    }
  }

  /**
   * Load comments for this entity
   */
  async loadComments() {
    try {
      const response = await fetch(
        `${this.apiBase}/engagements/${this.entityType}/${this.entityId}?type=comment&limit=20`
      );
      const data = await response.json();

      if (data.success && data.data) {
        this.comments = data.data;
        this.renderComments();
      }
    } catch (e) {
      console.error('Failed to load comments:', e);
    }
  }

  /**
   * Check if user has already engaged
   */
  async checkUserEngagement() {
    if (!this.currentUser) return;

    try {
      const likeCheck = await this.hasEngaged('like');
      this.hasLiked = likeCheck;
      if (this.hasLiked) {
        const likeBtn = document.getElementById('like-btn');
        if (likeBtn) likeBtn.classList.add('active');
      }
    } catch (e) {
      console.error('Failed to check engagement:', e);
    }
  }

  /**
   * Check if user has engaged with specific type
   */
  async hasEngaged(type) {
    if (!this.currentUser) return false;

    try {
      const token = localStorage.getItem('auth_token');
      const response = await fetch(
        `${this.apiBase}/engagements/check/${this.entityType}/${this.entityId}/${type}`,
        {
          headers: { 'Authorization': `Bearer ${token}` }
        }
      );
      const data = await response.json();
      return data.success && data.data?.has_engaged === true;
    } catch (e) {
      return false;
    }
  }

  /**
   * Update count in UI
   */
  updateCountUI(type, count) {
    const countEl = document.getElementById(`${type}-count`);
    if (countEl) {
      countEl.textContent = this.formatCount(count);
    }
  }

  /**
   * Format count for display
   */
  formatCount(num) {
    if (num >= 1000000) {
      return (num / 1000000).toFixed(1) + 'M';
    } else if (num >= 1000) {
      return (num / 1000).toFixed(1) + 'K';
    }
    return num.toString();
  }

  /**
   * Attach event listeners to buttons
   */
  attachEventListeners() {
    const likeBtn = document.getElementById('like-btn');
    const shareBtn = document.getElementById('share-btn');
    const commentBtn = document.getElementById('comment-btn');
    const sparkBtn = document.getElementById('spark-btn');
    const submitCommentBtn = document.getElementById('submit-comment-btn');

    if (likeBtn) likeBtn.addEventListener('click', () => this.handleLike());
    if (shareBtn) shareBtn.addEventListener('click', () => this.openShareModal());
    if (commentBtn) commentBtn.addEventListener('click', () => this.scrollToComments());
    if (sparkBtn) sparkBtn.addEventListener('click', () => this.openSparkModal());
    if (submitCommentBtn) submitCommentBtn.addEventListener('click', () => this.submitComment());
  }

  /**
   * Handle like/unlike
   */
  async handleLike() {
    if (!this.currentUser) {
      alert('Please log in to like this content.');
      window.location.href = '/login.php';
      return;
    }

    const likeBtn = document.getElementById('like-btn');
    const countEl = document.getElementById('like-count');

    // Optimistic UI update
    const currentCount = parseInt(countEl.textContent.replace(/[KM]/g, '')) || 0;
    const newCount = this.hasLiked ? currentCount - 1 : currentCount + 1;

    this.hasLiked = !this.hasLiked;
    likeBtn.classList.toggle('active');
    countEl.textContent = this.formatCount(newCount);

    try {
      const token = localStorage.getItem('auth_token');

      if (this.hasLiked) {
        // Create like
        const response = await fetch(`${this.apiBase}/engagements`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
          },
          body: JSON.stringify({
            entity_type: this.entityType,
            entity_id: this.entityId,
            type: 'like'
          })
        });

        const data = await response.json();
        if (!data.success) {
          throw new Error(data.message || 'Failed to like');
        }
      } else {
        // Remove like (would need engagement ID, so for now just create)
        // In a real implementation, you'd track the engagement ID
        console.log('Unlike not fully implemented - requires engagement ID');
      }
    } catch (e) {
      console.error('Like error:', e);
      // Revert optimistic update
      this.hasLiked = !this.hasLiked;
      likeBtn.classList.toggle('active');
      countEl.textContent = this.formatCount(currentCount);
      alert('Failed to like. Please try again.');
    }
  }

  /**
   * Open share modal
   */
  openShareModal() {
    const modal = document.getElementById('share-modal');
    if (modal) {
      modal.style.display = 'flex';
    }
  }

  /**
   * Close share modal
   */
  closeShareModal() {
    const modal = document.getElementById('share-modal');
    if (modal) {
      modal.style.display = 'none';
    }
  }

  /**
   * Share on platform
   */
  async shareOn(platform) {
    const url = window.location.href;
    const title = document.title;

    let shareUrl = '';
    switch (platform) {
      case 'facebook':
        shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
        break;
      case 'twitter':
        shareUrl = `https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${encodeURIComponent(title)}`;
        break;
      case 'reddit':
        shareUrl = `https://reddit.com/submit?url=${encodeURIComponent(url)}&title=${encodeURIComponent(title)}`;
        break;
      case 'copy':
        try {
          await navigator.clipboard.writeText(url);
          alert('Link copied to clipboard!');
          this.closeShareModal();
          this.recordShare('copy');
          return;
        } catch (e) {
          alert('Failed to copy link');
          return;
        }
    }

    if (shareUrl) {
      window.open(shareUrl, '_blank', 'width=600,height=400');
      this.closeShareModal();
      this.recordShare(platform);
    }
  }

  /**
   * Record share in database
   */
  async recordShare(platform) {
    if (!this.currentUser) return;

    try {
      const token = localStorage.getItem('auth_token');
      const response = await fetch(`${this.apiBase}/engagements`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({
          entity_type: this.entityType,
          entity_id: this.entityId,
          type: 'share',
          share_platform: platform
        })
      });

      const data = await response.json();
      if (data.success) {
        await this.loadCounts(); // Refresh counts
      }
    } catch (e) {
      console.error('Failed to record share:', e);
    }
  }

  /**
   * Scroll to comments section
   */
  scrollToComments() {
    const commentsSection = document.getElementById('comments-section');
    if (commentsSection) {
      commentsSection.scrollIntoView({ behavior: 'smooth' });
    }
  }

  /**
   * Submit a comment
   */
  async submitComment() {
    if (!this.currentUser) {
      alert('Please log in to comment.');
      window.location.href = '/login.php';
      return;
    }

    const textarea = document.getElementById('comment-input');
    const commentText = textarea.value.trim();

    if (!commentText) {
      alert('Please enter a comment.');
      return;
    }

    try {
      const token = localStorage.getItem('auth_token');
      const response = await fetch(`${this.apiBase}/engagements`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({
          entity_type: this.entityType,
          entity_id: this.entityId,
          type: 'comment',
          comment_text: commentText
        })
      });

      const data = await response.json();
      if (data.success) {
        textarea.value = '';
        await this.loadComments();
        await this.loadCounts();
      } else {
        alert(data.message || 'Failed to post comment');
      }
    } catch (e) {
      console.error('Failed to submit comment:', e);
      alert('Failed to post comment. Please try again.');
    }
  }

  /**
   * Render comments list
   */
  renderComments() {
    const container = document.getElementById('comments-list');
    if (!container) return;

    if (this.comments.length === 0) {
      container.innerHTML = '<p class="text-muted">No comments yet. Be the first to comment!</p>';
      return;
    }

    container.innerHTML = this.comments.map(comment => `
      <div class="comment-item">
        <div class="comment-header">
          <strong>${this.escapeHtml(comment.user_name || 'Anonymous')}</strong>
          <span class="comment-time">${this.formatTime(comment.created_at)}</span>
        </div>
        <div class="comment-text">${this.escapeHtml(comment.comment_text || '')}</div>
      </div>
    `).join('');
  }

  /**
   * Format timestamp
   */
  formatTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now - date;
    const seconds = Math.floor(diff / 1000);
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);

    if (days > 7) {
      return date.toLocaleDateString();
    } else if (days > 0) {
      return `${days}d ago`;
    } else if (hours > 0) {
      return `${hours}h ago`;
    } else if (minutes > 0) {
      return `${minutes}m ago`;
    } else {
      return 'Just now';
    }
  }

  /**
   * Escape HTML to prevent XSS
   */
  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  /**
   * Open spark modal
   */
  openSparkModal() {
    if (!this.currentUser) {
      alert('Please log in to send sparks.');
      window.location.href = '/login.php';
      return;
    }

    const modal = document.getElementById('spark-modal');
    if (modal) {
      modal.style.display = 'flex';
    }
  }

  /**
   * Close spark modal
   */
  closeSparkModal() {
    const modal = document.getElementById('spark-modal');
    if (modal) {
      modal.style.display = 'none';
    }
  }

  /**
   * Send sparks
   */
  async sendSparks(amount) {
    if (!this.currentUser) return;

    try {
      const token = localStorage.getItem('auth_token');
      const response = await fetch(`${this.apiBase}/royalties/spark`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({
          to_entity_type: this.entityType,
          to_entity_id: this.entityId,
          spark_amount: amount,
          message: `Sparked ${this.entityType} #${this.entityId}`
        })
      });

      const data = await response.json();
      if (data.success) {
        this.closeSparkModal();
        alert(`Successfully sent ${amount} sparks!`);
        await this.loadCounts();
      } else {
        alert(data.message || 'Failed to send sparks');
      }
    } catch (e) {
      console.error('Failed to send sparks:', e);
      alert('Failed to send sparks. Please try again.');
    }
  }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
  module.exports = NGNEngagement;
}
