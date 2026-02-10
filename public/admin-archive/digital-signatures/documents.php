<?php
// admin/digital-signatures/documents.php
require_once __DIR__ . '/../_guard.php'; // Admin authentication guard
require_once __DIR__ . '/../../lib/bootstrap.php'; // Bootstrap application

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Services\Legal\AgreementService;

$currentPage = 'digital-signatures'; // For sidebar highlighting

$config = new Config();
$db = ConnectionFactory::write($config);
$service = new AgreementService($db);

$message = '';
$messageType = '';

// Handle template creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'upsert') {
        $slug = $_POST['slug'] ?? '';
        $title = $_POST['title'] ?? '';
        $body = $_POST['body'] ?? '';
        $version = $_POST['version'] ?? '1.0.0';

        if (!empty($slug) && !empty($title) && !empty($body)) {
            try {
                $service->upsertTemplate($slug, $title, $body, $version);
                $message = "Template '{$title}' updated successfully.";
                $messageType = 'success';
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = "All fields are required.";
            $messageType = 'error';
        }
    }
}

// Fetch all templates
$stmt = $db->query("SELECT * FROM agreement_templates ORDER BY updated_at DESC");
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Include the admin header
require_once __DIR__ . '/../_header.php';
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 dark:bg-gray-800">
    <div class="container mx-auto px-6 py-8">
        <div class="flex justify-between items-center">
            <h3 class="text-gray-700 text-3xl font-medium dark:text-gray-200">Document Management</h3>
            <button onclick="document.getElementById('new-template-modal').classList.remove('hidden')" class="px-4 py-2 bg-brand text-white rounded-lg font-bold hover:bg-brand-dark transition-all">
                <i class="bi bi-plus-lg mr-2"></i> Create Template
            </button>
        </div>

        <?php if ($message): ?>
            <div class="mt-4 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="mt-8 bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                <thead class="bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Document Title</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Slug</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Version</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Last Updated</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    <?php foreach ($templates as $t): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($t['title']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($t['slug']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($t['version']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-bold rounded-full <?= $t['is_active'] ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-700' ?>">
                                    <?= $t['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"><?= date('M j, Y', strtotime($t['updated_at'])) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-bold">
                                <button onclick='editTemplate(<?= json_encode($t) ?>)' class="text-brand hover:underline">Edit</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- New/Edit Template Modal -->
    <div id="new-template-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="this.parentElement.parentElement.classList.add('hidden')"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white dark:bg-gray-900 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                <form action="documents.php" method="POST" class="p-8">
                    <input type="hidden" name="action" value="upsert">
                    <h3 class="text-2xl font-black text-gray-900 dark:text-white mb-6" id="modal-title">Agreement Template</h3>
                    
                    <div class="grid grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Title</label>
                            <input type="text" name="title" id="form-title" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm focus:border-brand focus:ring-brand" required>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Slug</label>
                            <input type="text" name="slug" id="form-slug" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm focus:border-brand focus:ring-brand" required>
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Version</label>
                        <input type="text" name="version" id="form-version" value="1.0.0" class="w-32 rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm focus:border-brand focus:ring-brand" required>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Agreement Body (HTML supported)</label>
                        <textarea name="body" id="form-body" rows="12" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm focus:border-brand focus:ring-brand font-mono text-sm" required></textarea>
                    </div>

                    <div class="flex justify-end gap-4">
                        <button type="button" onclick="document.getElementById('new-template-modal').classList.add('hidden')" class="px-6 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg font-bold hover:bg-gray-300 transition-all">Cancel</button>
                        <button type="submit" class="px-6 py-2 bg-brand text-white rounded-lg font-bold hover:bg-brand-dark transition-all">Save Template</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
function editTemplate(t) {
    document.getElementById('form-title').value = t.title;
    document.getElementById('form-slug').value = t.slug;
    document.getElementById('form-version').value = t.version;
    document.getElementById('form-body').value = t.body;
    document.getElementById('modal-title').innerText = 'Edit Template: ' + t.title;
    document.getElementById('new-template-modal').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/../_footer.php'; ?>