<?php
/**
 * API Endpoint Tester
 *
 * Interactive endpoint testing interface (similar to Postman)
 * Allows admins to manually test any API endpoint with custom headers, body, etc.
 */

require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../../lib/bootstrap.php';

$currentPage = 'api-tester';
$result = null;
$error = null;

// Handle test request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_endpoint') {
    $method = strtoupper(trim($_POST['method'] ?? 'GET'));
    $url = trim($_POST['url'] ?? '');
    $headers_raw = trim($_POST['headers'] ?? '');
    $body = trim($_POST['body'] ?? '');

    // Validate
    if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'])) {
        $error = 'Invalid HTTP method';
    } elseif (empty($url)) {
        $error = 'URL is required';
    } else {
        // Parse URL - allow relative paths or full URLs
        if (strpos($url, 'http') !== 0) {
            $url = $_ENV['APP_URL'] . $url;
        }

        // Parse headers
        $headers = [];
        if (!empty($headers_raw)) {
            $lines = explode("\n", $headers_raw);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                if (strpos($line, ':') === false) continue;
                [$key, $val] = explode(':', $line, 2);
                $headers[trim($key)] = trim($val);
            }
        }

        // Add default headers
        $headers['User-Agent'] = 'NGN-Admin-Tester/1.0';
        $headers['Accept'] = 'application/json';

        // Make request
        $startTime = microtime(true);
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local testing

            // Set headers
            $headerLines = [];
            foreach ($headers as $key => $val) {
                $headerLines[] = "$key: $val";
            }
            if (!empty($headerLines)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);
            }

            // Set body if needed
            if (!empty($body) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }

            $response_body = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $response_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $error_msg = curl_error($ch);
            curl_close($ch);

            $duration = (microtime(true) - $startTime) * 1000;

            if ($error_msg) {
                $error = 'cURL Error: ' . $error_msg;
            } else {
                $result = [
                    'method' => $method,
                    'url' => $url,
                    'http_code' => $http_code,
                    'response_type' => $response_type,
                    'duration_ms' => round($duration, 2),
                    'response_body' => $response_body,
                    'response_preview' => substr($response_body, 0, 500)
                ];
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/_header.php';
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 dark:bg-gray-800">
    <div class="container mx-auto px-6 py-8">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-gray-700 text-3xl font-medium dark:text-gray-200">API Endpoint Tester</h3>
            <p class="text-gray-600 dark:text-gray-400 text-sm">Manual endpoint testing interface</p>
        </div>

        <div class="grid grid-cols-3 gap-6">
            <!-- Test Form -->
            <div class="col-span-2">
                <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6">
                    <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Test Request</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="test_endpoint">

                        <!-- Method + URL -->
                        <div class="flex gap-3 mb-4">
                            <select name="method" class="w-24 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded dark:bg-gray-700 dark:text-gray-200 font-mono">
                                <option value="GET">GET</option>
                                <option value="POST">POST</option>
                                <option value="PUT">PUT</option>
                                <option value="DELETE">DELETE</option>
                                <option value="PATCH">PATCH</option>
                                <option value="HEAD">HEAD</option>
                                <option value="OPTIONS">OPTIONS</option>
                            </select>
                            <input type="text" name="url" class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded dark:bg-gray-700 dark:text-gray-200"
                                   placeholder="/api/v1/artists or https://example.com/api/..."
                                   value="<?= isset($_POST['url']) ? htmlspecialchars($_POST['url']) : '' ?>">
                        </div>

                        <!-- Headers -->
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-800 dark:text-gray-100 mb-2">Headers (Optional)</label>
                            <textarea name="headers" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded dark:bg-gray-700 dark:text-gray-200 font-mono text-xs" rows="4" placeholder="Authorization: Bearer token&#10;X-Custom-Header: value"><?= isset($_POST['headers']) ? htmlspecialchars($_POST['headers']) : '' ?></textarea>
                        </div>

                        <!-- Body -->
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-800 dark:text-gray-100 mb-2">Body (Optional)</label>
                            <textarea name="body" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded dark:bg-gray-700 dark:text-gray-200 font-mono text-xs" rows="6" placeholder='{"key":"value"}'><?= isset($_POST['body']) ? htmlspecialchars($_POST['body']) : '' ?></textarea>
                        </div>

                        <!-- Submit -->
                        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                            Send Request
                        </button>
                    </form>
                </div>

                <!-- Response -->
                <?php if ($error): ?>
                <div class="mt-6 bg-red-50 dark:bg-red-900 border-l-4 border-red-500 p-4 rounded">
                    <p class="text-red-700 dark:text-red-200 font-semibold">Error</p>
                    <p class="text-red-600 dark:text-red-300 text-sm mt-1"><?= htmlspecialchars($error) ?></p>
                </div>
                <?php elseif ($result): ?>
                <div class="mt-6 bg-white dark:bg-gray-900 rounded-lg shadow p-6">
                    <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Response</h4>

                    <!-- Response Info -->
                    <div class="grid grid-cols-4 gap-4 mb-4">
                        <div>
                            <p class="text-xs text-gray-500 uppercase">Status</p>
                            <p class="text-2xl font-bold <?= $result['http_code'] >= 200 && $result['http_code'] < 300 ? 'text-green-600' : 'text-red-600' ?>">
                                <?= $result['http_code'] ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase">Time</p>
                            <p class="text-2xl font-bold text-gray-800 dark:text-gray-100"><?= $result['duration_ms'] ?>ms</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase">Content-Type</p>
                            <p class="text-sm text-gray-800 dark:text-gray-100 break-all"><?= htmlspecialchars($result['response_type'] ?? 'unknown') ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase">Method</p>
                            <p class="text-sm font-mono text-gray-800 dark:text-gray-100"><?= htmlspecialchars($result['method']) ?></p>
                        </div>
                    </div>

                    <!-- Response Body -->
                    <div>
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-2">Response Body</p>
                        <div class="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded p-4 max-h-96 overflow-auto">
                            <pre class="text-xs text-gray-800 dark:text-gray-200 whitespace-pre-wrap break-words font-mono"><?php
                                // Try to pretty-print JSON
                                if (strpos($result['response_type'] ?? '', 'json') !== false || json_decode($result['response_body'])) {
                                    echo htmlspecialchars(json_encode(json_decode($result['response_body']), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                                } else {
                                    echo htmlspecialchars($result['response_body']);
                                }
                            ?></pre>
                        </div>
                    </div>

                    <!-- Save Test -->
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button type="button" class="px-4 py-2 bg-green-600 text-white text-sm rounded hover:bg-green-700"
                                onclick="saveTest('<?= htmlspecialchars($result['method']) ?>', '<?= htmlspecialchars($result['url']) ?>')">
                            Save as Health Check
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Quick Links / Presets -->
            <div>
                <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6 mb-6">
                    <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Quick Tests</h4>
                    <div class="space-y-2">
                        <button class="w-full text-left px-3 py-2 rounded hover:bg-gray-100 dark:hover:bg-gray-800 text-sm text-gray-700 dark:text-gray-300"
                                onclick="fillForm('GET', '/api/v1/health')">
                            ✓ API Health
                        </button>
                        <button class="w-full text-left px-3 py-2 rounded hover:bg-gray-100 dark:hover:bg-gray-800 text-sm text-gray-700 dark:text-gray-300"
                                onclick="fillForm('GET', '/api/v1/artists?limit=10')">
                            ✓ List Artists
                        </button>
                        <button class="w-full text-left px-3 py-2 rounded hover:bg-gray-100 dark:hover:bg-gray-800 text-sm text-gray-700 dark:text-gray-300"
                                onclick="fillForm('GET', '/api/v1/stations?limit=10')">
                            ✓ List Stations
                        </button>
                        <button class="w-full text-left px-3 py-2 rounded hover:bg-gray-100 dark:hover:bg-gray-800 text-sm text-gray-700 dark:text-gray-300"
                                onclick="fillForm('GET', '/admin/System/database_health.php')">
                            ✓ Database Health
                        </button>
                        <button class="w-full text-left px-3 py-2 rounded hover:bg-gray-100 dark:hover:bg-gray-800 text-sm text-gray-700 dark:text-gray-300"
                                onclick="fillForm('GET', '/admin/System/cache_health.php')">
                            ✓ Cache Health
                        </button>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6 mb-6">
                    <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Third-Party</h4>
                    <div class="space-y-2">
                        <button class="w-full text-left px-3 py-2 rounded hover:bg-gray-100 dark:hover:bg-gray-800 text-sm text-gray-700 dark:text-gray-300"
                                onclick="fillForm('GET', '/admin/testing/lib/stripe_test.php')">
                            💳 Stripe Test
                        </button>
                        <button class="w-full text-left px-3 py-2 rounded hover:bg-gray-100 dark:hover:bg-gray-800 text-sm text-gray-700 dark:text-gray-300"
                                onclick="fillForm('GET', '/admin/testing/lib/facebook_test.php')">
                            f Facebook Test
                        </button>
                        <button class="w-full text-left px-3 py-2 rounded hover:bg-gray-100 dark:hover:bg-gray-800 text-sm text-gray-700 dark:text-gray-300"
                                onclick="fillForm('GET', '/admin/testing/lib/mailchimp_test.php')">
                            ✉ Mailchimp Test
                        </button>
                    </div>
                </div>

                <div class="bg-blue-50 dark:bg-blue-900 border-l-4 border-blue-500 p-4 rounded">
                    <p class="text-blue-900 dark:text-blue-100 text-sm font-semibold mb-2">Tips</p>
                    <ul class="text-blue-800 dark:text-blue-200 text-xs space-y-1">
                        <li>• Relative paths use <?= htmlspecialchars($_ENV['APP_URL'] ?? 'APP_URL') ?></li>
                        <li>• Headers: one per line (Key: Value)</li>
                        <li>• POST/PUT bodies accept JSON or form data</li>
                        <li>• Responses are limited to 500 chars preview</li>
                        <li>• Click "Save" to add to health checks</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
function fillForm(method, url) {
    const form = document.querySelector('form');
    form.elements['method'].value = method;
    form.elements['url'].value = url;
    form.elements['headers'].value = '';
    form.elements['body'].value = '';
}

function saveTest(method, url) {
    // TODO: Save to health_check_scenarios
    alert('Save test:\n' + method + ' ' + url);
}
</script>

<?php require_once __DIR__ . '/_footer.php'; ?>
