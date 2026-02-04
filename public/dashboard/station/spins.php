<?php
/**
 * Station Dashboard - Spins Submission
 * Supports manual entry and CSV bulk upload
 */
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('station');

$user = dashboard_get_user();
$entity = dashboard_get_entity('station');
$pageTitle = 'Spins';
$currentPage = 'spins';

$action = $_GET['action'] ?? 'list';
$success = $error = null;
$spins = [];
$recentArtists = [];
$csvResults = null;

// Fetch recent spins
if ($entity) {
    try {
        $spinsPdo = dashboard_pdo_spins();
        $stmt = $spinsPdo->prepare("SELECT * FROM station_spins WHERE station_id = ? ORDER BY played_at DESC LIMIT 50");
        $stmt->execute([$entity['id']]);
        $spins = $stmt->fetchAll() ?: [];

        // Get recent unique artists for quick add
        $stmt2 = $spinsPdo->prepare("SELECT DISTINCT artist_name FROM station_spins WHERE station_id = ? ORDER BY played_at DESC LIMIT 10");
        $stmt2->execute([$entity['id']]);
        $recentArtists = array_column($stmt2->fetchAll() ?: [], 'artist_name');
    } catch (PDOException $e) {
        error_log('Station spins fetch error: ' . $e->getMessage());
        // Tables may not exist
    }
}

// Helper function to validate file is actually CSV
function validateCsvFile($filePath) {
    $mimeType = mime_content_type($filePath);
    $allowedMimes = ['text/csv', 'text/plain', 'application/csv'];
    return in_array($mimeType, $allowedMimes, true);
}

// Helper function to validate and parse date string
function validateDateString($dateStr) {
    if (empty($dateStr)) {
        return null;
    }
    // Try to parse the date
    $timestamp = strtotime($dateStr);
    if ($timestamp === false) {
        return null;
    }
    // Return formatted date for database
    return date('Y-m-d H:i:s', $timestamp);
}

// Handle CSV upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $entity && isset($_FILES['csv_file'])) {
    if (!dashboard_validate_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $file = $_FILES['csv_file'];

        // Validate file upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'File upload error. Please try again.';
        } elseif ($file['size'] === 0) {
            $error = 'File is empty. Please select a file.';
        } elseif ($file['size'] > 50 * 1024 * 1024) { // 50MB max
            $error = 'File is too large. Maximum size is 50MB.';
        } else {
            // Validate file extension
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext !== 'csv') {
                $error = 'Please upload a CSV file (.csv extension required).';
            }
            // Validate MIME type
            elseif (!validateCsvFile($file['tmp_name'])) {
                $error = 'Invalid file format. Please upload a valid CSV file.';
            }
            else {
                $handle = fopen($file['tmp_name'], 'r');
                if (!$handle) {
                    $error = 'Could not read the CSV file. Please try again.';
                } else {
                    $header = fgetcsv($handle);
                    if ($header === false) {
                        $error = 'CSV file is empty or invalid.';
                    } else {
                        $header = array_map('strtolower', array_map('trim', $header));

                        // Find column indices
                        $artistCol = array_search('artist', $header);
                        $songCol = array_search('song', $header);
                        $spinsCol = array_search('spins', $header);
                        $programCol = array_search('program', $header);
                        $timestampCol = array_search('timestamp', $header);

                        if ($artistCol === false || $songCol === false) {
                            $error = 'CSV must have "Artist" and "Song" columns.';
                        } else {
                            try {
                                $spinsPdo = dashboard_pdo_spins();
                                $imported = 0;
                                $skipped = 0;

                                // Validate default timestamp
                                $defaultTs = validateDateString($_POST['csv_timestamp'] ?? null);
                                if (!$defaultTs) {
                                    $defaultTs = date('Y-m-d H:i:s');
                                }

                                $stmt = $spinsPdo->prepare("INSERT INTO station_spins (station_id, artist_name, song_title, played_at, program, spins_count, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");

                                while (($row = fgetcsv($handle)) !== false) {
                                    $artist = trim($row[$artistCol] ?? '');
                                    $song = trim($row[$songCol] ?? '');

                                    if (empty($artist) || empty($song)) {
                                        $skipped++;
                                        continue;
                                    }

                                    $spinsCount = $spinsCol !== false ? (int)($row[$spinsCol] ?? 1) : 1;
                                    $program = $programCol !== false ? trim($row[$programCol] ?? '') : '';

                                    // Validate timestamp from CSV row or use default
                                    $ts = null;
                                    if ($timestampCol !== false && !empty($row[$timestampCol])) {
                                        $ts = validateDateString($row[$timestampCol]);
                                    }
                                    if (!$ts) {
                                        $ts = $defaultTs;
                                    }

                                    try {
                                        $stmt->execute([$entity['id'], $artist, $song, $ts, $program, $spinsCount]);
                                        $imported++;
                                    } catch (PDOException $e) {
                                        error_log('CSV row insert error: ' . $e->getMessage());
                                        $skipped++;
                                    }
                                }

                                fclose($handle);
                                $csvResults = ['imported' => $imported, 'skipped' => $skipped];
                                $success = "Imported $imported spins" . ($skipped > 0 ? " ($skipped skipped)" : "");
                                $action = 'list';
                            } catch (PDOException $e) {
                                error_log('CSV import error: ' . $e->getMessage());
                                $error = 'Database error during import. Please try again.';
                                fclose($handle);
                            }
                        }
                    }
                    if ($handle && is_resource($handle)) {
                        fclose($handle);
                    }
                }
            }
        }
    }
}

// Handle manual spin submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $entity && !isset($_FILES['csv_file']) && isset($_POST['artist_name'])) {
    if (!dashboard_validate_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $artistName = trim($_POST['artist_name'] ?? '');
        $songTitle = trim($_POST['song_title'] ?? '');
        $playedAtInput = $_POST['played_at'] ?? null;
        $program = trim($_POST['program'] ?? '');

        if (empty($artistName) || empty($songTitle)) {
            $error = 'Artist and song are required.';
        } else {
            // Validate the played_at timestamp
            $playedAt = validateDateString($playedAtInput);
            if (!$playedAt) {
                $playedAt = date('Y-m-d H:i:s');
            }

            try {
                $spinsPdo = dashboard_pdo_spins();
                $stmt = $spinsPdo->prepare("INSERT INTO station_spins (station_id, artist_name, song_title, played_at, program, spins_count, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
                $stmt->execute([$entity['id'], $artistName, $songTitle, $playedAt, $program]);
                $success = 'Spin logged successfully!';
                $action = 'list';

                // Refresh spins list
                try {
                    $stmt = $spinsPdo->prepare("SELECT * FROM station_spins WHERE station_id = ? ORDER BY played_at DESC LIMIT 50");
                    $stmt->execute([$entity['id']]);
                    $spins = $stmt->fetchAll() ?: [];
                } catch (PDOException $e) {
                    error_log('Refresh spins error: ' . $e->getMessage());
                }
            } catch (PDOException $e) {
                error_log('Spin insert error: ' . $e->getMessage());
                $error = 'Failed to log spin. Please try again.';
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
        <h1 class="page-title">Radio Spins</h1>
        <p class="page-subtitle">Log the songs you play to boost artist rankings</p>
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
            Set up your station profile first.
            <a href="profile.php">Set up profile →</a>
        </div>
        <?php elseif ($action === 'add'): ?>

        <!-- Add Spin Form -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Log a Spin</h2>
            </div>

            <form method="POST">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label">Artist Name *</label>
                        <input type="text" name="artist_name" id="artistSearch" class="form-input" required
                               placeholder="e.g., Metallica" autocomplete="off">
                        <div id="artistSuggestions" style="display:none; position:absolute; background:var(--bg-card); border:1px solid var(--border); border-radius:4px; max-height:200px; overflow-y:auto; z-index:100; width:100%;"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Song Title *</label>
                        <input type="text" name="song_title" class="form-input" required
                               placeholder="e.g., Enter Sandman">
                    </div>
                </div>

                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label">Played At</label>
                        <input type="datetime-local" name="played_at" class="form-input"
                               value="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Program/Show (Optional)</label>
                        <input type="text" name="program" class="form-input"
                               placeholder="e.g., Morning Metal">
                    </div>
                </div>

                <div style="display: flex; gap: 12px;">
                    <a href="spins.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-broadcast"></i> Log Spin
                    </button>
                </div>
            </form>
        </div>

        <!-- Quick Add from Recent Artists -->
        <?php if (!empty($recentArtists)): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Quick Add - Recent Artists</h2>
            </div>
            <p style="color: var(--text-muted); margin-bottom: 16px;">
                Click an artist to pre-fill the form above.
            </p>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <?php foreach ($recentArtists as $artist): ?>
                <button type="button" class="btn btn-secondary quick-artist" data-artist="<?= htmlspecialchars($artist) ?>">
                    <?= htmlspecialchars($artist) ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php elseif ($action === 'upload'): ?>

        <!-- CSV Upload Form -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><i class="bi bi-file-earmark-spreadsheet"></i> Bulk Upload Spins (CSV)</h2>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

                <div class="alert" style="background: rgba(29, 185, 84, 0.1); border: 1px solid rgba(29, 185, 84, 0.3); margin-bottom: 20px;">
                    <strong>CSV Format:</strong> Your file must have columns: <code>Artist</code>, <code>Song</code>.
                    Optional columns: <code>Spins</code> (count), <code>Program</code>, <code>Timestamp</code>.
                </div>

                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label">CSV File *</label>
                        <input type="file" name="csv_file" class="form-input" accept=".csv" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Default Timestamp (if not in CSV)</label>
                        <input type="datetime-local" name="csv_timestamp" class="form-input"
                               value="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                </div>

                <div style="display: flex; gap: 12px;">
                    <a href="spins.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload"></i> Upload & Import
                    </button>
                </div>
            </form>

            <hr style="margin: 24px 0; border-color: var(--border);">

            <h4 style="margin-bottom: 12px;">Sample CSV Format</h4>
            <pre style="background: var(--bg-secondary); padding: 12px; border-radius: 4px; font-size: 12px; overflow-x: auto;">Artist,Song,Spins,Program
Metallica,Enter Sandman,3,Morning Metal
Slayer,Raining Blood,2,Thrash Hour
Pantera,Walk,1,Afternoon Drive</pre>

            <a href="data:text/csv;charset=utf-8,Artist,Song,Spins,Program%0AMetallica,Enter Sandman,1,Morning Show"
               download="spins_template.csv" class="btn btn-secondary" style="margin-top: 12px;">
                <i class="bi bi-download"></i> Download Template
            </a>
        </div>
        
        <?php else: ?>

        <!-- Spins List -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Your Spins <span style="font-weight:normal; color:var(--text-muted);">(<?= count($spins) ?>)</span></h2>
                <div style="display: flex; gap: 8px;">
                    <a href="spins.php?action=upload" class="btn btn-secondary">
                        <i class="bi bi-upload"></i> CSV Upload
                    </a>
                    <a href="spins.php?action=add" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Log Spin
                    </a>
                </div>
            </div>

            <?php if (empty($spins)): ?>
            <div style="text-align: center; padding: 48px 24px; color: var(--text-muted);">
                <i class="bi bi-broadcast" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                <p>No spins logged yet.</p>
                <div style="display: flex; gap: 12px; justify-content: center; margin-top: 16px;">
                    <a href="spins.php?action=upload" class="btn btn-secondary">
                        <i class="bi bi-upload"></i> Bulk Upload CSV
                    </a>
                    <a href="spins.php?action=add" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Log First Spin
                    </a>
                </div>
            </div>
            <?php else: ?>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <th style="text-align: left; padding: 12px 8px; font-size: 12px; color: var(--text-muted);">PLAYED</th>
                        <th style="text-align: left; padding: 12px 8px; font-size: 12px; color: var(--text-muted);">ARTIST</th>
                        <th style="text-align: left; padding: 12px 8px; font-size: 12px; color: var(--text-muted);">SONG</th>
                        <th style="text-align: left; padding: 12px 8px; font-size: 12px; color: var(--text-muted);">PROGRAM</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($spins as $spin): ?>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 12px 8px; color: var(--text-muted); font-size: 13px;">
                            <?php
                                $timestamp = strtotime($spin['played_at']);
                                if ($timestamp !== false) {
                                    echo date('M j, g:i A', $timestamp);
                                } else {
                                    echo htmlspecialchars($spin['played_at']);
                                }
                            ?>
                        </td>
                        <td style="padding: 12px 8px; font-weight: 500;"><?= htmlspecialchars($spin['artist_name'] ?? '') ?></td>
                        <td style="padding: 12px 8px; color: var(--text-secondary);"><?= htmlspecialchars($spin['song_title'] ?? '') ?></td>
                        <td style="padding: 12px 8px; color: var(--text-muted); font-size: 13px;"><?= htmlspecialchars($spin['program'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        
        <!-- How Spins Work -->
        <div class="card" style="background: linear-gradient(135deg, rgba(29, 185, 84, 0.05) 0%, rgba(0, 212, 255, 0.05) 100%);">
            <div class="card-header">
                <h2 class="card-title"><i class="bi bi-info-circle" style="color: var(--accent);"></i> How Spins Work</h2>
            </div>
            <div class="grid grid-3">
                <div>
                    <h4 style="font-size: 14px; margin-bottom: 8px; color: var(--text-primary);">1. Log Your Plays</h4>
                    <p style="font-size: 13px; color: var(--text-secondary);">
                        Every time you play a song on air, log it here. The more spins, the better!
                    </p>
                </div>
                <div>
                    <h4 style="font-size: 14px; margin-bottom: 8px; color: var(--text-primary);">2. Boost Rankings</h4>
                    <p style="font-size: 13px; color: var(--text-secondary);">
                        Radio spins are a major factor in NGN artist rankings. Your plays matter!
                    </p>
                </div>
                <div>
                    <h4 style="font-size: 14px; margin-bottom: 8px; color: var(--text-primary);">3. Help Artists Grow</h4>
                    <p style="font-size: 13px; color: var(--text-secondary);">
                        Artists see which stations play them and can connect with you directly.
                    </p>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
</div>

<script>
// Quick artist buttons
document.querySelectorAll('.quick-artist').forEach(btn => {
    btn.addEventListener('click', function() {
        const artist = this.dataset.artist;
        const input = document.getElementById('artistSearch');
        if (input) input.value = artist;
    });
});

// Artist search autocomplete
const artistInput = document.getElementById('artistSearch');
const suggestionsDiv = document.getElementById('artistSuggestions');
let searchTimeout = null;

if (artistInput && suggestionsDiv) {
    artistInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const q = this.value.trim();
        if (q.length < 2) {
            suggestionsDiv.style.display = 'none';
            return;
        }
        searchTimeout = setTimeout(async () => {
            try {
                const res = await fetch('/api/v1/artists?search=' + encodeURIComponent(q) + '&per_page=8');
                if (!res.ok) {
                    console.error('Artist search API error:', res.status);
                    suggestionsDiv.style.display = 'none';
                    return;
                }
                const json = await res.json();
                let items = json?.data?.items || [];

                // No mock data fallback - only show real results
                if (items.length === 0) {
                    suggestionsDiv.style.display = 'none';
                    return;
                }
                suggestionsDiv.innerHTML = items.map(a =>
                    `<div class="artist-suggestion" style="padding:8px 12px; cursor:pointer; border-bottom:1px solid var(--border);" data-name="${a.name}">${a.name}</div>`
                ).join('');
                suggestionsDiv.style.display = 'block';

                suggestionsDiv.querySelectorAll('.artist-suggestion').forEach(el => {
                    el.addEventListener('click', function() {
                        artistInput.value = this.dataset.name;
                        suggestionsDiv.style.display = 'none';
                    });
                    el.addEventListener('mouseenter', function() {
                        this.style.background = 'var(--bg-secondary)';
                    });
                    el.addEventListener('mouseleave', function() {
                        this.style.background = 'transparent';
                    });
                });
            } catch (e) {
                console.error('Artist search error:', e);
                suggestionsDiv.style.display = 'none';
            }
        }, 300);
    });

    // Hide suggestions on click outside
    document.addEventListener('click', function(e) {
        if (!artistInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
            suggestionsDiv.style.display = 'none';
        }
    });
}
</script>

</body>
</html>

