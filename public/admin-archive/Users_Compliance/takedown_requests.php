<?php
require_once dirname(__DIR__, 2) . '/_guard.php';
$root = dirname(__DIR__, 2);
// require_once $root.'/lib/bootstrap.php'; // Handled by _guard.php

use NGN\Lib\Config;
use NGN\Lib\TakedownRequestService;

$config = new Config();
$takedownSvc = new NGN\Lib\TakedownRequestService($config);

try {
    $takedownRequests = $takedownSvc->listTakedownRequests();
} catch (\Throwable $e) {
    $takedownRequests = [];
}

$pageTitle = 'Takedown Requests';
$currentPage = 'takedown_requests';
include dirname(__DIR__).'/_header.php';

include dirname(__DIR__).'/_topbar.php';
?>

<section class="max-w-7xl mx-auto px-4 py-6 space-y-6">
    <h1 class="text-2xl font-bold">Takedown Requests</h1>

    <div class="bg-dark shadow rounded-md overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">ID</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">Content ID</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">Content Type</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">Reason</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">Status</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($takedownRequests as $request): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap dark:text-gray-100"><?= htmlspecialchars($request['id']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap dark:text-gray-100"><?= htmlspecialchars($request['content_id']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap dark:text-gray-100"><?= htmlspecialchars($request['content_type']) ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($request['reason']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap dark:text-gray-100"><?= htmlspecialchars($request['status']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-right font-medium">
                        <a href="#" class="text-indigo-600 hover:text-indigo-900">View</a>
                        <a href="counter_notice.php?takedown_request_id=<?= htmlspecialchars($request['id']) ?>">Counter-Notice</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div>
        <h2 class="text-lg font-bold">Create New Takedown Request</h2>
        <form method="POST" action="./takedown_requests.php">
            <label for="content_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Content ID</label>
            <input type="text" name="content_id" id="content_id" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md dark:bg-gray-700 dark:text-gray-100 dark:border-gray-600">

            <label for="content_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Content Type</label>
            <input type="text" name="content_type" id="content_type" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md dark:bg-gray-700 dark:text-gray-100 dark:border-gray-600">

            <label for="reason" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Reason</label>
            <textarea name="reason" id="reason" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md dark:bg-gray-700 dark:text-gray-100 dark:border-gray-600"></textarea>

            <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">Create</button>
        </form>
    </div>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $contentId = isset($_POST['content_id']) ? (int)$_POST['content_id'] : 0;
        $contentType = trim((string)($_POST['content_type'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));

        if ($contentId > 0 && $contentType !== '' && $reason !== '') {
            try {
                $takedownSvc->createTakedownRequest([
                    'content_id' => $contentId,
                    'content_type' => $contentType,
                    'reason' => $reason,
                ]);
                echo "Takedown request submitted successfully.";
                echo '<script>window.location.href = "/admin/takedown_requests.php?cache=" + new Date().getTime();</script>';
            } catch (\Throwable $e) {
                echo "Failed to submit takedown request: " . htmlspecialchars($e->getMessage());
            }
        } else {
            echo "Please fill in all fields correctly.";
        }
    }
    ?>
</section>
<?php include dirname(__DIR__).'/_footer.php'; ?>
