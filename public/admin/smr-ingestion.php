<?php
// admin/smr-ingestion.php
require_once __DIR__ . '/_guard.php'; // Admin authentication guard
require_once __DIR__ . '/../../lib/bootstrap.php'; // Bootstrap application

$currentPage = 'smr-ingestion'; // For sidebar highlighting

// Include the admin header
require_once __DIR__ . '/_header.php';
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 dark:bg-gray-800">
    <div class="container mx-auto px-6 py-8">
        <h3 class="text-gray-700 text-3xl font-medium dark:text-gray-200">SMR Data Ingestion</h3>

        <div class="mt-4">
            <p class="text-gray-600 dark:text-gray-400">
                Upload raw SMR (Secondary Market Radio) data files for processing.
            </p>
        </div>

        <div class="mt-8 bg-white dark:bg-gray-900 rounded-lg shadow p-6">
            <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Upload SMR File</h4>
            <form action="/admin/smr-ingestion.php" method="POST" enctype="multipart/form-data">
                <div class="mb-4">
                    <label for="smrFile" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">
                        Choose CSV or Excel File:
                    </label>
                    <input type="file" name="smrFile" id="smrFile" accept=".csv, .xls, .xlsx" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-200 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <button type="submit" class="px-4 py-2 bg-brand text-white rounded hover:bg-brand-dark focus:outline-none focus:bg-brand-dark">
                    Upload and Process
                </button>
            </form>

            <?php
            // SMR Upload & Ingestion Workflow
            // Bible Ch. 5.2.2: Step 1-2: Upload and parse
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['smrFile'])) {
                $uploadDir = __DIR__ . '/../../storage/uploads/smr/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $fileName = basename($_FILES['smrFile']['name']);
                $tmpPath = $_FILES['smrFile']['tmp_name'];
                $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $fileHash = hash_file('sha256', $tmpPath);
                $fileSize = filesize($tmpPath);

                // Validate file
                if (!in_array($fileType, ['csv', 'xls', 'xlsx'])) {
                    echo '<div class="mt-6 p-4 bg-red-100 dark:bg-red-800 text-red-700 dark:text-red-100 rounded-lg">
                        <strong>Error:</strong> Unsupported file type. Please upload CSV or Excel.
                    </div>';
                } elseif ($fileSize > 50 * 1024 * 1024) {
                    echo '<div class="mt-6 p-4 bg-red-100 dark:bg-red-800 text-red-700 dark:text-red-100 rounded-lg">
                        <strong>Error:</strong> File too large (max 50MB).
                    </div>';
                } else {
                    try {
                        $pdo = new PDO("mysql:host=localhost;dbname=ngn_2025", 'root', '');
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                        // Move file to storage
                        $storageName = date('Y-m-d_His') . '_' . pathinfo($fileName, PATHINFO_FILENAME) . '.' . $fileType;
                        $filePath = $uploadDir . $storageName;
                        move_uploaded_file($tmpPath, $filePath);

                        // Start transaction for parsing
                        $pdo->beginTransaction();

                        // Create upload record
                        $stmt = $pdo->prepare("
                            INSERT INTO smr_uploads (upload_filename, file_hash, file_size_bytes, row_count, status, uploaded_by)
                            VALUES (?, ?, ?, 0, 'parsing', ?)
                        ");
                        $stmt->execute([$fileName, $fileHash, $fileSize, $_SESSION['user_id'] ?? 1]);
                        $uploadId = $pdo->lastInsertId();

                        // NGN 2.0.2: Register in content ledger (non-blocking)
                        try {
                            require_once __DIR__ . '/../../lib/bootstrap.php';
                            $config = new \NGN\Lib\Config();
                            $ledgerService = new \NGN\Lib\Legal\ContentLedgerService($pdo, $config, new \Monolog\Logger('smr_ingestion'));

                            $ownerId = $_SESSION['user_id'] ?? 1;
                            if ($ownerId > 0) {
                                $ledgerRecord = $ledgerService->registerContent(
                                    ownerId: $ownerId,
                                    contentHash: $fileHash,
                                    uploadSource: 'smr_ingestion',
                                    metadata: [
                                        'title' => 'SMR Data Upload: ' . $fileName,
                                        'artist_name' => '',
                                        'credits' => null,
                                        'rights_split' => null
                                    ],
                                    fileInfo: [
                                        'size_bytes' => $fileSize,
                                        'mime_type' => 'text/csv',
                                        'filename' => $fileName
                                    ],
                                    sourceRecordId: $uploadId
                                );

                                // Update smr_uploads with certificate ID
                                if (!empty($ledgerRecord['certificate_id'])) {
                                    $updateStmt = $pdo->prepare("UPDATE smr_uploads SET certificate_id = ? WHERE id = ?");
                                    $updateStmt->execute([$ledgerRecord['certificate_id'], $uploadId]);
                                }
                            }
                        } catch (\Throwable $e) {
                            // Log but don't fail the upload
                            error_log('SMR content ledger registration failed: ' . $e->getMessage());
                        }

                        // Parse CSV
                        $handle = fopen($filePath, "r");
                        $rowData = [];
                        $rowCount = 0;
                        $headers = [];
                        $artistCol = $titleCol = $spinsCol = $addsCol = -1;

                        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                            if ($rowCount === 0) {
                                // Header row - find column indices
                                foreach ($row as $i => $header) {
                                    $headerLower = strtolower(trim($header));
                                    if (strpos($headerLower, 'artist') !== false) $artistCol = $i;
                                    if (strpos($headerLower, 'title') !== false || strpos($headerLower, 'song') !== false) $titleCol = $i;
                                    if (strpos($headerLower, 'spin') !== false) $spinsCol = $i;
                                    if (strpos($headerLower, 'add') !== false) $addsCol = $i;
                                }
                            } else {
                                // Data row
                                if ($artistCol >= 0 && $titleCol >= 0 && $spinsCol >= 0) {
                                    $artistName = trim($row[$artistCol] ?? '');
                                    $title = trim($row[$titleCol] ?? '');
                                    $spins = (int)($row[$spinsCol] ?? 0);
                                    $adds = (int)($row[$addsCol] ?? 0);

                                    if ($artistName && $title) {
                                        // Insert to staging table
                                        $stmtInsert = $pdo->prepare("
                                            INSERT INTO smr_staging (upload_id, row_number, artist_name, song_title, spins, adds)
                                            VALUES (?, ?, ?, ?, ?, ?)
                                        ");
                                        $stmtInsert->execute([$uploadId, $rowCount, $artistName, $title, $spins, $adds]);
                                        $rowData[] = [$artistName, $title, $spins, $adds];
                                    }
                                }
                            }
                            $rowCount++;
                        }
                        fclose($handle);

                        // Count unmatched artists
                        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT artist_name) FROM smr_staging WHERE upload_id = ?");
                        $stmt->execute([$uploadId]);
                        $unmatchedCount = (int)$stmt->fetchColumn();

                        // Update upload status
                        $stmt = $pdo->prepare("
                            UPDATE smr_uploads
                            SET row_count = ?, unmatched_count = ?, status = 'review'
                            WHERE id = ?
                        ");
                        $stmt->execute([$rowCount - 1, $unmatchedCount, $uploadId]);

                        $pdo->commit();

                        // Show success and redirect to mapping
                        echo '<div class="mt-6 p-4 bg-green-100 dark:bg-green-800 text-green-700 dark:text-green-100 rounded-lg">
                            <strong>✓ File uploaded successfully!</strong><br>
                            Parsed ' . ($rowCount - 1) . ' rows with ' . $unmatchedCount . ' unique artists.
                        </div>';

                        echo '<div class="mt-6 p-4 bg-blue-100 dark:bg-blue-800 text-blue-700 dark:text-blue-100 rounded-lg">
                            <a href="/admin/smr-mapping.php?upload_id=' . $uploadId . '" class="underline font-semibold">
                                Continue to Artist Mapping →
                            </a>
                        </div>';

                    } catch (PDOException $e) {
                        error_log('SMR parsing error: ' . $e->getMessage());
                        echo '<div class="mt-6 p-4 bg-red-100 dark:bg-red-800 text-red-700 dark:text-red-100 rounded-lg">
                            <strong>Error:</strong> Failed to process file. ' . $e->getMessage()
                        . '</div>';
                    }
                }
            }

            // Display recent uploads
            try {
                $pdo = new PDO("mysql:host=localhost;dbname=ngn_2025", 'root', '');
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $stmt = $pdo->query("
                    SELECT id, upload_filename, row_count, linkage_rate, status, created_at
                    FROM smr_uploads
                    ORDER BY created_at DESC
                    LIMIT 10
                ");
                $uploads = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($uploads)) {
                    echo '<div class="mt-8 bg-white dark:bg-gray-900 rounded-lg shadow p-6">
                        <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Recent Uploads</h4>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr class="bg-gray-50 dark:bg-gray-800">
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Filename</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Rows</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Linkage</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    ';

                    foreach ($uploads as $upload) {
                        $statusColor = [
                            'parsing' => 'blue',
                            'review' => 'yellow',
                            'mapping' => 'purple',
                            'ready' => 'green',
                            'finalized' => 'gray'
                        ][$upload['status']] ?? 'gray';

                        echo '<tr>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-200">' . htmlspecialchars($upload['upload_filename']) . '</td>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-200">' . number_format($upload['row_count']) . '</td>
                            <td class="px-6 py-4 text-sm"><span class="' . ($upload['linkage_rate'] >= 95 ? 'text-green' : 'text-yellow') . '-600 font-semibold">' . round($upload['linkage_rate'], 1) . '%</span></td>
                            <td class="px-6 py-4 text-sm"><span class="px-2 py-1 rounded text-white text-xs bg-' . $statusColor . '-500">' . ucfirst($upload['status']) . '</span></td>
                            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">' . date('M j, Y H:i', strtotime($upload['created_at'])) . '</td>
                            <td class="px-6 py-4 text-sm">
                                <a href="/admin/smr-mapping.php?upload_id=' . $upload['id'] . '" class="text-blue-500 hover:text-blue-700">Map</a> |
                                <a href="/admin/smr-finalize.php?upload_id=' . $upload['id'] . '" class="text-green-500 hover:text-green-700">Review</a>
                            </td>
                        </tr>';
                    }

                    echo '          </tbody>
                            </table>
                        </div>
                    </div>';
                }
            } catch (PDOException $e) {
                error_log('Upload list error: ' . $e->getMessage());
            }
            ?>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/_footer.php'; ?>