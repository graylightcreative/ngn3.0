<?php
require_once dirname(__DIR__, 2) . '/_guard.php';
$root = dirname(__DIR__, 2);
require_once $root.'/lib/bootstrap.php';

use NGN\Lib\Config;

$config = new Config();

$pageTitle = 'Database Backups';
$currentPage = 'database_backups';
include __DIR__.'/_header.php';

include __DIR__.'/_topbar.php';
?>

<section class="max-w-7xl mx-auto px-4 py-6 space-y-6">
    <h1 class="text-2xl font-bold">Database Backups</h1>
    <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <h2 class="text-lg font-semibold mb-2">Database Backups</h2>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Filename</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap"><code>backup_2024-01-01.sql</code></td>
                    <td class="px-6 py-4 whitespace-nowrap">10 MB</td>
                    <td class="px-6 py-4 whitespace-nowrap">2024-01-01</td>
                    <td class="px-6 py-4 whitespace-nowrap text-right font-medium">
                        <a href="#" class="text-brand hover:text-brand-900">Download</a> |
                        <a href="#" class="text-red-600 hover:text-red-900">Restore</a>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="mt-4">
            <button class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-brand hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">Create New Backup</button>
        </div>
    </div>
</section>

<?php include __DIR__.'/_footer.php'; ?>
