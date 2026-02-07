<?php
// admin/erik-smr/index.php
session_start();

require_once __DIR__ . '/../../lib/bootstrap.php'; // Bootstrap application

// Guard: Check if Erik Baker is logged in
if (!isset($_SESSION['erik_smr_logged_in']) || $_SESSION['erik_smr_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

$currentPage = 'erik-smr-portal'; // For sidebar highlighting

// Include the admin header and footer, but first, ensure they don't break our context.
// These are simplified for this dedicated portal.
// In a full integration, Erik's portal might use its own distinct header/footer or
// adapt the main admin ones with specific permissions.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erik Baker SMR Portal</title>
    <link href="/frontend/public/build/tailwind.css" rel="stylesheet">
    <!-- If using admin-specific CSS, include it here -->
</head>
<body class="bg-gray-100 dark:bg-gray-900">
    <div class="flex h-screen">
        <?php // Assuming a simplified sidebar for Erik's portal, or none at all for now ?>
        <?php // This is where a dedicated sidebar or a link back to main admin would go ?>
        <aside class="w-64 bg-white dark:bg-gray-800 shadow">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">SMR Portal</h3>
            </div>
            <nav class="mt-5">
                <a href="index.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-200 dark:hover:bg-gray-700 dark:text-gray-200">Dashboard</a>
                <a href="upload.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-200 dark:hover:bg-gray-700 dark:text-gray-200">Upload SMR Data</a>
                <a href="review.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-200 dark:hover:bg-gray-700 dark:text-gray-200">Review & Validate</a>
                <a href="logout.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-200 dark:hover:bg-gray-700 dark:text-gray-200 text-red-500">Logout</a>
            </nav>
        </aside>

        <main class="flex-1 p-10 overflow-auto">
            <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100 mb-6">Welcome, Erik Baker!</h1>
            <p class="text-gray-700 dark:text-gray-300 mb-8">
                This is your dedicated portal for managing Secondary Market Radio (SMR) data.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100 mb-3">SMR Uploads</h2>
                    <p class="text-gray-600 dark:text-gray-400">Manage and upload new SMR data files.</p>
                    <a href="upload.php" class="mt-4 inline-block px-4 py-2 bg-brand text-white rounded hover:bg-brand-dark">Go to Upload</a>
                </div>

                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100 mb-3">Data Review & Validation</h2>
                    <p class="text-gray-600 dark:text-gray-400">Review, validate, and reconcile SMR data for integrity.</p>
                    <a href="review.php" class="mt-4 inline-block px-4 py-2 bg-brand text-white rounded hover:bg-brand-dark">Review Data</a>
                </div>

                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100 mb-3">Historical Archive</h2>
                    <p class="text-gray-600 dark:text-gray-400">Access the 2022-2026 SMR Historical Archive.</p>
                    <a href="archive.php" class="mt-4 inline-block px-4 py-2 bg-brand text-white rounded hover:bg-brand-dark">View Archive</a>
                </div>
            </div>
            
            <div class="mt-10 bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100 mb-3">Recent Activity</h2>
                <ul class="text-gray-600 dark:text-gray-400 list-disc list-inside">
                    <li>Placeholder: Last SMR file uploaded: `Weekly_Report_2026-01-15.csv` on Jan 16, 2026</li>
                    <li>Placeholder: 3 files pending review</li>
                    <li>Placeholder: Bounty triggered for Artist X on Jan 14, 2026</li>
                </ul>
            </div>
        </main>
    </div>
</body>
</html>
