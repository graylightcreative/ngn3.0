<?php
require_once dirname(__DIR__, 2) . '/_guard.php';
$root = dirname(__DIR__, 2);
require_once $root.'/lib/bootstrap.php';

use NGN\Lib\Config;

$config = new Config();

$pageTitle = 'Database Schema';
$currentPage = 'database_schema';
include __DIR__.'/_header.php';

include __DIR__.'/_topbar.php';
?>

<section class="max-w-7xl mx-auto px-4 py-6 space-y-6">
    <h1 class="text-2xl font-bold">Database Schema</h1>
    <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <h2 class="text-lg font-semibold mb-2">Database Schema</h2>
        <p>Here's a list of tables in the database:</p>
        <ul class="list-disc list-inside">
            <li><code>table_1</code> (col1, col2, col3)</li>
            <li><code>table_2</code> (col1, col2, col3, col4)</li>
            <li><code>table_3</code> (col1, col2)</li>
        </ul>

        <div class="mt-4">
            <a href="#" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-brand hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">Download Schema Dump</a>
        </div>
    </div>
</section>

<?php include __DIR__.'/_footer.php'; ?>
