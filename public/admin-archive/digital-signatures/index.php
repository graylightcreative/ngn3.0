<?php
// admin/digital-signatures/index.php
require_once __DIR__ . '/../_guard.php'; // Admin authentication guard
require_once __DIR__ . '/../../lib/bootstrap.php'; // Bootstrap application

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

$currentPage = 'digital-signatures'; // For sidebar highlighting

$config = new Config();
$db = ConnectionFactory::write($config);

// Fetch recent signatures with user and template info
$stmt = $db->query("
    SELECT s.*, u.email, u.display_name, t.title as template_title, t.version
    FROM agreement_signatures s
    JOIN users u ON s.user_id = u.id
    JOIN agreement_templates t ON s.template_id = t.id
    ORDER BY s.signed_at DESC
    LIMIT 100
");
$signatures = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Include the admin header
require_once __DIR__ . '/../_header.php';
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 dark:bg-gray-800">
    <div class="container mx-auto px-6 py-8">
        <div class="flex justify-between items-center">
            <h3 class="text-gray-700 text-3xl font-medium dark:text-gray-200">Digital Signatures</h3>
            <a href="documents.php" class="px-4 py-2 bg-zinc-800 text-white rounded-lg font-bold hover:bg-zinc-700 transition-all">
                <i class="bi bi-file-earmark-text mr-2"></i> Manage Templates
            </a>
        </div>

        <div class="mt-4">
            <p class="text-gray-600 dark:text-gray-400">
                Audit log of all digital agreements signed on the platform.
            </p>
        </div>

        <div class="mt-8 bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Agreement</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Version</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Signed At</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">IP Address</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Hash (SHA-256)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                        <?php if (empty($signatures)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400 font-bold">
                                    No signatures recorded yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($signatures as $s): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($s['display_name'] ?: 'N/A') ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($s['email']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-900 dark:text-white font-medium"><?= htmlspecialchars($s['template_title']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-500 dark:text-gray-400"><?= htmlspecialchars($s['version']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-500 dark:text-gray-400"><?= date('M j, Y H:i', strtotime($s['signed_at'])) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-500 dark:text-gray-400 font-mono"><?= htmlspecialchars($s['ip_address']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 bg-gray-100 dark:bg-gray-800 rounded text-[10px] font-mono text-gray-500" title="<?= $s['agreement_hash'] ?>">
                                        <?= substr($s['agreement_hash'], 0, 8) ?>...
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../_footer.php'; ?>
