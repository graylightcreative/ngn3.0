<?php
/**
 * Station Dashboard - Posts/News Management
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('station');

$user = dashboard_get_user();
$entity = dashboard_get_entity('station');
$pageTitle = 'posts';
$currentPage = 'posts';

$action = $_GET['action'] ?? 'list';
$postId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$success = $error = null;
$posts = [];

if ($entity) {
    try {
        $pdo = dashboard_pdo();
        $stmt = $pdo->prepare("SELECT * FROM `ngn_2025`.`posts` WHERE entity_type = 'station' AND entity_id = ? ORDER BY COALESCE(published_at, created_at) DESC");
        $stmt->execute([$entity['id']]);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
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
                    $stmt = $pdo->prepare("DELETE FROM `ngn_2025`.`posts` WHERE id = ? AND entity_type = 'station' AND entity_id = ?");
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
                    $publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;

                    if ($action === 'edit' && $postId) {
                        $stmt = $pdo->prepare("SELECT published_at FROM `ngn_2025`.`posts` WHERE id = ? AND entity_type = 'station' AND entity_id = ?");
                        $stmt->execute([$postId, $entity['id']]);
                        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($existing && $existing['published_at']) {
                            $publishedAt = $existing['published_at'];
                        } elseif ($status === 'published') {
                            $publishedAt = date('Y-m-d H:i:s');
                        }

                        $stmt = $pdo->prepare("UPDATE `ngn_2025`.`posts` SET
                            title = ?, excerpt = ?, content = ?, featured_image_url = ?, status = ?, is_pinned = ?, published_at = ?
                            WHERE id = ? AND entity_type = 'station' AND entity_id = ?");
                        $stmt->execute([$title, $excerpt ?: null, $content ?: null, $featuredImageUrl ?: null, $status, $isPinned, $publishedAt, $postId, $entity['id']]);
                        $success = 'Post updated!';
                    } else {
                        $slug = dashboard_generate_slug($title, 'post', $entity['id']);
                        $stmt = $pdo->prepare("INSERT INTO `ngn_2025`.`posts`
                            (entity_type, entity_id, slug, title, excerpt, content, featured_image_url, status, is_pinned, published_at)
                            VALUES ('station', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$entity['id'], $slug, $title, $excerpt ?: null, $content ?: null, $featuredImageUrl ?: null, $status, $isPinned, $publishedAt]);
                        $success = 'Post created!';
                    }
                    
                    $action = 'list';
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
        // After any POST action, reload the posts list
        $stmt = $pdo->prepare("SELECT * FROM `ngn_2025`.`posts` WHERE entity_type = 'station' AND entity_id = ? ORDER BY COALESCE(published_at, created_at) DESC");
        $stmt->execute([$entity['id']]);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$editPost = null;
if ($action === 'edit' && $postId && $entity) {
    try {
        $pdo = dashboard_pdo();
        $stmt = $pdo->prepare("SELECT * FROM `ngn_2025`.`posts` WHERE id = ? AND entity_type = 'station' AND entity_id = ?");
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
        <p class="page-subtitle">Share updates about your station</p>
    </header>
    
    <div class="page-content">
        <?php if ($success): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
        
        <?php if (!$entity): ?>
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Set up your station profile first. <a href="profile.php">Set up profile →</a></div>
        <?php elseif ($action === 'add' || ($action === 'edit' && $editPost)): ?>
        
        <div class="card">
            <div class="card-header"><h2 class="card-title"><?= $action === 'edit' ? 'Edit Post' : 'Create New Post' ?></h2></div>
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <div class="form-group">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" class="form-input" required value="<?= htmlspecialchars($editPost['title'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Excerpt</label>
                    <textarea name="excerpt" class="form-textarea" rows="2"><?= htmlspecialchars($editPost['excerpt'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Content</label>
                    <textarea name="content" class="form-textarea" rows="10"><?= htmlspecialchars($editPost['content'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Featured Image URL</label>
                    <input type="url" name="featured_image_url" class="form-input" value="<?= htmlspecialchars($editPost['featured_image_url'] ?? '') ?>">
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
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <a href="posts.php?action=edit&id=<?= $post['id'] ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;"><i class="bi bi-pencil"></i> Edit</a>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this post?');">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                            <button type="submit" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;"><i class="bi bi-trash"></i></button>
                            <?php /* TODO: Replace browser confirm() with a custom modal for better UX */ ?>
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

<!-- Custom Delete Confirmation Modal for Posts -->
<div id="deletePostConfirmationModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Confirm Post Deletion</h2>
            <button class="modal-close" id="closeDeletePostModal">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete "<strong id="postTitleToDelete"></strong>"? This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" id="cancelDeletePost">Cancel</button>
            <button class="btn btn-danger" id="confirmDeletePost">Delete</button>
        </div>
    </div>
</div>

<!-- Undo Notification for Posts -->
<div id="undoNotification" style="display: none; position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background-color: #333; color: #fff; padding: 15px 20px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.2); z-index: 2000;">
    Post deleted successfully. <button id="undoDeleteBtn" class="btn btn-secondary btn-sm" style="margin-left: 10px; background-color: #555;">Undo</button>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const deletePostConfirmationModal = document.getElementById('deletePostConfirmationModal');
    const closeDeletePostModal = document.getElementById('closeDeletePostModal');
    const cancelDeletePost = document.getElementById('cancelDeletePost');
    const confirmDeletePost = document.getElementById('confirmDeletePost');
    const postTitleToDelete = document.getElementById('postTitleToDelete');
    let formToDeletePost = null;
    let postRowToDelete = null; // To store the HTML element of the post being deleted

    const undoNotification = document.getElementById('undoNotification');
    const undoDeleteBtn = document.getElementById('undoDeleteBtn');
    let undoTimeout = null;
    let lastDeletedPostId = null;
    let lastDeletedPostHtml = null;

    document.querySelectorAll('form').forEach(form => {
        if (form.querySelector('input[name="action"][value="delete"]')) {
            form.onsubmit = function(event) {
                event.preventDefault();
                formToDeletePost = this;
                lastDeletedPostId = this.querySelector('input[name="post_id"]').value;
                postRowToDelete = this.closest('div[style*="display: flex; align-items: center; gap: 16px;"]'); // Assuming this is the row element
                const titleElement = postRowToDelete ? postRowToDelete.querySelector('span[style*="font-weight: 600;"]').textContent : 'this post';
                postTitleToDelete.textContent = titleElement;
                deletePostConfirmationModal.style.display = 'flex';
            };
        }
    });

    closeDeletePostModal.addEventListener('click', function() {
        deletePostConfirmationModal.style.display = 'none';
    });

    cancelDeletePost.addEventListener('click', function() {
        deletePostConfirmationModal.style.display = 'none';
    });

    confirmDeletePost.addEventListener('click', async function() {
        if (formToDeletePost) {
            deletePostConfirmationModal.style.display = 'none';

            const formData = new FormData(formToDeletePost);
            const postId = formData.get('post_id');
            const csrfToken = formData.get('csrf');

            try {
                const response = await fetch('posts.php', { // AJAX to the same page
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest', // Indicate AJAX request
                        'Content-Type': 'application/x-www-form-urlencoded' // For form data
                    },
                    body: new URLSearchParams(formData).toString()
                });
                const result = await response.text(); // Get raw text to check for success messages or redirects

                // Check for success message in the response (a basic way to detect success in a non-API file)
                if (result.includes('Post deleted successfully.')) {
                    if (postRowToDelete) {
                        lastDeletedPostHtml = postRowToDelete.outerHTML; // Store HTML for undo
                        postRowToDelete.remove();
                        // Show undo notification
                        undoNotification.style.display = 'block';
                        clearTimeout(undoTimeout);
                        undoTimeout = setTimeout(() => {
                            undoNotification.style.display = 'none';
                        }, 5000); // Hide after 5 seconds
                    }
                } else {
                    // Assuming an error occurred, re-evaluate response to get error message
                    alert('Error deleting post.'); // Generic error
                    console.error('Delete response:', result);
                }
            } catch (error) {
                console.error('Fetch error:', error);
                alert('An error occurred during deletion.');
            }
        }
    });

    undoDeleteBtn.addEventListener('click', function() {
        // Simulate undo (for now, just dismiss the notification)
        undoNotification.style.display = 'none';
        clearTimeout(undoTimeout);
        // In a real implementation, this would send an AJAX request to "undelete" the post
        // and re-insert lastDeletedPostHtml into the DOM.
        alert('Undo functionality is not yet fully implemented, but would restore the post here.');
        // If we had a mechanism to restore the post visually:
        // document.querySelector('.card .grid').append(document.createRange().createContextualFragment(lastDeletedPostHtml));
    });

    deletePostConfirmationModal.addEventListener('click', function(event) {
        if (event.target === deletePostConfirmationModal) {
            deletePostConfirmationModal.style.display = 'none';
        }
    });
});
</script>

