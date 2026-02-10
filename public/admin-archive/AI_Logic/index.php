<?php
require_once dirname(__DIR__, 2) . '/_guard.php';
$root = dirname(__DIR__, 2);

use NGN\Lib\Env;
use NGN\Lib\Config;

if (!class_exists('NGN\\Lib\\Env')) { exit('Bootstrap failed'); }
Env::load($root);
$cfg = new Config();
include dirname(__DIR__).'/_mint_token.php';

$pageTitle = 'AI Logic Dashboard';
$currentPage = 'ai_logic';
include dirname(__DIR__).'/_header.php';

include dirname(__DIR__).'/_topbar.php';
?>

<section class="max-w-7xl mx-auto px-4 py-6 space-y-6">
    <h1 class="text-2xl font-bold sk-text-gradient-secondary">AI Logic Section</h1>
    <p class="text-gray-500 dark:text-gray-400">Explore various AI-powered features:</p>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <a href="./compute-rankings.php" class="block p-6 rounded-lg border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 hover:border-brand transition-colors">
            <h3 class="text-lg font-semibold mb-2">Compute Rankings</h3>
            <p class="text-sm text-gray-500">Trigger ranking calculation for NGN 2.0</p>
        </a>
        <a href="./readiness.php" class="block p-6 rounded-lg border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 hover:border-brand transition-colors">
            <h3 class="text-lg font-semibold mb-2">Readiness Check</h3>
            <p class="text-sm text-gray-500">System status overview for seamless transition.</p>
        </a>
        <a href="./roadmap.php" class="block p-6 rounded-lg border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 hover:border-brand transition-colors">
            <h3 class="text-lg font-semibold mb-2">AI Roadmap</h3>
            <p class="text-sm text-gray-500">View upcoming AI features and plans.</p>
        </a>
        <a href="./royalties.php" class="block p-6 rounded-lg border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 hover:border-brand transition-colors">
            <h3 class="text-lg font-semibold mb-2">AI Royalties</h3>
            <p class="text-sm text-gray-500">Manage AI-driven royalty calculations.</p>
        </a>
        <a href="./sparks.php" class="block p-6 rounded-lg border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 hover:border-brand transition-colors">
            <h3 class="text-lg font-semibold mb-2">AI Sparks</h3>
            <p class="text-sm text-gray-500">Monitor and manage AI Sparks usage.</p>
        </a>
    </div>
</section>

<?php include dirname(__DIR__).'/_footer.php'; ?>