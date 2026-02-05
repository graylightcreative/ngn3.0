<?php
/**
 * Artist Dashboard - Posts Management
 * (Bible Ch. 22 - Social Feed and Engagement: Content posting and community interaction)
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('artist');

$user = dashboard_get_user();
$entity = dashboard_get_entity('artist');
$pageTitle = 'posts';
$currentPage = 'posts';

$action = $_GET['action'] ?? 'list';
$postId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$success = $error = null;
$posts = [];
$editPost = null;
$tiers = [];

if ($entity) {
    try {
        $stmt = $pdo->prepare("SELECT id, slug, title, body as content, status, published_at, required_tier_id FROM `ngn_2025`.`posts` WHERE author_id = ? ORDER BY COALESCE(published_at, created_at) DESC");
        $stmt->execute([$entity['id']]);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT id, name, price_monthly FROM `ngn_2025`.`fan_subscription_tiers` WHERE artist_id = ? ORDER BY price_monthly ASC");
        $stmt->execute([$entity['id']]);
        $tiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($postId && $action === 'edit') {
            $stmt = $pdo->prepare("SELECT id, slug, title, body as content, status, published_at, required_tier_id FROM `ngn_2025`.`posts` WHERE id = ? AND author_id = ?");
            $stmt->execute([$postId, $entity['id']]);
            $editPost = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log('Artist posts fetch error: ' . $e->getMessage());
        // Tables may not exist yet or database connection issue
        // $posts and $editPost remain empty arrays
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $entity) {
    if (!dashboard_validate_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $post_action = $_POST['action'] ?? $action;

        if ($post_action === 'delete') {
            $postId_to_delete = (int)($_POST['post_id'] ?? 0);
            if ($postId_to_delete > 0) {
                try {
                    $pdo = dashboard_pdo();
                    $stmt = $pdo->prepare("DELETE FROM `ngn_2025`.`posts` WHERE id = ? AND author_id = ?");
                    $stmt->execute([$postId_to_delete, $entity['id']]);
                    if ($stmt->rowCount() > 0) {
                        $success = 'Post deleted successfully.';
                    } else {
                        $error = 'Post not found or you do not have permission to delete it.';
                    }
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            } else {
                $error = 'Invalid post ID for deletion.';
            }
        } else {
            $title = trim($_POST['title'] ?? '');
            $body = trim($_POST['body'] ?? '');
            $status = $_POST['status'] ?? 'draft';
            $required_tier_id = !empty($_POST['required_tier_id']) ? (int)$_POST['required_tier_id'] : null;

            if (empty($title)) {
                $error = 'Post title is required.';
            } else {
                try {
                    $pdo = dashboard_pdo();
                    $publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;

                    if ($action === 'edit' && $postId) {
                        $stmt = $pdo->prepare("UPDATE `ngn_2025`.`posts` SET title = ?, body = ?, status = ?, published_at = ?, required_tier_id = ? WHERE id = ? AND author_id = ?");
                        $stmt->execute([$title, $content, $status, $publishedAt, $required_tier_id, $postId, $entity['id']]);
                        $success = 'Post updated!';
                    } else {
                        $slug = dashboard_generate_slug($title, 'post', $entity['id']);
                        $stmt = $pdo->prepare("INSERT INTO `ngn_2025`.`posts` (author_id, slug, title, body, status, published_at, required_tier_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$entity['id'], $slug, $title, $content, $status, $publishedAt, $required_tier_id]);
                        $success = 'Post created!';
                    }
                    $action = 'list';
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }

        // Refresh list
        $stmt = $pdo->prepare("SELECT id, slug, title, body as content, status, published_at, required_tier_id FROM `ngn_2025`.`posts` WHERE author_id = ? ORDER BY COALESCE(published_at, created_at) DESC");
        $stmt->execute([$entity['id']]);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$csrf = dashboard_csrf_token();
include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title">Posts</h1>
        <p class="page-subtitle">Share updates with your fans</p>
    </header>
    
    <div class="page-content">
        <?php if ($success): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
        
        <?php if ($action === 'add' || ($action === 'edit' && $editPost)): ?>
        
        <div class="card">
            <div class="card-header"><h2 class="card-title"><?= $action === 'edit' ? 'Edit Post' : 'Create New Post' ?></h2></div>
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <div class="form-group">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" class="form-input" required value="<?= htmlspecialchars($editPost['title'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Content</label>
                    <textarea name="content" class="form-textarea" rows="10"><?= htmlspecialchars($editPost['content'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Exclusive Content</label>
                    <select name="required_tier_id" class="form-input">
                        <option value="">Public (no tier required)</option>
                        <?php foreach ($tiers as $tier): ?>
                        <option value="<?= $tier['id'] ?>" <?= ($editPost['required_tier_id'] ?? '') == $tier['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tier['name']) ?> ($<?= htmlspecialchars($tier['price_monthly']) ?>/month)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-input">
                        <option value="draft" <?= ($editPost['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="published" <?= ($editPost['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
                    </select>
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
                        <div style="font-weight: 600;"><?= htmlspecialchars($post['title']) ?></div>
                        <div style="font-size: 13px; color: var(--text-muted);">
                            <span style="padding: 2px 8px; border-radius: 4px; font-size: 11px; background: <?= $post['status'] === 'published' ? 'rgba(34,197,94,0.2)' : 'rgba(234,179,8,0.2)' ?>; color: <?= $post['status'] === 'published' ? '#22c55e' : '#eab308' ?>;"><?= ucfirst($post['status'] ?? 'draft') ?></span>
                            <?php if ($post['required_tier_id']): ?>
                                <span class="badge badge-primary">Exclusive</span>
                            <?php endif; ?>
                            <?php if (!empty($post['published_at'])): ?> · <?= date('M j, Y', strtotime($post['published_at'])) ?><?php endif; ?>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <a href="posts.php?action=edit&id=<?= $post['id'] ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;"><i class="bi bi-pencil"></i> Edit</a>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this post?');">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                            <button type="submit" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;"><i class="bi bi-trash"></i></button>
                        </form>
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
