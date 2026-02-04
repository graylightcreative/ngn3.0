<?php
/**
 * API Health & Configuration Dashboard
 *
 * Critical for money flow operations:
 * - Stripe live/sandbox key management
 * - Webhook secret configuration
 * - Real-time health check status
 * - Test webhook signatures
 *
 * BLOCKS: Royalty transactions, payments, payouts
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/_guard.php';
$root = dirname(__DIR__, 2);

use NGN\Lib\Env;
use NGN\Lib\Config;

if (!class_exists('NGN\\Lib\\Env')) { exit('Bootstrap failed'); }
Env::load($root);
$cfg = new Config();

@header('Cache-Control: no-store, no-cache, must-revalidate');
@header('Pragma: no-cache');
@header('Expires: 0');

include __DIR__.'/_mint_token.php';

$pageTitle = 'API Health & Configuration';
$currentPage = 'api-health';
$env = $cfg->env() ?? 'development';
include __DIR__.'/_header.php';
include __DIR__.'/_sidebar.php';
include __DIR__.'/_topbar.php';
?>

<main class="flex-1 overflow-y-auto">
  <section class="max-w-7xl mx-auto px-4 py-6 space-y-6">

    <!-- Page Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">API Health & Configuration</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Monitor API connections and manage credentials for live/sandbox environments</p>
      </div>
      <div class="flex gap-2">
        <button id="runHealthChecks" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium">
          <span class="inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            Run Health Checks
          </span>
        </button>
      </div>
    </div>

    <!-- Environment Indicator -->
    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
      <div class="flex items-center gap-3">
        <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        <div>
          <div class="font-semibold text-yellow-900 dark:text-yellow-100">Current Environment: <span id="currentEnv" class="uppercase"><?php echo htmlspecialchars($env); ?></span></div>
          <div class="text-sm text-yellow-700 dark:text-yellow-300">Ensure you're using the correct Stripe keys (live vs sandbox) for this environment</div>
        </div>
      </div>
    </div>

    <!-- Stripe Configuration (Priority #1) -->
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div class="bg-gradient-to-r from-purple-600 to-blue-600 px-6 py-4">
        <div class="flex items-center gap-3">
          <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M13.976 9.15c-2.172-.806-3.356-1.426-3.356-2.409 0-.831.683-1.305 1.901-1.305 2.227 0 4.515.858 6.09 1.631l.89-5.494C18.252.975 15.697 0 12.165 0 9.667 0 7.589.654 6.104 1.872 4.56 3.147 3.757 4.992 3.757 7.218c0 4.039 2.467 5.76 6.476 7.219 2.585.92 3.445 1.574 3.445 2.583 0 .98-.84 1.545-2.354 1.545-1.875 0-4.965-.921-6.99-2.109l-.9 5.555C5.175 22.99 8.385 24 11.714 24c2.641 0 4.843-.624 6.328-1.813 1.664-1.305 2.525-3.236 2.525-5.732 0-4.128-2.524-5.851-6.591-7.305z"/></svg>
          <div>
            <h2 class="text-xl font-bold text-white">Stripe Configuration</h2>
            <p class="text-purple-100 text-sm">Payments, Payouts, Subscriptions - CRITICAL FOR MONEY FLOW</p>
          </div>
        </div>
      </div>

      <div class="p-6 space-y-6">
        <!-- Sandbox/Test Environment -->
        <div id="stripeSandbox" class="space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-2">
              <div class="w-3 h-3 rounded-full bg-yellow-500" id="stripeSandboxStatus"></div>
              <h3 class="font-semibold text-gray-900 dark:text-white">Sandbox / Test Mode</h3>
              <span class="text-xs bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300 px-2 py-1 rounded">SAFE FOR TESTING</span>
            </div>
            <label class="flex items-center gap-2 cursor-pointer">
              <span class="text-sm text-gray-600 dark:text-gray-400">Active</span>
              <input type="checkbox" id="stripeSandboxActive" class="sr-only peer">
              <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
            </label>
          </div>

          <div class="grid grid-cols-1 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Publishable Key</label>
              <input type="text" id="stripeSandboxPublishable" placeholder="pk_test_..." class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Public key used in frontend (safe to expose)</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Secret Key (API Key)</label>
              <div class="flex gap-2">
                <input type="password" id="stripeSandboxSecret" placeholder="sk_test_..." class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <button onclick="togglePasswordVisibility('stripeSandboxSecret')" class="px-4 py-2 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-500 transition-colors text-sm">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                </button>
              </div>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Secret key for server-side API calls (NEVER expose publicly)</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Webhook Signing Secret</label>
              <div class="flex gap-2">
                <input type="password" id="stripeSandboxWebhook" placeholder="whsec_..." class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <button onclick="togglePasswordVisibility('stripeSandboxWebhook')" class="px-4 py-2 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-500 transition-colors text-sm">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                </button>
              </div>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Used to verify webhook signatures (prevents tampering)</p>
            </div>
          </div>

          <div class="flex gap-2">
            <button onclick="testStripeConnection('sandbox')" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors text-sm font-medium">
              Test Connection
            </button>
            <button onclick="testWebhook('sandbox')" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors text-sm font-medium">
              Test Webhook
            </button>
          </div>
        </div>

        <div class="border-t border-gray-200 dark:border-gray-700 pt-6"></div>

        <!-- Live/Production Environment -->
        <div id="stripeLive" class="space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-2">
              <div class="w-3 h-3 rounded-full bg-gray-400" id="stripeLiveStatus"></div>
              <h3 class="font-semibold text-gray-900 dark:text-white">Live / Production Mode</h3>
              <span class="text-xs bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300 px-2 py-1 rounded">⚠️ REAL MONEY</span>
            </div>
            <label class="flex items-center gap-2 cursor-pointer">
              <span class="text-sm text-gray-600 dark:text-gray-400">Active</span>
              <input type="checkbox" id="stripeLiveActive" class="sr-only peer">
              <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 dark:peer-focus:ring-red-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-red-600"></div>
            </label>
          </div>

          <div class="bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-4">
            <div class="flex gap-3">
              <svg class="w-5 h-5 text-red-600 dark:text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
              <div class="text-sm text-red-800 dark:text-red-200">
                <p class="font-semibold">Production Mode Warning</p>
                <p class="mt-1">These keys process real payments and payouts. Thoroughly test in sandbox before activating live mode.</p>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Publishable Key</label>
              <input type="text" id="stripeLivePublishable" placeholder="pk_live_..." class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Secret Key (API Key)</label>
              <div class="flex gap-2">
                <input type="password" id="stripeLiveSecret" placeholder="sk_live_..." class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
                <button onclick="togglePasswordVisibility('stripeLiveSecret')" class="px-4 py-2 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-500 transition-colors text-sm">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                </button>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Webhook Signing Secret</label>
              <div class="flex gap-2">
                <input type="password" id="stripeLiveWebhook" placeholder="whsec_..." class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
                <button onclick="togglePasswordVisibility('stripeLiveWebhook')" class="px-4 py-2 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-500 transition-colors text-sm">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                </button>
              </div>
            </div>
          </div>

          <div class="flex gap-2">
            <button onclick="testStripeConnection('live')" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors text-sm font-medium">
              Test Connection
            </button>
            <button onclick="testWebhook('live')" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors text-sm font-medium">
              Test Webhook
            </button>
          </div>
        </div>

        <!-- Save Button -->
        <div class="flex justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
          <button id="saveStripeConfig" class="px-6 py-2 bg-gradient-to-r from-purple-600 to-blue-600 text-white rounded-lg hover:from-purple-700 hover:to-blue-700 transition-colors font-medium">
            Save Stripe Configuration
          </button>
        </div>
      </div>
    </div>

    <!-- API Health Status (Other Services) -->
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">API Health Status</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Monitor connectivity to external services</p>
      </div>

      <div class="p-6">
        <div id="healthStatusGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <!-- Will be populated by JavaScript -->
        </div>
      </div>
    </div>

    <!-- Health Check History -->
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Health Checks</h2>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-gray-900/50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Service</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Environment</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Response Time</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Checked At</th>
            </tr>
          </thead>
          <tbody id="healthHistoryTable" class="divide-y divide-gray-200 dark:divide-gray-700">
            <!-- Will be populated by JavaScript -->
          </tbody>
        </table>
      </div>
    </div>

  </section>
</main>

<?php include __DIR__.'/_token_store.php'; ?>
<script>
// Helper functions
function togglePasswordVisibility(id) {
  const input = document.getElementById(id);
  input.type = input.type === 'password' ? 'text' : 'password';
}

function getAuthHeaders() {
  const token = localStorage.getItem('ngn_admin_token') || localStorage.getItem('admin_token') || '';
  return token ? { 'Authorization': `Bearer ${token}` } : {};
}

function showMessage(message, type = 'success') {
  // TODO: Implement toast notification
  console.log(`[${type}] ${message}`);
  alert(message);
}

// Load Stripe configuration on page load
async function loadStripeConfig(environment) {
  try {
    const response = await fetch(`/api/v1/admin/api-health/stripe/config?environment=${environment}`, {
      headers: getAuthHeaders()
    });
    const data = await response.json();

    if (data.success && data.data) {
      const config = data.data;
      const prefix = environment === 'sandbox' ? 'stripeSandbox' : 'stripeLive';

      // Set masked values
      document.getElementById(`${prefix}Publishable`).placeholder = config.publishable_key_masked || 'pk_' + environment.substring(0,4) + '_...';
      document.getElementById(`${prefix}Secret`).placeholder = config.secret_key_masked || 'sk_' + environment.substring(0,4) + '_...';
      document.getElementById(`${prefix}Webhook`).placeholder = config.webhook_secret_masked || 'whsec_...';

      // Set active toggle
      document.getElementById(`${prefix}Active`).checked = config.is_active;

      // Update status indicator
      const statusEl = document.getElementById(`${prefix}Status`);
      if (config.is_active) {
        statusEl.className = 'w-3 h-3 rounded-full bg-green-500';
      } else {
        statusEl.className = 'w-3 h-3 rounded-full bg-gray-400';
      }
    }
  } catch (error) {
    console.error(`Error loading ${environment} config:`, error);
  }
}

// Save Stripe configuration
async function saveStripeConfig() {
  const sandbox = {
    environment: 'sandbox',
    publishable_key: document.getElementById('stripeSandboxPublishable').value,
    secret_key: document.getElementById('stripeSandboxSecret').value,
    webhook_secret: document.getElementById('stripeSandboxWebhook').value,
    is_active: document.getElementById('stripeSandboxActive').checked
  };

  const live = {
    environment: 'live',
    publishable_key: document.getElementById('stripeLivePublishable').value,
    secret_key: document.getElementById('stripeLiveSecret').value,
    webhook_secret: document.getElementById('stripeLiveWebhook').value,
    is_active: document.getElementById('stripeLiveActive').checked
  };

  try {
    // Save sandbox
    const sandboxResponse = await fetch('/api/v1/admin/api-health/stripe/config', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...getAuthHeaders()
      },
      body: JSON.stringify(sandbox)
    });

    if (!sandboxResponse.ok) {
      const error = await sandboxResponse.json();
      throw new Error(error.error || 'Failed to save sandbox config');
    }

    // Save live
    const liveResponse = await fetch('/api/v1/admin/api-health/stripe/config', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...getAuthHeaders()
      },
      body: JSON.stringify(live)
    });

    if (!liveResponse.ok) {
      const error = await liveResponse.json();
      throw new Error(error.error || 'Failed to save live config');
    }

    showMessage('Stripe configuration saved successfully!', 'success');

    // Reload configs to show masked values
    await loadStripeConfig('sandbox');
    await loadStripeConfig('live');

    // Clear input fields
    document.getElementById('stripeSandboxPublishable').value = '';
    document.getElementById('stripeSandboxSecret').value = '';
    document.getElementById('stripeSandboxWebhook').value = '';
    document.getElementById('stripeLivePublishable').value = '';
    document.getElementById('stripeLiveSecret').value = '';
    document.getElementById('stripeLiveWebhook').value = '';

  } catch (error) {
    showMessage('Error: ' + error.message, 'error');
  }
}

// Test Stripe connection
async function testStripeConnection(environment) {
  const prefix = environment === 'sandbox' ? 'stripeSandbox' : 'stripeLive';
  const statusEl = document.getElementById(`${prefix}Status`);

  // Show loading
  statusEl.className = 'w-3 h-3 rounded-full bg-yellow-500 animate-pulse';

  try {
    const response = await fetch('/api/v1/admin/api-health/stripe/test', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...getAuthHeaders()
      },
      body: JSON.stringify({ environment })
    });

    const data = await response.json();

    if (data.success) {
      statusEl.className = 'w-3 h-3 rounded-full bg-green-500';
      showMessage(`✓ ${environment} connection successful! Response time: ${data.data.response_time_ms}ms`, 'success');
    } else {
      statusEl.className = 'w-3 h-3 rounded-full bg-red-500';
      showMessage(`✗ ${environment} connection failed: ${data.data.error}`, 'error');
    }
  } catch (error) {
    statusEl.className = 'w-3 h-3 rounded-full bg-red-500';
    showMessage('Error testing connection: ' + error.message, 'error');
  }
}

// Test webhook signature
async function testWebhook(environment) {
  showMessage('Webhook testing coming soon - requires actual webhook event from Stripe', 'info');
  // TODO: Implement webhook test with sample event
}

// Run all health checks
async function runAllHealthChecks() {
  const button = document.getElementById('runHealthChecks');
  button.disabled = true;
  button.innerHTML = '<span class="inline-flex items-center gap-2"><svg class="animate-spin w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Running...</span>';

  try {
    const response = await fetch('/api/v1/admin/api-health/run-all', {
      method: 'POST',
      headers: getAuthHeaders()
    });

    const data = await response.json();

    if (data.success) {
      showMessage(`Health checks complete! ${data.data.length} service(s) tested`, 'success');
      // Reload configs to show updated status
      await loadStripeConfig('sandbox');
      await loadStripeConfig('live');
    }
  } catch (error) {
    showMessage('Error running health checks: ' + error.message, 'error');
  } finally {
    button.disabled = false;
    button.innerHTML = '<span class="inline-flex items-center gap-2"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>Run Health Checks</span>';
  }
}

// Attach event listeners
document.getElementById('saveStripeConfig').addEventListener('click', saveStripeConfig);
document.getElementById('runHealthChecks').addEventListener('click', runAllHealthChecks);

// Load configurations on page load
window.addEventListener('DOMContentLoaded', () => {
  loadStripeConfig('sandbox');
  loadStripeConfig('live');
});

console.log('API Health Dashboard loaded and ready!');
</script>

<?php include __DIR__.'/_footer.php'; ?>
