<?php
/**
 * Station Dashboard - PLN Playlist Management
 * Create and manage station playlists with BYOS + catalog mixing
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\Stations\StationPlaylistService;
use NGN\Lib\Stations\StationTierService;

dashboard_require_auth();
dashboard_require_entity_type('station');

$user = dashboard_get_user();
$entity = dashboard_get_entity('station');
$pageTitle = 'Playlists';
$currentPage = 'playlists';

$config = new Config();
$playlistService = new StationPlaylistService($config);
$tierService = new StationTierService($config);

$playlists = [];
$error = $success = null;
$currentTier = null;

// Get current tier
try {
    $currentTier = $tierService->getStationTier($entity['id']);
} catch (\Throwable $e) {
    $error = 'Failed to load tier information.';
}

// Handle playlist creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    if (!dashboard_validate_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        try {
            // Check tier access
            if (!$tierService->hasFeature($entity['id'], 'pln_playlists')) {
                $error = 'PLN playlists are not available on your current tier. Please upgrade.';
            } else {
                $title = $_POST['title'] ?? '';
                if (empty($title)) {
                    $error = 'Playlist title is required.';
                } else {
                    $geoRestrictions = null;
                    if (!empty($_POST['geo_restriction_type'])) {
                        $type = $_POST['geo_restriction_type'];
                        $territories = explode(',', $_POST['geo_territories'] ?? '');
                        $territories = array_map('trim', array_filter($territories));
                        if (!empty($territories)) {
                            $geoRestrictions = [
                                'type' => $type,
                                'territories' => $territories
                            ];
                        }
                    }

                    $result = $playlistService->createPlaylist(
                        $entity['id'],
                        $title,
                        [],
                        $geoRestrictions
                    );

                    if ($result['success']) {
                        $success = 'Playlist created successfully.';
                    } else {
                        $error = $result['message'] ?? 'Failed to create playlist.';
                    }
                }
            }
        } catch (\Throwable $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Handle playlist deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!dashboard_validate_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        try {
            $playlistId = (int)($_POST['playlist_id'] ?? 0);
            $result = $playlistService->deletePlaylist($playlistId, $entity['id']);
            if ($result) {
                $success = 'Playlist deleted.';
            } else {
                $error = 'Failed to delete playlist.';
            }
        } catch (\Throwable $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Fetch playlists
try {
    $result = $playlistService->listPlaylists($entity['id']);
    if ($result['success']) {
        $playlists = $result['items'] ?? [];
    } else {
        $error = 'Failed to load playlists: ' . ($result['message'] ?? 'Unknown error');
    }
} catch (\Throwable $e) {
    $error = 'Error loading playlists: ' . $e->getMessage();
}

include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title">PLN Playlists</h1>
        <p class="page-subtitle">Create and manage station playlists mixing catalog and your own content</p>
    </header>

    <div class="page-content">
        <?php if ($success): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Create Playlist Section -->
        <?php if ($tierService->hasFeature($entity['id'], 'pln_playlists')): ?>
        <div class="card" style="margin-bottom: 2rem;">
            <h2 class="text-xl" style="margin-top: 0;">Create New Playlist</h2>
            <form method="post">
                <input type="hidden" name="csrf" value="<?php echo dashboard_csrf_token(); ?>">
                <input type="hidden" name="action" value="create">

                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Playlist Title *</label>
                        <input type="text" name="title" required maxlength="255" class="form-input" placeholder="e.g., Monday Night Mix" />
                    </div>
                </div>

                <div style="padding: 1rem; background: var(--bg-secondary); border-radius: 0.5rem; margin-bottom: 1rem;">
                    <h3 style="margin: 0 0 1rem 0; font-size: 1rem;">Geo-Blocking (Optional)</h3>

                    <div class="form-row">
                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Restriction Type</label>
                            <select name="geo_restriction_type" class="form-input">
                                <option value="">None</option>
                                <option value="allow">Allow List (specific territories)</option>
                                <option value="block">Block List (exclude territories)</option>
                            </select>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Territories (comma-separated codes)</label>
                            <input type="text" name="geo_territories" class="form-input" placeholder="US, CA, GB, AU" />
                            <p style="margin: 0.5rem 0 0 0; font-size: 0.875rem; color: var(--text-muted);">ISO 3166-1 alpha-2 codes</p>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Create Playlist</button>
            </form>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> PLN playlists are available on Pro and Elite tiers. <a href="tier.php">Upgrade your station</a> to start creating playlists.
        </div>
        <?php endif; ?>

        <!-- Playlists List -->
        <div class="card">
            <h2 class="text-xl" style="margin-top: 0; margin-bottom: 1rem;">Your Playlists</h2>

            <?php if (empty($playlists)): ?>
            <div style="padding: 2rem; text-align: center; color: var(--text-muted);">
                <p><i class="bi bi-music-note-list" style="font-size: 2rem; opacity: 0.5;"></i></p>
                <p>No playlists yet. Create one to get started.</p>
            </div>
            <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1rem;">
                <?php foreach ($playlists as $playlist): ?>
                <div style="padding: 1rem; border: 1px solid var(--border); border-radius: 0.5rem;">
                    <h3 style="margin: 0 0 0.5rem 0;"><?= htmlspecialchars($playlist['title']) ?></h3>

                    <div style="margin-bottom: 1rem; font-size: 0.875rem; color: var(--text-muted);">
                        <p style="margin: 0.25rem 0;">
                            <i class="bi bi-music-note"></i>
                            <?= isset($playlist['items']) ? count($playlist['items']) : '0' ?> items
                        </p>
                        <p style="margin: 0.25rem 0;">
                            <i class="bi bi-calendar"></i>
                            Created <?= date('M d, Y', strtotime($playlist['created_at'])) ?>
                        </p>
                        <?php if (!empty($playlist['geo_restrictions'])): ?>
                        <p style="margin: 0.25rem 0; color: var(--warning);">
                            <i class="bi bi-geo-alt"></i>
                            <?= htmlspecialchars($playlist['geo_restrictions']['type']) ?> list active
                        </p>
                        <?php endif; ?>
                    </div>

                    <!-- Actions -->
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="playlist-edit.php?id=<?php echo $playlist['id']; ?>" class="btn btn-secondary btn-sm">Edit</a>
                        <form method="post" style="display: inline;" onsubmit="return confirm('Delete this playlist?');">
                            <input type="hidden" name="csrf" value="<?php echo dashboard_csrf_token(); ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="playlist_id" value="<?php echo $playlist['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
