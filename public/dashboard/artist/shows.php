<?php
/**
 * Artist Dashboard - Shows/Events Management
 * (Bible Ch. 9 - Touring Ecosystem: Artist shows and event management)
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('artist');

$user = dashboard_get_user();
$entity = dashboard_get_entity('artist');
$pageTitle = 'shows';
$currentPage = 'shows';

$action = $_GET['action'] ?? 'list';
$showId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$success = $error = null;
$shows = [];
$editShow = null;

// Fetch shows for this artist
if ($entity) {
    try {
        $pdo = dashboard_pdo();
        $stmt = $pdo->prepare("
            SELECT s.*, sl.is_headliner, sl.set_time
            FROM `ngn_2025`.`shows` s
            JOIN `ngn_2025`.`show_lineup` sl ON sl.show_id = s.id
            WHERE sl.artist_id = ?
            ORDER BY s.starts_at DESC
        ");
        $stmt->execute([$entity['id']]);
        $shows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($showId && $action === 'edit') {
            $stmt = $pdo->prepare("
                SELECT s.*, sl.is_headliner, sl.set_time
                FROM `ngn_2025`.`shows` s
                JOIN `ngn_2025`.`show_lineup` sl ON sl.show_id = s.id
                WHERE s.id = ? AND sl.artist_id = ?
            ");
            $stmt->execute([$showId, $entity['id']]);
            $editShow = $stmt->fetch(PDO::FETCH_ASSOC);
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
        $post_action = $_POST['action'] ?? $action;

        if ($post_action === 'delete') {
            $showId_to_delete = (int)($_POST['show_id'] ?? 0);
            if ($showId_to_delete > 0) {
                try {
                    $pdo = dashboard_pdo();
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("DELETE FROM `ngn_2025`.`shows` WHERE id = ?");
                    $stmt->execute([$showId_to_delete]);
                    $pdo->commit();
                    $success = 'Show deleted successfully.';
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = 'Database error: ' . $e->getMessage();
                }
            } else {
                $error = 'Invalid show ID for deletion.';
            }
        } else {
            $title = trim($_POST['title'] ?? '');
            $venueName = trim($_POST['venue_name'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $region = trim($_POST['region'] ?? '');
            $startsAt = $_POST['starts_at'] ?? null;
            $ticketUrl = trim($_POST['ticket_url'] ?? '');
            $isHeadliner = !empty($_POST['is_headliner']);
            
            if (empty($title) || empty($startsAt)) {
                $error = 'Title and date are required.';
            } else {
                try {
                    $pdo = dashboard_pdo();
                    if ($action === 'edit' && $editShow) {
                        // Update show
                        $stmt = $pdo->prepare("
                            UPDATE `ngn_2025`.`shows` SET title = ?, venue_name = ?, city = ?, region = ?,
                            starts_at = ?, ticket_url = ?, updated_at = NOW() WHERE id = ?
                        ");
                        $stmt->execute([$title, $venueName, $city, $region, $startsAt, $ticketUrl, $editShow['id']]);
                        // Update lineup
                        $stmt = $pdo->prepare("UPDATE `ngn_2025`.`show_lineup` SET is_headliner = ? WHERE show_id = ? AND artist_id = ?");
                        $stmt->execute([$isHeadliner ? 1 : 0, $editShow['id'], $entity['id']]);
                        $success = 'Show updated!';
                    } else {
                        // Create show
                        $slug = dashboard_generate_slug($title, 'show', $entity['id']);
                        $stmt = $pdo->prepare("
                            INSERT INTO `ngn_2025`.`shows` (slug, title, venue_name, city, region, starts_at, ticket_url)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$slug, $title, $venueName, $city, $region, $startsAt, $ticketUrl]);
                        $newShowId = $pdo->lastInsertId();
                        // Add to lineup
                        $stmt = $pdo->prepare("INSERT INTO `ngn_2025`.`show_lineup` (show_id, artist_id, is_headliner) VALUES (?, ?, ?)");
                        $stmt->execute([$newShowId, $entity['id'], $isHeadliner ? 1 : 0]);
                        $success = 'Show added!';
                    }
                    
                    $action = 'list';
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
        // Refresh
        $stmt = $pdo->prepare("
            SELECT s.*, sl.is_headliner FROM `ngn_2025`.`shows` s
            JOIN `ngn_2025`.`show_lineup` sl ON sl.show_id = s.id WHERE sl.artist_id = ?
            ORDER BY s.starts_at DESC
        ");
        $stmt->execute([$entity['id']]);
        $shows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$csrf = dashboard_csrf_token();

// Helper function to safely parse dates
function safeStrtotime($dateStr) {
    if (empty($dateStr)) {
        return false;
    }
    $timestamp = strtotime($dateStr);
    return ($timestamp !== false) ? $timestamp : null;
}

// Filter upcoming and past shows with date validation
$upcomingShows = array_filter($shows, function($s) {
    $timestamp = safeStrtotime($s['starts_at']);
    return $timestamp !== null && $timestamp > time();
});

$pastShows = array_filter($shows, function($s) {
    $timestamp = safeStrtotime($s['starts_at']);
    return $timestamp !== null && $timestamp <= time();
});

include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title">Shows & Events</h1>
        <p class="page-subtitle">Manage your tour dates and live performances</p>
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
            Set up your profile first to add shows.
            <a href="profile.php">Set up profile →</a>
        </div>
        <?php elseif ($action === 'add' || ($action === 'edit' && $editShow)): ?>
        
        <!-- Add/Edit Form -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?= $action === 'edit' ? 'Edit Show' : 'Add New Show' ?></h2>
            </div>
            
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                
                <div class="form-group">
                    <label class="form-label">Event Title *</label>
                    <input type="text" name="title" class="form-input" required
                           value="<?= htmlspecialchars($editShow['title'] ?? '') ?>"
                           placeholder="e.g., Summer Tour 2025 - Chicago">
                </div>
                
                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label">Venue Name</label>
                        <input type="text" name="venue_name" class="form-input"
                               value="<?= htmlspecialchars($editShow['venue_name'] ?? '') ?>"
                               placeholder="e.g., House of Blues">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date & Time *</label>
                        <input type="datetime-local" name="starts_at" class="form-input" required
                               value="<?= $editShow ? (($ts = safeStrtotime($editShow['starts_at'])) ? date('Y-m-d\TH:i', $ts) : '') : '' ?>">
                    </div>
                </div>
                
                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label">City</label>
                        <input type="text" name="city" class="form-input"
                               value="<?= htmlspecialchars($editShow['city'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">State/Region</label>
                        <input type="text" name="region" class="form-input"
                               value="<?= htmlspecialchars($editShow['region'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Ticket URL</label>
                    <input type="url" name="ticket_url" class="form-input" placeholder="https://"
                           value="<?= htmlspecialchars($editShow['ticket_url'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="is_headliner" value="1" <?= !empty($editShow['is_headliner']) ? 'checked' : '' ?>>
                        <span>I'm headlining this show</span>
                    </label>
                </div>
                
                <div style="display: flex; gap: 12px;">
                    <a href="shows.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> <?= $action === 'edit' ? 'Update Show' : 'Add Show' ?>
                    </button>
                </div>
            </form>
        </div>
        
        <?php else: ?>
        
        <!-- Shows List -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Upcoming Shows (<?= count($upcomingShows) ?>)</h2>
                <a href="shows.php?action=add" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Add Show
                </a>
            </div>
            
            <?php if (empty($upcomingShows)): ?>
            <div style="text-align: center; padding: 48px 24px; color: var(--text-muted);">
                <i class="bi bi-calendar-x" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                <p>No upcoming shows. Add your next gig!</p>
            </div>
            <?php else: ?>
            <div style="display: grid; gap: 12px;">
                <?php foreach ($upcomingShows as $show): ?>
                <div style="display: flex; align-items: center; gap: 16px; padding: 16px; background: var(--bg-primary); border-radius: 8px;">
                    <div style="width: 60px; text-align: center; flex-shrink: 0;">
                        <?php $ts = safeStrtotime($show['starts_at']); if ($ts): ?>
                        <div style="font-size: 24px; font-weight: 700; color: var(--brand);"><?= date('d', $ts) ?></div>
                        <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;"><?= date('M', $ts) ?></div>
                        <?php else: ?>
                        <div style="font-size: 12px; color: var(--text-muted);">—</div>
                        <?php endif; ?>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 600; margin-bottom: 4px;">
                            <?= htmlspecialchars($show['title']) ?>
                            <?php if ($show['is_headliner']): ?>
                            <span style="font-size: 10px; background: var(--brand); color: #000; padding: 2px 6px; border-radius: 3px; margin-left: 8px;">HEADLINER</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size: 13px; color: var(--text-muted);">
                            <?= htmlspecialchars($show['venue_name'] ?? '') ?>
                            <?php if ($show['city']): ?> · <?= htmlspecialchars($show['city']) ?><?php endif; ?>
                            <?php if ($show['region']): ?>, <?= htmlspecialchars($show['region']) ?><?php endif; ?>
                        </div>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <?php if ($show['ticket_url']): ?>
                        <a href="<?= htmlspecialchars($show['ticket_url']) ?>" target="_blank" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">
                            <i class="bi bi-ticket"></i> Tickets
                        </a>
                        <?php endif; ?>
                        <a href="shows.php?action=edit&id=<?= $show['id'] ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this show?');" style="display: inline;">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="show_id" value="<?= $show['id'] ?>">
                            <button type="submit" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($pastShows)): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Past Shows (<?= count($pastShows) ?>)</h2>
            </div>
            <div style="display: grid; gap: 8px; opacity: 0.7;">
                <?php foreach (array_slice($pastShows, 0, 5) as $show): ?>
                <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--bg-primary); border-radius: 6px;">
                    <div style="font-size: 13px; color: var(--text-muted); width: 80px;">
                        <?php $ts = safeStrtotime($show['starts_at']); echo ($ts ? date('M j, Y', $ts) : '—'); ?>
                    </div>
                    <div style="flex: 1; font-size: 14px;"><?= htmlspecialchars($show['title']) ?></div>
                    <div style="font-size: 13px; color: var(--text-muted);"><?= htmlspecialchars($show['city'] ?? '') ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>
</div>

</body>
</html>

