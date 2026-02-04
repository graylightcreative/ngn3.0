<?php
// admin/testing/index.php
require_once __DIR__ . '/../_guard.php'; // Admin authentication guard
require_once __DIR__ . '/../../lib/bootstrap.php'; // Bootstrap application

$currentPage = 'testing'; // For sidebar highlighting

// Include the admin header
require_once __DIR__ . '/../_header.php';
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 dark:bg-gray-800">
    <div class="container mx-auto px-6 py-8">
        <h3 class="text-gray-700 text-3xl font-medium dark:text-gray-200">System Testing</h3>

        <div class="mt-4">
            <p class="text-gray-600 dark:text-gray-400">
                This section allows administrators to perform connectivity tests with integrated third-party services.
            </p>
        </div>

        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <!-- Stripe Test Card -->
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6">
                <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Stripe Integration Test</h4>
                <p class="text-gray-600 dark:text-gray-400 mt-2 text-sm">
                    Verify connectivity and basic API operations with Stripe.
                </p>
                <button id="runStripeTest" class="mt-4 px-4 py-2 bg-brand text-white rounded hover:bg-brand-dark focus:outline-none focus:bg-brand-dark text-sm">
                    Run Test
                </button>
                <div id="stripeTestResult" class="mt-3 text-sm font-medium"></div>
            </div>

            <!-- Facebook Test Card -->
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6">
                <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Facebook Integration Test</h4>
                <p class="text-gray-600 dark:text-gray-400 mt-2 text-sm">
                    Verify connectivity and basic API operations with Facebook (Meta).
                </p>
                <button id="runFacebookTest" class="mt-4 px-4 py-2 bg-brand text-white rounded hover:bg-brand-dark focus:outline-none focus:bg-brand-dark text-sm">
                    Run Test
                </button>
                <div id="facebookTestResult" class="mt-3 text-sm font-medium"></div>
            </div>

            <!-- Mailchimp Test Card -->
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6">
                <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Mailchimp Integration Test</h4>
                <p class="text-gray-600 dark:text-gray-400 mt-2 text-sm">
                    Verify connectivity and basic API operations with Mailchimp.
                </p>
                <button id="runMailchimpTest" class="mt-4 px-4 py-2 bg-brand text-white rounded hover:bg-brand-dark focus:outline-none focus:bg-brand-dark text-sm">
                    Run Test
                </button>
                <div id="mailchimpTestResult" class="mt-3 text-sm font-medium"></div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../_footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function runTest(testName, resultElementId) {
        const resultElement = document.getElementById(resultElementId);
        resultElement.innerHTML = '<span class="text-blue-500">Running test...</span>';

        fetch(`/admin/testing/lib/${testName}_test.php`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultElement.innerHTML = `<span class="text-green-500">Success: ${data.message}</span>`;
                } else {
                    resultElement.innerHTML = `<span class="text-red-500">Failed: ${data.message}</span>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultElement.innerHTML = `<span class="text-red-500">Error: ${error.message || 'An unexpected error occurred.'}</span>`;
            });
    }

    document.getElementById('runStripeTest')?.addEventListener('click', function() {
        runTest('stripe', 'stripeTestResult');
    });

    document.getElementById('runFacebookTest')?.addEventListener('click', function() {
        runTest('facebook', 'facebookTestResult');
    });

    document.getElementById('runMailchimpTest')?.addEventListener('click', function() {
        runTest('mailchimp', 'mailchimpTestResult');
    });
});
</script>