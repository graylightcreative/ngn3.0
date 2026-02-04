<?php
// admin/erik-smr/upload.php
session_start();
require_once __DIR__ . '/../../lib/bootstrap.php';
if (!isset($_SESSION['erik_smr_logged_in']) || $_SESSION['erik_smr_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}
$currentPage = 'erik-smr-portal-upload'; // For sidebar highlighting or internal nav
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload SMR Data - Erik Baker SMR Portal</title>
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
                <a href="upload.php" class="block py-2.5 px-4 rounded transition duration-200 bg-gray-100 dark:bg-gray-700 dark:text-gray-200">Upload SMR Data</a>
                <a href="review.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-200 dark:hover:bg-gray-700 dark:text-gray-200">Review & Validate</a>
                <a href="logout.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-200 dark:hover:bg-gray-700 dark:text-gray-200 text-red-500">Logout</a>
            </nav>
        </aside>
        <main class="flex-1 p-10 overflow-auto">
            <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100 mb-6">Upload SMR Data (Erik)</h1>
            <p class="text-gray-700 dark:text-gray-300 mb-8">
                Upload new SMR (Secondary Market Radio) data files for processing and validation.
            </p>

            <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6">
                <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Upload SMR File</h4>
                <form action="upload.php" method="POST" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label for="smrFile" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">
                            Choose CSV or Excel File:
                        </label>
                        <input type="file" name="smrFile" id="smrFile" accept=".csv, .xls, .xlsx" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-200 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    <button type="submit" class="px-4 py-2 bg-brand text-white rounded hover:bg-brand-dark focus:outline-none focus:bg-brand-dark">
                        Upload and Process
                    </button>
                </form>

                <?php
                // PHP logic for file upload and display will go here
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['smrFile'])) {
                    $uploadDir = __DIR__ . '/../../storage/uploads/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $fileName = basename($_FILES['smrFile']['name']);
                    $filePath = $uploadDir . $fileName;
                    $fileType = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

                    if (move_uploaded_file($_FILES['smrFile']['tmp_name'], $filePath)) {
                        echo '<div class="mt-6 p-4 bg-green-100 dark:bg-green-800 text-green-700 dark:text-green-100 rounded-lg">File uploaded successfully: ' . htmlspecialchars($fileName) . '</div>';

                        // Basic CSV/Excel parsing (skeleton only)
                        if ($fileType === 'csv') {
                            $handle = fopen($filePath, "r");
                            if ($handle) {
                                echo '<div class="mt-6"><h5 class="text-md font-semibold text-gray-800 dark:text-gray-100 mb-2">First 5 Rows:</h5>';
                                echo '<div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">';
                                $rowCount = 0;
                                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE && $rowCount < 5) {
                                    if ($rowCount === 0) { // Header row
                                        echo '<thead><tr>';
                                        foreach ($data as $header) {
                                            echo '<th class="px-6 py-3 bg-gray-50 dark:bg-gray-700 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">' . htmlspecialchars($header) . '</th>';
                                        }
                                        echo '</tr></thead><tbody>';
                                    } else {
                                        echo '<tr>';
                                        foreach ($data as $cell) {
                                            echo '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">' . htmlspecialchars($cell) . '</td>';
                                        }
                                        echo '</tr>';
                                    }
                                    $rowCount++;
                                }
                                echo '</tbody></table></div></div>';
                                fclose($handle);
                            } else {
                                echo '<div class="mt-6 p-4 bg-red-100 dark:bg-red-800 text-red-700 dark:text-red-100 rounded-lg">Error reading CSV file.</div>';
                            }
                        } elseif (in_array($fileType, ['xls', 'xlsx'])) {
                            echo '<div class="mt-6 p-4 bg-yellow-100 dark:bg-yellow-800 text-yellow-700 dark:text-yellow-100 rounded-lg">Excel file parsing is not yet fully implemented, displaying basic message.</div>';
                            // Further implementation would involve a library like PhpSpreadsheet
                        } else {
                            echo '<div class="mt-6 p-4 bg-red-100 dark:bg-red-800 text-red-700 dark:text-red-100 rounded-lg">Unsupported file type. Please upload a CSV or Excel file.</div>';
                        }

                        // --- Placeholder for saving to staging table ---
                        echo '<div class="mt-6 p-4 bg-blue-100 dark:bg-blue-800 text-blue-700 dark:text-blue-100 rounded-lg">Data would be saved to a staging table here, pending Erik\'s validation.</div>';

                    } else {
                        echo '<div class="mt-6 p-4 bg-red-100 dark:bg-red-800 text-red-700 dark:text-red-100 rounded-lg">Error uploading file.</div>';
                    }
                }
                ?>
            </div>
        </main>
    </div>
</body>
</html>
