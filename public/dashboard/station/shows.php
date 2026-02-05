<?php
/**
 * Station Dashboard - Shows/Events Management
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('station');

$user = dashboard_get_user();
$entity = dashboard_get_entity('station');
$pageTitle = 'shows';
$currentPage = 'shows';

$action = $_GET['action'] ?? 'list';
$showId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$success = $error = null;
$shows = [];

if ($entity) {
    try {
        $pdo = dashboard_pdo();
        $stmt = $pdo->prepare("SELECT * FROM `ngn_2025`.`shows` WHERE entity_type = 'station' AND entity_id = ? ORDER BY event_date DESC");
        $stmt->execute([$entity['id']]);
        $shows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

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
                    $stmt = $pdo->prepare("DELETE FROM `ngn_2025`.`shows` WHERE id = ? AND entity_type = 'station' AND entity_id = ?");
                    $stmt->execute([$showId_to_delete, $entity['id']]);
                    if ($stmt->rowCount() > 0) {
                        $success = 'Show deleted successfully.';
                        // FUTURE: Implement soft delete / trash bin functionality in v2.1
                        // This would allow recovery of accidentally deleted shows
                    } else {
                        $error = 'Show not found or you do not have permission to delete it.';
                    }
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            } else {
                $error = 'Invalid show ID for deletion.';
            }
        } else {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $eventDate = $_POST['event_date'] ?? null;
            $eventTime = $_POST['event_time'] ?? null;
            $location = trim($_POST['location'] ?? '');
            $ticketUrl = trim($_POST['ticket_url'] ?? '');
            $imageUrl = trim($_POST['image_url'] ?? '');
            
            if (empty($title)) {
                $error = 'Show title is required.';
            } elseif (empty($eventDate)) {
                $error = 'Event date is required.';
            } else {
                try {
                    $pdo = dashboard_pdo();

                    if ($action === 'edit' && $showId) {
                        $stmt = $pdo->prepare("UPDATE `ngn_2025`.`shows` SET
                            title = ?, description = ?, event_date = ?, event_time = ?, location = ?, ticket_url = ?, image_url = ?
                            WHERE id = ? AND entity_type = 'station' AND entity_id = ?");
                        $stmt->execute([$title, $description ?: null, $eventDate, $eventTime ?: null, $location ?: null, $ticketUrl ?: null, $imageUrl ?: null, $showId, $entity['id']]);
                        $success = 'Show updated!';
                    } else {
                        $slug = dashboard_generate_slug($title, 'show', $entity['id']);
                        $stmt = $pdo->prepare("INSERT INTO `ngn_2025`.`shows`
                            (entity_type, entity_id, slug, title, description, event_date, event_time, location, ticket_url, image_url)
                            VALUES ('station', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$entity['id'], $slug, $title, $description ?: null, $eventDate, $eventTime ?: null, $location ?: null, $ticketUrl ?: null, $imageUrl ?: null]);
                        $success = 'Show added!';
                    }
                    
                    $action = 'list';
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
        
        // After any POST action, reload the shows list
        $stmt = $pdo->prepare("SELECT * FROM `ngn_2025`.`shows` WHERE entity_type = 'station' AND entity_id = ? ORDER BY event_date DESC");
        $stmt->execute([$entity['id']]);
        $shows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$editShow = null;
if ($action === 'edit' && $showId && $entity) {
    try {
        $pdo = dashboard_pdo();
        $stmt = $pdo->prepare("SELECT * FROM `ngn_2025`.`shows` WHERE id = ? AND entity_type = 'station' AND entity_id = ?");
        $stmt->execute([$showId, $entity['id']]);
        $editShow = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
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

include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title">Shows & Events</h1>
        <p class="page-subtitle">Manage your station's events</p>
    </header>
    
    <div class="page-content">
        <?php if ($success): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
        
        <?php if (!$entity): ?>
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Set up your station profile first. <a href="profile.php">Set up profile →</a></div>
        <?php elseif ($action === 'add' || ($action === 'edit' && $editShow)): ?>
        
        <div class="card">
            <div class="card-header"><h2 class="card-title"><?= $action === 'edit' ? 'Edit Show' : 'Add New Show' ?></h2></div>
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <div class="form-group">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" class="form-input" required value="<?= htmlspecialchars($editShow['title'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="3"><?= htmlspecialchars($editShow['description'] ?? '') ?></textarea>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Event Date *</label>
                        <input type="date" name="event_date" class="form-input" required value="<?= htmlspecialchars($editShow['event_date'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Event Time</label>
                        <input type="time" name="event_time" class="form-input" value="<?= htmlspecialchars($editShow['event_time'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" class="form-input" value="<?= htmlspecialchars($editShow['location'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Ticket URL</label>
                    <input type="url" name="ticket_url" class="form-input" value="<?= htmlspecialchars($editShow['ticket_url'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Image URL</label>
                    <input type="url" name="image_url" class="form-input" value="<?= htmlspecialchars($editShow['image_url'] ?? '') ?>">
                </div>
                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <a href="shows.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-<?= $action === 'edit' ? 'check-lg' : 'plus-lg' ?>"></i> <?= $action === 'edit' ? 'Update' : 'Add' ?></button>
                </div>
            </form>
        </div>
        
        <?php else: ?>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Your Shows (<?= count($shows) ?>)</h2>
                <a href="shows.php?action=add" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Show</a>
            </div>
            <?php if (empty($shows)): ?>
            <div style="text-align: center; padding: 48px 24px; color: var(--text-muted);">
                <i class="bi bi-calendar-event" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                <p>No shows yet.</p>
                <a href="shows.php?action=add" class="btn btn-primary" style="margin-top: 16px;"><i class="bi bi-plus-lg"></i> Add First Show</a>
            </div>
            <?php else: ?>
            <div style="display: grid; gap: 12px;">
                <?php foreach ($shows as $show):
                    $dateTs = safeStrtotime($show['event_date']);
                    $todayTs = safeStrtotime('today');
                    $isPast = ($dateTs !== null && $todayTs !== null) ? ($dateTs < $todayTs) : false;
                ?>
                <div style="display: flex; align-items: center; gap: 16px; padding: 16px; background: var(--bg-primary); border-radius: 8px; <?= $isPast ? 'opacity: 0.6;' : '' ?>">
                    <div style="width: 60px; text-align: center; flex-shrink: 0;">
                        <?php if ($dateTs): ?>
                        <div style="font-size: 24px; font-weight: 700; color: var(--accent);"><?= date('d', $dateTs) ?></div>
                        <div style="font-size: 12px; text-transform: uppercase; color: var(--text-muted);"><?= date('M', $dateTs) ?></div>
                        <?php else: ?>
                        <div style="font-size: 12px; color: var(--text-muted);">—</div>
                        <?php endif; ?>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-weight: 600; margin-bottom: 4px;"><?= htmlspecialchars($show['title']) ?></div>
                        <div style="font-size: 13px; color: var(--text-muted);">
                            <?php if (!empty($show['event_time'])):
                                $timeTs = safeStrtotime($show['event_time']);
                                if ($timeTs): ?><?= date('g:i A', $timeTs) ?> · <?php endif;
                            endif; ?>
                            <?php if (!empty($show['location'])): ?><?= htmlspecialchars($show['location']) ?><?php endif; ?>
                            <?php if ($isPast): ?><span style="color: #ef4444;"> (Past)</span><?php endif; ?>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <a href="shows.php?action=edit&id=<?= $show['id'] ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;"><i class="bi bi-pencil"></i> Edit</a>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this show?');">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="show_id" value="<?= $show['id'] ?>">
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

<!-- Undo Notification for Shows -->
<div id="undoNotification" style="display: none; position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background-color: #333; color: #fff; padding: 15px 20px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.2); z-index: 2000;">
    Show deleted successfully. <button id="undoDeleteBtn" class="btn btn-secondary btn-sm" style="margin-left: 10px; background-color: #555;">Undo</button>
</div>

<!-- Custom Delete Confirmation Modal -->
<div id="deleteConfirmationModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Confirm Deletion</h2>
            <button class="modal-close" id="closeDeleteModal">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete "<strong id="showTitleToDelete"></strong>"? This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" id="cancelDelete">Cancel</button>
            <button class="btn btn-danger" id="confirmDelete">Delete</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const deleteConfirmationModal = document.getElementById('deleteConfirmationModal');
    const closeDeleteModal = document.getElementById('closeDeleteModal');
    const cancelDelete = document.getElementById('cancelDelete');
    const confirmDelete = document.getElementById('confirmDelete');
    const showTitleToDelete = document.getElementById('showTitleToDelete');
    let formToDelete = null;
    let showRowToDelete = null; // To store the HTML element of the show being deleted

    const undoNotification = document.getElementById('undoNotification');
    const undoDeleteBtn = document.getElementById('undoDeleteBtn');
    let undoTimeout = null;
    let lastDeletedShowId = null;
    let lastDeletedShowHtml = null;

    document.querySelectorAll('form').forEach(form => {
        if (form.querySelector('input[name="action"][value="delete"]')) {
            form.onsubmit = function(event) {
                event.preventDefault();
                formToDelete = this;
                lastDeletedShowId = this.querySelector('input[name="show_id"]').value;
                showRowToDelete = this.closest('div[style*="display: flex; align-items: center; gap: 16px;"]'); // Assuming this is the row element
                const parentDiv = this.closest('div[style*="display: flex; align-items: center; gap: 16px;"]');
                const titleElement = parentDiv ? parentDiv.querySelector('div[style*="font-weight: 600;"]').textContent : 'this show';
                showTitleToDelete.textContent = titleElement;
                deleteConfirmationModal.style.display = 'flex';
            };
        }
    });

    closeDeleteModal.addEventListener('click', function() {
        deleteConfirmationModal.style.display = 'none';
    });

    cancelDelete.addEventListener('click', function() {
        deleteConfirmationModal.style.display = 'none';
    });

    confirmDelete.addEventListener('click', async function() {
        if (formToDelete) {
            deleteConfirmationModal.style.display = 'none';

            const formData = new FormData(formToDelete);
            const showId = formData.get('show_id');
            const csrfToken = formData.get('csrf');

            try {
                const response = await fetch('shows.php', { // AJAX to the same page
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest', // Indicate AJAX request
                        'Content-Type': 'application/x-www-form-urlencoded' // For form data
                    },
                    body: new URLSearchParams(formData).toString()
                });
                const result = await response.text(); // Get raw text to check for success messages or redirects

                // Check for success message in the response (a basic way to detect success in a non-API file)
                if (result.includes('Show deleted successfully.')) {
                    if (showRowToDelete) {
                        lastDeletedShowHtml = showRowToDelete.outerHTML; // Store HTML for undo
                        showRowToDelete.remove();
                        // Show undo notification
                        undoNotification.style.display = 'block';
                        clearTimeout(undoTimeout);
                        undoTimeout = setTimeout(() => {
                            undoNotification.style.display = 'none';
                        }, 5000); // Hide after 5 seconds
                    }
                } else {
                    // Assuming an error occurred, re-evaluate response to get error message
                    alert('Error deleting show.'); // Generic error
                    console.error('Delete response:', result);
                }
            } catch (error) {
                console.error('Fetch error:', error);
                alert('An error occurred during deletion.');
            }
        }
    });

    undoDeleteBtn.addEventListener('click', function() {
        undoNotification.style.display = 'none';
        clearTimeout(undoTimeout);
        if (lastDeletedShowHtml) {
            const showListContainer = document.querySelector('.page-content > .card > div[style*="display: grid; gap: 12px;"]');
            if (showListContainer) {
                showListContainer.insertAdjacentHTML('beforeend', lastDeletedShowHtml);
                // Optionally re-attach event listeners if dynamically loaded content needs them
                // This would be more complex if forms inside needed re-initialization
            }
            lastDeletedShowHtml = null; // Clear stored HTML after undo
        }
    });

    deleteConfirmationModal.addEventListener('click', function(event) {
        if (event.target === deleteConfirmationModal) {
            deleteConfirmationModal.style.display = 'none';
        }
    });
});
</script>

