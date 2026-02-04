<?php
// admin/digital-signatures/index.php
require_once __DIR__ . '/../_guard.php'; // Admin authentication guard
require_once __DIR__ . '/../../lib/bootstrap.php'; // Bootstrap application

$currentPage = 'digital-signatures'; // For sidebar highlighting

// Handle form submission for mock signature request
$message = '';
$messageType = ''; // 'success' or 'error'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipientEmail = $_POST['recipient_email'] ?? '';
    $documentName = $_POST['document_name'] ?? '';

    if (filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) && !empty($documentName)) {
        // In a real implementation, this would call a Digital Signature Service API
        // For now, simulate success
        $message = "Mock signature request sent successfully for '{$documentName}' to {$recipientEmail}.";
        $messageType = 'success';
        // Log this action for review
        error_log("Digital Signature: Mock request sent - Doc: '{$documentName}', Recipient: '{$recipientEmail}'");
    } else {
        $message = 'Invalid recipient email or document name. Please check your inputs.';
        $messageType = 'error';
    }
}

// Include the admin header
require_once __DIR__ . '/../_header.php';
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 dark:bg-gray-800">
    <div class="container mx-auto px-6 py-8">
        <h3 class="text-gray-700 text-3xl font-medium dark:text-gray-200">Digital Signature Management</h3>

        <div class="mt-4">
            <p class="text-gray-600 dark:text-gray-400">
                Initiate and manage digital signature requests for platform contracts.
                <a href="documents.php" class="text-brand hover:underline">Manage contract documents</a>.
            </p>
        </div>

        <div class="mt-8 bg-white dark:bg-gray-900 rounded-lg shadow p-6">
            <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Send New Signature Request</h4>
            
            <?php if ($message): ?>
                <div class="mb-4 p-4 rounded-lg 
                    <?php echo $messageType === 'success' ? 'bg-green-100 dark:bg-green-800 text-green-700 dark:text-green-100' : 'bg-red-100 dark:bg-red-800 text-red-700 dark:text-red-100'; ?>" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form action="index.php" method="POST">
                <div class="mb-4">
                    <label for="recipient_email" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">
                        Recipient Email:
                    </label>
                    <input type="email" id="recipient_email" name="recipient_email" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-200 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="signer@example.com" required>
                </div>
                <div class="mb-4">
                    <label for="document_name" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">
                        Document Name (e.g., Artist Agreement):
                    </label>
                    <input type="text" id="document_name" name="document_name" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-200 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="Artist Onboarding Contract" required>
                </div>
                <button type="submit" class="px-4 py-2 bg-brand text-white rounded hover:bg-brand-dark focus:outline-none focus:bg-brand-dark">
                    Send Signature Request
                </button>
            </form>
        </div>

        <div class="mt-8">
            <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Signature Request Status</h4>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Pending Requests -->
                <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6">
                    <h5 class="text-md font-semibold text-gray-800 dark:text-gray-100 mb-3 border-b pb-2">Pending (Action Required)</h5>
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        <li class="py-3">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Artist Agreement - Jane Doe</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Recipient: jane.doe@example.com | Created: 2026-01-18</p>
                        </li>
                    </ul>
                </div>

                <!-- Awaiting Signature -->
                <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6">
                    <h5 class="text-md font-semibold text-gray-800 dark:text-gray-100 mb-3 border-b pb-2">Awaiting Signature</h5>
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        <li class="py-3">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Venue Contract - The Garage</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Recipient: venue@example.com | Sent: 2026-01-15</p>
                        </li>
                        <li class="py-3">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Music License - Artist B</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Recipient: artistb@example.com | Sent: 2026-01-10</p>
                        </li>
                    </ul>
                </div>

                <!-- Completed Requests -->
                <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6">
                    <h5 class="text-md font-semibold text-gray-800 dark:text-gray-100 mb-3 border-b pb-2">Completed</h5>
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        <li class="py-3">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Partnership Agreement - MusicCorp</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Recipient: legal@musiccorp.com | Signed: 2026-01-05</p>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../_footer.php'; ?>