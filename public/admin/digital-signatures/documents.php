<?php
// admin/digital-signatures/documents.php
require_once __DIR__ . '/../_guard.php'; // Admin authentication guard
require_once __DIR__ . '/../../lib/bootstrap.php'; // Bootstrap application

$currentPage = 'digital-signatures'; // For sidebar highlighting in main admin nav

// Include the admin header
require_once __DIR__ . '/../_header.php';
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 dark:bg-gray-800">
    <div class="container mx-auto px-6 py-8">
        <h3 class="text-gray-700 text-3xl font-medium dark:text-gray-200">Document Management</h3>

        <div class="mt-4">
            <p class="text-gray-600 dark:text-gray-400">
                Manage contract documents available for digital signing.
            </p>
        </div>

        <div class="mt-8 bg-white dark:bg-gray-900 rounded-lg shadow p-6">
            <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Available Documents (Mock Data)</h4>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Document Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Version</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Last Updated</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">Artist Onboarding Agreement</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">1.2</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">2025-12-01</td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="#" class="text-brand hover:underline">View</a>
                                <a href="#" class="text-blue-600 hover:underline ml-3">Edit</a>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">Venue Partnership Contract</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">1.0</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">2026-01-01</td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="#" class="text-brand hover:underline">View</a>
                                <a href="#" class="text-blue-600 hover:underline ml-3">Edit</a>
                            </td>
                        </tr>
                        <!-- More mock documents -->
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                <button class="px-4 py-2 bg-brand text-white rounded hover:bg-brand-dark focus:outline-none focus:bg-brand-dark">
                    Upload New Document
                </button>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../_footer.php'; ?>
