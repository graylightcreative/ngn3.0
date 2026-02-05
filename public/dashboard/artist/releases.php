<?php
/**
 * Artist Dashboard - Releases Management
 * (Bible Ch. 2 & 7 - Core Data Model & Product Specs: Music releases and distribution)
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('artist');

$user = dashboard_get_user();
$entity = dashboard_get_entity('artist');
$pageTitle = 'releases';
$currentPage = 'releases';

$action = $_GET['action'] ?? 'list';
$releaseId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$success = $error = null;
$releases = [];
$editRelease = null;

// Fetch releases
if ($entity) {
    try {
        $pdo = dashboard_pdo();
        $stmt = $pdo->prepare("SELECT id, title, released_at, created_at, cover_url FROM `ngn_2025`.`releases` WHERE artist_id = ? ORDER BY released_at DESC, created_at DESC");
        $stmt->execute([$entity['id']]);
        $releases = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($releaseId && $action === 'edit') {
            $stmt = $pdo->prepare("SELECT id, slug, title, description, released_at, cover_url, listening_url, watch_url, label_id FROM `ngn_2025`.`releases` WHERE id = ? AND artist_id = ?");
            $stmt->execute([$releaseId, $entity['id']]);
            $editRelease = $stmt->fetch(PDO::FETCH_ASSOC);
        }
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
        $releasedAt = $_POST['released_at'] ?? null;
        $releaseType = $_POST['release_type'] ?? 'album';
        
        if (empty($title)) {
            $error = 'Title is required.';
        } else {
            try {
                $pdo = dashboard_pdo();

                if ($action === 'edit' && $editRelease) {
                    $stmt = $pdo->prepare("UPDATE `ngn_2025`.`releases` SET title = ?, description = ?, released_at = ?, cover_url = ?, listening_url = ?, watch_url = ?, label_id = ?, updated_at = NOW() WHERE id = ? AND artist_id = ?");
                    $stmt->execute([$title, $_POST['description'] ?? null, $releasedAt ?: null, $_POST['cover_url'] ?? null, $_POST['listening_url'] ?? null, $_POST['watch_url'] ?? null, $_POST['label_id'] ?? null, $editRelease['id'], $entity['id']]);
                    $success = 'Release updated!';
                } else {
                    $slug = dashboard_generate_slug($title, 'release', $entity['id']);
                    $stmt = $pdo->prepare("INSERT INTO `ngn_2025`.`releases` (artist_id, slug, title, description, released_at, cover_url, listening_url, watch_url, label_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$entity['id'], $slug, $title, $_POST['description'] ?? null, $releasedAt ?: null, $_POST['cover_url'] ?? null, $_POST['listening_url'] ?? null, $_POST['watch_url'] ?? null, $_POST['label_id'] ?? null]);
                    $success = 'Release added!';
                }
                
                // Refresh list
                $stmt = $pdo->prepare("SELECT id, title, released_at, created_at, cover_url FROM `ngn_2025`.`releases` WHERE artist_id = ? ORDER BY released_at DESC");
                $stmt->execute([$entity['id']]);
                $releases = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $action = 'list';
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Handle delete
if ($action === 'delete' && $releaseId && $entity) {
    try {
        $pdo = dashboard_pdo();
        $stmt = $pdo->prepare("DELETE FROM `ngn_2025`.`releases` WHERE id = ? AND artist_id = ?");
        $stmt->execute([$releaseId, $entity['id']]);
        $success = 'Release deleted.';
        header('Location: releases.php?deleted=1');
        exit;
    } catch (PDOException $e) {
        $error = 'Could not delete release.';
    }
}

if (isset($_GET['deleted'])) $success = 'Release deleted.';

$csrf = dashboard_csrf_token();

include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title">Releases</h1>
        <p class="page-subtitle">Manage your albums, EPs, and singles</p>
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
            Your artist profile needs to be set up before you can add releases.
            <a href="profile.php">Set up profile →</a>
        </div>
        <?php elseif ($action === 'add' || ($action === 'edit' && $editRelease)): ?>
        
        <!-- Add/Edit Form -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?= $action === 'edit' ? 'Edit Release' : 'Add New Release' ?></h2>
            </div>
            
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                
                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-input" required
                               value="<?= htmlspecialchars($editRelease['title'] ?? '') ?>"
                               placeholder="Album or EP title">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Release Date</label>
                        <input type="date" name="released_at" class="form-input"
                               value="<?= htmlspecialchars($editRelease['released_at'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Release Type</label>
                    <select name="release_type" class="form-select">
                        <option value="album">Album</option>
                        <option value="ep">EP</option>
                        <option value="single">Single</option>
                        <option value="compilation">Compilation</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="5"><?= htmlspecialchars($editRelease['description'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Label</label>
                    <select name="label_id" class="form-select">
                        <option value="">Select label...</option>
                        <?php
                            $labels = [];
                            try {
                                $stmt = $pdo->query("SELECT id, name FROM `ngn_2025`.`labels` ORDER BY name ASC");
                                $labels = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            } catch (PDOException $e) { /* ignore */ }
                        ?>
                        <?php foreach ($labels as $label): ?>
                        <option value="<?= $label['id'] ?>" <?= ($editRelease['label_id'] ?? '') == $label['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Listening URL (Spotify, Apple Music, etc.)</label>
                    <input type="url" name="listening_url" class="form-input"
                           value="<?= htmlspecialchars($editRelease['listening_url'] ?? '') ?>"
                           placeholder="https://spotify.com/album/...">
                </div>

                <div class="form-group">
                    <label class="form-label">Watch URL (YouTube, Vimeo, etc.)</label>
                    <input type="url" name="watch_url" class="form-input"
                           value="<?= htmlspecialchars($editRelease['watch_url'] ?? '') ?>"
                           placeholder="https://youtube.com/watch?v=...">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Cover Art URL</label>
                    <input type="url" name="cover_url" class="form-input"
                           value="<?= htmlspecialchars($editRelease['cover_url'] ?? '') ?>"
                           placeholder="https://example.com/cover.jpg">
                </div>
                
                <div style="width: 200px; height: 200px; border-radius: 8px; background: var(--bg-primary); border: 2px dashed var(--border); display: flex; align-items: center; justify-content: center; cursor: pointer; margin-top: 12px;">
                        <?php if (!empty($editRelease['cover_url'])): ?>
                        <img src="<?= htmlspecialchars($editRelease['cover_url']) ?>" alt="Cover Art" style="width: 100%; height: 100%; object-fit: cover; border-radius: 6px;">
                        <?php else: ?>
                        <div style="text-align: center; color: var(--text-muted);">
                            <i class="bi bi-image" style="font-size: 32px;"></i>
                            <div style="font-size: 12px; margin-top: 8px;">No Cover</div>
                        </div>
                        <?php endif; ?>
                    </div>
                
                <div style="display: flex; gap: 12px;">
                    <a href="releases.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> <?= $action === 'edit' ? 'Update Release' : 'Add Release' ?>
                    </button>
                </div>
            </form>
        </div>
        
        <?php else: ?>
        
        <!-- Releases List -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Your Releases (<?= count($releases) ?>)</h2>
                <a href="releases.php?action=add" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Add Release
                </a>
            </div>
            
            <?php if (empty($releases)): ?>
            <div style="text-align: center; padding: 48px 24px; color: var(--text-muted);">
                <i class="bi bi-disc" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                <p>No releases yet. Add your first album or EP!</p>
                <a href="releases.php?action=add" class="btn btn-primary" style="margin-top: 16px;">
                    <i class="bi bi-plus-lg"></i> Add Release
                </a>
            </div>
            <?php else: ?>
            <div style="display: grid; gap: 16px;">
                <?php foreach ($releases as $release): ?>
                <div style="display: flex; align-items: center; gap: 16px; padding: 16px; background: var(--bg-primary); border-radius: 8px;">
                    <div style="width: 64px; height: 64px; border-radius: 6px; background: var(--border); display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0;">
                        <?php if (!empty($release['cover_url'])): ?>
                        <img src="<?= htmlspecialchars($release['cover_url']) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                        <i class="bi bi-disc" style="font-size: 24px; color: var(--text-muted);"></i>
                        <?php endif; ?>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 600; margin-bottom: 4px;"><?= htmlspecialchars($release['title']) ?></div>
                        <div style="font-size: 13px; color: var(--text-muted);">
                            <?= $release['released_at'] ? date('M j, Y', strtotime($release['released_at'])) : 'No release date' ?>
                        </div>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <a href="songs.php?release_id=<?= $release['id'] ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">
                            <i class="bi bi-music-note-list"></i> Tracks
                        </a>
                        <a href="releases.php?action=edit&id=<?= $release['id'] ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                        <a href="releases.php?action=delete&id=<?= $release['id'] ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px; color: var(--danger);" onclick="return confirm('Delete this release?')">
                            <i class="bi bi-trash"></i>
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

