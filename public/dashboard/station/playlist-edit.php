<?php
/**
 * Station Dashboard - Playlist Editor
 */
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\Stations\StationPlaylistService;

dashboard_require_auth();
dashboard_require_entity_type('station');

$user = dashboard_get_user();
$entity = dashboard_get_entity('station');

$config = new Config();
$playlistService = new StationPlaylistService($config);

$playlistId = (int)($_GET['id'] ?? 0);
$playlist = null;
$error = $success = null;

if (!$playlistId) {
    header('Location: playlists.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!dashboard_validate_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            $playlist = $playlistService->getPlaylist($playlistId);
            if (!$playlist || $playlist['station_id'] !== $entity['id']) {
                $error = 'Playlist not found or you do not have permission to edit it.';
            }
            elseif ($action === 'update_details') {
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

                    $updateData = [
                        'title' => $title,
                        'geo_restrictions' => $geoRestrictions,
                    ];

                    $result = $playlistService->updatePlaylist($playlistId, $entity['id'], $updateData);

                    if ($result) {
                        $success = 'Playlist details updated successfully.';
                    } else {
                        $error = 'Failed to update playlist.';
                    }
                }
            } elseif ($action === 'remove_item') {
                $position = (int)($_POST['position'] ?? -1);
                if ($position >= 0) {
                    if ($playlistService->removeItem($playlistId, $position)) {
                        $success = 'Track removed from playlist.';
                    } else {
                        $error = 'Failed to remove track from playlist.';
                    }
                } else {
                    $error = 'Invalid track position.';
                }
            }
        } catch (\Throwable $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}


// Fetch playlist details
try {
    $playlist = $playlistService->getPlaylist($playlistId);
    if (!$playlist || $playlist['station_id'] !== $entity['id']) {
        // Ensure playlist belongs to the current station
        $error = 'Playlist not found or you do not have permission to edit it.';
        $playlist = null;
    }
} catch (\Throwable $e) {
    $error = 'Error loading playlist: ' . $e->getMessage();
}

$pageTitle = $playlist ? 'Edit Playlist: ' . htmlspecialchars($playlist['title']) : 'Edit Playlist';
$currentPage = 'playlists';

include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <a href="playlists.php" class="back-link"><i class="bi bi-arrow-left"></i> Back to Playlists</a>
        <h1 class="page-title"><?= $pageTitle ?></h1>
        <?php if ($playlist): ?>
        <p class="page-subtitle">Manage the tracks and settings for your playlist</p>
        <?php endif; ?>
    </header>

    <div class="page-content">
        <?php if ($success): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($playlist): ?>
        <div class="card" style="margin-bottom: 2rem;">
            <h2 class="text-xl" style="margin-top: 0;">Playlist Details</h2>
            <?php /* TODO: Consider integrating a visual playlist scheduling interface here, e.g., a calendar
                          to manage when this playlist goes live, repeats, or is retired. */ ?>
            <div id="playlist-scheduling-calendar" style="margin-bottom: 1.5rem; padding: 1rem; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-secondary);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <button class="btn btn-secondary btn-sm" id="prevMonth"><i class="bi bi-chevron-left"></i></button>
                    <h3 style="margin: 0;" id="currentMonthYear"></h3>
                    <button class="btn btn-secondary btn-sm" id="nextMonth"><i class="bi bi-chevron-right"></i></button>
                </div>
                <div style="display: grid; grid-template-columns: repeat(7, 1fr); text-align: center; font-size: 0.875rem; color: var(--text-muted); margin-bottom: 0.5rem;">
                    <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
                </div>
                <div id="calendar-grid" style="display: grid; grid-template-columns: repeat(7, 1fr); text-align: center; gap: 0.5rem;">
                    <!-- Calendar days will be rendered here by JS -->
                </div>
                <p style="margin-top: 1rem; font-size: 0.875rem; color: var(--text-muted);">Selected Publish Date: <span id="selectedPublishDate">None</span></p>
            </div>
            <form method="post">
                <input type="hidden" name="csrf" value="<?php echo dashboard_csrf_token(); ?>">
                <input type="hidden" name="action" value="update_details">

                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Playlist Title *</label>
                        <input type="text" name="title" required maxlength="255" class="form-input" placeholder="e.g., Monday Night Mix" value="<?= htmlspecialchars($playlist['title']) ?>" />
                    </div>
                </div>

                <div style="padding: 1rem; background: var(--bg-secondary); border-radius: 0.5rem; margin-bottom: 1rem;">
                    <h3 style="margin: 0 0 1rem 0; font-size: 1rem;">Geo-Blocking (Optional)</h3>

                    <div class="form-row">
                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Restriction Type</label>
                            <select name="geo_restriction_type" class="form-input">
                                <option value="" <?= empty($playlist['geo_restrictions']) ? 'selected' : '' ?>>None</option>
                                <option value="allow" <?= ($playlist['geo_restrictions']['type'] ?? '') === 'allow' ? 'selected' : '' ?>>Allow List (specific territories)</option>
                                <option value="block" <?= ($playlist['geo_restrictions']['type'] ?? '') === 'block' ? 'selected' : '' ?>>Block List (exclude territories)</option>
                            </select>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Territories (comma-separated codes)</label>
                            <input type="text" name="geo_territories" class="form-input" placeholder="US, CA, GB, AU" value="<?= htmlspecialchars(implode(', ', $playlist['geo_restrictions']['territories'] ?? [])) ?>" />
                            <p style="margin: 0.5rem 0 0 0; font-size: 0.875rem; color: var(--text-muted);">ISO 3166-1 alpha-2 codes</p>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Save Details</button>
            </form>
        </div>

        <div class="card">
             <h2 class="text-xl" style="margin-top: 0; margin-bottom: 1.5rem;">Playlist Items</h2>
             <div style="display: flex; justify-content: flex-end; margin-bottom: 1rem;">
                <button id="saveOrderBtn" class="btn btn-primary" style="display: none;">Save Order</button>
             </div>

            <div id="playlist-items-container">
            <?php if (empty($playlist['items'])): ?>
            <div style="padding: 2rem; text-align: center; color: var(--text-muted);">
                <p><i class="bi bi-music-note-list" style="font-size: 2rem; opacity: 0.5;"></i></p>
                <p>This playlist is empty. Add some tracks below.</p>
            </div>
            <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <?php foreach ($playlist['items'] as $item): ?>
                <div class="playlist-item" draggable="true" data-position="<?= $item['position'] ?>" data-item-id="<?= $item['track_id'] ?? $item['station_content_id'] ?>" data-item-type="<?= $item['content_type'] ?>" style="display: flex; align-items: center; gap: 1rem; padding: 0.75rem; border: 1px solid var(--border); border-radius: 0.5rem; cursor: grab;">
                    <span style="color: var(--text-muted);"><i class="bi bi-grip-vertical"></i></span>
                    <div style="flex: 1;">
                        <div style="font-weight: 500;"><?= htmlspecialchars($item['title']) ?></div>
                        <div style="font-size: 0.875rem; color: var(--text-muted);"><?= htmlspecialchars($item['artist_name'] ?? 'Unknown Artist') ?></div>
                    </div>
                    <form method="post" style="margin: 0;">
                         <input type="hidden" name="csrf" value="<?php echo dashboard_csrf_token(); ?>">
                         <input type="hidden" name="action" value="remove_item">
                         <input type="hidden" name="position" value="<?= $item['position'] ?>">
                         <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Remove this track?');">Remove</button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            </div>
        </div>
        
        <div class="card">
            <h2 class="text-xl" style="margin-top: 0; margin-bottom: 1rem;">Add Tracks</h2>
            <div class="form-group">
                <label class="form-label">Search for a track</label>
                <div style="display: flex; gap: 0.5rem;">
                    <input type="text" id="trackSearch" class="form-input" placeholder="Search by artist or title...">
                    <button id="trackSearchBtn" class="btn btn-secondary">Search</button>
                </div>
            </div>
            <div id="searchResults" style="margin-top: 1rem;">
                <!-- Search results will be displayed here -->
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchBtn = document.getElementById('trackSearchBtn');
    const searchInput = document.getElementById('trackSearch');
    const searchResults = document.getElementById('searchResults');
    const stationId = <?= $entity['id'] ?>;
    const playlistId = <?= $playlistId ?>;

    if(searchBtn) {
        searchBtn.addEventListener('click', async function() {
            const query = searchInput.value.trim();
            if (query.length < 2) {
                searchResults.innerHTML = '<p style="color: var(--text-muted);">Please enter at least 2 characters to search.</p>';
                return;
            }

            searchResults.innerHTML = '<p>Searching...</p>';

            try {
                const response = await fetch(`/api/v1/tracks/search?q=${encodeURIComponent(query)}&station_id=${stationId}`);
                const data = await response.json();

                if (data.success && data.data.items.length > 0) {
                    let html = '<div style="display: flex; flex-direction: column; gap: 0.5rem;">';
                    data.data.items.forEach(item => {
                        html += `
                            <div style="display: flex; align-items: center; gap: 1rem; padding: 0.75rem; border: 1px solid var(--border); border-radius: 0.5rem;">
                                <div style="flex: 1;">
                                    <div style="font-weight: 500;">${item.title}</div>
                                    <div style="font-size: 0.875rem; color: var(--text-muted);">${item.artist_name} (${item.type})</div>
                                </div>
                                <button class="btn btn-primary btn-sm add-track-btn" data-track-id="${item.id}" data-track-type="${item.type}">Add</button>
                            </div>
                        `;
                    });
                    html += '</div>';
                    searchResults.innerHTML = html;
                } else {
                    searchResults.innerHTML = '<p style="color: var(--text-muted);">No tracks found.</p>';
                }
            } catch (error) {
                searchResults.innerHTML = '<p style="color: var(--danger);">An error occurred while searching.</p>';
                console.error('Track search error:', error);
            }
        });
    }

    if(searchResults) {
        searchResults.addEventListener('click', async function(e) {
            if (e.target.classList.contains('add-track-btn')) {
                const button = e.target;
                const trackId = button.dataset.trackId;
                const trackType = button.dataset.trackType;

                button.disabled = true;
                button.textContent = 'Adding...';

                try {
                    const response = await fetch(`/api/v1/playlists/${playlistId}/items`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + '<?php echo dashboard_get_jwt(); ?>'
                        },
                        body: JSON.stringify({
                            track_id: trackId,
                            track_type: trackType
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        alert('Track added successfully!');
                        location.reload();
                    } else {
                        alert('Failed to add track: ' + data.message);
                        button.disabled = false;
                        button.textContent = 'Add';
                    }
                } catch (error) {
                    alert('An error occurred while adding the track.');
                    console.error('Add track error:', error);
                    button.disabled = false;
                    button.textContent = 'Add';
                }
            }
        });
        /* TODO: Consider adding more sophisticated UI/UX for track addition,
                 e.g., advanced search filters, multi-select for batch adding,
                 or a dedicated modal for track selection. */
    }

    const saveOrderBtn = document.getElementById('saveOrderBtn');
    const itemsContainer = document.getElementById('playlist-items-container');
    let draggedItem = null;

    if(itemsContainer) {
        itemsContainer.addEventListener('dragstart', e => {
            if (e.target.classList.contains('playlist-item')) {
                draggedItem = e.target;
                setTimeout(() => {
                    e.target.style.opacity = '0.5';
                }, 0);
            }
        });

        itemsContainer.addEventListener('dragend', e => {
            if (draggedItem) {
                setTimeout(() => {
                    draggedItem.style.opacity = '1';
                    draggedItem = null;
                }, 0);
            }
        });
        
        itemsContainer.addEventListener('dragover', e => {
            e.preventDefault();
            const afterElement = getDragAfterElement(itemsContainer.firstElementChild, e.clientY); // Pass the inner div that contains the draggable items
            const currentContainer = itemsContainer.firstElementChild; // The actual container of draggable items
            if (draggedItem && currentContainer) {
                if (afterElement == null) {
                    currentContainer.appendChild(draggedItem);
                } else {
                    currentContainer.insertBefore(draggedItem, afterElement);
                }
                saveOrderBtn.style.display = 'inline-block';
            }
        });
    }

    function getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('.playlist-item:not(.dragging)')];

        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    if(saveOrderBtn) {
        saveOrderBtn.addEventListener('click', async function() {
            const items = [];
            document.querySelectorAll('#playlist-items-container .playlist-item').forEach((item, index) => {
                items.push({
                    id: item.dataset.itemId,
                    type: item.dataset.itemType,
                    position: index
                });
            });

            saveOrderBtn.disabled = true;
            saveOrderBtn.textContent = 'Saving...';

            try {
                const response = await fetch(`/api/v1/playlists/${playlistId}/items/reorder`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + '<?php echo dashboard_get_jwt(); ?>'
                    },
                    body: JSON.stringify({ items: items })
                });

                const data = await response.json();

                if (data.success) {
                    alert('Playlist order saved!');
                    saveOrderBtn.style.display = 'none';
                } else {
                    alert('Failed to save order: ' + data.message);
                }
            } catch (error) {
                alert('An error occurred while saving the order.');
                console.error('Save order error:', error);
            } finally {
                saveOrderBtn.disabled = false;
                saveOrderBtn.textContent = 'Save Order';
            }
        });
    }

    // Playlist Scheduling Calendar Logic
    const calendarGrid = document.getElementById('calendar-grid');
    const currentMonthYearSpan = document.getElementById('currentMonthYear');
    const prevMonthBtn = document.getElementById('prevMonth');
    const nextMonthBtn = document.getElementById('nextMonth');
    const selectedPublishDateSpan = document.getElementById('selectedPublishDate');

    let currentDate = new Date();
    let selectedDate = null;

    function renderCalendar() {
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth(); // 0-indexed

        currentMonthYearSpan.textContent = new Date(year, month).toLocaleString('default', { month: 'long', year: 'numeric' });

        // Clear previous days
        calendarGrid.innerHTML = '';

        // Get first day of the month and total days in month
        const firstDayOfMonth = new Date(year, month, 1).getDay(); // 0 for Sunday, 1 for Monday, etc.
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        // Fill in leading empty days
        for (let i = 0; i < firstDayOfMonth; i++) {
            const emptyDay = document.createElement('div');
            calendarGrid.appendChild(emptyDay);
        }

        // Fill in days of the month
        for (let day = 1; day <= daysInMonth; day++) {
            const dayElement = document.createElement('div');
            dayElement.textContent = day;
            dayElement.style.padding = '0.5rem';
            dayElement.style.borderRadius = '4px';
            dayElement.style.background = 'var(--bg-primary)';
            dayElement.style.cursor = 'pointer';
            dayElement.dataset.date = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

            if (selectedDate && selectedDate.getFullYear() === year && selectedDate.getMonth() === month && selectedDate.getDate() === day) {
                dayElement.style.backgroundColor = 'var(--brand)';
                dayElement.style.color = '#000';
            }

            dayElement.addEventListener('click', function() {
                // Clear previous selection
                if (selectedDate) {
                    const prevSelected = calendarGrid.querySelector(`[data-date="${selectedDate.getFullYear()}-${String(selectedDate.getMonth() + 1).padStart(2, '0')}-${String(selectedDate.getDate()).padStart(2, '0')}"]`);
                    if (prevSelected) {
                        prevSelected.style.backgroundColor = 'var(--bg-primary)';
                        prevSelected.style.color = 'var(--text-primary)';
                    }
                }

                // Set new selection
                selectedDate = new Date(year, month, day);
                this.style.backgroundColor = 'var(--brand)';
                this.style.color = '#000';
                selectedPublishDateSpan.textContent = selectedDate.toDateString();
            });

            calendarGrid.appendChild(dayElement);
        }
    }

    prevMonthBtn.addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() - 1);
        renderCalendar();
    });

    nextMonthBtn.addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() + 1);
        renderCalendar();
    });

    renderCalendar(); // Initial render
});
</script>

</body>
</html>

</body>
</html>
