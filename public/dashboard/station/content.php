<?php
/**
 * Station Dashboard - BYOS Content Management
 * Handles uploading, managing, and tracking Bring Your Own Songs content
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\Stations\StationContentService;
use NGN\Lib\Stations\StationTierService;

dashboard_require_auth();
dashboard_require_entity_type('station');

$user = dashboard_get_user();
$entity = dashboard_get_entity('station');
$pageTitle = 'BYOS Content';
$currentPage = 'content';

$config = new Config();
$contentService = new StationContentService($config);
$tierService = new StationTierService($config);

$success = $error = null;
$contents = [];
$tier = null;
$filterStatus = $_GET['status'] ?? 'approved';

// Get station tier info
try {
    if ($entity) {
        $tier = $tierService->getStationTier($entity['id']);
        if (!$tier) {
            $error = 'Unable to determine station tier.';
        }
    } else {
        $error = 'Station profile not found. Please set up your profile first.';
    }
} catch (\Throwable $e) {
    $error = 'Failed to load tier information.';
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    if (!$entity) {
        $error = 'Station profile not found.';
    } elseif (!dashboard_validate_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        try {
            // Check if BYOS uploads are allowed
            if (!$entity || !$tierService->hasFeature($entity['id'], 'byos_upload')) {
                $error = 'BYOS uploads are not available on your current tier. Please upgrade.';
            } else {
                // Prepare metadata
                $metadata = [
                    'title' => $_POST['title'] ?? '',
                    'artist_name' => $_POST['artist_name'] ?? ''
                ];

                // Check indemnity acceptance
                $indemnityAccepted = isset($_POST['indemnity_accept']) && $_POST['indemnity_accept'] === '1';

                // Upload content
                $result = $contentService->uploadContent(
                    $entity['id'],
                    $_FILES['file'],
                    $metadata,
                    $indemnityAccepted
                );

                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'] ?? 'Upload failed.';
                }
            }
        } catch (\InvalidArgumentException $e) {
            $error = $e->getMessage();
        } catch (\Throwable $e) {
            $error = 'Failed to upload file: ' . $e->getMessage();
        }
    }
}

// Handle content deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!$entity) {
        $error = 'Station profile not found.';
    } elseif (!dashboard_validate_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        try {
            $contentId = (int)($_POST['content_id'] ?? 0);
            $success_delete = $contentService->deleteContent($contentId, $entity['id']);
            if ($success_delete) {
                $success = 'Content deleted successfully.';
            } else {
                $error = 'Failed to delete content.';
            }
        } catch (\Throwable $e) {
            $error = 'Failed to delete content: ' . $e->getMessage();
        }
    }
}

// Fetch content list
try {
    if ($entity) {
        $result = $contentService->listContent($entity['id'], $filterStatus ?: null, 1, 50);
        if ($result['success']) {
            $contents = $result['items'] ?? [];
        } else {
            $error = 'Failed to load content list.';
        }
    } else {
        $error = 'Station profile not found. Please set up your profile first.';
    }
} catch (\Throwable $e) {
    $error = 'Failed to load content: ' . $e->getMessage();
}

// Calculate usage
$approvedCount = 0;
foreach ($contents as $c) {
    if ($c['status'] === 'approved') {
        $approvedCount++;
    }
}
$tierLimit = $tier['limits']['max_byos_tracks'] ?? 0;

include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title">BYOS Content</h1>
        <p class="page-subtitle">Upload and manage your Bring Your Own Songs library</p>
    </header>

    <div class="page-content">
        <?php if ($success): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Upload Section -->
        <?php if ($entity && $tierService->hasFeature($entity['id'], 'byos_upload')): ?>
        <div class="card" style="margin-bottom: 2rem;">
            <h2 class="text-xl" style="margin-top: 0;">Upload New Track</h2>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?php echo dashboard_csrf_token(); ?>">

                <div style="border: 2px dashed var(--border); padding: 2rem; text-align: center; margin-bottom: 1rem; border-radius: 0.5rem;">
                    <input type="file" name="file" id="file-input" accept="audio/*" required style="display: none;" />
                    <label for="file-input" style="cursor: pointer; display: block;">
                        <p><i class="bi bi-cloud-arrow-up" style="font-size: 2rem;"></i></p>
                        <p>Drop audio file or <strong>browse</strong></p>
                        <p style="font-size: 0.875rem; color: var(--text-muted);">MP3, WAV, FLAC, AAC (max 50MB)</p>
                    </label>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Track Title *</label>
                        <input type="text" name="title" required maxlength="255" class="form-input" />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Artist Name</label>
                        <input type="text" name="artist_name" maxlength="255" class="form-input" />
                    </div>
                </div>

                <div class="form-group" style="padding: 1rem; background: var(--bg-secondary); border-left: 3px solid var(--warning); border-radius: 0.25rem; margin-bottom: 1rem;">
                    <label style="display: flex; align-items: flex-start; gap: 0.5rem;">
                        <input type="checkbox" name="indemnity_accept" value="1" required />
                        <span>
                            <strong>Indemnity Clause</strong>
                            <p style="margin-top: 0.5rem; font-size: 0.875rem; color: var(--text-muted);">
                                I own all rights to this content or have obtained necessary licenses. I indemnify NGN from any copyright claims or third-party infringement.
                            </p>
                        </span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary">Upload Track</button>
            </form>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> BYOS uploads are available on Pro and Elite tiers. <a href="tier.php">Upgrade your station</a> to start uploading your music.
        </div>
        <?php endif; ?>

        <!-- Usage Stats -->
        <?php if ($tierLimit > 0 && $tier): ?>
        <div class="card" style="margin-bottom: 2rem;">
            <h3>Storage Usage</h3>
            <div style="display: grid; gap: 0.5rem;">
                <div style="display: flex; justify-content: space-between; font-size: 0.875rem;">
                    <span><?= $approvedCount ?> of <?= $tierLimit === -1 ? '∞' : $tierLimit ?> tracks</span>
                    <span><?= $tierLimit === -1 ? 'N/A' : round(($approvedCount / $tierLimit) * 100) ?>%</span>
                </div>
                <?php if ($tierLimit > 0): ?>
                <div style="width: 100%; height: 0.5rem; background: var(--bg-secondary); border-radius: 9999px; overflow: hidden;">
                    <div style="width: <?= min(100, ($approvedCount / $tierLimit) * 100) ?>%; height: 100%; background: var(--brand);"></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Content List -->
        <div class="card">
            <h2 class="text-xl" style="margin-top: 0; margin-bottom: 1.5rem;">Your Content</h2>

            <!-- Status Filters -->
            <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
                <a href="?status=approved" class="btn <?= ($filterStatus === 'approved' ? 'btn-primary' : 'btn-secondary') ?>">Approved</a>
                <a href="?status=pending" class="btn <?= ($filterStatus === 'pending' ? 'btn-primary' : 'btn-secondary') ?>">Pending</a>
                <a href="?status=rejected" class="btn <?= ($filterStatus === 'rejected' ? 'btn-primary' : 'btn-secondary') ?>">Rejected</a>
                <a href="content.php" class="btn btn-secondary">All</a>
            </div>

            <!-- Content Grid -->
            <?php if (empty($contents)): ?>
            <div style="padding: 2rem; text-align: center; color: var(--text-muted);">
                <p><i class="bi bi-inbox" style="font-size: 2rem; opacity: 0.5;"></i></p>
                <p>No content uploaded yet.</p>
            </div>
            <?php else: ?>
            <div style="display: grid; gap: 1rem;">
                <?php foreach ($contents as $content): ?>
                <div style="padding: 1rem; border: 1px solid var(--border); border-radius: 0.5rem; display: flex; justify-content: space-between; align-items: flex-start;">
                    <div style="flex: 1;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                            <strong><?= htmlspecialchars($content['title']) ?></strong>
                            <span class="badge badge-<?= $content['status'] === 'approved' ? 'success' : ($content['status'] === 'pending' ? 'warning' : 'error') ?>">
                                <?= ucfirst($content['status']) ?>
                            </span>
                        </div>
                        <p style="font-size: 0.875rem; color: var(--text-muted); margin: 0.25rem 0;">
                            <?= htmlspecialchars($content['artist_name'] ?? 'Unknown Artist') ?>
                        </p>
                        <p style="font-size: 0.75rem; color: var(--text-muted); margin: 0;">
                            <?= date('M d, Y', strtotime($content['created_at'])) ?> • <?= round($content['file_size_bytes'] / 1024 / 1024, 2) ?>MB
                        </p>
                        <?php if (!empty($content['review_notes'])): ?>
                        <p style="font-size: 0.875rem; color: var(--danger); margin-top: 0.5rem; font-style: italic;">
                            Admin: <?= htmlspecialchars(substr($content['review_notes'], 0, 100)) ?>
                        </p>
                        <?php endif; ?>
                    </div>

                    <!-- Actions -->
                    <div style="margin-left: 1rem; display: flex; gap: 0.5rem;">
                        <?php if ($content['status'] === 'approved'): ?>
                        <form method="post" style="display: inline;" onsubmit="return confirm('Delete this content?');">
                            <input type="hidden" name="csrf" value="<?php echo dashboard_csrf_token(); ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="content_id" value="<?php echo $content['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                        <?php endif; ?>
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
