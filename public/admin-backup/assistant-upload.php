<?php
/**
 * Assistant Upload Portal - Simplified SMR Upload Interface
 *
 * Dedicated portal for Erik Baker's assistant to upload Station Music Reports
 * and manage content without full admin access.
 *
 * Features:
 * - Simple CSV upload interface
 * - Upload history view
 * - No access to QA approval, mappings, or other admin functions
 * - Clean, focused workflow
 *
 * Related: Bible Ch. 5 (Data Integrity), Ch. 28 (Chart Integrity)
 */

// Require authentication
require_once __DIR__ . '/assistant-auth.php';

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config\Config;

// Simple authentication check for assistant role
// Replace with your actual auth system
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'assistant') {
//     header('Location: /login');
//     exit;
// }

$config = new Config();
$pdo = ConnectionFactory::read($config);

// Handle CSV upload
$uploadSuccess = false;
$uploadError = null;
$uploadedFileId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['smr_csv'])) {
    try {
        $file = $_FILES['smr_csv'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception("File upload failed with error code: " . $file['error']);
        }

        if ($file['size'] > 50 * 1024 * 1024) { // 50MB limit
            throw new \Exception("File too large. Maximum size is 50MB.");
        }

        $allowedExtensions = ['csv', 'txt'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions)) {
            throw new \Exception("Invalid file type. Only CSV files are allowed.");
        }

        // Calculate SHA-256 hash
        $sha256Hash = hash_file('sha256', $file['tmp_name']);

        // Check for duplicate upload
        $stmt = $pdo->prepare("SELECT id FROM ngn_2025.smr_uploads WHERE file_hash = ?");
        $stmt->execute([$sha256Hash]);
        if ($stmt->fetch()) {
            throw new \Exception("This file has already been uploaded (duplicate detected).");
        }

        // Store file
        $uploadDir = __DIR__ . '/../storage/smr_uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $timestamp = date('Y-m-d_His');
        $safeFilename = preg_replace('/[^a-zA-Z0-9_.-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
        $newFilename = "{$timestamp}_{$safeFilename}.csv";
        $destination = $uploadDir . $newFilename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new \Exception("Failed to move uploaded file.");
        }

        // Parse basic CSV stats
        $handle = fopen($destination, 'r');
        $rowCount = 0;
        $header = null;
        while (($data = fgetcsv($handle)) !== false) {
            if ($rowCount === 0) {
                $header = $data;
            }
            $rowCount++;
        }
        fclose($handle);
        $rowCount--; // Subtract header row

        // Insert upload record
        $reportDate = $_POST['report_date'] ?? null;
        $reportType = $_POST['report_type'] ?? 'weekly';
        $notes = $_POST['notes'] ?? '';

        $stmt = $pdo->prepare("
            INSERT INTO ngn_2025.smr_uploads
            (filename, file_hash, file_size, row_count, report_date, report_type, notes, status, uploaded_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([
            $newFilename,
            $sha256Hash,
            $file['size'],
            $rowCount,
            $reportDate,
            $reportType,
            $notes
        ]);

        $uploadedFileId = $pdo->lastInsertId();
        $uploadSuccess = true;

        // NGN 2.0.2: Register in content ledger (non-blocking)
        try {
            require_once __DIR__ . '/../../lib/bootstrap.php';
            $config = new \NGN\Lib\Config();
            $ledgerService = new \NGN\Lib\Legal\ContentLedgerService($pdo, $config, new \Monolog\Logger('assistant_upload'));

            $ownerId = $_SESSION['user_id'] ?? 1;
            if ($ownerId > 0) {
                $ledgerRecord = $ledgerService->registerContent(
                    ownerId: $ownerId,
                    contentHash: $sha256Hash,
                    uploadSource: 'smr_assistant',
                    metadata: [
                        'title' => 'Assistant Upload: ' . $newFilename,
                        'artist_name' => '',
                        'credits' => null,
                        'rights_split' => null
                    ],
                    fileInfo: [
                        'size_bytes' => $file['size'],
                        'mime_type' => 'text/csv',
                        'filename' => $newFilename
                    ],
                    sourceRecordId: $uploadedFileId
                );

                // Update smr_uploads with certificate ID
                if (!empty($ledgerRecord['certificate_id'])) {
                    $updateStmt = $pdo->prepare("UPDATE smr_uploads SET certificate_id = ? WHERE id = ?");
                    $updateStmt->execute([$ledgerRecord['certificate_id'], $uploadedFileId]);
                }
            }
        } catch (\Throwable $e) {
            // Log but don't fail the upload
            error_log('Assistant upload ledger registration failed: ' . $e->getMessage());
        }

        // Redirect to avoid resubmission
        header("Location: assistant-upload.php?upload=success&file_id={$uploadedFileId}");
        exit;

    } catch (\Throwable $e) {
        $uploadError = $e->getMessage();
        error_log("SMR upload error (assistant): " . $e->getMessage());
    }
}

// Fetch recent uploads by this assistant (last 30 days)
$uploadHistory = [];
try {
    $stmt = $pdo->prepare("
        SELECT
            id,
            filename,
            file_hash,
            file_size,
            row_count,
            report_date,
            report_type,
            status,
            notes,
            uploaded_at
        FROM ngn_2025.smr_uploads
        WHERE uploaded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY uploaded_at DESC
        LIMIT 50
    ");
    $stmt->execute();
    $uploadHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    error_log("Failed to fetch upload history: " . $e->getMessage());
}

// Calculate upload stats
$uploadStats = [
    'total_30d' => count($uploadHistory),
    'pending' => count(array_filter($uploadHistory, fn($u) => $u['status'] === 'pending')),
    'approved' => count(array_filter($uploadHistory, fn($u) => $u['status'] === 'approved')),
    'rejected' => count(array_filter($uploadHistory, fn($u) => $u['status'] === 'rejected'))
];

?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMR Upload Portal - NextGenNoise</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100">
    <div class="min-h-screen p-6">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-white mb-2">Station Music Reports Upload</h1>
                    <p class="text-gray-400">Upload weekly SMR data for chart processing</p>
                </div>
                <div class="text-right">
                    <div class="text-sm text-gray-400 mb-2">
                        <div>Erik Baker's Assistant Portal</div>
                        <div class="text-xs mt-1">Logged in as: <span class="text-white font-medium"><?= htmlspecialchars($_SESSION['name'] ?? 'Assistant') ?></span></div>
                    </div>
                    <a href="assistant-logout.php" class="inline-block px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded transition">
                        Sign Out
                    </a>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_GET['upload']) && $_GET['upload'] === 'success'): ?>
            <div class="mb-6 p-4 bg-green-900/30 border border-green-500 rounded-lg">
                <div class="flex items-center space-x-2">
                    <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <p class="text-green-400 font-medium">Upload successful! File ID: <?= htmlspecialchars($_GET['file_id'] ?? 'N/A') ?></p>
                </div>
                <p class="text-green-300 text-sm mt-2">Your file is now pending QA review and will be processed shortly.</p>
            </div>
        <?php endif; ?>

        <?php if ($uploadError): ?>
            <div class="mb-6 p-4 bg-red-900/30 border border-red-500 rounded-lg">
                <div class="flex items-center space-x-2">
                    <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    <p class="text-red-400 font-medium">Upload failed: <?= htmlspecialchars($uploadError) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Upload Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-gradient-to-br from-blue-900/40 to-blue-800/20 border border-blue-700/50 rounded-lg p-6">
                <div class="text-blue-400 text-sm font-medium mb-2">Total Uploads (30d)</div>
                <div class="text-3xl font-bold text-white"><?= $uploadStats['total_30d'] ?></div>
            </div>

            <div class="bg-gradient-to-br from-yellow-900/40 to-yellow-800/20 border border-yellow-700/50 rounded-lg p-6">
                <div class="text-yellow-400 text-sm font-medium mb-2">Pending Review</div>
                <div class="text-3xl font-bold text-white"><?= $uploadStats['pending'] ?></div>
            </div>

            <div class="bg-gradient-to-br from-green-900/40 to-green-800/20 border border-green-700/50 rounded-lg p-6">
                <div class="text-green-400 text-sm font-medium mb-2">Approved</div>
                <div class="text-3xl font-bold text-white"><?= $uploadStats['approved'] ?></div>
            </div>

            <div class="bg-gradient-to-br from-red-900/40 to-red-800/20 border border-red-700/50 rounded-lg p-6">
                <div class="text-red-400 text-sm font-medium mb-2">Rejected</div>
                <div class="text-3xl font-bold text-white"><?= $uploadStats['rejected'] ?></div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Upload Form -->
            <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
                <h2 class="text-xl font-bold text-white mb-4">Upload New SMR File</h2>

                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">
                            Report Date <span class="text-red-400">*</span>
                        </label>
                        <input type="date" name="report_date" required
                               value="<?= date('Y-m-d', strtotime('last Monday')) ?>"
                               class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-blue-500">
                        <p class="mt-1 text-xs text-gray-400">Select the Monday of the reporting week</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">
                            Report Type <span class="text-red-400">*</span>
                        </label>
                        <select name="report_type" required
                                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-blue-500">
                            <option value="weekly" selected>Weekly Report</option>
                            <option value="monthly">Monthly Report</option>
                            <option value="annual">Annual Report</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">
                            CSV File <span class="text-red-400">*</span>
                        </label>
                        <input type="file" name="smr_csv" accept=".csv,.txt" required
                               class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-blue-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-blue-600 file:text-white hover:file:bg-blue-500">
                        <p class="mt-2 text-xs text-gray-400">
                            Maximum file size: 50MB<br>
                            Accepted formats: .csv, .txt
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">
                            Notes (Optional)
                        </label>
                        <textarea name="notes" rows="3"
                                  class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-blue-500"
                                  placeholder="Any special notes about this upload..."></textarea>
                    </div>

                    <button type="submit" class="w-full px-6 py-3 bg-blue-600 hover:bg-blue-500 text-white font-medium rounded-lg transition flex items-center justify-center space-x-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        <span>Upload CSV File</span>
                    </button>
                </form>

                <!-- Upload Instructions -->
                <div class="mt-6 p-4 bg-blue-900/20 border border-blue-700/50 rounded-lg">
                    <h3 class="text-sm font-semibold text-blue-300 mb-2">Upload Instructions</h3>
                    <ul class="text-sm text-gray-400 space-y-1">
                        <li>1. Select the report date (usually the Monday of the week)</li>
                        <li>2. Choose the report type (typically "Weekly Report")</li>
                        <li>3. Upload the CSV file provided by the station</li>
                        <li>4. Add any special notes if needed</li>
                        <li>5. Click "Upload CSV File" to submit</li>
                    </ul>
                </div>
            </div>

            <!-- Upload History -->
            <div class="bg-gray-800 border border-gray-700 rounded-lg">
                <div class="p-6 border-b border-gray-700">
                    <h2 class="text-xl font-bold text-white">Recent Uploads (30 Days)</h2>
                </div>
                <div class="overflow-y-auto" style="max-height: 600px;">
                    <?php if (empty($uploadHistory)): ?>
                        <div class="p-8 text-center text-gray-500">
                            <svg class="w-16 h-16 mx-auto mb-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <div class="font-medium mb-1">No uploads yet</div>
                            <div class="text-sm">Upload your first SMR file using the form on the left</div>
                        </div>
                    <?php else: ?>
                        <div class="divide-y divide-gray-700">
                            <?php foreach ($uploadHistory as $upload): ?>
                                <div class="p-4 hover:bg-gray-750 transition">
                                    <div class="flex items-start justify-between mb-2">
                                        <div class="flex-1">
                                            <div class="font-medium text-white mb-1">
                                                <?= htmlspecialchars($upload['filename']) ?>
                                            </div>
                                            <div class="text-xs text-gray-400 space-y-1">
                                                <div>Report: <?= $upload['report_date'] ?> (<?= ucfirst($upload['report_type']) ?>)</div>
                                                <div>Uploaded: <?= date('M j, Y g:ia', strtotime($upload['uploaded_at'])) ?></div>
                                                <div><?= number_format($upload['row_count']) ?> rows · <?= number_format($upload['file_size'] / 1024, 1) ?> KB</div>
                                            </div>
                                        </div>
                                        <div>
                                            <?php
                                            $statusConfig = [
                                                'pending' => ['bg' => 'bg-yellow-900/30', 'text' => 'text-yellow-400', 'border' => 'border-yellow-700', 'label' => 'PENDING'],
                                                'approved' => ['bg' => 'bg-green-900/30', 'text' => 'text-green-400', 'border' => 'border-green-700', 'label' => '✓ APPROVED'],
                                                'rejected' => ['bg' => 'bg-red-900/30', 'text' => 'text-red-400', 'border' => 'border-red-700', 'label' => '✗ REJECTED']
                                            ];
                                            $status = $statusConfig[$upload['status']] ?? $statusConfig['pending'];
                                            ?>
                                            <span class="inline-block px-2 py-1 rounded border text-xs font-medium <?= $status['bg'] ?> <?= $status['text'] ?> <?= $status['border'] ?>">
                                                <?= $status['label'] ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php if ($upload['notes']): ?>
                                        <div class="text-xs text-gray-500 mt-2">
                                            <span class="font-medium">Notes:</span> <?= htmlspecialchars($upload['notes']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Help Section -->
        <div class="mt-8 bg-gray-800 border border-gray-700 rounded-lg p-6">
            <h3 class="text-lg font-bold text-white mb-4">Need Help?</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
                <div>
                    <div class="font-semibold text-blue-400 mb-2">File Format Questions</div>
                    <p class="text-gray-400">
                        If you're unsure about the CSV format or have issues with file formatting,
                        contact the technical team for guidance.
                    </p>
                </div>
                <div>
                    <div class="font-semibold text-blue-400 mb-2">Upload Issues</div>
                    <p class="text-gray-400">
                        If you encounter errors during upload, check that:
                        • File is under 50MB
                        • File is in CSV format
                        • File hasn't been uploaded before
                    </p>
                </div>
                <div>
                    <div class="font-semibold text-blue-400 mb-2">Status Explanations</div>
                    <p class="text-gray-400">
                        <strong>Pending:</strong> Awaiting QA review<br>
                        <strong>Approved:</strong> Ready for processing<br>
                        <strong>Rejected:</strong> Needs correction
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
