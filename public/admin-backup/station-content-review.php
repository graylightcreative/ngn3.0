<?php
/**
 * Admin Dashboard - Station Content Review Queue
 * Moderation interface for BYOS content uploads
 * Pattern: /admin/takedown_requests.php
 */

require_once dirname(__DIR__, 2) . '/_guard.php'; // Admin authentication
$root = dirname(__DIR__, 2);

use NGN\Lib\Config;
use NGN\Lib\Stations\StationContentService;
use NGN\Lib\DB\ConnectionFactory;

Env::load($root);
$config = new Config();

try {
    $contentService = new StationContentService($config);
    $pdo = ConnectionFactory::read($config);
} catch (\Throwable $e) {
    die('Failed to initialize services');
}

// Page configuration
$pageTitle = 'BYOS Content Review';
$currentPage = 'station_content_review';

// Get filter from query string
$filterStatus = $_GET['status'] ?? 'pending';
$page = (int)($_GET['page'] ?? 1);
$perPage = 20;

// Handle admin actions
$message = $messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $contentId = (int)($_POST['content_id'] ?? 0);
    $adminId = (int)($_POST['admin_id'] ?? $_SESSION['user_id'] ?? 0); // Get admin ID from session

    try {
        if ($action === 'approve') {
            $success = $contentService->approveContent($contentId, $adminId);
            $message = $success ? 'Content approved' : 'Failed to approve content';
            $messageType = $success ? 'success' : 'error';
        } elseif ($action === 'reject') {
            $reason = $_POST['reason'] ?? 'Rejected by admin';
            $success = $contentService->rejectContent($contentId, $adminId, $reason);
            $message = $success ? 'Content rejected' : 'Failed to reject content';
            $messageType = $success ? 'success' : 'error';
        } elseif ($action === 'takedown') {
            $reason = $_POST['reason'] ?? 'Content takedown';
            $success = $contentService->takedownContent($contentId, $adminId, $reason);
            $message = $success ? 'Content taken down' : 'Failed to takedown content';
            $messageType = $success ? 'success' : 'error';
        }

        // Refresh list after action
        header("Location: ?status=$filterStatus&page=$page");
        exit;
    } catch (\Throwable $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Fetch content for review - Admin query (all stations)
try {
    $where = [];
    $params = [];

    if ($filterStatus && $filterStatus !== 'all') {
        $where[] = 'status = :status';
        $params[':status'] = $filterStatus;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Count total
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM station_content $whereClause");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // Fetch paginated
    $offset = ($page - 1) * $perPage;
    $listStmt = $pdo->prepare("
        SELECT
            sc.id, sc.station_id, sc.title, sc.artist_name, sc.file_size_bytes, sc.mime_type,
            sc.status, sc.created_at, sc.reviewed_at, sc.review_notes, sc.reviewed_by,
            s.name as station_name, u.Title as reviewer_name
        FROM station_content sc
        LEFT JOIN stations s ON sc.station_id = s.id
        LEFT JOIN users u ON sc.reviewed_by = u.Id
        $whereClause
        ORDER BY
            CASE WHEN sc.status = 'pending' THEN 0 ELSE 1 END,
            sc.created_at DESC
        LIMIT :offset, :perPage
    ");
    $listStmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
    $listStmt->bindValue(':perPage', $perPage, \PDO::PARAM_INT);
    foreach ($params as $key => $value) {
        $listStmt->bindValue($key, $value);
    }
    $listStmt->execute();
    $contents = $listStmt->fetchAll(\PDO::FETCH_ASSOC) ?? [];
} catch (\Throwable $e) {
    $message = 'Failed to load content: ' . $e->getMessage();
    $messageType = 'error';
    $contents = [];
    $total = 0;
}

// Get stats
$stats = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'taken_down' => 0];
try {
    $statsStmt = $pdo->prepare("
        SELECT
            status,
            COUNT(*) as count
        FROM station_content
        GROUP BY status
    ");
    $statsStmt->execute();
    $statsResults = $statsStmt->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($statsResults as $row) {
        $status = $row['status'];
        if (isset($stats[$status])) {
            $stats[$status] = (int)$row['count'];
        }
    }
} catch (\Throwable $e) {
    // Silently fail
}

include dirname(__FILE__) . '/_header.php';
?>

<div class="rounded-lg border border-gray-200 dark:border-white/10 p-6">
    <!-- Page Title -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">BYOS Content Review</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-1">Moderate station-uploaded music content</p>
    </div>

    <!-- Message Display -->
    <?php if ($message): ?>
        <div class="mb-4 p-3 rounded <?php echo $messageType === 'success' ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-200' : 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800">
            <p class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Pending</p>
            <p class="text-2xl font-bold text-yellow-900 dark:text-yellow-100"><?php echo $stats['pending']; ?></p>
        </div>
        <div class="p-4 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg border border-emerald-200 dark:border-emerald-800">
            <p class="text-sm font-medium text-emerald-800 dark:text-emerald-200">Approved</p>
            <p class="text-2xl font-bold text-emerald-900 dark:text-emerald-100"><?php echo $stats['approved']; ?></p>
        </div>
        <div class="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800">
            <p class="text-sm font-medium text-red-800 dark:text-red-200">Rejected</p>
            <p class="text-2xl font-bold text-red-900 dark:text-red-100"><?php echo $stats['rejected']; ?></p>
        </div>
        <div class="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800">
            <p class="text-sm font-medium text-red-800 dark:text-red-200">Taken Down</p>
            <p class="text-2xl font-bold text-red-900 dark:text-red-100"><?php echo $stats['taken_down']; ?></p>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="flex flex-wrap gap-2 mb-6">
        <a href="?status=all" class="px-4 py-2 rounded-lg font-medium transition <?php echo ($filterStatus === 'all' || $filterStatus === '') ? 'bg-brand text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white hover:bg-gray-300'; ?>">
            All
        </a>
        <a href="?status=pending" class="px-4 py-2 rounded-lg font-medium transition <?php echo ($filterStatus === 'pending') ? 'bg-brand text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white hover:bg-gray-300'; ?>">
            Pending (<?php echo $stats['pending']; ?>)
        </a>
        <a href="?status=approved" class="px-4 py-2 rounded-lg font-medium transition <?php echo ($filterStatus === 'approved') ? 'bg-brand text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white hover:bg-gray-300'; ?>">
            Approved
        </a>
        <a href="?status=rejected" class="px-4 py-2 rounded-lg font-medium transition <?php echo ($filterStatus === 'rejected') ? 'bg-brand text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white hover:bg-gray-300'; ?>">
            Rejected
        </a>
        <a href="?status=taken_down" class="px-4 py-2 rounded-lg font-medium transition <?php echo ($filterStatus === 'taken_down') ? 'bg-brand text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white hover:bg-gray-300'; ?>">
            Taken Down
        </a>
    </div>

    <!-- Content Table -->
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-100 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-900 dark:text-white">Track Info</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-900 dark:text-white">Station</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-900 dark:text-white">Status</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-900 dark:text-white">Submitted</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-900 dark:text-white">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php foreach ($contents as $content): ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                        <td class="px-4 py-3">
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($content['title']); ?></p>
                                <p class="text-xs text-gray-600 dark:text-gray-400">
                                    <?php echo htmlspecialchars($content['artist_name'] ?? 'Unknown'); ?> • <?php echo round($content['file_size_bytes'] / 1024 / 1024, 1); ?>MB
                                </p>
                                <?php if (!empty($content['review_notes'])): ?>
                                    <p class="text-xs text-gray-700 dark:text-gray-300 mt-1 italic">
                                        Note: <?php echo htmlspecialchars(substr($content['review_notes'], 0, 60)); ?>...
                                    </p>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <a href="/dashboard/station/?id=<?php echo $content['station_id']; ?>" class="text-blue-600 hover:underline dark:text-blue-400">
                                <?php echo htmlspecialchars($content['station_name']); ?>
                            </a>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 text-xs font-medium rounded <?php
                                echo match($content['status']) {
                                    'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-200',
                                    'approved' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200',
                                    'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200',
                                    'taken_down' => 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200',
                                    default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'
                                };
                            ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $content['status'])); ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-400">
                            <?php echo date('M d, g:i A', strtotime($content['created_at'])); ?>
                        </td>
                        <td class="px-4 py-3">
                            <?php if ($content['status'] === 'pending'): ?>
                                <div class="flex items-center gap-2">
                                    <!-- Approve Button -->
                                    <form method="post" class="inline">
                                        <input type="hidden" name="action" value="approve" />
                                        <input type="hidden" name="content_id" value="<?php echo $content['id']; ?>" />
                                        <button type="submit" class="px-2 py-1 bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-200 rounded text-xs hover:bg-emerald-200 dark:hover:bg-emerald-900 transition">
                                            ✓ Approve
                                        </button>
                                    </form>

                                    <!-- Reject Button with Modal -->
                                    <button onclick="openRejectModal(<?php echo $content['id']; ?>)" class="px-2 py-1 bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-200 rounded text-xs hover:bg-red-200 dark:hover:bg-red-900 transition">
                                        ✕ Reject
                                    </button>

                                    <!-- Takedown Button with Modal -->
                                    <button onclick="openTakedownModal(<?php echo $content['id']; ?>)" class="px-2 py-1 bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-200 rounded text-xs hover:bg-red-200 dark:hover:bg-red-900 transition">
                                        ⚠ Takedown
                                    </button>
                                </div>
                            <?php else: ?>
                                <span class="text-xs text-gray-500 dark:text-gray-500">
                                    Reviewed by <?php echo htmlspecialchars($content['reviewer_name'] ?? 'Admin'); ?> on <?php echo date('M d, Y', strtotime($content['reviewed_at'])); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Empty State -->
    <?php if (empty($contents)): ?>
        <div class="text-center py-12">
            <p class="text-gray-600 dark:text-gray-400">No content to review in this category.</p>
        </div>
    <?php endif; ?>

    <!-- Pagination -->
    <?php if ($total > $perPage): ?>
        <div class="mt-6 flex justify-center gap-2">
            <?php
            $totalPages = ceil($total / $perPage);
            for ($i = 1; $i <= $totalPages; $i++):
            ?>
                <a href="?status=<?php echo htmlspecialchars($filterStatus); ?>&page=<?php echo $i; ?>" class="px-3 py-1 rounded <?php echo ($page === $i) ? 'bg-brand text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white hover:bg-gray-300'; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Reject Modal -->
<div id="rejectModal" style="display: none;" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-sm">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Reject Content</h2>
        <form method="post" id="rejectForm">
            <input type="hidden" name="action" value="reject" />
            <input type="hidden" name="content_id" id="rejectContentId" />
            <textarea name="reason" placeholder="Reason for rejection..." maxlength="1000" rows="4" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white mb-4" required></textarea>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">Reject</button>
                <button type="button" onclick="closeRejectModal()" class="flex-1 px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-300 transition">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Takedown Modal -->
<div id="takedownModal" style="display: none;" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-sm">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Takedown Notice</h2>
        <form method="post" id="takedownForm">
            <input type="hidden" name="action" value="takedown" />
            <input type="hidden" name="content_id" id="takedownContentId" />
            <textarea name="reason" placeholder="DMCA/Copyright claim reason..." maxlength="1000" rows="4" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white mb-4" required></textarea>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">Takedown</button>
                <button type="button" onclick="closeTakedownModal()" class="flex-1 px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-300 transition">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRejectModal(contentId) {
    document.getElementById('rejectContentId').value = contentId;
    document.getElementById('rejectModal').style.display = 'flex';
}
function closeRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
}
function openTakedownModal(contentId) {
    document.getElementById('takedownContentId').value = contentId;
    document.getElementById('takedownModal').style.display = 'flex';
}
function closeTakedownModal() {
    document.getElementById('takedownModal').style.display = 'none';
}
// Close modals when clicking outside
document.addEventListener('click', function(event) {
    if (event.target.id === 'rejectModal') closeRejectModal();
    if (event.target.id === 'takedownModal') closeTakedownModal();
});
</script>

<?php include dirname(__FILE__) . '/_footer.php'; ?>
