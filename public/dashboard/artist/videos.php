<?php
/**
 * Artist Dashboard - Videos Management
 * (Bible Ch. 19 & 22 - Video System and Social Feed: Video content and distribution)
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('artist');

$user = dashboard_get_user();
$entity = dashboard_get_entity('artist');
$pageTitle = 'videos';
$currentPage = 'videos';

$action = $_GET['action'] ?? 'list';
$videoId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$success = $error = null;
$videos = [];
$editVideo = null;
$tiers = [];

// Fetch videos and tiers
if ($entity) {
    try {
        $pdo = dashboard_pdo();
        
        $stmt = $pdo->prepare("SELECT id, slug, title, description, artist_id, platform, external_id, view_count, published_at, image_url, required_tier_id FROM `ngn_2025`.`videos` WHERE artist_id = ? ORDER BY created_at DESC");
        $stmt->execute([$entity['id']]);
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch fan subscription tiers for this artist
        $stmt = $pdo->prepare("SELECT id, name, price_monthly FROM `ngn_2025`.`fan_subscription_tiers` WHERE artist_id = ? ORDER BY price_monthly ASC");
        $stmt->execute([$entity['id']]);
        $tiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If editing, fetch the specific video
        if ($videoId && $action === 'edit') {
            $stmt = $pdo->prepare("SELECT id, slug, title, description, artist_id, platform, external_id, view_count, published_at, image_url, required_tier_id FROM `ngn_2025`.`videos` WHERE id = ? AND artist_id = ?");
            $stmt->execute([$videoId, $entity['id']]);
            $editVideo = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Handle form submission (Add/Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $entity) {
    if (!dashboard_validate_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $post_action = $_POST['action_type'] ?? $action; // Use a distinct name for action type

        $title = trim($_POST['title'] ?? '');
        $youtubeUrl = trim($_POST['youtube_url'] ?? '');
        $required_tier_id = !empty($_POST['required_tier_id']) ? (int)$_POST['required_tier_id'] : null;
        
        // Extract YouTube ID
        $youtubeId = null;
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $youtubeUrl, $matches)) {
            $youtubeId = $matches[1];
        }
        
        if (empty($title)) {
            $error = 'Title is required.';
        } elseif (empty($youtubeId)) {
            $error = 'Valid YouTube URL is required.';
        } else {
            try {
                $pdo = dashboard_pdo();
                $slug = $entity['slug'] . '-' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title)) . '-' . time();
                
                if ($post_action === 'edit' && $editVideo) {
                    $stmt = $pdo->prepare("
                        UPDATE `ngn_2025`.`videos` 
                        SET title = ?, description = ?, platform = 'youtube', external_id = ?, image_url = ?, required_tier_id = ?, published_at = ?
                        WHERE id = ? AND artist_id = ?
                    ");
                    $stmt->execute([
                        $title,
                        $_POST['description'] ?? null, // Assuming description from form
                        $youtubeId,
                        "https://img.youtube.com/vi/{$youtubeId}/maxresdefault.jpg",
                        $required_tier_id,
                        $_POST['publish_at'] ? date('Y-m-d H:i:s', strtotime($_POST['publish_at'])) : null, // Published at from form
                        $editVideo['id'],
                        $entity['id']
                    ]);
                    $success = 'Video updated!';
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO `ngn_2025`.`videos` (artist_id, slug, title, description, platform, external_id, image_url, required_tier_id, published_at)
                        VALUES (?, ?, ?, ?, 'youtube', ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $entity['id'],
                        $slug,
                        $title,
                        $_POST['description'] ?? null,
                        $youtubeId,
                        "https://img.youtube.com/vi/{$youtubeId}/maxresdefault.jpg",
                        $required_tier_id,
                        $_POST['publish_at'] ? date('Y-m-d H:i:s', strtotime($_POST['publish_at'])) : null,
                    ]);
                    $success = 'Video added!';
                }
                $action = 'list';
                
                // Refresh videos list after successful operation
                $stmt = $pdo->prepare("SELECT id, slug, title, description, artist_id, platform, external_id, view_count, published_at, image_url, required_tier_id FROM `ngn_2025`.`videos` WHERE artist_id = ? ORDER BY created_at DESC");
                $stmt->execute([$entity['id']]);
                $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Handle delete
if ($action === 'delete' && $videoId) {
    try {
        $pdo = dashboard_pdo();
        $stmt = $pdo->prepare("DELETE FROM `ngn_2025`.`videos` WHERE id = ? AND artist_id = ?");
        $stmt->execute([$videoId, $entity['id']]);
        if ($stmt->rowCount() > 0) {
            $success = 'Video deleted successfully.';
        } else {
            $error = 'Video not found or you do not have permission to delete it.';
        }
        // Redirect to clear GET params and show success/error
        header('Location: videos.php?success=' . urlencode($success ?? ''));
        exit;
    } catch (PDOException $e) {
        $error = 'Could not delete video: ' . $e->getMessage();
    }
}

if (isset($_GET['success'])) $success = $_GET['success'];
if (isset($_GET['error'])) $error = $_GET['error'];

$csrf = dashboard_csrf_token();

include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title">Videos</h1>
        <p class="page-subtitle">Manage your music videos and content</p>
    </header>
    
    <div class="page-content">
        <?php if ($success): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if (!$entity): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            Set up your profile first to add videos.
            <a href="profile.php">Set up profile →</a>
        </div>
        <?php elseif ($action === 'add' || ($action === 'edit' && $editVideo)): ?>
        
        <!-- Add/Edit Form -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?= $action === 'edit' ? 'Edit Video' : 'Add New Video' ?></h2>
            </div>
            
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action_type" value="<?= $action ?>">
                
                <div class="form-group">
                    <label class="form-label">Video Title *</label>
                    <input type="text" name="title" class="form-input" required
                           value="<?= htmlspecialchars($editVideo['title'] ?? '') ?>"
                           placeholder="e.g., Official Music Video - Song Name">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-input" rows="5"><?= htmlspecialchars($editVideo['description'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">YouTube URL *</label>
                    <input type="url" name="youtube_url" class="form-input" required
                           value="<?= htmlspecialchars(!empty($editVideo['external_id']) ? "https://www.youtube.com/watch?v=" . $editVideo['external_id'] : '') ?>"
                           placeholder="https://www.youtube.com/watch?v=...">
                    <p style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                        Paste the full YouTube URL. We'll extract the video ID automatically.
                    </p>
                </div>

                <div class="form-group">
                    <label class="form-label">Exclusive Content Tier</label>
                    <select name="required_tier_id" class="form-input">
                        <option value="">Public (no tier required)</option>
                        <?php foreach ($tiers as $tier): ?>
                        <option value="<?= $tier['id'] ?>" <?= (($editVideo['required_tier_id'] ?? '') == $tier['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tier['name']) ?> ($<?= htmlspecialchars($tier['price_monthly']) ?>/month)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                        Select a tier to make this video exclusive to its subscribers.
                    </p>
                </div>

                <div class="form-group">
                    <label class="form-label">Publish Date & Time</label>
                    <input type="datetime-local" name="publish_at" class="form-input"
                           value="<?= htmlspecialchars(date('Y-m-d\TH:i', strtotime($editVideo['published_at'] ?? ''))) ?>"
                           placeholder="Leave empty to publish immediately">
                    <p style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                        Schedule when this video should go live (optional).
                    </p>
                </div>

                <div class="form-group">
                    <label class="form-label">Preview Mode</label>
                    <select name="preview_mode" class="form-input">
                        <option value="none" <?= (($editVideo['preview_mode'] ?? 'none') == 'none') ? 'selected' : '' ?>>No Preview (Exclusive Members Only)</option>
                        <option value="30s" <?= (($editVideo['preview_mode'] ?? 'none') == '30s') ? 'selected' : '' ?>>30-Second Preview</option>
                        <option value="1m" <?= (($editVideo['preview_mode'] ?? 'none') == '1m') ? 'selected' : '' ?>>1-Minute Preview</option>
                    </select>
                    <p style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                        Allow non-members to preview the content before purchasing.
                    </p>
                </div>

                <div class="card" style="margin-top: 1.5rem; background: var(--bg-secondary); border: 1px solid var(--border); padding: 1rem;">
                    <h4 style="margin-top: 0; margin-bottom: 1rem; font-size: 14px;">Video Performance Analytics</h4>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                        <div style="text-align: center; background: var(--bg-primary); padding: 1rem; border-radius: 6px;">
                            <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0 0 0.5rem 0;">Views</p>
                            <p style="font-weight: bold; font-size: 1.2rem; margin: 0;">0</p>
                            <p style="font-size: 0.75rem; color: var(--text-muted); margin: 0.5rem 0 0 0;">Last 30 days</p>
                        </div>
                        <div style="text-align: center; background: var(--bg-primary); padding: 1rem; border-radius: 6px;">
                            <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0 0 0.5rem 0;">Engagement</p>
                            <p style="font-weight: bold; font-size: 1.2rem; margin: 0;">0%</p>
                            <p style="font-size: 0.75rem; color: var(--text-muted); margin: 0.5rem 0 0 0;">Watch time</p>
                        </div>
                        <div style="text-align: center; background: var(--bg-primary); padding: 1rem; border-radius: 6px;">
                            <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0 0 0.5rem 0;">Tier Revenue</p>
                            <p style="font-weight: bold; font-size: 1.2rem; margin: 0;">$0</p>
                            <p style="font-size: 0.75rem; color: var(--text-muted); margin: 0.5rem 0 0 0;">From members</p>
                        </div>
                    </div>
                    <p style="font-size: 0.875rem; color: var(--text-muted); margin-top: 1rem; margin-bottom: 0;">Analytics are tracked after publishing. Advanced metrics available in the Analytics dashboard.</p>
                </div>
                
                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <a href="videos.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-<?= $action === 'edit' ? 'check-lg' : 'plus-lg' ?>"></i> <?= $action === 'edit' ? 'Update Video' : 'Add Video' ?>
                    </button>
                </div>
            </form>
        </div>
        
        <?php else: ?>
        
        <!-- Videos Grid -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Your Videos (<?= count($videos) ?>)</h2>
                <a href="videos.php?action=add" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Add Video
                </a>
            </div>
            
            <?php if (empty($videos)): ?>
            <div style="text-align: center; padding: 48px 24px; color: var(--text-muted);">
                <i class="bi bi-camera-video" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                <p>No videos yet. Add your first music video!</p>
                <a href="videos.php?action=add" class="btn btn-primary" style="margin-top: 16px;">
                    <i class="bi bi-plus-lg"></i> Add Video
                </a>
            </div>
            <?php else: ?>
            <div class="grid grid-3">
                <?php foreach ($videos as $video): ?>
                <div style="background: var(--bg-primary); border-radius: 8px; overflow: hidden;">
                    <div style="position: relative; padding-top: 56.25%;">
                        <?php if ($video['external_id']): ?>
                        <img src="https://img.youtube.com/vi/<?= htmlspecialchars($video['external_id']) ?>/mqdefault.jpg" 
                             alt="" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover;">
                        <a href="https://youtube.com/watch?v=<?= htmlspecialchars($video['external_id']) ?>" target="_blank"
                           style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 48px; height: 48px; background: rgba(0,0,0,0.7); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-play-fill" style="font-size: 24px; color: #fff;"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <div style="padding: 12px;">
                        <div style="font-weight: 500; margin-bottom: 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?= htmlspecialchars($video['title']) ?>
                            <?php if (!empty($video['required_tier_id'])): ?>
                                <span class="badge badge-primary">Exclusive</span>
                            <?php endif; ?>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <a href="videos.php?action=edit&id=<?= $video['id'] ?>" 
                               class="btn btn-secondary" style="flex: 1; padding: 6px; font-size: 12px;">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            <a href="videos.php?action=delete&id=<?= $video['id'] ?>" 
                               class="btn btn-secondary" style="padding: 6px 10px; font-size: 12px; color: var(--danger);"
                               onclick="return confirm('Delete this video?')">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <?php endif; ?>
    </div>
</div>

</body>
</html>