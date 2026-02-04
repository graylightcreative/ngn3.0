<?php
require_once dirname(__DIR__, 2) . '/_guard.php';
$root = dirname(__DIR__, 2);
// require_once $root.'/lib/bootstrap.php';

use NGN\Lib\Env;
use NGN\Lib\Config;

if (!class_exists('NGN\\Lib\\Env')) { exit('Bootstrap failed'); }
Env::load($root);
$cfg = new Config();
include dirname(__DIR__).'/_mint_token.php';

$pageTitle = 'Counter Notice';
$currentPage = 'counter_notice';
include dirname(__DIR__).'/_header.php';
include dirname(__DIR__).'/_topbar.php';
?>
<div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
    <h1 class="text-2xl font-bold mb-4">Counter Notice</h1>
    <p class="text-gray-500 dark:text-gray-400">This is the counter notice page. Content will be added soon.</p>
</div>
<?php
include dirname(__DIR__).'/_footer.php';
?>