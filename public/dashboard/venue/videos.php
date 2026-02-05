<?php
/**
 * Venue Dashboard - Videos Management
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('venue');

$user = dashboard_get_user();
$entity = dashboard_get_entity('venue');
$pageTitle = 'videos';
$currentPage = 'videos';

$action = $_GET['action'] ?? 'list';
$videoId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$success = $error = null;
$videos = [];

if ($entity) {
    try {
        $pdo = dashboard_pdo();
        $stmt = $pdo->prepare("SELECT * FROM `ngn_2025`.`videos` WHERE entity_type = 'venue' AND entity_id = ? ORDER BY COALESCE(published_at, created_at) DESC");
        $stmt->execute([$entity['id']]);
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $entity) {
    if (!dashboard_validate_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $videoType = $_POST['video_type'] ?? 'youtube';
        $videoIdInput = trim($_POST['video_id'] ?? '');
        $thumbnailUrl = trim($_POST['thumbnail_url'] ?? '');
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
        
        if ($videoType === 'youtube' && strpos($videoIdInput, 'youtube.com') !== false) {
            preg_match('/[?&]v=([^&]+)/', $videoIdInput, $m);
            $videoIdInput = $m[1] ?? $videoIdInput;
        } elseif ($videoType === 'youtube' && strpos($videoIdInput, 'youtu.be') !== false) {
            preg_match('/youtu\.be\/([^?]+)/', $videoIdInput, $m);
            $videoIdInput = $m[1] ?? $videoIdInput;
        }
        
        if (empty($title)) {
            $error = 'Video title is required.';
        } elseif (empty($videoIdInput)) {
            $error = 'Video ID or URL is required.';
        } else {
            try {
                $pdo = dashboard_pdo();
                $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title)) . '-' . time();
                
                if (empty($thumbnailUrl) && $videoType === 'youtube' && $videoIdInput) {
                    $thumbnailUrl = "https://img.youtube.com/vi/{$videoIdInput}/maxresdefault.jpg";
                }
                
                if ($action === 'edit' && $videoId) {
                    $stmt = $pdo->prepare("UPDATE `ngn_2025`.`videos` SET 
                        title = ?, description = ?, video_type = ?, video_id = ?, thumbnail_url = ?, is_featured = ?
                        WHERE id = ? AND entity_type = 'venue' AND entity_id = ?");
                    $stmt->execute([$title, $description ?: null, $videoType, $videoIdInput, $thumbnailUrl ?: null, $isFeatured, $videoId, $entity['id']]);
                    $success = 'Video updated!';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO `ngn_2025`.`videos` 
                        (entity_type, entity_id, slug, title, description, video_type, video_id, thumbnail_url, is_featured, published_at) 
                        VALUES ('venue', ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$entity['id'], $slug, $title, $description ?: null, $videoType, $videoIdInput, $thumbnailUrl ?: null, $isFeatured]);
                    $success = 'Video added!';
                }
                
                $action = 'list';
                $stmt = $pdo->prepare("SELECT * FROM `ngn_2025`.`videos` WHERE entity_type = 'venue' AND entity_id = ? ORDER BY COALESCE(published_at, created_at) DESC");
                $stmt->execute([$entity['id']]);
                $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

$editVideo = null;
if ($action === 'edit' && $videoId && $entity) {
    try {
        $pdo = dashboard_pdo();
        $stmt = $pdo->prepare("SELECT * FROM `ngn_2025`.`videos` WHERE id = ? AND entity_type = 'venue' AND entity_id = ?");
        $stmt->execute([$videoId, $entity['id']]);
        $editVideo = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

$csrf = dashboard_csrf_token();
include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title">Videos</h1>
        <p class="page-subtitle">Manage your venue's video content</p>
    </header>
    
    <div class="page-content">
        <?php if ($success): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
        
        <?php if (!$entity): ?>
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Set up your venue profile first. <a href="profile.php">Set up profile →</a></div>
        <?php elseif ($action === 'add' || ($action === 'edit' && $editVideo)): ?>
        
        <div class="card">
            <div class="card-header"><h2 class="card-title"><?= $action === 'edit' ? 'Edit Video' : 'Add New Video' ?></h2></div>
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <div class="form-group">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" class="form-input" required value="<?= htmlspecialchars($editVideo['title'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="3"><?= htmlspecialchars($editVideo['description'] ?? '') ?></textarea>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Video Type</label>
                        <select name="video_type" class="form-input">
                            <option value="youtube" <?= ($editVideo['video_type'] ?? '') === 'youtube' ? 'selected' : '' ?>>YouTube</option>
                            <option value="vimeo" <?= ($editVideo['video_type'] ?? '') === 'vimeo' ? 'selected' : '' ?>>Vimeo</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Video ID or URL *</label>
                        <input type="text" name="video_id" class="form-input" value="<?= htmlspecialchars($editVideo['video_id'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Thumbnail URL (optional)</label>
                    <input type="url" name="thumbnail_url" class="form-input" value="<?= htmlspecialchars($editVideo['thumbnail_url'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="is_featured" value="1" <?= !empty($editVideo['is_featured']) ? 'checked' : '' ?>>
                        <span>Feature this video</span>
                    </label>
                </div>
                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <a href="videos.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-<?= $action === 'edit' ? 'check-lg' : 'plus-lg' ?>"></i> <?= $action === 'edit' ? 'Update' : 'Add' ?></button>
                </div>
            </form>
        </div>
        
        <?php else: ?>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Your Videos (<?= count($videos) ?>)</h2>
                <a href="videos.php?action=add" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Video</a>
            </div>
            <?php if (empty($videos)): ?>
            <div style="text-align: center; padding: 48px 24px; color: var(--text-muted);">
                <i class="bi bi-camera-video" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                <p>No videos yet.</p>
                <a href="videos.php?action=add" class="btn btn-primary" style="margin-top: 16px;"><i class="bi bi-plus-lg"></i> Add First Video</a>
            </div>
            <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px;">
                <?php foreach ($videos as $video): ?>
                <div style="background: var(--bg-primary); border-radius: 8px; overflow: hidden;">
                    <div style="aspect-ratio: 16/9; background: var(--border); position: relative;">
                        <?php if (!empty($video['thumbnail_url'])): ?>
                        <img src="<?= htmlspecialchars($video['thumbnail_url']) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                        <div style="display: flex; align-items: center; justify-content: center; height: 100%;"><i class="bi bi-play-circle" style="font-size: 48px; color: var(--text-muted);"></i></div>
                        <?php endif; ?>
                        <?php if (!empty($video['is_featured'])): ?><span style="position: absolute; top: 8px; right: 8px; background: var(--accent); color: #000; padding: 2px 6px; border-radius: 4px; font-size: 10px;">FEATURED</span><?php endif; ?>
                    </div>
                    <div style="padding: 12px;">
                        <div style="font-weight: 600; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($video['title']) ?></div>
                        <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 8px;"><?= ucfirst($video['video_type'] ?? 'youtube') ?> · <?= $video['view_count'] ?? 0 ?> views</div>
                        <a href="videos.php?action=edit&id=<?= $video['id'] ?>" class="btn btn-secondary" style="width: 100%; padding: 6px; font-size: 12px;"><i class="bi bi-pencil"></i> Edit</a>
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

