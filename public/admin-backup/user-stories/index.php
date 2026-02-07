<?php
// admin/user-stories/index.php
require_once __DIR__ . '/../_guard.php'; // Admin authentication guard
require_once __DIR__ . '/../../lib/bootstrap.php'; // Bootstrap application

$currentPage = 'user-stories'; // For sidebar highlighting

// Include the admin header
require_once __DIR__ . '/../_header.php';

$userStoryFiles = [];
$userStoriesDir = __DIR__ . '/../../docs/user_stories/';

if (is_dir($userStoriesDir)) {
    $files = scandir($userStoriesDir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'md') {
            $userStoryFiles[] = $file;
        }
    }
}
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 dark:bg-gray-800">
    <div class="container mx-auto px-6 py-8">
        <h3 class="text-gray-700 text-3xl font-medium dark:text-gray-200">User Story Checklists</h3>

        <div class="mt-4">
            <p class="text-gray-600 dark:text-gray-400">
                View and manage markdown files containing user story checklists for various user types.
            </p>
        </div>

        <div class="mt-8 bg-white dark:bg-gray-900 rounded-lg shadow p-6">
            <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Available Checklists</h4>
            
            <?php if (empty($userStoryFiles)): ?>
                <div class="p-4 bg-yellow-100 dark:bg-yellow-800 text-yellow-700 dark:text-yellow-100 rounded-lg">
                    No user story markdown files found in the 'docs/user_stories/' directory.
                </div>
            <?php else: ?>
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($userStoryFiles as $file): ?>
                        <li class="py-3 flex justify-between items-center">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($file) ?></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Path: <?= htmlspecialchars('docs/user_stories/' . $file) ?></p>
                            </div>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100">View (Placeholder)</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../_footer.php'; ?>
