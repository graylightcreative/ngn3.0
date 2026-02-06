<?php
/**
 * Venue Dashboard - Posts/News Management
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('venue');

$user = dashboard_get_user();
$entity = dashboard_get_entity('venue');
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
        $stmt = $pdo->prepare("SELECT * FROM `ngn_2025`.`posts` WHERE entity_type = 'venue' AND entity_id = ? ORDER BY COALESCE(published_at, created_at) DESC");
        $stmt->execute([$entity['id']]);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $entity) {
    if (!dashboard_validate_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
                    $title = trim($_POST['title'] ?? '');
                    $excerpt = trim($_POST['excerpt'] ?? '');
                    $content = trim($_POST['content'] ?? '');
                    $featuredImageUrl = $_POST['current_image'] ?? '';
                    $status = $_POST['status'] ?? 'draft';
                    $isPinned = isset($_POST['is_pinned']) ? 1 : 0;
        
                    // Handle Image Upload
                    if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
                        $uploadDir = dirname(__DIR__, 2) . '/storage/uploads/posts/';
                        if (!is_dir($uploadDir)) {
                            @mkdir($uploadDir, 0775, true);
                        }
        
                        $fileTmpPath = $_FILES['featured_image']['tmp_name'];
                        $fileName = $_FILES['featured_image']['name'];
                        $fileNameCmps = explode(".", $fileName);
                        $fileExtension = strtolower(end($fileNameCmps));
        
                        $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                        $dest_path = $uploadDir . $newFileName;
        
                        if (move_uploaded_file($fileTmpPath, $dest_path)) {
                            $featuredImageUrl = $newFileName;
                        }
                    }
                    
                    if (empty($title)) {            $error = 'Post title is required.';
        } else {
            try {
                $pdo = dashboard_pdo();
                $publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;

                if ($action === 'edit' && $postId) {
                    $stmt = $pdo->prepare("SELECT published_at FROM `ngn_2025`.`posts` WHERE id = ? AND entity_type = 'venue' AND entity_id = ?");
                    $stmt->execute([$postId, $entity['id']]);
                    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($existing && $existing['published_at']) {
                        $publishedAt = $existing['published_at'];
                    } elseif ($status === 'published') {
                        $publishedAt = date('Y-m-d H:i:s');
                    }

                    $stmt = $pdo->prepare("UPDATE `ngn_2025`.`posts` SET
                        title = ?, excerpt = ?, content = ?, featured_image_url = ?, status = ?, is_pinned = ?, published_at = ?
                        WHERE id = ? AND entity_type = 'venue' AND entity_id = ?");
                    $stmt->execute([$title, $excerpt ?: null, $content ?: null, $featuredImageUrl ?: null, $status, $isPinned, $publishedAt, $postId, $entity['id']]);
                    $success = 'Post updated!';
                } else {
                    $slug = dashboard_generate_slug($title, 'post', $entity['id']);
                    $stmt = $pdo->prepare("INSERT INTO `ngn_2025`.`posts`
                        (entity_type, entity_id, slug, title, excerpt, content, featured_image_url, status, is_pinned, published_at)
                        VALUES ('venue', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$entity['id'], $slug, $title, $excerpt ?: null, $content ?: null, $featuredImageUrl ?: null, $status, $isPinned, $publishedAt]);
                    $success = 'Post created!';
                }
                
                $action = 'list';
                $stmt = $pdo->prepare("SELECT * FROM `ngn_2025`.`posts` WHERE entity_type = 'venue' AND entity_id = ? ORDER BY COALESCE(published_at, created_at) DESC");
                $stmt->execute([$entity['id']]);
                $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

$editPost = null;
if ($action === 'edit' && $postId && $entity) {
    try {
        $pdo = dashboard_pdo();
        $stmt = $pdo->prepare("SELECT * FROM `ngn_2025`.`posts` WHERE id = ? AND entity_type = 'venue' AND entity_id = ?");
        $stmt->execute([$postId, $entity['id']]);
        $editPost = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

$csrf = dashboard_csrf_token();
include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title">Posts & News</h1>
        <p class="page-subtitle">Share updates about your venue</p>
    </header>
    
    <div class="page-content">
        <?php if ($success): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
        
        <?php if (!$entity): ?>
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Set up your venue profile first. <a href="profile.php">Set up profile →</a></div>
        <?php elseif ($action === 'add' || ($action === 'edit' && $editPost)): ?>
        
        <div class="card">
            <div class="card-header"><h2 class="card-title"><?= $action === 'edit' ? 'Edit Post' : 'Create New Post' ?></h2></div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="current_image" value="<?= htmlspecialchars($editPost['featured_image_url'] ?? '') ?>">
                <div class="form-group">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" class="form-input" required value="<?= htmlspecialchars($editPost['title'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Featured Image</label>
                    <?php if (!empty($editPost['featured_image_url'])): ?>
                        <div style="margin-bottom: 10px;">
                            <img src="/uploads/posts/<?= htmlspecialchars($editPost['featured_image_url']) ?>" alt="Current Image" style="max-width: 200px; border-radius: 8px;">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="featured_image" class="form-input" accept="image/*">
                </div>
                <div class="form-group">
                    <label class="form-label">Excerpt</label>
                    <textarea name="excerpt" class="form-textarea" rows="2"><?= htmlspecialchars($editPost['excerpt'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Content</label>
                    <textarea name="content" class="form-textarea" rows="10"><?= htmlspecialchars($editPost['content'] ?? '') ?></textarea>
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
                            <span>Pin to top</span>
                        </label>
                    </div>
                </div>
                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <a href="posts.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-<?= $action === 'edit' ? 'check-lg' : 'plus-lg' ?>"></i> <?= $action === 'edit' ? 'Update' : 'Create' ?></button>
                </div>
            </form>
        </div>
        
        <?php else: ?>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Your Posts (<?= count($posts) ?>)</h2>
                <a href="posts.php?action=add" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Post</a>
            </div>
            <?php if (empty($posts)): ?>
            <div style="text-align: center; padding: 48px 24px; color: var(--text-muted);">
                <i class="bi bi-newspaper" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                <p>No posts yet.</p>
                <a href="posts.php?action=add" class="btn btn-primary" style="margin-top: 16px;"><i class="bi bi-plus-lg"></i> Create First Post</a>
            </div>
            <?php else: ?>
            <div style="display: grid; gap: 12px;">
                <?php foreach ($posts as $post): ?>
                <div style="display: flex; align-items: center; gap: 16px; padding: 16px; background: var(--bg-primary); border-radius: 8px;">
                    <div style="flex: 1;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                            <span style="font-weight: 600;"><?= htmlspecialchars($post['title']) ?></span>
                            <?php if (!empty($post['is_pinned'])): ?><span style="background: var(--accent); color: #000; padding: 2px 6px; border-radius: 4px; font-size: 10px;">PINNED</span><?php endif; ?>
                        </div>
                        <div style="font-size: 13px; color: var(--text-muted);">
                            <span style="padding: 2px 8px; border-radius: 4px; font-size: 11px; background: <?= $post['status'] === 'published' ? 'rgba(34,197,94,0.2)' : 'rgba(234,179,8,0.2)' ?>; color: <?= $post['status'] === 'published' ? '#22c55e' : '#eab308' ?>;"><?= ucfirst($post['status'] ?? 'draft') ?></span>
                            <?php if (!empty($post['published_at'])): ?> · <?= date('M j, Y', strtotime($post['published_at'])) ?><?php endif; ?>
                        </div>
                    </div>
                    <a href="posts.php?action=edit&id=<?= $post['id'] ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;"><i class="bi bi-pencil"></i> Edit</a>
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

