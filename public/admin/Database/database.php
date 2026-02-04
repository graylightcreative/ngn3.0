<?php
require_once dirname(__DIR__, 2) . '/_guard.php';
$root = dirname(__DIR__, 2);
require_once $root.'/lib/bootstrap.php';

use NGN\Lib\Config;

$config = new Config();

$pageTitle = 'Database Overview';
$currentPage = 'database';
include __DIR__.'/_header.php';

include __DIR__.'/_topbar.php';
?>

<section class="max-w-7xl mx-auto px-4 py-6 space-y-6">
    <h1 class="text-2xl font-bold">Database Overview</h1>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
            <h2 class="text-lg font-semibold mb-2">Database Information</h2>
            <p><strong>Server:</strong> <code>localhost</code></p>
            <p><strong>Database:</strong> <code>ngn_2025</code></p>
            <p><strong>Status:</strong> <span class="text-green-500">Connected</span></p>
        </div>

        <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
            <h2 class="text-lg font-semibold mb-2">Quick Actions</h2>
            <ul class="list-disc list-inside">
                <li><a href="database_migrations.php" class="text-brand hover:underline">Manage Migrations</a></li>
                <li><a href="database_schema.php" class="text-brand hover:underline">View Database Schema</a></li>
                <li><a href="database_backups.php" class="text-brand hover:underline">Manage Backups</a></li>
                <li><a href="database_explorer.php" class="text-brand hover:underline">Explore Database</a></li>
            </ul>
        </div>
    </div>
</section>

<?php include __DIR__.'/_footer.php'; ?>
