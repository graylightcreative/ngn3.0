<?php
/**
 * Admin: Compute Rankings
 * Triggers ranking calculation for NGN 2.0
 */
require_once dirname(__DIR__, 2) . '/_guard.php';
$root = dirname(__DIR__, 2);
// require_once $root . '/lib/bootstrap.php';

use NGN\Lib\Env;
use NGN\Lib\Config;
use NGN\Lib\Rankings\RankingCalculator;

if (!class_exists('NGN\\Lib\\Env')) { exit('Bootstrap failed'); }
Env::load($root);
$cfg = new Config();
include dirname(__DIR__).'/_mint_token.php';

$config = Config::load();
$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['compute'])) {
    try {
        $calculator = new RankingCalculator($config);

        $interval = $_POST['interval'] ?? 'all';

        if ($interval === 'all') {
            $result = $calculator->computeAll();
        } else {
            $result = [$interval => $calculator->computeForInterval($interval)];
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = 'Compute Rankings';
$currentPage = 'compute-rankings';
include dirname(__DIR__).'/_header.php';

include dirname(__DIR__).'/_topbar.php';
?>

      <section class="max-w-7xl mx-auto px-4 py-6 space-y-6">

        <div class="rounded-lg border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 p-4">
          <form method="POST" class="flex flex-wrap items-end gap-4">
            <div>
              <label class="block text-sm text-gray-600 dark:text-gray-300 mb-1">Interval</label>
              <select name="interval" class="px-3 py-2 rounded border border-gray-300 dark:border-white/20 bg-white dark:bg-white/10 text-sm">
                <option value="all">All (Daily, Weekly, Monthly)</option>
                <option value="daily">Daily Only</option>
                <option value="weekly">Weekly Only</option>
                <option value="monthly">Monthly Only</option>
              </select>
            </div>
            <button type="submit" name="compute" value="1" class="px-4 py-2 rounded bg-brand text-white text-sm font-medium">
              Compute Rankings
            </button>
          </form>
        </div>

        <?php if ($error): ?>
        <div class="rounded-lg border border-red-300 dark:border-red-500/30 bg-red-50 dark:bg-red-500/10 p-4 text-red-700 dark:text-red-300">
          <strong>Error:</strong> <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if ($result): ?>
        <div class="rounded-lg border border-green-300 dark:border-green-500/30 bg-green-50 dark:bg-green-500/10 p-4 text-green-700 dark:text-green-300">
          <strong>Rankings computed successfully!</strong>
        </div>

        <div class="rounded-lg border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 overflow-hidden">
          <div class="px-4 py-2 border-b border-gray-200 dark:border-white/10 text-sm font-medium">Results</div>
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-gray-50 dark:bg-white/5">
                <tr>
                  <th class="px-4 py-2 text-left">Interval</th>
                  <th class="px-4 py-2 text-left">Window ID</th>
                  <th class="px-4 py-2 text-left">Window Start</th>
                  <th class="px-4 py-2 text-left">Window End</th>
                  <th class="px-4 py-2 text-left">Artists Ranked</th>
                  <th class="px-4 py-2 text-left">Labels Ranked</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($result as $interval => $data): ?>
                <tr class="border-t border-gray-200 dark:border-white/10">
                  <td class="px-4 py-2"><?= htmlspecialchars($interval) ?></td>
                  <td class="px-4 py-2"><?= htmlspecialchars($data['window_id'] ?? '-') ?></td>
                  <td class="px-4 py-2"><?= htmlspecialchars($data['window_start'] ?? '-') ?></td>
                  <td class="px-4 py-2"><?= htmlspecialchars($data['window_end'] ?? '-') ?></td>
                  <td class="px-4 py-2"><?= htmlspecialchars($data['artists_ranked'] ?? 0) ?></td>
                  <td class="px-4 py-2"><?= htmlspecialchars($data['labels_ranked'] ?? 0) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endif; ?>

        <div class="rounded-lg border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5">
          <div class="px-4 py-2 border-b border-gray-200 dark:border-white/10 text-sm font-medium">About Rankings</div>
          <div class="p-4 text-sm text-gray-600 dark:text-gray-300 space-y-2">
            <p>This tool computes NGN rankings for artists and labels based on:</p>
            <ul class="list-disc list-inside space-y-1">
              <li><strong>Radio Spins</strong> - Plays on partner stations</li>
              <li><strong>Social Connections</strong> - Connected platforms (Spotify, Facebook, Instagram, etc.)</li>
              <li><strong>Releases</strong> - Number of albums/EPs/singles</li>
              <li><strong>Videos</strong> - Music videos and content</li>
              <li><strong>Mentions</strong> - Press and blog mentions</li>
              <li><strong>Views</strong> - Profile and content views</li>
              <li><strong>Claimed Status</strong> - Verified profile ownership</li>
            </ul>
            <p>Rankings are stored in <code class="px-1 py-0.5 rounded bg-gray-100 dark:bg-white/10 text-xs">ngn_rankings_2025.ranking_windows</code> and <code class="px-1 py-0.5 rounded bg-gray-100 dark:bg-white/10 text-xs">ranking_items</code> tables.</p>
          </div>
        </div>
      </section>
    </main>
  </div>
<?php include dirname(__DIR__).'/_footer.php'; ?>

