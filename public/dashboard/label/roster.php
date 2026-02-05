<?php
/**
 * Label Dashboard - Roster Management
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('label');

$user = dashboard_get_user();
$entity = dashboard_get_entity('label');
$pageTitle = 'Roster';
$currentPage = 'roster';

$action = $_GET['action'] ?? 'list';
$artistId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$success = $error = null;
$roster = [];

// Fetch roster artists
if ($entity) {
    try {
        $pdo = dashboard_pdo();
        $stmt = $pdo->prepare("SELECT * FROM artists WHERE label_id = ? ORDER BY name");
        $stmt->execute([$entity['id']]);
        $roster = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Table may not exist
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $entity) {
    if (!dashboard_validate_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        
        if (empty($name)) {
            $error = 'Artist name is required.';
        } else {
            try {
                $pdo = dashboard_pdo();
                $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name)) . '-' . time();
                
                $stmt = $pdo->prepare("INSERT INTO artists (slug, name, bio, label_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$slug, $name, $bio, $entity['id']]);
                
                $success = 'Artist added to roster!';
                $action = 'list';
                
                // Refresh
                $stmt = $pdo->prepare("SELECT * FROM artists WHERE label_id = ? ORDER BY name");
                $stmt->execute([$entity['id']]);
                $roster = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        <h1 class="page-title">Artist Roster</h1>
        <p class="page-subtitle">Manage artists signed to your label</p>
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
        <?php elseif ($action === 'add'): ?>
        
        <!-- Add Artist Form -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Add Artist to Roster</h2>
            </div>
            
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                
                <div class="form-group">
                    <label class="form-label">Artist/Band Name *</label>
                    <input type="text" name="name" class="form-input" required placeholder="Enter artist name">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Bio</label>
                    <textarea name="bio" class="form-textarea" placeholder="Brief artist bio..."></textarea>
                </div>
                
                <div style="display: flex; gap: 12px;">
                    <a href="roster.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Add Artist
                    </button>
                </div>
            </form>
        </div>
        
        <?php else: ?>
        
        <!-- Roster List -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Your Artists (<?= count($roster) ?>)</h2>
                <a href="roster.php?action=add" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Add Artist
                </a>
            </div>
            
            <?php if (empty($roster)): ?>
            <div style="text-align: center; padding: 48px 24px; color: var(--text-muted);">
                <i class="bi bi-people" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                <p>No artists on your roster yet.</p>
                <a href="roster.php?action=add" class="btn btn-primary" style="margin-top: 16px;">
                    <i class="bi bi-plus-lg"></i> Add First Artist
                </a>
            </div>
            <?php else: ?>
            <div style="display: grid; gap: 12px;">
                <?php foreach ($roster as $artist): ?>
                <div style="display: flex; align-items: center; gap: 16px; padding: 16px; background: var(--bg-primary); border-radius: 8px;">
                    <div style="width: 56px; height: 56px; border-radius: 50%; background: var(--border); display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0;">
                        <?php if (!empty($artist['image_url'])): ?>
                        <img src="<?= htmlspecialchars($artist['image_url']) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                        <i class="bi bi-person" style="font-size: 24px; color: var(--text-muted);"></i>
                        <?php endif; ?>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 600; margin-bottom: 4px;"><?= htmlspecialchars($artist['name']) ?></div>
                        <div style="font-size: 13px; color: var(--text-muted);">
                            Rank: #<?= $artist['rank'] ?? '--' ?> · Score: <?= $artist['score'] ?? '--' ?>
                        </div>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <a href="../artist/?id=<?= $artist['id'] ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">
                            <i class="bi bi-eye"></i> View
                        </a>
                        <a href="releases.php?artist_id=<?= $artist['id'] ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">
                            <i class="bi bi-disc"></i> Releases
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

