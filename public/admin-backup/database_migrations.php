<?php
error_log('admin/database_migrations.php: start');
require_once dirname(__DIR__, 2) . '/_guard.php';
$root = dirname(__DIR__, 2);
require_once $root . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\MigrationService;
use NGN\Lib\Env;

$config = new Config();
$migrationSvc = new MigrationService($config);

$pageTitle = 'Database Migrations';
$currentPage = 'database_migrations';
include __DIR__ . '/_header.php';

include __DIR__ . '/_topbar.php';

$migrationsDir = $root . '/migrations/sql/schema';
$allFiles = scandir($migrationsDir);
$migrationFiles = array_filter($allFiles, function ($file) {
    return !in_array($file, ['.', '..']) && $file[0] !== '.';
});

// Handle form submission
error_log('admin/database_migrations.php: checking request method');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log('admin/database_migrations.php: form submitted');
    if (isset($_POST['run_single_migration']) && isset($_POST['migration_file'])) {
        $file = $_POST['migration_file'];
        $result = $migrationSvc->runSingleMigration($file);
        $results = [$file => $result];
    } elseif (isset($_POST['run_migrations'])) {
        $results = $migrationSvc->runPendingMigrations();
    }
}

// Get applied migrations
try {
    $appliedMigrations = $migrationSvc->getAppliedMigrations();
} catch (\Exception $e) {
    $appliedMigrations = [];
    echo '<div class="max-w-7xl mx-auto px-4 py-6 space-y-6">'
        . '<div class="rounded-lg border border-red-500 bg-red-100 p-4">'
        . '<p class="text-red-700">Could not connect to the database to fetch applied migrations: '
        . htmlspecialchars($e->getMessage()) . '</p></div></div>';
}

?>

    <section class="max-w-7xl mx-auto px-4 py-6 space-y-6">
        <h1 class="text-2xl font-bold">Database Migrations</h1>

        <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
            <h2 class="text-lg font-semibold mb-2">Migrations</h2>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Migration
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Status
                    </th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($migrationFiles as $file) : ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap"><code><?= htmlspecialchars($file) ?></code></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php if (in_array($file, $appliedMigrations)) : ?>
                                <span class="text-green-500">Applied</span>
                            <?php else : ?>
                                <span class="text-yellow-500">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right font-medium">
                            <?php if (in_array($file, $appliedMigrations)) : ?>
                                <a href="#" class="text-red-600 hover:text-red-900">Rollback</a>
                            <?php else : ?>
                                <form method="POST" action="" class="inline">
                                    <input type="hidden" name="migration_file" value="<?= htmlspecialchars($file) ?>">
                                    <button type="submit" name="run_single_migration"
                                            class="text-brand hover:text-brand-900 bg-transparent border-none p-0 cursor-pointer">
                                        Run
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div class="mt-4">
                <form method="POST" action="">
                    <button type="submit" name="run_migrations"
                            class="inline-flex items-center px-4 py-2 border border-transparent
                                   shadow-sm text-sm font-medium rounded-md text-white
                                   bg-brand hover:bg-brand-700 focus:outline-none
                                   focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                        Run Pending Migrations
                    </button>
                </form>
            </div>
        </div>

        <?php if (isset($results)) : ?>
            <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4
                        bg-white/70 dark:bg-white/5">
                <h2 class="text-lg font-semibold mb-2">Migration Results</h2>
                <ul>
                    <?php foreach ($results as $file => $result) : ?>
                        <li>
                            <code><?= htmlspecialchars($file) ?></code>:
                            <?php if ($result === true) : ?>
                                <span class="text-green-500">Success</span>
                            <?php else : ?>
                                <span class="text-red-500">Failed: <?= htmlspecialchars($result) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </section>

<?php include __DIR__ . '/_footer.php'; ?>