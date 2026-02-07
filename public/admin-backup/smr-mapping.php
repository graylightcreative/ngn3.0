<?php
/**
 * SMR Artist Mapping Interface
 *
 * Implements Bible Ch. 5.2.2 Step 4: "Map: Click to map typos to Canonical Artists."
 * Admin maps unmatched artist names from SMR CSV to canonical NGN artists.
 *
 * Workflow:
 * 1. Display list of unmatched artist names from current upload
 * 2. For each, show artist search/autocomplete
 * 3. Save mappings to smr_artist_mappings table
 * 4. Auto-update smr_staging with artist_id when mapped
 * 5. Show linkage rate progress
 */

require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../../lib/bootstrap.php';

$currentPage = 'smr-mapping';
$uploadId = isset($_GET['upload_id']) ? (int)$_GET['upload_id'] : null;

if (!$uploadId) {
    header('Location: /admin/smr-ingestion.php');
    exit;
}

// Get upload info
try {
    $pdo = new PDO("mysql:host=localhost;dbname=ngn_2025", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT * FROM smr_uploads WHERE id = ?");
    $stmt->execute([$uploadId]);
    $upload = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$upload) {
        header('Location: /admin/smr-ingestion.php?error=Upload not found');
        exit;
    }
} catch (PDOException $e) {
    error_log('Upload fetch error: ' . $e->getMessage());
    header('Location: /admin/smr-ingestion.php?error=Database error');
    exit;
}

// Get unmatched artist names
$unmatchedArtists = [];
$mappedArtists = [];

try {
    // Get unmapped names
    $stmt = $pdo->prepare("
        SELECT DISTINCT artist_name
        FROM smr_staging
        WHERE upload_id = ? AND is_matched = FALSE
        ORDER BY artist_name
    ");
    $stmt->execute([$uploadId]);
    $unmatchedArtists = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get already mapped names
    $stmt = $pdo->prepare("
        SELECT submitted_name, a.id, a.name as canonical_name
        FROM smr_artist_mappings m
        JOIN artists a ON m.canonical_artist_id = a.id
        WHERE m.upload_id = ?
        ORDER BY submitted_name
    ");
    $stmt->execute([$uploadId]);
    $mappedArtists = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Artist fetch error: ' . $e->getMessage());
}

// Handle AJAX artist search
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'search') {
    header('Content-Type: application/json');

    $search = trim($_POST['q'] ?? '');
    if (strlen($search) < 2) {
        echo json_encode(['results' => []]);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT id, name FROM artists
            WHERE name LIKE ? OR slug LIKE ?
            LIMIT 20
        ");
        $stmt->execute(["%{$search}%", "%{$search}%"]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['results' => $results]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Search failed']);
    }
    exit;
}

// Handle mapping save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'map') {
    header('Content-Type: application/json');

    $artistName = trim($_POST['artist_name'] ?? '');
    $artistId = isset($_POST['artist_id']) ? (int)$_POST['artist_id'] : 0;

    if (!$artistName || !$artistId) {
        echo json_encode(['success' => false, 'error' => 'Invalid input']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Save mapping
        $stmt = $pdo->prepare("
            INSERT INTO smr_artist_mappings (upload_id, submitted_name, canonical_artist_id, matched_by)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                canonical_artist_id = VALUES(canonical_artist_id),
                matched_by = VALUES(matched_by)
        ");
        $stmt->execute([$uploadId, $artistName, $artistId, $_SESSION['user_id'] ?? 1]);

        // Update staging rows with artist_id and mark as matched
        $stmt = $pdo->prepare("
            UPDATE smr_staging
            SET artist_id = ?, is_matched = TRUE, match_confidence = 1.00
            WHERE upload_id = ? AND artist_name = ?
        ");
        $stmt->execute([$artistId, $uploadId, $artistName]);

        $pdo->commit();

        // Calculate new linkage rate
        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN is_matched = TRUE THEN 1 ELSE 0 END) as matched
            FROM smr_staging
            WHERE upload_id = ?
        ");
        $stmt->execute([$uploadId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        $linkageRate = $stats['total'] > 0 ? round(($stats['matched'] / $stats['total']) * 100, 2) : 0;

        // Update upload status
        $stmt = $pdo->prepare("
            UPDATE smr_uploads
            SET linkage_rate = ?, unmatched_count = ?
            WHERE id = ?
        ");
        $stmt->execute([$linkageRate, $stats['total'] - $stats['matched'], $uploadId]);

        echo json_encode([
            'success' => true,
            'linkageRate' => $linkageRate,
            'unmatchedCount' => $stats['total'] - $stats['matched']
        ]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Mapping save error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    exit;
}

$currentPage = 'smr-mapping';
require_once __DIR__ . '/_header.php';
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 dark:bg-gray-800">
    <div class="container mx-auto px-6 py-8">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-gray-700 text-3xl font-medium dark:text-gray-200">Map Artist Names</h3>
            <a href="/admin/smr-ingestion.php" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">Back to Uploads</a>
        </div>

        <!-- Upload Info Card -->
        <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6 mb-6">
            <div class="grid grid-cols-4 gap-4">
                <div>
                    <p class="text-gray-500 text-sm">Filename</p>
                    <p class="font-semibold text-gray-800 dark:text-gray-100"><?= htmlspecialchars($upload['upload_filename']) ?></p>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Total Rows</p>
                    <p class="font-semibold text-gray-800 dark:text-gray-100"><?= number_format($upload['row_count']) ?></p>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Linkage Rate</p>
                    <p class="font-semibold <?= $upload['linkage_rate'] >= 95 ? 'text-green-600' : 'text-yellow-600' ?>">
                        <?= round($upload['linkage_rate'], 1) ?>%
                    </p>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Unmatched</p>
                    <p class="font-semibold text-gray-800 dark:text-gray-100"><?= $upload['unmatched_count'] ?></p>
                </div>
            </div>
        </div>

        <!-- Linkage Rate Progress -->
        <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6 mb-6">
            <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Linkage Rate Progress (Bible Ch. 12.6)</h4>
            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                <div class="bg-<?= $upload['linkage_rate'] >= 95 ? 'green' : 'yellow' ?>-500 h-3 rounded-full"
                     style="width: <?= min(100, $upload['linkage_rate']) ?>%"></div>
            </div>
            <p class="text-gray-600 dark:text-gray-400 text-sm mt-2">
                Required: 95% | Current: <?= round($upload['linkage_rate'], 1) ?>% |
                <?= $upload['linkage_rate'] >= 95 ? '✓ Ready for finalization' : '⚠ More mappings needed' ?>
            </p>
        </div>

        <!-- Already Mapped Artists -->
        <?php if (!empty($mappedArtists)): ?>
        <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6 mb-6">
            <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">✓ Already Mapped (<?= count($mappedArtists) ?>)</h4>
            <div class="space-y-2">
                <?php foreach ($mappedArtists as $mapped): ?>
                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded">
                    <div>
                        <span class="font-mono text-gray-600 dark:text-gray-400"><?= htmlspecialchars($mapped['submitted_name']) ?></span>
                        <span class="text-gray-400 mx-2">→</span>
                        <span class="font-semibold text-green-600"><?= htmlspecialchars($mapped['canonical_name']) ?></span>
                    </div>
                    <button type="button" class="text-red-500 hover:text-red-700 text-sm" onclick="removeMapping(this, '<?= htmlspecialchars($mapped['submitted_name']) ?>')">
                        Remove
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Unmatched Artists (to be mapped) -->
        <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6">
            <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">
                Unmatched Artist Names (<?= count($unmatchedArtists) ?>)
            </h4>

            <?php if (empty($unmatchedArtists)): ?>
            <div class="text-center py-12">
                <p class="text-gray-600 dark:text-gray-400 text-lg">All artists mapped! ✓</p>
                <a href="/admin/smr-ingestion.php?upload_id=<?= $uploadId ?>" class="inline-block mt-4 px-4 py-2 bg-brand text-white rounded hover:bg-brand-dark">
                    Review & Finalize
                </a>
            </div>
            <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($unmatchedArtists as $artist): ?>
                <div class="border-l-4 border-yellow-400 bg-gray-50 dark:bg-gray-800 p-4 flex items-center justify-between">
                    <div class="flex-1">
                        <p class="font-mono text-gray-800 dark:text-gray-100 font-semibold">
                            <?= htmlspecialchars($artist['artist_name']) ?>
                        </p>
                    </div>
                    <div class="flex-1 ml-6">
                        <input type="text" class="artist-search w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded dark:bg-gray-700 dark:text-gray-200"
                               placeholder="Search for artist..."
                               data-artist-name="<?= htmlspecialchars($artist['artist_name']) ?>"
                               autocomplete="off">
                        <div class="search-results mt-2 hidden" style="max-height: 200px; overflow-y-auto;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<style>
.search-results {
    position: absolute;
    background: white;
    border: 1px solid #ccc;
    border-radius: 4px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    z-index: 100;
    min-width: 300px;
}
.search-results.dark:bg-gray-700 {
    background: #1f2937;
    border-color: #4b5563;
}
.search-result-item {
    padding: 8px 12px;
    cursor: pointer;
    border-bottom: 1px solid #f0f0f0;
}
.search-result-item:hover {
    background: #f5f5f5;
}
.dark .search-result-item:hover {
    background: #374151;
}
.search-result-item:last-child {
    border-bottom: none;
}
</style>

<script>
document.querySelectorAll('.artist-search').forEach(input => {
    input.addEventListener('input', debounce(async (e) => {
        const query = e.target.value.trim();
        const resultsDiv = e.target.nextElementSibling;

        if (query.length < 2) {
            resultsDiv.classList.add('hidden');
            return;
        }

        try {
            const response = await fetch('/admin/smr-mapping.php?upload_id=<?= $uploadId ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=search&q=' + encodeURIComponent(query)
            });

            const data = await response.json();
            resultsDiv.innerHTML = '';

            if (data.results.length > 0) {
                data.results.forEach(artist => {
                    const div = document.createElement('div');
                    div.className = 'search-result-item';
                    div.textContent = artist.name;
                    div.onclick = () => selectArtist(e.target, artist);
                    resultsDiv.appendChild(div);
                });
                resultsDiv.classList.remove('hidden');
            } else {
                resultsDiv.innerHTML = '<div class="search-result-item text-gray-500">No artists found</div>';
                resultsDiv.classList.remove('hidden');
            }
        } catch (error) {
            console.error('Search error:', error);
        }
    }, 300));
});

function selectArtist(input, artist) {
    const artistName = input.getAttribute('data-artist-name');
    const resultsDiv = input.nextElementSibling;

    // Show loading state
    input.disabled = true;
    input.value = 'Mapping...';

    fetch('/admin/smr-mapping.php?upload_id=<?= $uploadId ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=map&artist_name=' + encodeURIComponent(artistName) +
              '&artist_id=' + artist.id
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Remove this row and reload mapped artists section
            input.closest('.border-l-4').remove();

            // Update linkage rate display
            const linkageDisplay = document.querySelector('[data-linkage-rate]');
            if (linkageDisplay) {
                linkageDisplay.textContent = data.linkageRate + '%';
            }

            // Check if all mapped
            if (document.querySelectorAll('.artist-search').length === 0) {
                location.reload();
            }
        } else {
            alert('Error: ' + (data.error || 'Failed to save mapping'));
            input.disabled = false;
            input.value = '';
        }
    })
    .catch(err => {
        console.error('Map error:', err);
        alert('Error saving mapping');
        input.disabled = false;
        input.value = '';
    });

    resultsDiv.classList.add('hidden');
}

function removeMapping(btn, artistName) {
    if (confirm('Remove this mapping?')) {
        // TODO: Implement removal
        console.log('Remove mapping for:', artistName);
    }
}

function debounce(fn, delay) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => fn(...args), delay);
    };
}
</script>

<?php require_once __DIR__ . '/_footer.php'; ?>
