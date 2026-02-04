<?php
require_once dirname(__DIR__, 2) . '/_guard.php';
$root = dirname(__DIR__, 2);
require_once $root.'/lib/bootstrap.php';

use NGN\Lib\Config;

$config = new Config();

$pageTitle = 'Database Explorer';
$currentPage = 'database_explorer';
include __DIR__.'/_header.php';

include __DIR__.'/_topbar.php';
?>

<section class="max-w-7xl mx-auto px-4 py-6 space-y-6">
    <h1 class="text-2xl font-bold">Database Explorer</h1>
    <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <h2 class="text-lg font-semibold mb-2">SQL Explorer</h2>
        <textarea class="w-full h-48 rounded border border-gray-300 dark:border-white/10 bg-white/80 dark:bg-white/10"></textarea>

        <div class="mt-4">
            <button class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-brand hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">Execute Query</button>
        </div>

        <div class="mt-4">
            <h3 class="text-lg font-semibold mb-2">Results</h3>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Column 1</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Column 2</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">Value 1</td>
                        <td class="px-6 py-4 whitespace-nowrap">Value 2</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php include __DIR__.'/_footer.php'; ?>
