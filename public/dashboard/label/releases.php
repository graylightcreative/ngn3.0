<?php
/**
 * Label Dashboard - Releases Management
 * Manage releases for roster artists
 */
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('label');

$user = dashboard_get_user();
$entity = dashboard_get_entity('label');
$pageTitle = 'releases';
$currentPage = 'releases';

$action = $_GET['action'] ?? 'list';
$releaseId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$artistId = isset($_GET['artist_id']) ? (int)$_GET['artist_id'] : null;
$success = $error = null;
$releases = [];
$roster = [];
$selectedArtist = null;

// Fetch roster artists
if ($entity) {
    try {
        $pdo = dashboard_pdo();
        $stmt = $pdo->prepare("SELECT id, name FROM `ngn_2025`.`artists` WHERE label_id = ? ORDER BY name");
        $stmt->execute([$entity['id']]);
        $roster = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get selected artist
        if ($artistId) {
            foreach ($roster as $a) {
                if ((int)$a['id'] === $artistId) {
                    $selectedArtist = $a;
                    break;
                }
            }
        }
        
        // Fetch releases
        if ($artistId && $selectedArtist) {
            $stmt = $pdo->prepare("SELECT * FROM `ngn_2025`.`releases` WHERE artist_id = ? ORDER BY release_date DESC, title");
            $stmt->execute([$artistId]);
            $releases = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif (!$artistId) {
            // All releases for label's roster
            $artistIds = array_column($roster, 'id');
            if (!empty($artistIds)) {
                $placeholders = implode(',', array_fill(0, count($artistIds), '?'));
                $stmt = $pdo->prepare("SELECT r.*, a.name as artist_name FROM `ngn_2025`.`releases` r 
                    JOIN `ngn_2025`.`artists` a ON a.id = r.artist_id 
                    WHERE r.artist_id IN ($placeholders) ORDER BY r.release_date DESC, r.title");
                $stmt->execute($artistIds);
                $releases = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
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
        $postArtistId = (int)($_POST['artist_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $type = $_POST['type'] ?? 'album';
        $releaseDate = $_POST['release_date'] ?? null;
        $genre = trim($_POST['genre'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $coverUrl = trim($_POST['cover_url'] ?? '');
        $spotifyUrl = trim($_POST['spotify_url'] ?? '');
        $appleMusicUrl = trim($_POST['apple_music_url'] ?? '');
        $bandcampUrl = trim($_POST['bandcamp_url'] ?? '');
        $youtubeUrl = trim($_POST['youtube_url'] ?? '');
        
        // Validate artist belongs to label
        $validArtist = false;
        foreach ($roster as $a) {
            if ((int)$a['id'] === $postArtistId) {
                $validArtist = true;
                break;
            }
        }
        
        if (empty($title)) {
            $error = 'Release title is required.';
        } elseif (!$validArtist) {
            $error = 'Invalid artist selected.';
        } else {
            try {
                $pdo = dashboard_pdo();
                $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title)) . '-' . time();
                
                if ($action === 'edit' && $releaseId) {
                    $stmt = $pdo->prepare("UPDATE `ngn_2025`.`releases` SET 
                        title = ?, type = ?, release_date = ?, genre = ?, description = ?,
                        cover_url = ?, spotify_url = ?, apple_music_url = ?, bandcamp_url = ?, youtube_url = ?
                        WHERE id = ? AND artist_id = ?");
                    $stmt->execute([$title, $type, $releaseDate ?: null, $genre ?: null, $description ?: null,
                        $coverUrl ?: null, $spotifyUrl ?: null, $appleMusicUrl ?: null, $bandcampUrl ?: null, $youtubeUrl ?: null,
                        $releaseId, $postArtistId]);
                    $success = 'Release updated!';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO `ngn_2025`.`releases` 
                        (artist_id, label_id, slug, title, type, release_date, genre, description, cover_url, spotify_url, apple_music_url, bandcamp_url, youtube_url) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$postArtistId, $entity['id'], $slug, $title, $type, $releaseDate ?: null, $genre ?: null, $description ?: null,
                        $coverUrl ?: null, $spotifyUrl ?: null, $appleMusicUrl ?: null, $bandcampUrl ?: null, $youtubeUrl ?: null]);
                    $success = 'Release added!';
                }
                
                $action = 'list';
                $artistId = $postArtistId;
                
                // Refresh releases
                $stmt = $pdo->prepare("SELECT * FROM `ngn_2025`.`releases` WHERE artist_id = ? ORDER BY release_date DESC, title");
                $stmt->execute([$artistId]);
                $releases = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Load release for editing
$editRelease = null;
if ($action === 'edit' && $releaseId) {
    try {
        $pdo = dashboard_pdo();
        $artistIds = array_column($roster, 'id');
        if (!empty($artistIds)) {
            $placeholders = implode(',', array_fill(0, count($artistIds), '?'));
            $stmt = $pdo->prepare("SELECT * FROM `ngn_2025`.`releases` WHERE id = ? AND artist_id IN ($placeholders)");
            $params = array_merge([$releaseId], $artistIds);
            $stmt->execute($params);
            $editRelease = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($editRelease) {
                $artistId = (int)$editRelease['artist_id'];
            }
        }
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
        <h1 class="page-title">Releases</h1>
        <p class="page-subtitle">Manage releases for your roster artists</p>
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
        <?php elseif (empty($roster)): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            Add artists to your roster first.
            <a href="roster.php?action=add">Add artist →</a>
        </div>
        <?php elseif ($action === 'add' || ($action === 'edit' && $editRelease)): ?>
        
        <!-- Add/Edit Release Form -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?= $action === 'edit' ? 'Edit Release' : 'Add New Release' ?></h2>
            </div>
            
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                
                <div class="form-group">
                    <label class="form-label">Artist *</label>
                    <select name="artist_id" class="form-input" required>
                        <option value="">Select artist...</option>
                        <?php foreach ($roster as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= ($editRelease && (int)$editRelease['artist_id'] === (int)$a['id']) || $artistId === (int)$a['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-input" required 
                            value="<?= htmlspecialchars($editRelease['title'] ?? '') ?>" placeholder="Album/EP/Single title">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-input">
                            <option value="album" <?= ($editRelease['type'] ?? '') === 'album' ? 'selected' : '' ?>>Album</option>
                            <option value="ep" <?= ($editRelease['type'] ?? '') === 'ep' ? 'selected' : '' ?>>EP</option>
                            <option value="single" <?= ($editRelease['type'] ?? '') === 'single' ? 'selected' : '' ?>>Single</option>
                            <option value="compilation" <?= ($editRelease['type'] ?? '') === 'compilation' ? 'selected' : '' ?>>Compilation</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Release Date</label>
                        <input type="date" name="release_date" class="form-input" 
                            value="<?= htmlspecialchars($editRelease['release_date'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Genre</label>
                        <input type="text" name="genre" class="form-input" 
                            value="<?= htmlspecialchars($editRelease['genre'] ?? '') ?>" placeholder="e.g., Metal, Rock, Hardcore">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" placeholder="Release description..."><?= htmlspecialchars($editRelease['description'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Cover Art URL</label>
                    <input type="url" name="cover_url" class="form-input" 
                        value="<?= htmlspecialchars($editRelease['cover_url'] ?? '') ?>" placeholder="https://...">
                </div>
                
                <h3 style="margin: 24px 0 16px; font-size: 16px;">Streaming Links</h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Spotify URL</label>
                        <input type="url" name="spotify_url" class="form-input" 
                            value="<?= htmlspecialchars($editRelease['spotify_url'] ?? '') ?>" placeholder="https://open.spotify.com/...">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Apple Music URL</label>
                        <input type="url" name="apple_music_url" class="form-input" 
                            value="<?= htmlspecialchars($editRelease['apple_music_url'] ?? '') ?>" placeholder="https://music.apple.com/...">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Bandcamp URL</label>
                        <input type="url" name="bandcamp_url" class="form-input" 
                            value="<?= htmlspecialchars($editRelease['bandcamp_url'] ?? '') ?>" placeholder="https://....bandcamp.com/...">
                    </div>
                    <div class="form-group">
                        <label class="form-label">YouTube URL</label>
                        <input type="url" name="youtube_url" class="form-input" 
                            value="<?= htmlspecialchars($editRelease['youtube_url'] ?? '') ?>" placeholder="https://youtube.com/...">
                    </div>
                </div>
                
                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <a href="releases.php<?= $artistId ? '?artist_id='.$artistId : '' ?>" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-<?= $action === 'edit' ? 'check-lg' : 'plus-lg' ?>"></i>
                        <?= $action === 'edit' ? 'Update Release' : 'Add Release' ?>
                    </button>
                </div>
            </form>
        </div>
        
        <?php else: ?>
        
        <!-- Artist Filter -->
        <div class="card" style="margin-bottom: 16px;">
            <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                <span style="font-weight: 500;">Filter by artist:</span>
                <a href="releases.php" class="btn <?= !$artistId ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 6px 12px; font-size: 13px;">All</a>
                <?php foreach ($roster as $a): ?>
                <a href="releases.php?artist_id=<?= $a['id'] ?>" class="btn <?= $artistId === (int)$a['id'] ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 6px 12px; font-size: 13px;">
                    <?= htmlspecialchars($a['name']) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Releases List -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <?= $selectedArtist ? htmlspecialchars($selectedArtist['name']) . ' Releases' : 'All Releases' ?>
                    (<?= count($releases) ?>)
                </h2>
                <a href="releases.php?action=add<?= $artistId ? '&artist_id='.$artistId : '' ?>" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Add Release
                </a>
            </div>
            
            <?php if (empty($releases)): ?>
            <div style="text-align: center; padding: 48px 24px; color: var(--text-muted);">
                <i class="bi bi-disc" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                <p>No releases yet.</p>
                <a href="releases.php?action=add<?= $artistId ? '&artist_id='.$artistId : '' ?>" class="btn btn-primary" style="margin-top: 16px;">
                    <i class="bi bi-plus-lg"></i> Add First Release
                </a>
            </div>
            <?php else: ?>
            <div style="display: grid; gap: 12px;">
                <?php foreach ($releases as $release): ?>
                <div style="display: flex; align-items: center; gap: 16px; padding: 16px; background: var(--bg-primary); border-radius: 8px;">
                    <div style="width: 64px; height: 64px; border-radius: 4px; background: var(--border); display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0;">
                        <?php if (!empty($release['cover_url'])): ?>
                        <img src="<?= htmlspecialchars($release['cover_url']) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                        <i class="bi bi-disc" style="font-size: 24px; color: var(--text-muted);"></i>
                        <?php endif; ?>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 600; margin-bottom: 4px;"><?= htmlspecialchars($release['title']) ?></div>
                        <div style="font-size: 13px; color: var(--text-muted);">
                            <?php if (!$artistId && !empty($release['artist_name'])): ?>
                            <?= htmlspecialchars($release['artist_name']) ?> · 
                            <?php endif; ?>
                            <?= ucfirst($release['type'] ?? 'album') ?>
                            <?php if (!empty($release['release_date'])): ?>
                            · <?= date('M j, Y', strtotime($release['release_date'])) ?>
                            <?php endif; ?>
                            <?php if (!empty($release['genre'])): ?>
                            · <?= htmlspecialchars($release['genre']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <a href="releases.php?action=edit&id=<?= $release['id'] ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">
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

