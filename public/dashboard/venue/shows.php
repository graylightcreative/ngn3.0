<?php
/**
 * Venue Dashboard - Shows Management
 */
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('venue');

$user = dashboard_get_user();
$entity = dashboard_get_entity('venue');
$pageTitle = 'shows';
$currentPage = 'shows';

$action = $_GET['action'] ?? 'list';
$showId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$success = $error = null;
$shows = [];
$editShow = null;

// Fetch shows for this venue
if ($entity) {
    try {
        $pdo = dashboard_pdo();
        $stmt = $pdo->prepare("SELECT * FROM shows WHERE venue_id = ? ORDER BY starts_at DESC");
        $stmt->execute([$entity['id']]);
        $shows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($showId && $action === 'edit') {
            $stmt = $pdo->prepare("SELECT * FROM shows WHERE id = ? AND venue_id = ?");
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
                    $stmt = $pdo->prepare("DELETE FROM `shows` WHERE id = ? AND venue_id = ?");
                    $stmt->execute([$showId_to_delete, $entity['id']]);
                    if ($stmt->rowCount() > 0) {
                        $success = 'Show deleted successfully.';
                    } else {
                        $error = 'Show not found or you do not have permission to delete it.';
                    }
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            } else {
                $error = 'Invalid show ID for deletion.';
            }
        }
        else {
            $title = trim($_POST['title'] ?? '');
            $startsAt = $_POST['starts_at'] ?? null;
            $ticketUrl = trim($_POST['ticket_url'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            if (empty($title) || empty($startsAt)) {
                $error = 'Title and date are required.';
            } else {
                try {
                    $pdo = dashboard_pdo();

                    if ($action === 'edit' && $editShow) {
                        $stmt = $pdo->prepare("
                            UPDATE shows SET title = ?, starts_at = ?, ticket_url = ?,
                            description = ?, updated_at = NOW() WHERE id = ?
                        ");
                        $stmt->execute([$title, $startsAt, $ticketUrl, $description, $editShow['id']]);
                        $success = 'Show updated!';
                    } else {
                        $slug = dashboard_generate_slug($title, 'show', $entity['id']);
                        $stmt = $pdo->prepare("
                            INSERT INTO shows (slug, title, venue_id, venue_name, city, region, starts_at, ticket_url, description)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $slug, $title, $entity['id'], $entity['name'],
                            $entity['city'] ?? '', $entity['region'] ?? '',
                            $startsAt, $ticketUrl, $description
                        ]);
                        $success = 'Show added!';
                    }
                    
                    $action = 'list';
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }

        // After any POST action, reload the shows list
        $stmt = $pdo->prepare("SELECT * FROM shows WHERE venue_id = ? ORDER BY starts_at DESC");
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
        <p class="page-subtitle">Manage events at your venue</p>
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
            Set up your venue profile first.
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
                           placeholder="e.g., Metal Night with Band Name">
                </div>
                
                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label">Date & Time *</label>
                        <input type="datetime-local" name="starts_at" class="form-input" required
                               value="<?= $editShow ? (($ts = safeStrtotime($editShow['starts_at'])) ? date('Y-m-d\TH:i', $ts) : '') : '' ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Ticket URL</label>
                        <input type="url" name="ticket_url" class="form-input" placeholder="https://"
                               value="<?= htmlspecialchars($editShow['ticket_url'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" placeholder="Event details..."><?= htmlspecialchars($editShow['description'] ?? '') ?></textarea>
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
        <?php /* TODO: Consider integrating a full calendar UI here for better visual management of shows. */ ?>
        <div id="calendar-component" style="margin-bottom: 1.5rem; padding: 1rem; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-secondary);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <button class="btn btn-secondary btn-sm"><i class="bi bi-chevron-left"></i></button>
                <h3 style="margin: 0;">January 2026</h3>
                <button class="btn btn-secondary btn-sm"><i class="bi bi-chevron-right"></i></button>
            </div>
            <div style="display: grid; grid-template-columns: repeat(7, 1fr); text-align: center; font-size: 0.875rem; color: var(--text-muted); margin-bottom: 0.5rem;">
                <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
            </div>
            <div style="display: grid; grid-template-columns: repeat(7, 1fr); text-align: center; gap: 0.5rem;">
                <?php for ($i = 1; $i <= 31; $i++): ?>
                    <div style="padding: 0.5rem; border-radius: 4px; background: var(--bg-primary);"><?= $i ?></div>
                <?php endfor; ?>
            </div>
        </div>
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
                <p>No upcoming shows scheduled.</p>
            </div>
            <?php else: ?>
            <div style="display: grid; gap: 12px;">
                <?php foreach ($upcomingShows as $show): ?>
                <div class="show-item" data-date="<?= date('Y-m-d', strtotime($show['starts_at'])) ?>" style="display: flex; align-items: center; gap: 16px; padding: 16px; background: var(--bg-primary); border-radius: 8px;">
                    <div style="width: 60px; text-align: center; flex-shrink: 0;">
                        <div style="font-size: 24px; font-weight: 700; color: var(--brand);"><?= date('d', strtotime($show['starts_at'])) ?></div>
                        <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;"><?= date('M', strtotime($show['starts_at'])) ?></div>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 600; margin-bottom: 4px;"><?= htmlspecialchars($show['title']) ?></div>
                        <div style="font-size: 13px; color: var(--text-muted);">
                            <?= date('g:i A', strtotime($show['starts_at'])) ?>
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
                        <button class="btn btn-secondary qr-btn" style="padding: 6px 12px; font-size: 12px;" data-show-id="<?= $show['id'] ?>"><i class="bi bi-qr-code"></i></button>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this show?');">
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

        <div id="qr-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
            <div style="background: var(--bg-card); padding: 2rem; border-radius: 1rem; text-align: center;">
                <h2 class="card-title">Scan QR Code</h2>
                <div id="qr-code-container" style="margin: 1rem 0;"></div>
                <?php /* TODO: Integrate QR code customization options (e.g., color, logo) or analytics display here. */ ?>
                <div id="qr-customization-options" style="margin-top: 1rem; padding: 1rem; background: var(--bg-secondary); border-radius: 0.5rem;">
                    <h3 style="margin-top: 0; font-size: 1rem;">Customize QR Code</h3>
                    <div class="form-group">
                        <label class="form-label">QR Content (URL/Text)</label>
                        <input type="text" id="qrContentInput" class="form-input" value="" placeholder="https://your.event.link">
                    </div>
                    <div class="form-group">
                        <label class="form-label">QR Color</label>
                        <input type="color" id="qrColorInput" class="form-input" value="#000000">
                    </div>
                    <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" id="qrIncludeLogo">
                        <label for="qrIncludeLogo" class="form-label" style="margin: 0;">Include Logo (Simulated)</label>
                    </div>
                    <div class="form-group" id="qrLogoUrlGroup" style="display: none;">
                        <label class="form-label">Logo URL (Simulated)</label>
                        <input type="url" id="qrLogoUrlInput" class="form-input" value="" placeholder="https://your.logo.com/logo.png">
                    </div>
                </div>
                <div id="qr-analytics-placeholder" style="margin-top: 1rem; padding: 1rem; border: 1px dashed var(--brand); text-align: center; color: var(--text-muted);">
                    <i class="bi bi-bar-chart" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                    <span>Mock QR Scan Analytics Here</span>
                </div>
                <button id="close-qr-modal" class="btn btn-secondary">Close</button>
            </div>
        </div>
        
        <?php if (!empty($pastShows)): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Past Shows (<?= count($pastShows) ?>)</h2>
            </div>
            <div style="display: grid; gap: 8px; opacity: 0.7;">
                <?php foreach (array_slice($pastShows, 0, 10) as $show): ?>
                <div class="show-item" data-date="<?= date('Y-m-d', strtotime($show['starts_at'])) ?>" style="display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--bg-primary); border-radius: 6px;">
                    <div style="font-size: 13px; color: var(--text-muted); width: 80px;"><?= date('M j, Y', strtotime($show['starts_at'])) ?></div>
                    <div style="flex: 1; font-size: 14px;"><?= htmlspecialchars($show['title']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- QR Modal Logic ---
    const qrModal = document.getElementById('qr-modal');
    const closeQrModalBtn = document.getElementById('close-qr-modal');
    const qrCodeContainer = document.getElementById('qr-code-container');
    const qrButtons = document.querySelectorAll('.qr-btn');

    // QR Customization elements
    const qrContentInput = document.getElementById('qrContentInput');
    const qrColorInput = document.getElementById('qrColorInput');
    const qrIncludeLogo = document.getElementById('qrIncludeLogo');
    const qrLogoUrlGroup = document.getElementById('qrLogoUrlGroup');
    const qrLogoUrlInput = document.getElementById('qrLogoUrlInput');

    let currentShowIdForQr = null; // To store the show ID for current QR

    function updateQrCode() {
        if (!currentShowIdForQr) return; // Only update if a show is selected

        let content = qrContentInput.value.trim();
        if (!content) {
            // Fallback to a default or show-specific URL if content is empty
            content = `https://ngn.com/show/${currentShowIdForQr}`;
            qrContentInput.value = content; // Pre-fill with default
        }

        const color = qrColorInput.value.substring(1); // Remove '#'
        const includeLogo = qrIncludeLogo.checked;
        const logoUrl = qrLogoUrlInput.value.trim();

        let qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(content)}&bgcolor=ffffff&color=${color}`;
        if (includeLogo && logoUrl) {
            // This is a simulation, real QR code APIs might not support this easily
            // For now, just add as a param, the API server would need to handle it.
            qrUrl += `&qzone=1&margin=0&logo=${encodeURIComponent(logoUrl)}`;
        }
        
        qrCodeContainer.innerHTML = `<img src="${qrUrl}" alt="QR Code">`;
    }

    qrButtons.forEach(button => {
        button.addEventListener('click', async function() {
            const showId = this.dataset.showId;
            currentShowIdForQr = showId; // Set current show ID
            qrCodeContainer.innerHTML = '<p>Generating QR Code...</p>';
            qrModal.style.display = 'flex';
            
            // Populate initial values based on show (or defaults)
            // For simulation, we'll just use a generic show URL initially
            qrContentInput.value = `https://ngn.com/show/${currentShowIdForQr}`;
            qrColorInput.value = '#000000';
            qrIncludeLogo.checked = false;
            qrLogoUrlGroup.style.display = 'none';
            qrLogoUrlInput.value = '';

            updateQrCode(); // Generate initial QR code with default/simulated content

            // Original API call (commented out for full client-side simulation)
            /*
            try {
                const response = await fetch('/api/v1/qr-codes', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + '<?php echo dashboard_get_jwt(); ?>'
                    },
                    body: JSON.stringify({
                        entity_type: 'show',
                        entity_id: showId
                    })
                });

                const data = await response.json();

                if (data.success && data.qr_code_url) {
                    qrCodeContainer.innerHTML = `<img src="${data.qr_code_url}" alt="QR Code">`;
                } else {
                    qrCodeContainer.innerHTML = '<p style="color: var(--danger);">Could not generate QR code.</p>';
                }
            } catch (error) {
                console.error('QR Code error:', error);
                qrCodeContainer.innerHTML = '<p style="color: var(--danger);">An error occurred.</p>';
            }
            */
        });
    });

    qrContentInput.addEventListener('input', updateQrCode);
    qrColorInput.addEventListener('input', updateQrCode);
    qrIncludeLogo.addEventListener('change', function() {
        qrLogoUrlGroup.style.display = this.checked ? 'block' : 'none';
        updateQrCode();
    });
    qrLogoUrlInput.addEventListener('input', updateQrCode);

    if (closeQrModalBtn) {
        closeQrModalBtn.addEventListener('click', function() {
            qrModal.style.display = 'none';
        });
    }
    
    if (qrModal) {
        qrModal.addEventListener('click', function(e) {
            if (e.target === qrModal) {
                qrModal.style.display = 'none';
            }
        });
    }

    // --- Interactive Calendar Logic ---
    const calendarComponent = document.getElementById('calendar-component');
    const prevMonthBtn = calendarComponent.querySelector('.btn-sm:first-child');
    const nextMonthBtn = calendarComponent.querySelector('.btn-sm:last-child');
    const currentMonthYearHeader = calendarComponent.querySelector('h3');
    const calendarGrid = calendarComponent.querySelector('div[style*="display: grid; gap: 0.5rem;"]');
    const allShowItems = document.querySelectorAll('.show-item');

    let currentDate = new Date();
    let selectedCalendarDate = null; // Stores the date selected on the calendar

    function renderCalendar() {
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth(); // 0-indexed

        currentMonthYearHeader.textContent = new Date(year, month).toLocaleString('default', { month: 'long', year: 'numeric' });

        calendarGrid.innerHTML = ''; // Clear previous days

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
            
            const fullDate = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            dayElement.dataset.date = fullDate;

            // Highlight if this day has shows
            const hasShows = Array.from(allShowItems).some(item => item.dataset.date === fullDate);
            if (hasShows) {
                dayElement.style.border = '1px solid var(--brand)';
            }

            // Highlight selected date
            if (selectedCalendarDate && selectedCalendarDate.toDateString() === new Date(year, month, day).toDateString()) {
                dayElement.style.backgroundColor = 'var(--brand)';
                dayElement.style.color = '#000';
            }

            dayElement.addEventListener('click', function() {
                // Clear previous selection
                document.querySelectorAll('.calendar-day-selected').forEach(el => {
                    el.classList.remove('calendar-day-selected');
                    el.style.backgroundColor = 'var(--bg-primary)';
                    el.style.color = 'var(--text-primary)';
                });

                // Set new selection
                this.classList.add('calendar-day-selected');
                this.style.backgroundColor = 'var(--brand)';
                this.style.color = '#000';
                selectedCalendarDate = new Date(year, month, day);
                filterShowsByDate(fullDate);
            });

            calendarGrid.appendChild(dayElement);
        }
    }

    function filterShowsByDate(dateToFilter) {
        allShowItems.forEach(item => {
            if (dateToFilter === null || item.dataset.date === dateToFilter) {
                item.style.display = 'flex'; // Show
            } else {
                item.style.display = 'none'; // Hide
            }
        });
    }

    prevMonthBtn.addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() - 1);
        renderCalendar();
        filterShowsByDate(null); // Clear filter when changing month
    });

    nextMonthBtn.addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() + 1);
        renderCalendar();
        filterShowsByDate(null); // Clear filter when changing month
    });

    renderCalendar(); // Initial render
    filterShowsByDate(null); // Show all shows initially
});
</script>

</body>
</html>

