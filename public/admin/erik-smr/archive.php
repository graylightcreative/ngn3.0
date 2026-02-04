<?php
// admin/erik-smr/archive.php
session_start();
require_once __DIR__ . '/../../lib/bootstrap.php';
if (!isset($_SESSION['erik_smr_logged_in']) || $_SESSION['erik_smr_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}
$currentPage = 'erik-smr-portal-archive'; // For sidebar highlighting or internal nav
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMR Historical Archive - Erik Baker SMR Portal</title>
    <link href="/frontend/public/build/tailwind.css" rel="stylesheet">
</head>
<body class="bg-gray-100 dark:bg-gray-900">
    <div class="flex h-screen">
        <aside class="w-64 bg-white dark:bg-gray-800 shadow">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">SMR Portal</h3>
            </div>
            <nav class="mt-5">
                <a href="index.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-200 dark:hover:bg-gray-700 dark:text-gray-200">Dashboard</a>
                <a href="upload.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-200 dark:hover:bg-gray-700 dark:text-gray-200">Upload SMR Data</a>
                <a href="review.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-200 dark:hover:bg-gray-700 dark:text-gray-200">Review & Validate</a>
                <a href="archive.php" class="block py-2.5 px-4 rounded transition duration-200 bg-gray-100 dark:bg-gray-700 dark:text-gray-200">Historical Archive</a>
                <a href="logout.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-200 dark:hover:bg-gray-700 dark:text-gray-200 text-red-500">Logout</a>
            </nav>
        </aside>
        <main class="flex-1 p-10 overflow-auto">
            <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100 mb-6">SMR Historical Archive (Erik)</h1>
            <p class="text-gray-700 dark:text-gray-300 mb-8">
                Access and review the historical SMR data archives.
            </p>

            <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6">
                <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Historical SMR Files (Mock Data)</h4>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Archive Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date Range</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Uploaded On</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">SMR 2025 Q4 Archive</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">Oct - Dec 2025</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">2026-01-05</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 dark:text-green-400">Processed</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="#" class="text-brand hover:underline">View Details</a>
                                </td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">SMR 2025 Q3 Archive</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">Jul - Sep 2025</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">2025-10-01</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 dark:text-green-400">Processed</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="#" class="text-brand hover:underline">View Details</a>
                                </td>
                            </tr>
                            <!-- More mock archive entries -->
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
