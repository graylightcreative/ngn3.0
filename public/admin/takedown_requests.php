<?php
require_once dirname(__DIR__, 2) . '/_guard.php';
$root = dirname(__DIR__, 2);
// require_once $root.'/lib/bootstrap.php'; // Handled by _guard.php

use NGN\Lib\Env;
use NGN\Lib\Config;
use NGN\Lib\Services\Legal\TakedownService;

if (!class_exists('NGN\Lib\Env')) { exit('Bootstrap failed'); }
Env::load($root);
$cfg = new Config();
include __DIR__.'/_mint_token.php';

// Auth Check (Admin only)
// Assuming _mint_token.php handles some auth or we rely on the guard/sidebar context.
// In a real app, strict role checking is needed here.

$takedownService = new TakedownService($cfg);
$message = '';
$messageType = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    
    if ($id > 0 && in_array($action, ['approve', 'reject'])) {
        try {
            // TODO: Get actual admin ID from session
            $adminId = 1; 
            $takedownService->process($id, $action, $adminId);
            $message = "Request #{$id} " . ($action === 'approve' ? 'approved (content removed)' : 'rejected') . ".";
            $messageType = 'success';
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Fetch Requests
$filterStatus = $_GET['status'] ?? '';
$requests = $takedownService->listAll($filterStatus ?: null);

$pageTitle = 'DMCA Takedown Requests';
$currentPage = 'takedown_requests';
include __DIR__.'/_header.php';
include __DIR__.'/_topbar.php';
?>

<div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
  <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
    <div class="font-semibold text-lg">DMCA Takedown Requests</div>
    <div class="flex flex-wrap gap-2">
        <a href="?status=" class="px-3 h-8 flex items-center rounded border <?php echo $filterStatus === '' ? 'bg-brand text-white border-brand' : 'border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10'; ?> text-sm">All</a>
        <a href="?status=pending" class="px-3 h-8 flex items-center rounded border <?php echo $filterStatus === 'pending' ? 'bg-brand text-white border-brand' : 'border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10'; ?> text-sm">Pending</a>
        <a href="?status=approved" class="px-3 h-8 flex items-center rounded border <?php echo $filterStatus === 'approved' ? 'bg-brand text-white border-brand' : 'border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10'; ?> text-sm">Approved</a>
        <a href="?status=rejected" class="px-3 h-8 flex items-center rounded border <?php echo $filterStatus === 'rejected' ? 'bg-brand text-white border-brand' : 'border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10'; ?> text-sm">Rejected</a>
    </div>
  </div>

  <?php if ($message): ?>
    <div class="mb-4 p-3 rounded <?php echo $messageType === 'success' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
  <?php endif; ?>
  
  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead class="text-left text-gray-600 dark:text-gray-300 border-b border-gray-200 dark:border-white/10">
        <tr>
          <th class="py-2 pr-3">ID</th>
          <th class="py-2 pr-3">Type</th>
          <th class="py-2 pr-3">Content ID</th>
          <th class="py-2 pr-3">Reason</th>
          <th class="py-2 pr-3">Status</th>
          <th class="py-2 pr-3">Created</th>
          <th class="py-2 pr-3">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-white/5">
        <?php if (empty($requests)): ?>
            <tr><td colspan="7" class="py-4 text-center text-gray-500">No requests found.</td></tr>
        <?php else: ?>
            <?php foreach ($requests as $r): ?>
                <tr>
                    <td class="py-2 pr-3"><?php echo $r['id']; ?></td>
                    <td class="py-2 pr-3"><span class="uppercase text-xs font-bold bg-gray-100 dark:bg-white/10 px-2 py-0.5 rounded"><?php echo htmlspecialchars($r['content_type']); ?></span></td>
                    <td class="py-2 pr-3"><?php echo $r['content_id']; ?></td>
                    <td class="py-2 pr-3 max-w-xs truncate" title="<?php echo htmlspecialchars($r['reason']); ?>"><?php echo htmlspecialchars($r['reason']); ?></td>
                    <td class="py-2 pr-3">
                        <?php 
                        $statusColor = match($r['status']) {
                            'approved' => 'text-emerald-500',
                            'rejected' => 'text-red-500',
                            'pending' => 'text-amber-500',
                            default => 'text-gray-500'
                        };
                        ?>
                        <span class="font-medium <?php echo $statusColor; ?>"><?php echo ucfirst($r['status']); ?></span>
                    </td>
                    <td class="py-2 pr-3"><?php echo explode(' ', $r['created_at'])[0]; ?></td>
                    <td class="py-2 pr-3">
                        <?php if ($r['status'] === 'pending'): ?>
                            <div class="flex gap-2">
                                <form method="post" onsubmit="return confirm('Approve takedown? Content will be hidden.');">
                                    <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="px-2 h-7 rounded bg-red-600 hover:bg-red-700 text-white text-xs">Takedown</button>
                                </form>
                                <form method="post" onsubmit="return confirm('Reject request?');">
                                    <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="px-2 h-7 rounded bg-gray-200 dark:bg-white/10 hover:bg-gray-300 text-xs">Reject</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <span class="text-xs text-gray-400">Processed</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__.'/_footer.php'; ?>