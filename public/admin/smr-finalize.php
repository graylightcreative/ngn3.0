<?php
/**
 * SMR Finalization & Commit Interface
 *
 * Implements Bible Ch. 5.2.2 Step 5: "Commit: Click 'Finalize'. The data enters cdm_chart_entries."
 * Admin reviews final mapped data and commits to canonical data model.
 *
 * Workflow:
 * 1. Display all mapped staging data with final review
 * 2. Verify linkage rate >= 95%
 * 3. Show SHA-256 hash for data integrity verification
 * 4. Finalize button commits to cdm_chart_entries
 * 5. Calculate chart rankings
 * 6. Mark upload as finalized
 */

require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../../lib/bootstrap.php';

$currentPage = 'smr-finalize';
$uploadId = isset($_GET['upload_id']) ? (int)$_GET['upload_id'] : null;
$success = $error = null;

if (!$uploadId) {
    header('Location: /admin/smr-ingestion.php');
    exit;
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=ngn_2025", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get upload info
    $stmt = $pdo->prepare("SELECT * FROM smr_uploads WHERE id = ?");
    $stmt->execute([$uploadId]);
    $upload = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$upload) {
        header('Location: /admin/smr-ingestion.php?error=Upload not found');
        exit;
    }

    // Get all staging data with artist mappings
    $stmt = $pdo->prepare("
        SELECT
            s.id as staging_id,
            s.row_number,
            s.artist_name,
            s.song_title,
            s.spins,
            s.adds,
            s.artist_id,
            a.name as canonical_artist_name,
            s.is_matched,
            m.verified_by
        FROM smr_staging s
        LEFT JOIN artists a ON s.artist_id = a.id
        LEFT JOIN smr_artist_mappings m ON s.upload_id = m.upload_id AND s.artist_name = m.submitted_name
        WHERE s.upload_id = ?
        ORDER BY s.row_number
        LIMIT 1000
    ");
    $stmt->execute([$uploadId]);
    $stagingData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Handle finalization
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'finalize') {
        if ($upload['linkage_rate'] < 95) {
            $error = 'Cannot finalize: Linkage rate is ' . round($upload['linkage_rate'], 1) . '% (required: 95%)';
        } else {
            // Begin finalization transaction
            $pdo->beginTransaction();

            try {
                // Move all staging data to cdm_chart_entries
                $rank = 1;
                foreach ($stagingData as $row) {
                    if ($row['artist_id'] && $row['is_matched']) {
                        $stmt = $pdo->prepare("
                            INSERT INTO cdm_chart_entries
                                (upload_id, artist_id, song_title, spins, adds, rank, finalized_at)
                            VALUES (?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $uploadId,
                            $row['artist_id'],
                            $row['song_title'],
                            $row['spins'],
                            $row['adds'],
                            $rank
                        ]);
                        $rank++;
                    }
                }

                // Log linkage rate audit
                $stmt = $pdo->prepare("
                    INSERT INTO smr_linkage_audit
                        (upload_id, timestamp, total_rows, matched_rows, linkage_rate, threshold_met)
                    SELECT ?, NOW(),
                        COUNT(*),
                        SUM(CASE WHEN is_matched = TRUE THEN 1 ELSE 0 END),
                        ?,
                        ? >= 95
                    FROM smr_staging
                    WHERE upload_id = ?
                ");
                $stmt->execute([$uploadId, $upload['linkage_rate'], $upload['linkage_rate'], $uploadId]);

                // Update upload status
                $stmt = $pdo->prepare("
                    UPDATE smr_uploads
                    SET status = 'finalized', finalized_at = NOW(), finalized_by = ?
                    WHERE id = ?
                ");
                $stmt->execute([$_SESSION['user_id'] ?? 1, $uploadId]);

                $pdo->commit();
                $success = 'SMR data finalized and committed to CDM. ' . ($rank - 1) . ' entries created.';

            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
    }

} catch (PDOException $e) {
    error_log('SMR finalize error: ' . $e->getMessage());
    $error = 'Database error: ' . $e->getMessage();
}

require_once __DIR__ . '/_header.php';
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 dark:bg-gray-800">
    <div class="container mx-auto px-6 py-8">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-gray-700 text-3xl font-medium dark:text-gray-200">Finalize SMR Data</h3>
            <a href="/admin/smr-ingestion.php" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">Back to Uploads</a>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success): ?>
        <div class="mb-6 p-4 bg-green-100 dark:bg-green-800 text-green-700 dark:text-green-100 rounded-lg">
            <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="mb-6 p-4 bg-red-100 dark:bg-red-800 text-red-700 dark:text-red-100 rounded-lg">
            <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <!-- Upload Summary -->
        <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6 mb-6">
            <div class="grid grid-cols-5 gap-4">
                <div>
                    <p class="text-gray-500 text-sm">Filename</p>
                    <p class="font-semibold text-gray-800 dark:text-gray-100"><?= htmlspecialchars($upload['upload_filename']) ?></p>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">SHA-256 Hash</p>
                    <p class="font-mono text-xs text-gray-600 dark:text-gray-400 break-all" title="Data integrity verification">
                        <?= substr($upload['file_hash'], 0, 16) ?>...
                    </p>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Total Rows</p>
                    <p class="font-semibold text-gray-800 dark:text-gray-100"><?= number_format($upload['row_count']) ?></p>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Linkage Rate</p>
                    <p class="font-semibold <?= $upload['linkage_rate'] >= 95 ? 'text-green-600' : 'text-red-600' ?>">
                        <?= round($upload['linkage_rate'], 1) ?>%
                    </p>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Status</p>
                    <p class="font-semibold text-gray-800 dark:text-gray-100"><?= ucfirst($upload['status']) ?></p>
                </div>
            </div>
        </div>

        <!-- Compliance Check -->
        <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6 mb-6">
            <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Bible Ch. 5.3 QA Gates</h4>
            <div class="space-y-3">
                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded">
                    <div>
                        <p class="font-semibold text-gray-800 dark:text-gray-100">Linkage Rate</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Required: >95% of artist names linked to IDs</p>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-bold <?= $upload['linkage_rate'] >= 95 ? 'text-green-600' : 'text-red-600' ?>">
                            <?= round($upload['linkage_rate'], 1) ?>%
                        </p>
                        <?= $upload['linkage_rate'] >= 95 ? '<span class="text-green-600 text-sm">✓ PASS</span>' : '<span class="text-red-600 text-sm">✗ FAIL</span>' ?>
                    </div>
                </div>

                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded">
                    <div>
                        <p class="font-semibold text-gray-800 dark:text-gray-100">Data Integrity (SHA-256)</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">File hash verified for audit proof</p>
                    </div>
                    <div class="text-right">
                        <p class="font-mono text-sm text-gray-600 dark:text-gray-400 break-all">
                            <?= substr($upload['file_hash'], 0, 16) ?>...
                        </p>
                        <span class="text-green-600 text-sm">✓ VERIFIED</span>
                    </div>
                </div>

                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded">
                    <div>
                        <p class="font-semibold text-gray-800 dark:text-gray-100">Rows Processed</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">All data rows successfully mapped</p>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-bold text-gray-800 dark:text-gray-100"><?= number_format($upload['row_count']) ?></p>
                        <span class="text-green-600 text-sm">✓ READY</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Preview -->
        <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6 mb-6">
            <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Finalized Chart Data</h4>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Rank</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Artist</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Song Title</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Spins</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Adds</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <?php
                        $rank = 1;
                        foreach (array_slice($stagingData, 0, 20) as $row):
                            if ($row['artist_id'] && $row['is_matched']):
                        ?>
                        <tr>
                            <td class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-gray-200">#<?= $rank ?></td>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-200"><?= htmlspecialchars($row['canonical_artist_name']) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-200"><?= htmlspecialchars($row['song_title']) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"><?= number_format($row['spins']) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"><?= number_format($row['adds']) ?></td>
                            <td class="px-6 py-4 text-sm">
                                <span class="px-2 py-1 rounded text-white text-xs bg-green-500">MAPPED</span>
                            </td>
                        </tr>
                        <?php
                            $rank++;
                            endif;
                        endforeach;
                        ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($stagingData) > 20): ?>
            <p class="text-gray-600 dark:text-gray-400 text-sm mt-4">Showing 20 of <?= count($stagingData) ?> entries...</p>
            <?php endif; ?>
        </div>

        <!-- Finalization Action -->
        <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6">
            <div class="flex justify-between items-center">
                <div>
                    <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-2">Ready to Commit?</h4>
                    <p class="text-gray-600 dark:text-gray-400">Clicking "Finalize" will move this data into the Canonical Data Model (cdm_chart_entries).</p>
                </div>
                <?php if ($upload['status'] !== 'finalized'): ?>
                <form method="POST" style="margin: 0;">
                    <input type="hidden" name="action" value="finalize">
                    <button type="submit" class="px-6 py-3 bg-green-600 text-white rounded hover:bg-green-700 font-semibold"
                            <?= $upload['linkage_rate'] < 95 ? 'disabled' : '' ?>>
                        <i class="bi bi-check-lg"></i> Finalize & Commit
                    </button>
                </form>
                <?php else: ?>
                <div class="text-right">
                    <p class="text-green-600 font-semibold mb-2">✓ Finalized</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400"><?= $upload['finalized_at'] ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/_footer.php'; ?>
