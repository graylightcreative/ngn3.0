<?php
require_once dirname(__DIR__, 2) . '/_guard.php';
$root = dirname(__DIR__, 2);

use NGN\Lib\Env;
use NGN\Lib\Config;
use NGN\Lib\Database\ConnectionFactory;

if (!class_exists('NGN\\Lib\\Env')) { exit('Bootstrap failed'); }
Env::load($root);
$cfg = new Config();
include __DIR__.'/_mint_token.php';

$pdo = ConnectionFactory::write($cfg);
$msg = '';
$err = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $adminId = 1; // TODO: Get actual admin ID from session/token

    if ($id > 0) {
        if ($action === 'approve') {
            try {
                $stmt = $pdo->prepare("UPDATE ppv_expenses SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
                $stmt->execute([$adminId, $id]);
                $msg = "Expense #{$id} approved.";
            } catch (\Throwable $e) {
                $err = "Failed to approve: " . $e->getMessage();
            }
        } elseif ($action === 'reject') {
            $reason = trim($_POST['reason'] ?? '');
            if (!$reason) {
                $err = "Rejection reason is required.";
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE ppv_expenses SET status = 'rejected', rejection_reason = ?, approved_by = ?, approved_at = NOW() WHERE id = ?");
                    $stmt->execute([$reason, $adminId, $id]);
                    $msg = "Expense #{$id} rejected.";
                } catch (\Throwable $e) {
                    $err = "Failed to reject: " . $e->getMessage();
                }
            }
        }
    }
}

// Fetch Pending Expenses
$pending = [];
try {
    $stmt = $pdo->query(
        "SELECT e.*, s.title as show_title, u.email as user_email\n        FROM ppv_expenses e\n        LEFT JOIN shows s ON e.show_id = s.id\n        LEFT JOIN ngn_2025.users u ON e.user_id = u.id\n        WHERE e.status = 'pending'\n        ORDER BY e.created_at DESC"
    );
    $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    // Table might not exist yet if migration just ran but cache/connection weirdness?
    // Or just no data.
}

$pageTitle = 'PPV Expenses';
$currentPage = 'ppv_expenses';
include __DIR__.'/_header.php';
include __DIR__.'/_topbar.php';
?>

<div class="max-w-7xl mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">PPV Expense Audit Queue</h1>
        <div class="text-sm text-gray-500">Review and approve deductible expenses for Net Split calculations.</div>
    </div>

    <?php if ($msg): ?>
        <div class="mb-4 p-4 rounded bg-emerald-50 text-emerald-700 border border-emerald-200"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
        <div class="mb-4 p-4 rounded bg-red-50 text-red-700 border border-red-200"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <div class="rounded-lg border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 overflow-hidden">
        <?php if (empty($pending)): ?>
            <div class="p-8 text-center text-gray-500">
                <p>No pending expenses found.</p>
                <p class="text-xs mt-2">Good job! All caught up.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-white/5 border-b border-gray-200 dark:border-white/10">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Show / User</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Category</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Proof</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        <?php foreach ($pending as $row): ?>
                            <tr>
                                <td class="px-4 py-3 align-top text-gray-500">#<?= $row['id'] ?></td>
                                <td class="px-4 py-3 align-top">
                                    <div class="font-medium"><?= htmlspecialchars($row['show_title'] ?? 'Unknown Show') ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($row['user_email'] ?? 'User #'.$row['user_id']) ?></div>
                                    <div class="text-xs text-gray-400 mt-1"><?= $row['created_at'] ?></div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                        <?= htmlspecialchars($row['category']) ?>
                                    </div>
                                    <?php if($row['description']): ?>
                                        <div class="mt-1 text-xs text-gray-500 italic">"<?= htmlspecialchars($row['description']) ?>"</div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 align-top font-mono font-medium">
                                    <?= htmlspecialchars($row['currency']) ?> <?= number_format($row['amount'], 2) ?>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <?php if ($row['receipt_url']): ?>
                                        <a href="<?= htmlspecialchars($row['receipt_url']) ?>" target="_blank" class="text-brand hover:underline flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                                            View Receipt
                                        </a>
                                    <?php else: ?>
                                        <span class="text-red-400 text-xs">Missing</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 align-top text-right space-x-2">
                                    <form method="POST" class="inline-block" onsubmit="return confirm('Approve this expense? It will be deducted from the show revenue.');">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="px-3 py-1.5 rounded bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium transition-colors">Approve</button>
                                    </form>
                                    <button type="button" onclick="openRejectModal(<?= $row['id'] ?>)" class="px-3 py-1.5 rounded bg-rose-600 hover:bg-rose-700 text-white text-xs font-medium transition-colors">Reject</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="closeRejectModal()"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form method="POST">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="id" id="rejectId">
                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100" id="modal-title">Reject Expense</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 dark:text-gray-400">Please provide a reason for rejecting this expense. This will be visible to the user.</p>
                                <textarea name="reason" class="mt-2 w-full rounded border border-gray-300 dark:border-white/10 bg-white dark:bg-black/20 p-2 text-sm" rows="3" required placeholder="e.g. Receipt unclear, Not a valid show expense..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-black/20 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">Reject</button>
                    <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-white/10 shadow-sm px-4 py-2 bg-white dark:bg-transparent text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm" onclick="closeRejectModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openRejectModal(id) {
    document.getElementById('rejectId').value = id;
    document.getElementById('rejectModal').classList.remove('hidden');
}
function closeRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
}
</script>

<?php include __DIR__.'/_footer.php'; ?>
