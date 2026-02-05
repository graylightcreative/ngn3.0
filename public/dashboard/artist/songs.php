<?php
/**
 * Artist Dashboard - Songs Management
 * (Bible Ch. 2 & 7 - Core Data Model & Product Specs: Track/song metadata and rights)
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('artist');

$user = dashboard_get_user();
$entity = dashboard_get_entity('artist');
$pageTitle = 'Songs';
$currentPage = 'songs';

$releaseId = isset($_GET['release_id']) ? (int)$_GET['release_id'] : null;
$action = $_GET['action'] ?? 'list';
$songId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$success = $error = null;
$songs = [];
$releases = [];
$editSong = null;

// Fetch releases and songs
if ($entity) {
    try {
        $pdo = dashboard_pdo();
        
        // Get releases for dropdown
        $stmt = $pdo->prepare("SELECT id, title FROM `ngn_2025`.`releases` WHERE artist_id = ? ORDER BY released_at DESC");
        $stmt->execute([$entity['id']]);
        $releases = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get songs
        if ($releaseId) {
            $stmt = $pdo->prepare("
                SELECT t.*, r.title as release_title 
                FROM `ngn_2025`.`tracks` t 
                JOIN `ngn_2025`.`releases` r ON r.id = t.release_id 
                WHERE t.release_id = ? AND r.artist_id = ?
                ORDER BY t.track_number
            ");
            $stmt->execute([$releaseId, $entity['id']]);
        } else {
            $stmt = $pdo->prepare("
                SELECT t.*, r.title as release_title 
                FROM `ngn_2025`.`tracks` t 
                JOIN `ngn_2025`.`releases` r ON r.id = t.release_id 
                WHERE r.artist_id = ?
                ORDER BY r.released_at DESC, t.track_number
            ");
            $stmt->execute([$entity['id']]);
        }
        $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($songId && $action === 'edit') {
            $stmt = $pdo->prepare("
                SELECT t.* FROM `ngn_2025`.`tracks` t 
                JOIN `ngn_2025`.`releases` r ON r.id = t.release_id 
                WHERE t.id = ? AND r.artist_id = ?
            ");
            $stmt->execute([$songId, $entity['id']]);
            $editSong = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        // Tables may not exist
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $entity) {
    if (!dashboard_validate_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $postReleaseId = (int)($_POST['release_id'] ?? 0);
        $trackNumber = (int)($_POST['track_number'] ?? 1);
        $duration = (int)($_POST['duration'] ?? 0);
        $isrc = trim($_POST['isrc'] ?? '');
        
        if (empty($title) || !$postReleaseId) {
            $error = 'Title and release are required.';
        } else {
            try {
                $pdo = dashboard_pdo();
                $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title)) . '-' . time();
                
                if ($action === 'edit' && $editSong) {
                    $stmt = $pdo->prepare("
                        UPDATE `ngn_2025`.`tracks` SET title = ?, release_id = ?, track_number = ?, 
                        duration_seconds = ?, isrc = ?, updated_at = NOW() WHERE id = ?
                    ");
                    $stmt->execute([$title, $postReleaseId, $trackNumber, $duration, $isrc, $editSong['id']]);
                    $success = 'Song updated!';
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO `ngn_2025`.`tracks` (release_id, slug, title, track_number, duration_seconds, isrc, artist_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$postReleaseId, $slug, $title, $trackNumber, $duration, $isrc, $entity['id']]);
                    $success = 'Song added!';
                }
                
                $action = 'list';
                // Refresh
                            $stmt = $pdo->prepare("
                                SELECT t.*, r.title as release_title FROM `ngn_2025`.`tracks` t 
                                JOIN `ngn_2025`.`releases` r ON r.id = t.release_id WHERE r.artist_id = ?
                                ORDER BY r.released_at DESC, t.track_number
                            ");                $stmt->execute([$entity['id']]);
                $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

$csrf = dashboard_csrf_token();

include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title">Songs</h1>
        <p class="page-subtitle">Manage your tracks across all releases</p>
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
            Set up your profile first.
            <a href="profile.php">Set up profile →</a>
        </div>
        <?php elseif (empty($releases) && $action !== 'add'): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            Add a release first before adding songs.
            <a href="releases.php?action=add">Add release →</a>
        </div>
        <?php elseif ($action === 'add' || ($action === 'edit' && $editSong)): ?>
        
        <!-- Add/Edit Form -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?= $action === 'edit' ? 'Edit Song' : 'Add New Song' ?></h2>
            </div>
            
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                
                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label">Song Title *</label>
                        <input type="text" name="title" class="form-input" required
                               value="<?= htmlspecialchars($editSong['title'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Release *</label>
                        <select name="release_id" class="form-select" required>
                            <option value="">Select release...</option>
                            <?php foreach ($releases as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= ($editSong['release_id'] ?? $releaseId) == $r['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r['title']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-3">
                    <div class="form-group">
                        <label class="form-label">Track #</label>
                        <input type="number" name="track_number" class="form-input" min="1"
                               value="<?= htmlspecialchars($editSong['track_number'] ?? '1') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Duration (seconds)</label>
                        <input type="number" name="duration" class="form-input" min="0"
                               value="<?= htmlspecialchars($editSong['duration_seconds'] ?? '') ?>"
                               placeholder="e.g., 240">
                    </div>
                    <div class="form-group">
                        <label class="form-label">ISRC</label>
                        <input type="text" name="isrc" class="form-input"
                               value="<?= htmlspecialchars($editSong['isrc'] ?? '') ?>"
                               placeholder="e.g., USRC12345678">
                    </div>
                </div>
                
                <div style="display: flex; gap: 12px;">
                    <a href="songs.php<?= $releaseId ? "?release_id=$releaseId" : '' ?>" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> <?= $action === 'edit' ? 'Update Song' : 'Add Song' ?>
                    </button>
                </div>
            </form>
        </div>
        
        <?php else: ?>
        
        <!-- Songs List -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    Your Songs (<?= count($songs) ?>)
                    <?php if ($releaseId): ?>
                    <a href="songs.php" style="font-size: 12px; font-weight: 400; color: var(--text-muted); margin-left: 8px;">View all</a>
                    <?php endif; ?>
                </h2>
                <a href="songs.php?action=add<?= $releaseId ? "&release_id=$releaseId" : '' ?>" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Add Song
                </a>
            </div>
            
            <?php if (empty($songs)): ?>
            <div style="text-align: center; padding: 48px 24px; color: var(--text-muted);">
                <i class="bi bi-music-note-beamed" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                <p>No songs yet. Add tracks to your releases!</p>
            </div>
            <?php else: ?>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <th style="text-align: left; padding: 12px 8px; font-size: 12px; color: var(--text-muted); font-weight: 500;">#</th>
                        <th style="text-align: left; padding: 12px 8px; font-size: 12px; color: var(--text-muted); font-weight: 500;">TITLE</th>
                        <th style="text-align: left; padding: 12px 8px; font-size: 12px; color: var(--text-muted); font-weight: 500;">RELEASE</th>
                        <th style="text-align: right; padding: 12px 8px; font-size: 12px; color: var(--text-muted); font-weight: 500;">DURATION</th>
                        <th style="text-align: right; padding: 12px 8px; font-size: 12px; color: var(--text-muted); font-weight: 500;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($songs as $song): ?>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 12px 8px; color: var(--text-muted);"><?= $song['track_number'] ?></td>
                        <td style="padding: 12px 8px; font-weight: 500;"><?= htmlspecialchars($song['title']) ?></td>
                        <td style="padding: 12px 8px; color: var(--text-secondary); font-size: 13px;"><?= htmlspecialchars($song['release_title']) ?></td>
                        <td style="padding: 12px 8px; text-align: right; color: var(--text-muted); font-size: 13px;">
                            <?php if ($song['duration_seconds']): ?>
                            <?= floor($song['duration_seconds'] / 60) ?>:<?= str_pad($song['duration_seconds'] % 60, 2, '0', STR_PAD_LEFT) ?>
                            <?php else: ?>
                            --:--
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px 8px; text-align: right;">
                            <a href="songs.php?action=edit&id=<?= $song['id'] ?>" class="btn btn-secondary" style="padding: 4px 8px; font-size: 11px;">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        
        <?php endif; ?>
    </div>
</div>

</body>
</html>

