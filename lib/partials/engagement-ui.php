<?php
/**
 * Engagement UI Partial
 * Reusable engagement buttons and modals for any entity
 *
 * Required variables:
 * - $entity_type (string): 'artist', 'label', 'venue', 'station', 'post', etc.
 * - $entity_id (int): The ID of the entity
 * - $entity_name (string): Display name for the entity
 *
 * Optional variables:
 * - $show_comments (bool): Whether to show comments section (default: true)
 * - $show_sparks (bool): Whether to show spark button (default: true)
 */

$show_comments = $show_comments ?? true;
$show_sparks = $show_sparks ?? true;
?>

<!-- Engagement Styles -->
<style>
.engagement-bar {
  display: flex;
  gap: 12px;
  align-items: center;
  padding: 16px 0;
  border-top: 1px solid rgba(255,255,255,0.1);
  border-bottom: 1px solid rgba(255,255,255,0.1);
}

.engagement-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 8px;
  color: #f8fafc;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 14px;
  font-weight: 500;
}

.engagement-btn:hover {
  background: rgba(255,255,255,0.1);
  transform: translateY(-2px);
}

.engagement-btn.active {
  background: #1DB954;
  border-color: #1DB954;
  color: #06120a;
}

.engagement-btn.spark {
  background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
  border-color: #f59e0b;
  color: #fff;
}

.engagement-btn.spark:hover {
  background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
}

.engagement-btn i {
  font-size: 18px;
}

.engagement-count {
  font-weight: 700;
}

<?php if ($show_comments): ?>
/* Comments Section */
#comments-section {
  margin-top: 32px;
}

.comment-composer {
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 24px;
}

.comment-composer textarea {
  width: 100%;
  min-height: 100px;
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.15);
  border-radius: 8px;
  padding: 12px;
  color: #f8fafc;
  font-family: inherit;
  font-size: 14px;
  resize: vertical;
}

.comment-composer textarea:focus {
  outline: none;
  border-color: #1DB954;
}

.comment-composer-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 12px;
}

.btn {
  padding: 10px 20px;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all 0.2s;
}

.btn-primary {
  background: #1DB954;
  color: #06120a;
}

.btn-primary:hover {
  background: #1ed760;
  transform: translateY(-1px);
}

.btn-secondary {
  background: rgba(255,255,255,0.1);
  color: #f8fafc;
}

.btn-secondary:hover {
  background: rgba(255,255,255,0.15);
}

.comment-item {
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 12px;
}

.comment-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 8px;
}

.comment-time {
  color: #9ca3af;
  font-size: 13px;
}

.comment-text {
  color: #f8fafc;
  line-height: 1.6;
}

.text-muted {
  color: #9ca3af;
  font-style: italic;
}
<?php endif; ?>

/* Modal Styles */
.modal {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0,0,0,0.8);
  backdrop-filter: blur(4px);
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: #1c2642;
  border-radius: 16px;
  padding: 24px;
  max-width: 500px;
  width: 90%;
  max-height: 80vh;
  overflow-y: auto;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.modal-header h3 {
  margin: 0;
  color: #f8fafc;
}

.modal-close {
  background: none;
  border: none;
  color: #9ca3af;
  font-size: 24px;
  cursor: pointer;
  padding: 0;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 4px;
}

.modal-close:hover {
  background: rgba(255,255,255,0.1);
  color: #f8fafc;
}

.share-buttons {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.share-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px;
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 8px;
  color: #f8fafc;
  cursor: pointer;
  transition: all 0.2s;
  justify-content: center;
}

.share-btn:hover {
  background: rgba(255,255,255,0.1);
  transform: translateY(-2px);
}

.share-btn i {
  font-size: 20px;
}

<?php if ($show_sparks): ?>
.spark-amounts {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  margin-bottom: 16px;
}

.spark-amount-btn {
  padding: 16px;
  background: rgba(255,255,255,0.05);
  border: 2px solid rgba(255,255,255,0.1);
  border-radius: 12px;
  color: #f8fafc;
  cursor: pointer;
  transition: all 0.2s;
  text-align: center;
}

.spark-amount-btn:hover {
  background: rgba(255,255,255,0.1);
  border-color: #f59e0b;
}

.spark-amount-btn.selected {
  background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
  border-color: #f59e0b;
}

.spark-amount {
  font-size: 24px;
  font-weight: 700;
  color: #f59e0b;
}

.spark-price {
  font-size: 13px;
  color: #9ca3af;
  margin-top: 4px;
}

.spark-custom {
  margin: 16px 0;
}

.spark-custom label {
  display: block;
  margin-bottom: 8px;
  color: #f8fafc;
  font-weight: 500;
}

.spark-custom input {
  width: 100%;
  padding: 12px;
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.15);
  border-radius: 8px;
  color: #f8fafc;
  font-size: 16px;
}

.spark-custom input:focus {
  outline: none;
  border-color: #f59e0b;
}

.spark-preview {
  background: rgba(245, 158, 11, 0.1);
  border: 1px solid rgba(245, 158, 11, 0.3);
  border-radius: 8px;
  padding: 16px;
  margin: 16px 0;
}

.spark-preview-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 8px;
  color: #f8fafc;
}

.spark-preview-row:last-child {
  margin-bottom: 0;
  padding-top: 8px;
  border-top: 1px solid rgba(255,255,255,0.1);
  font-weight: 700;
}
<?php endif; ?>
</style>

<!-- Engagement Bar -->
<div class="engagement-bar">
  <button class="engagement-btn" id="like-btn">
    <i class="bi bi-heart-fill"></i>
    <span class="engagement-count" id="like-count">0</span>
  </button>

  <button class="engagement-btn" id="share-btn">
    <i class="bi bi-share-fill"></i>
    <span class="engagement-count" id="share-count">0</span>
  </button>

  <?php if ($show_comments): ?>
  <button class="engagement-btn" id="comment-btn">
    <i class="bi bi-chat-dots-fill"></i>
    <span class="engagement-count" id="comment-count">0</span>
  </button>
  <?php endif; ?>

  <?php if ($show_sparks): ?>
  <button class="engagement-btn spark" id="spark-btn">
    <i class="bi bi-lightning-charge-fill"></i>
    <span class="engagement-count" id="spark-count">0</span>
  </button>
  <?php endif; ?>
</div>

<?php if ($show_comments): ?>
<!-- Comments Section -->
<div id="comments-section">
  <h3 style="color: #f8fafc; margin-bottom: 20px;">Comments</h3>

  <div class="comment-composer">
    <textarea id="comment-input" placeholder="Share your thoughts..."></textarea>
    <div class="comment-composer-actions">
      <button class="btn btn-secondary" onclick="document.getElementById('comment-input').value = ''">Clear</button>
      <button class="btn btn-primary" id="submit-comment-btn">Post Comment</button>
    </div>
  </div>

  <div id="comments-list">
    <p class="text-muted">Loading comments...</p>
  </div>
</div>
<?php endif; ?>

<!-- Share Modal -->
<div class="modal" id="share-modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Share <?php echo htmlspecialchars($entity_name); ?></h3>
      <button class="modal-close" onclick="engagement.closeShareModal()">&times;</button>
    </div>

    <div class="share-buttons">
      <button class="share-btn" onclick="engagement.shareOn('facebook')">
        <i class="bi bi-facebook"></i>
        <span>Facebook</span>
      </button>

      <button class="share-btn" onclick="engagement.shareOn('twitter')">
        <i class="bi bi-twitter"></i>
        <span>Twitter</span>
      </button>

      <button class="share-btn" onclick="engagement.shareOn('reddit')">
        <i class="bi bi-reddit"></i>
        <span>Reddit</span>
      </button>

      <button class="share-btn" onclick="engagement.shareOn('copy')">
        <i class="bi bi-link-45deg"></i>
        <span>Copy Link</span>
      </button>
    </div>
  </div>
</div>

<?php if ($show_sparks): ?>
<!-- Spark Modal -->
<div class="modal" id="spark-modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Send Sparks to <?php echo htmlspecialchars($entity_name); ?></h3>
      <button class="modal-close" onclick="engagement.closeSparkModal()">&times;</button>
    </div>

    <div class="spark-amounts">
      <div class="spark-amount-btn" onclick="selectSparkAmount(100, this)">
        <div class="spark-amount">100</div>
        <div class="spark-price">$1.00</div>
      </div>

      <div class="spark-amount-btn" onclick="selectSparkAmount(500, this)">
        <div class="spark-amount">500</div>
        <div class="spark-price">$5.00</div>
      </div>

      <div class="spark-amount-btn" onclick="selectSparkAmount(1000, this)">
        <div class="spark-amount">1000</div>
        <div class="spark-price">$10.00</div>
      </div>
    </div>

    <div class="spark-custom">
      <label for="custom-spark-amount">Or enter a custom amount:</label>
      <input
        type="number"
        id="custom-spark-amount"
        min="10"
        step="10"
        placeholder="Enter sparks (min 10)"
        onchange="selectCustomSpark(this.value)"
      >
    </div>

    <div class="spark-preview" id="spark-preview" style="display: none;">
      <div class="spark-preview-row">
        <span>Gross Amount:</span>
        <span id="spark-gross">$0.00</span>
      </div>
      <div class="spark-preview-row">
        <span>Platform Fee (10%):</span>
        <span id="spark-fee">$0.00</span>
      </div>
      <div class="spark-preview-row">
        <span>Artist Receives:</span>
        <span id="spark-net">$0.00</span>
      </div>
    </div>

    <button
      class="btn btn-primary"
      id="send-spark-btn"
      style="width: 100%; margin-top: 16px;"
      disabled
      onclick="confirmSendSparks()"
    >
      Send Sparks
    </button>
  </div>
</div>

<script>
let selectedSparkAmount = 0;

function selectSparkAmount(amount, btn) {
  selectedSparkAmount = amount;
  document.querySelectorAll('.spark-amount-btn').forEach(el => el.classList.remove('selected'));
  btn.classList.add('selected');
  document.getElementById('custom-spark-amount').value = '';
  updateSparkPreview(amount);
}

function selectCustomSpark(amount) {
  amount = parseInt(amount) || 0;
  if (amount < 10) {
    alert('Minimum spark amount is 10');
    return;
  }
  selectedSparkAmount = amount;
  document.querySelectorAll('.spark-amount-btn').forEach(el => el.classList.remove('selected'));
  updateSparkPreview(amount);
}

function updateSparkPreview(sparks) {
  if (!sparks || sparks < 10) {
    document.getElementById('spark-preview').style.display = 'none';
    document.getElementById('send-spark-btn').disabled = true;
    return;
  }

  const gross = (sparks / 100).toFixed(2);
  const fee = (gross * 0.10).toFixed(2);
  const net = (gross * 0.90).toFixed(2);

  document.getElementById('spark-gross').textContent = `$${gross}`;
  document.getElementById('spark-fee').textContent = `$${fee}`;
  document.getElementById('spark-net').textContent = `$${net}`;
  document.getElementById('spark-preview').style.display = 'block';
  document.getElementById('send-spark-btn').disabled = false;
}

function confirmSendSparks() {
  if (selectedSparkAmount < 10) {
    alert('Please select a spark amount');
    return;
  }
  engagement.sendSparks(selectedSparkAmount);
  selectedSparkAmount = 0;
  document.getElementById('spark-preview').style.display = 'none';
  document.getElementById('send-spark-btn').disabled = true;
}
</script>
<?php endif; ?>

<!-- Initialize Engagement Component -->
<script src="/frontend/src/components/engagement.js"></script>
<script>
// Initialize engagement component
const engagement = new NGNEngagement({
  entityType: '<?php echo addslashes($entity_type); ?>',
  entityId: <?php echo (int)$entity_id; ?>,
  apiBase: '/api/v1'
});

// Init when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => engagement.init());
} else {
  engagement.init();
}
</script>

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
