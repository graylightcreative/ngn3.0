<?php
/**
 * Artist Dashboard - Tours Management
 * (Bible Ch. 10 - Touring Ecosystem: Multi-date tour management)
 */
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('artist');

$user = dashboard_get_user();
$entity = dashboard_get_entity('artist');
$pageTitle = 'Tours';
$currentPage = 'tours';

$action = $_GET['action'] ?? 'list';
$tourId = $_GET['id'] ?? null;
$success = $error = null;
$tours = [];
$editTour = null;
$tourStats = null;
$tourDates = [];

// Fetch tours for this artist
if ($entity) {
    try {
        $pdo = dashboard_pdo();

        // Get all tours for artist
        $stmt = $pdo->prepare("
            SELECT t.*
            FROM ngn_2025.tours t
            WHERE t.artist_id = ?
            ORDER BY t.tour_starts_at DESC
        ");
        $stmt->execute([$entity['id']]);
        $tours = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Parse JSON fields
        foreach ($tours as &$tour) {
            $tour['genres_json'] = json_decode($tour['genres_json'] ?? '[]', true);
            $tour['tags_json'] = json_decode($tour['tags_json'] ?? '[]', true);
        }

        // Load edit tour if requested
        if ($tourId && $action === 'edit') {
            $stmt = $pdo->prepare("
                SELECT t.*
                FROM ngn_2025.tours t
                WHERE t.id = ? AND t.artist_id = ?
            ");
            $stmt->execute([$tourId, $entity['id']]);
            $editTour = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($editTour) {
                $editTour['genres_json'] = json_decode($editTour['genres_json'] ?? '[]', true);
                $editTour['tags_json'] = json_decode($editTour['tags_json'] ?? '[]', true);

                // Load tour dates
                $stmt = $pdo->prepare("
                    SELECT td.*, e.title as event_title, e.starts_at as event_date,
                           e.city, e.region, e.tickets_sold, e.total_capacity,
                           v.name as venue_name
                    FROM ngn_2025.tour_dates td
                    LEFT JOIN ngn_2025.events e ON e.id = td.event_id
                    LEFT JOIN ngn_2025.venues v ON v.id = e.venue_id
                    WHERE td.tour_id = ?
                    ORDER BY td.position ASC
                ");
                $stmt->execute([$tourId]);
                $tourDates = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Calculate stats
                $totalCapacity = array_sum(array_map(fn($d) => $d['total_capacity'] ?? 0, $tourDates));
                $totalSold = array_sum(array_map(fn($d) => $d['tickets_sold'] ?? 0, $tourDates));
                $tourStats = [
                    'total_dates' => count($tourDates),
                    'total_capacity' => $totalCapacity,
                    'total_sold' => $totalSold,
                    'sellout_percent' => $totalCapacity > 0 ? round(($totalSold / $totalCapacity) * 100, 1) : 0
                ];
            }
        }
    } catch (PDOException $e) {
        error_log('Tour fetch error: ' . $e->getMessage());
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $entity) {
    if (!dashboard_validate_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $tourStartsAt = $_POST['tour_starts_at'] ?? null;
        $tourEndsAt = $_POST['tour_ends_at'] ?? null;
        $status = $_POST['status'] ?? 'planning';

        if (empty($name) || empty($tourStartsAt) || empty($tourEndsAt)) {
            $error = 'Tour name and dates are required.';
        } else {
            try {
                $pdo = dashboard_pdo();

                if ($action === 'edit' && $editTour) {
                    // Update tour
                    $stmt = $pdo->prepare("
                        UPDATE ngn_2025.tours
                        SET name = ?, description = ?, tour_starts_at = ?,
                            tour_ends_at = ?, status = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $description, $tourStartsAt, $tourEndsAt, $status, $editTour['id']]);
                    $success = 'Tour updated!';
                } else {
                    // Create new tour
                    $tourId = \Ramsey\Uuid\Uuid::uuid4()->toString();
                    $slug = dashboard_generate_slug($name, 'tour', $entity['id']);

                    $stmt = $pdo->prepare("
                        INSERT INTO ngn_2025.tours (
                            id, slug, name, description,
                            artist_id, tour_starts_at, tour_ends_at,
                            status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$tourId, $slug, $name, $description, $entity['id'], $tourStartsAt, $tourEndsAt, $status]);
                    $success = 'Tour created! Add events to build your tour.';
                }

                $action = 'list';

                // Refresh tours
                $stmt = $pdo->prepare("
                    SELECT t.* FROM ngn_2025.tours t
                    WHERE t.artist_id = ?
                    ORDER BY t.tour_starts_at DESC
                ");
                $stmt->execute([$entity['id']]);
                $tours = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($tours as &$tour) {
                    $tour['genres_json'] = json_decode($tour['genres_json'] ?? '[]', true);
                    $tour['tags_json'] = json_decode($tour['tags_json'] ?? '[]', true);
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Handle status changes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    if (!dashboard_validate_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $tourId = $_POST['tour_id'] ?? null;
        $actionType = $_POST['action_type'];

        if ($tourId) {
            try {
                $pdo = dashboard_pdo();

                if ($actionType === 'announce') {
                    $stmt = $pdo->prepare("
                        UPDATE ngn_2025.tours
                        SET status = 'announced', announced_at = COALESCE(announced_at, NOW())
                        WHERE id = ? AND artist_id = ?
                    ");
                    $stmt->execute([$tourId, $entity['id']]);
                    $success = 'Tour announced!';
                } elseif ($actionType === 'cancel') {
                    $reason = $_POST['cancel_reason'] ?? null;
                    $stmt = $pdo->prepare("
                        UPDATE ngn_2025.tours
                        SET status = 'cancelled', cancelled_reason = ?
                        WHERE id = ? AND artist_id = ?
                    ");
                    $stmt->execute([$reason, $tourId, $entity['id']]);
                    $success = 'Tour cancelled.';
                }

                // Refresh
                $stmt = $pdo->prepare("SELECT t.* FROM ngn_2025.tours t WHERE t.artist_id = ? ORDER BY t.tour_starts_at DESC");
                $stmt->execute([$entity['id']]);
                $tours = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($tours as &$tour) {
                    $tour['genres_json'] = json_decode($tour['genres_json'] ?? '[]', true);
                    $tour['tags_json'] = json_decode($tour['tags_json'] ?? '[]', true);
                }
            } catch (PDOException $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}

$csrf = dashboard_csrf_token();

// Helper function for safe date parsing
function safeStrtotime($dateStr) {
    if (empty($dateStr)) return false;
    $timestamp = strtotime($dateStr);
    return ($timestamp !== false) ? $timestamp : null;
}

// Separate active and past tours
$activeTours = array_filter($tours, fn($t) => $t['status'] !== 'completed' && $t['status'] !== 'cancelled');
$pastTours = array_filter($tours, fn($t) => $t['status'] === 'completed' || $t['status'] === 'cancelled');

include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title">Tours</h1>
        <p class="page-subtitle">Create and manage multi-date tours</p>
    </header>

    <div class="page-content">
        <?php if ($success): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Create/Edit Tour Modal -->
        <?php if ($action === 'create' || ($action === 'edit' && $editTour)): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?= $action === 'edit' ? 'Edit Tour' : 'Create New Tour' ?></h2>
            </div>
            <form method="POST" style="padding: 20px; display: grid; gap: 16px;">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action_type" value="">

                <div style="display: grid; gap: 8px;">
                    <label for="name" style="font-weight: 500;">Tour Name</label>
                    <input type="text" id="name" name="name" required
                           value="<?= $editTour ? htmlspecialchars($editTour['name']) : '' ?>"
                           placeholder="e.g., Summer 2026 Tour"
                           style="padding: 8px; border: 1px solid var(--border-color); border-radius: 6px;">
                </div>

                <div style="display: grid; gap: 8px;">
                    <label for="description" style="font-weight: 500;">Description</label>
                    <textarea id="description" name="description" rows="4" placeholder="Tour story and details..."
                              style="padding: 8px; border: 1px solid var(--border-color); border-radius: 6px;">
<?= $editTour ? htmlspecialchars($editTour['description'] ?? '') : '' ?></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div style="display: grid; gap: 8px;">
                        <label for="tour_starts_at" style="font-weight: 500;">Tour Starts</label>
                        <input type="date" id="tour_starts_at" name="tour_starts_at" required
                               value="<?= $editTour ? htmlspecialchars(substr($editTour['tour_starts_at'], 0, 10)) : '' ?>">
                    </div>
                    <div style="display: grid; gap: 8px;">
                        <label for="tour_ends_at" style="font-weight: 500;">Tour Ends</label>
                        <input type="date" id="tour_ends_at" name="tour_ends_at" required
                               value="<?= $editTour ? htmlspecialchars(substr($editTour['tour_ends_at'], 0, 10)) : '' ?>">
                    </div>
                </div>

                <div style="display: grid; gap: 8px;">
                    <label for="status" style="font-weight: 500;">Status</label>
                    <select id="status" name="status" style="padding: 8px; border: 1px solid var(--border-color); border-radius: 6px;">
                        <option value="planning" <?= (!$editTour || $editTour['status'] === 'planning') ? 'selected' : '' ?>>Planning</option>
                        <option value="announced" <?= ($editTour && $editTour['status'] === 'announced') ? 'selected' : '' ?>>Announced</option>
                        <option value="on_sale" <?= ($editTour && $editTour['status'] === 'on_sale') ? 'selected' : '' ?>>On Sale</option>
                    </select>
                </div>

                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check"></i> <?= $action === 'edit' ? 'Update' : 'Create' ?> Tour
                    </button>
                    <a href="tours.php" class="btn btn-secondary"><i class="bi bi-x"></i> Cancel</a>
                </div>
            </form>
        </div>

        <!-- View/Manage Tour Dates -->
        <?php if ($action === 'edit' && $editTour): ?>
        <div class="card" style="margin-top: 20px;">
            <div class="card-header">
                <h2 class="card-title">Tour Dates (<?= count($tourDates) ?>)</h2>
            </div>
            <div style="padding: 20px; display: grid; gap: 12px;">
                <?php if ($tourDates): ?>
                    <?php foreach ($tourDates as $idx => $date): ?>
                    <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--bg-primary); border-radius: 6px;">
                        <div style="font-weight: 600; color: var(--primary); min-width: 40px;">
                            Day <?= $idx + 1 ?>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 500;"><?= htmlspecialchars($date['event_title'] ?? 'Unknown Event') ?></div>
                            <div style="font-size: 13px; color: var(--text-muted);">
                                <?= htmlspecialchars($date['venue_name'] ?? '') ?> · <?= htmlspecialchars($date['city'] ?? '') ?>
                                <?php if ($date['event_date']): ?> · <?= date('M j, Y', strtotime($date['event_date'])) ?><?php endif; ?>
                            </div>
                        </div>
                        <div style="text-align: right; font-size: 13px;">
                            <div><?= ($date['tickets_sold'] ?? 0) ?>/<?= ($date['total_capacity'] ?? 0) ?> sold</div>
                            <?php if ($date['distance_from_previous_km']): ?>
                            <div style="color: var(--text-muted);"><?= round($date['distance_from_previous_km']) ?>km away</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: var(--text-muted);">No events added to this tour yet. Add events via the API or create them first.</p>
                <?php endif; ?>
            </div>

            <?php if ($tourStats): ?>
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; padding: 20px; border-top: 1px solid var(--border-color);">
                <div style="text-align: center;">
                    <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Dates</div>
                    <div style="font-size: 24px; font-weight: 600;"><?= $tourStats['total_dates'] ?></div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Capacity</div>
                    <div style="font-size: 24px; font-weight: 600;"><?= number_format($tourStats['total_capacity']) ?></div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Sold</div>
                    <div style="font-size: 24px; font-weight: 600;"><?= number_format($tourStats['total_sold']) ?></div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Sellout</div>
                    <div style="font-size: 24px; font-weight: 600;"><?= $tourStats['sellout_percent'] ?>%</div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- Tours List View -->
        <div style="display: flex; gap: 12px; margin-bottom: 20px;">
            <a href="tours.php?action=create" class="btn btn-primary">
                <i class="bi bi-plus"></i> Create Tour
            </a>
        </div>

        <?php if (!empty($activeTours)): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Active Tours (<?= count($activeTours) ?>)</h2>
            </div>
            <div style="display: grid; gap: 8px; padding: 12px;">
                <?php foreach ($activeTours as $tour): ?>
                <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--bg-primary); border-radius: 6px;">
                    <div style="flex: 1;">
                        <div style="font-weight: 500; margin-bottom: 4px;">
                            <?= htmlspecialchars($tour['name']) ?>
                        </div>
                        <div style="font-size: 13px; color: var(--text-muted);">
                            <?php
                            $start = safeStrtotime($tour['tour_starts_at']);
                            $end = safeStrtotime($tour['tour_ends_at']);
                            if ($start && $end) {
                                echo date('M j', $start) . ' - ' . date('M j, Y', $end);
                            }
                            ?>
                            · <span class="badge" style="background: var(--primary); color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; text-transform: uppercase;">
                                <?= htmlspecialchars($tour['status']) ?>
                            </span>
                            · <?= $tour['total_dates'] ?? 0 ?> dates
                        </div>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <a href="tours.php?action=edit&id=<?= htmlspecialchars($tour['id']) ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">
                            <i class="bi bi-pencil"></i> Manage
                        </a>
                        <?php if ($tour['status'] === 'planning'): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="action_type" value="announce">
                            <input type="hidden" name="tour_id" value="<?= htmlspecialchars($tour['id']) ?>">
                            <button type="submit" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;">
                                <i class="bi bi-megaphone"></i> Announce
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($pastTours)): ?>
        <div class="card" style="margin-top: 20px; opacity: 0.7;">
            <div class="card-header">
                <h2 class="card-title">Past Tours (<?= count($pastTours) ?>)</h2>
            </div>
            <div style="display: grid; gap: 8px; padding: 12px;">
                <?php foreach ($pastTours as $tour): ?>
                <div style="display: flex; align-items: center; gap: 12px; padding: 12px;">
                    <div style="flex: 1;">
                        <div style="font-weight: 500;"><?= htmlspecialchars($tour['name']) ?></div>
                        <div style="font-size: 13px; color: var(--text-muted);">
                            <?php
                            $start = safeStrtotime($tour['tour_starts_at']);
                            $end = safeStrtotime($tour['tour_ends_at']);
                            if ($start && $end) {
                                echo date('M j, Y', $start) . ' - ' . date('M j, Y', $end);
                            }
                            ?>
                            · <span class="badge" style="background: var(--text-muted); color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; text-transform: uppercase;">
                                <?= htmlspecialchars($tour['status']) ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($tours)): ?>
        <div class="card" style="text-align: center; padding: 40px;">
            <p style="color: var(--text-muted); margin-bottom: 16px;">No tours yet. Create one to get started!</p>
            <a href="tours.php?action=create" class="btn btn-primary">
                <i class="bi bi-plus"></i> Create Your First Tour
            </a>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
