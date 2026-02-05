<?php
/**
 * Label Dashboard - Posts/News Management
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('label');

$user = dashboard_get_user();
$entity = dashboard_get_entity('label');
$pageTitle = 'posts';
$currentPage = 'posts';

$action = $_GET['action'] ?? 'list';
$postId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$success = $error = null;
$posts = [];

// Fetch posts
if ($entity) {
    try {
        $pdo = dashboard_pdo();
        $stmt = $pdo->prepare("SELECT * FROM `ngn_2025`.`posts` WHERE entity_type = 'label' AND entity_id = ? ORDER BY COALESCE(published_at, created_at) DESC");
        $stmt->execute([$entity['id']]);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Table may not exist
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $entity) {
    if (!dashboard_validate_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $excerpt = trim($_POST['excerpt'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $featuredImageUrl = trim($_POST['featured_image_url'] ?? '');
        $status = $_POST['status'] ?? 'draft';
        $isPinned = isset($_POST['is_pinned']) ? 1 : 0;
        
        if (empty($title)) {
            $error = 'Post title is required.';
        } else {
            try {
                $pdo = dashboard_pdo();
                $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title)) . '-' . time();
                $publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;
                
                if ($action === 'edit' && $postId) {
                    // Check if already published
                    $stmt = $pdo->prepare("SELECT published_at FROM `ngn_2025`.`posts` WHERE id = ? AND entity_type = 'label' AND entity_id = ?");
                    $stmt->execute([$postId, $entity['id']]);
                    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($existing && $existing['published_at']) {
                        $publishedAt = $existing['published_at'];
                    } elseif ($status === 'published') {
                        $publishedAt = date('Y-m-d H:i:s');
                    }
                    
                    $stmt = $pdo->prepare("UPDATE `ngn_2025`.`posts` SET 
                        title = ?, excerpt = ?, content = ?, featured_image_url = ?, status = ?, is_pinned = ?, published_at = ?
                        WHERE id = ? AND entity_type = 'label' AND entity_id = ?");
                    $stmt->execute([$title, $excerpt ?: null, $content ?: null, $featuredImageUrl ?: null, $status, $isPinned, $publishedAt, $postId, $entity['id']]);
                    $success = 'Post updated!';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO `ngn_2025`.`posts` 
                        (entity_type, entity_id, slug, title, excerpt, content, featured_image_url, status, is_pinned, published_at) 
                        VALUES ('label', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$entity['id'], $slug, $title, $excerpt ?: null, $content ?: null, $featuredImageUrl ?: null, $status, $isPinned, $publishedAt]);
                    $success = 'Post created!';
                }
                
                $action = 'list';
                
                // Refresh
                $stmt = $pdo->prepare("SELECT * FROM `ngn_2025`.`posts` WHERE entity_type = 'label' AND entity_id = ? ORDER BY COALESCE(published_at, created_at) DESC");
                $stmt->execute([$entity['id']]);
                $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Load post for editing
$editPost = null;
if ($action === 'edit' && $postId && $entity) {
    try {
        $pdo = dashboard_pdo();
        $stmt = $pdo->prepare("SELECT * FROM `ngn_2025`.`posts` WHERE id = ? AND entity_type = 'label' AND entity_id = ?");
        $stmt->execute([$postId, $entity['id']]);
        $editPost = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // ignore
    }
}

$csrf = dashboard_csrf_token();

include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title">Posts & News</h1>
        <p class="page-subtitle">Share updates with your audience</p>
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
            Set up your label profile first.
            <a href="profile.php">Set up profile →</a>
        </div>
        <?php elseif ($action === 'add' || ($action === 'edit' && $editPost)): ?>
        
        <!-- Add/Edit Post Form -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?= $action === 'edit' ? 'Edit Post' : 'Create New Post' ?></h2>
            </div>
            
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                
                <div class="form-group">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" class="form-input" required 
                        value="<?= htmlspecialchars($editPost['title'] ?? '') ?>" placeholder="Post title">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Excerpt</label>
                    <textarea name="excerpt" class="form-textarea" rows="2" placeholder="Brief summary..."><?= htmlspecialchars($editPost['excerpt'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Content</label>
                    <textarea name="content" class="form-textarea" rows="10" placeholder="Full post content..."><?= htmlspecialchars($editPost['content'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Featured Image URL</label>
                    <input type="url" name="featured_image_url" class="form-input" 
                        value="<?= htmlspecialchars($editPost['featured_image_url'] ?? '') ?>" placeholder="https://...">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-input">
                            <option value="draft" <?= ($editPost['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="published" <?= ($editPost['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
                            <option value="archived" <?= ($editPost['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archived</option>
                        </select>
                    </div>
                    <div class="form-group" style="display: flex; align-items: center; padding-top: 28px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="is_pinned" value="1" <?= !empty($editPost['is_pinned']) ? 'checked' : '' ?>>
                            <span>Pin to top of profile</span>
                        </label>
                    </div>
                </div>
                
                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <a href="posts.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-<?= $action === 'edit' ? 'check-lg' : 'plus-lg' ?>"></i>
                        <?= $action === 'edit' ? 'Update Post' : 'Create Post' ?>
                    </button>
                </div>
            </form>
        </div>
        
        <?php else: ?>
        
        <!-- Posts List -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Your Posts (<?= count($posts) ?>)</h2>
                <a href="posts.php?action=add" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> New Post
                </a>
            </div>
            
            <?php if (empty($posts)): ?>
            <div style="text-align: center; padding: 48px 24px; color: var(--text-muted);">
                <i class="bi bi-newspaper" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                <p>No posts yet.</p>
                <a href="posts.php?action=add" class="btn btn-primary" style="margin-top: 16px;">
                    <i class="bi bi-plus-lg"></i> Create First Post
                </a>
            </div>
            <?php else: ?>
            <div style="display: grid; gap: 12px;">
                <?php foreach ($posts as $post): ?>
                <div style="display: flex; align-items: center; gap: 16px; padding: 16px; background: var(--bg-primary); border-radius: 8px;">
                    <div style="width: 80px; height: 60px; border-radius: 4px; background: var(--border); display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0;">
                        <?php if (!empty($post['featured_image_url'])): ?>
                        <img src="<?= htmlspecialchars($post['featured_image_url']) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                        <i class="bi bi-newspaper" style="font-size: 20px; color: var(--text-muted);"></i>
                        <?php endif; ?>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                            <span style="font-weight: 600;"><?= htmlspecialchars($post['title']) ?></span>
                            <?php if (!empty($post['is_pinned'])): ?>
                            <span style="background: var(--accent); color: #000; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 600;">PINNED</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size: 13px; color: var(--text-muted);">
                            <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 500;
                                background: <?= $post['status'] === 'published' ? 'rgba(34,197,94,0.2)' : ($post['status'] === 'archived' ? 'rgba(156,163,175,0.2)' : 'rgba(234,179,8,0.2)') ?>;
                                color: <?= $post['status'] === 'published' ? '#22c55e' : ($post['status'] === 'archived' ? '#9ca3af' : '#eab308') ?>;">
                                <?= ucfirst($post['status'] ?? 'draft') ?>
                            </span>
                            <?php if (!empty($post['published_at'])): ?>
                            · <?= date('M j, Y', strtotime($post['published_at'])) ?>
                            <?php endif; ?>
                            · <?= $post['view_count'] ?? 0 ?> views
                        </div>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <a href="posts.php?action=edit&id=<?= $post['id'] ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
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

