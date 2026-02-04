<?php
require_once dirname(__DIR__, 2) . '/_guard.php';
$root = dirname(__DIR__, 2);
require_once $root.'/lib/bootstrap.php';

use NGN\Lib\Env;
use NGN\Lib\Config;
use App\Lib\Email\Mailer; // Ensure Mailer is available for potential future use or context
use Monolog\Logger; // For checking Monolog setup
use Monolog\Handler\StreamHandler;

if (!class_exists('NGN\Lib\Env')) { exit('Bootstrap failed'); }
Env::load($root);
$cfg = new Config();
$env = $cfg->appEnv();
$featureAdmin = $cfg->featureAdmin();

@header('Cache-Control: no-store, no-cache, must-revalidate');
@header('Pragma: no-cache');
@header('Expires: 0');

// Auto-mint admin token - if we got past _guard.php, we're authorized
$mintedToken = null;
try {
    
    $sub = !empty($_SESSION['User']['Email']) ? (string)$_SESSION['User']['Email'] : 'admin@session';
    // Assuming $svc is an instance of TokenService or similar, properly initialized in bootstrap
    // $issued = $svc->issueAccessToken(['sub' => $sub, 'role' => 'admin']);
    // $mintedToken = $issued['token'] ?? null;
    
} catch (\Throwable $e) {
    error_log('Token mint failed: ' . $e->getMessage());
}

// Read current .env values
$envPath = $root . '/.env';
$envValues = [];
if (is_file($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (stripos($line, 'export ') === 0) $line = trim(substr($line, 7));
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $k = trim($parts[0]);
            $v = trim($parts[1]);
            // Remove quotes
            if ((substr($v, 0, 1) === '"' && substr($v, -1) === '"') || 
                (substr($v, 0, 1) === "'" && substr($v, -1) === "'")) {
                $v = substr($v, 1, -1);
            }
            $envValues[$k] = $v;
        }
    }
}

// Define settings sections with their fields
$sections = [
    'Application' => [
        'APP_ENV' => ['type' => 'select', 'options' => ['development', 'staging', 'production'], 'label' => 'Environment'],
        'APP_DEBUG' => ['type' => 'bool', 'label' => 'Debug Mode'],
        'APP_VERSION' => ['type' => 'text', 'label' => 'Version'],
    ],
    'CORS' => [
        'CORS_ALLOWED_ORIGINS' => ['type' => 'text', 'label' => 'Allowed Origins', 'hint' => 'Comma-separated, or *'],
        'CORS_ALLOWED_HEADERS' => ['type' => 'text', 'label' => 'Allowed Headers'],
        'CORS_ALLOWED_METHODS' => ['type' => 'text', 'label' => 'Allowed Methods'],
    ],
    'Logging' => [
        'LOG_PATH' => ['type' => 'text', 'label' => 'Log Path'],
        'LOG_LEVEL' => ['type' => 'select', 'options' => ['debug', 'info', 'warning', 'error'], 'label' => 'Log Level'],
    ],
    'Database (Primary)' => [
        'DB_HOST' => ['type' => 'text', 'label' => 'Host'],
        'DB_PORT' => ['type' => 'number', 'label' => 'Port'],
        'DB_NAME' => ['type' => 'text', 'label' => 'Database Name'],
        'DB_USER' => ['type' => 'text', 'label' => 'Username'],
        'DB_PASS' => ['type' => 'password', 'label' => 'Password'],
    ],
    'Database (Read Replica)' => [
        'DB_READ_HOST' => ['type' => 'text', 'label' => 'Host'],
        'DB_READ_PORT' => ['type' => 'number', 'label' => 'Port'],
        'DB_READ_NAME' => ['type' => 'text', 'label' => 'Database Name'],
        'DB_READ_USER' => ['type' => 'text', 'label' => 'Username'],
        'DB_READ_PASS' => ['type' => 'password', 'label' => 'Password'],
    ],
    'JWT Authentication' => [
        'JWT_SECRET' => ['type' => 'password', 'label' => 'Secret Key'],
        'JWT_ISS' => ['type' => 'text', 'label' => 'Issuer'],
        'JWT_AUD' => ['type' => 'text', 'label' => 'Audience'],
        'JWT_TTL_SECONDS' => ['type' => 'number', 'label' => 'Access Token TTL (seconds)'],
        'JWT_REFRESH_TTL_SECONDS' => ['type' => 'number', 'label' => 'Refresh Token TTL (seconds)'],
    ],
    'Feature Flags' => [
        'FEATURE_STATION_PORTAL' => ['type' => 'bool', 'label' => 'Station Portal'],
        'FEATURE_POST_SPINS' => ['type' => 'bool', 'label' => 'Post Spins'],
        'FEATURE_SMR_UPLOADS' => ['type' => 'bool', 'label' => 'SMR Uploads'],
        'FEATURE_SMR_TRAINING' => ['type' => 'bool', 'label' => 'SMR Training'],
        'FEATURE_RANKINGS' => ['type' => 'bool', 'label' => 'Rankings'],
        'FEATURE_RANKINGS_CACHE' => ['type' => 'bool', 'label' => 'Rankings Cache'],
        'FEATURE_RATE_LIMITING' => ['type' => 'bool', 'label' => 'Rate Limiting'],
        'FEATURE_AUTH_DEV_LEDGER' => ['type' => 'bool', 'label' => 'Auth Dev Ledger'],
        'FEATURE_ADMIN' => ['type' => 'bool', 'label' => 'Admin Features'],
        'ADMIN_API_ENABLED' => ['type' => 'bool', 'label' => 'Admin API Enabled'],
        'FEATURE_PUBLIC_VIEW_MODE' => ['type' => 'select', 'options' => ['legacy', 'next'], 'label' => 'Public View Mode'],
        'FEATURE_ROYALTIES' => ['type' => 'bool', 'label' => 'Royalties'],
        'FEATURE_PUBLIC_ROLLOUT' => ['type' => 'bool', 'label' => 'Public Rollout'],
        'ROLLOUT_PERCENTAGE' => ['type' => 'number', 'label' => 'Rollout Percentage', 'min' => 0, 'max' => 100],
	        // Sparks / monetization
	        'SPARKS_MODE' => ['type' => 'select', 'options' => ['development', 'staging', 'production'], 'label' => 'Sparks Mode'],
	        'SPARKS_ENFORCE_CHARGES' => ['type' => 'bool', 'label' => 'Enforce Sparks Charges'],
    ],
    'Maintenance' => [
        'MAINTENANCE_MODE' => ['type' => 'bool', 'label' => 'Maintenance Mode'],
        'MAINTENANCE_MESSAGE' => ['type' => 'text', 'label' => 'Maintenance Message'],
    ],
    'Rate Limiting' => [
        'RATE_LIMIT_PER_MIN' => ['type' => 'number', 'label' => 'Requests Per Minute'],
        'RATE_LIMIT_BURST' => ['type' => 'number', 'label' => 'Burst Limit'],
    ],
    'Uploads' => [
        'UPLOAD_MAX_MB' => ['type' => 'number', 'label' => 'Max Upload Size (MB)'],
        'UPLOAD_ALLOWED_MIME' => ['type' => 'text', 'label' => 'Allowed MIME Types'],
        'UPLOAD_DIR' => ['type' => 'text', 'label' => 'Upload Directory'],
        'UPLOAD_RETENTION_DAYS' => ['type' => 'number', 'label' => 'Retention Days'],
    ],
    'Rankings Cache' => [
        'RANKINGS_CACHE_TTL_SECONDS' => ['type' => 'number', 'label' => 'Cache TTL (seconds)'],
        'RANKINGS_CACHE_DIR' => ['type' => 'text', 'label' => 'Cache Directory'],
    ],
    'API Settings' => [
        'MAX_JSON_BODY_BYTES' => ['type' => 'number', 'label' => 'Max JSON Body Size (bytes)'],
        'PREVIEW_MAX_ROWS' => ['type' => 'number', 'label' => 'Preview Max Rows'],
        'PREVIEW_TIMEOUT_MS' => ['type' => 'number', 'label' => 'Preview Timeout (ms)'],
    ],
    'Stripe' => [
        'STRIPE_SECRET_KEY' => ['type' => 'password', 'label' => 'Secret Key'],
        'STRIPE_PUBLISHABLE_KEY' => ['type' => 'text', 'label' => 'Publishable Key'],
        'STRIPE_WEBHOOK_SECRET' => ['type' => 'password', 'label' => 'Webhook Secret'],
    ],
    'Mailchimp / Mandrill' => [
        'MAILCHIMP_API_KEY' => ['type' => 'password', 'label' => 'API Key'],
        'MAILCHIMP_LIST_ID' => ['type' => 'text', 'label' => 'List/Audience ID'],
        'MAILCHIMP_SERVER_PREFIX' => ['type' => 'text', 'label' => 'Server Prefix', 'hint' => 'e.g., us1, us2'],
        'MAILCHIMP_TEMPLATE_ID' => ['type' => 'text', 'label' => 'Template ID'],
        // Removed MANDRILL_KEY as per instructions
        // 'MANDRILL_KEY' => ['type' => 'password', 'label' => 'Mandrill Key'],
    ],
    'SMTP / Email' => [
        'SMTP_HOST' => ['type' => 'text', 'label' => 'SMTP Host'],
        'SMTP_PORT' => ['type' => 'number', 'label' => 'SMTP Port'],
        'SMTP_USER' => ['type' => 'text', 'label' => 'SMTP Username'],
        'SMTP_PASSWORD' => ['type' => 'password', 'label' => 'SMTP Password'],
    ],
    'Spotify API' => [
        'SPOTIFY_CLIENT_ID' => ['type' => 'text', 'label' => 'Client ID'],
        'SPOTIFY_CLIENT_SECRET' => ['type' => 'password', 'label' => 'Client Secret'],
    ],
    'Meta/Facebook API' => [
        'FACEBOOK_APP_ID' => ['type' => 'text', 'label' => 'App ID'],
        'FACEBOOK_APP_SECRET' => ['type' => 'password', 'label' => 'App Secret'],
        'FACEBOOK_VERIFY_TOKEN' => ['type' => 'text', 'label' => 'Verify Token', 'hint' => 'For webhooks'],
        'FB_REDIRECT_URI' => ['type' => 'text', 'label' => 'Redirect URI', 'hint' => 'e.g., meta/fb-callback'],
    ],
    'Instagram API' => [
        'INSTAGRAM_APP_ID' => ['type' => 'text', 'label' => 'App ID'],
        'INSTAGRAM_APP_SECRET' => ['type' => 'password', 'label' => 'App Secret'],
        'IG_REDIRECT_URI' => ['type' => 'text', 'label' => 'Redirect URI', 'hint' => 'e.g., meta/ig-callback'],
    ],
    'TikTok API' => [
        'TIKTOK_CLIENT_KEY' => ['type' => 'text', 'label' => 'Client Key'],
        'TIKTOK_CLIENT_SECRET' => ['type' => 'password', 'label' => 'Client Secret'],
    ],
    'Google Cloud / AI' => [
        'GOOGLE_CLOUD_PROJECT_ID' => ['type' => 'text', 'label' => 'Project ID'],
        'GOOGLE_CLOUD_API_KEY' => ['type' => 'password', 'label' => 'API Key'],
        'GOOGLE_APPLICATION_CREDENTIALS' => ['type' => 'text', 'label' => 'Service Account Path', 'hint' => 'Full path to JSON file'],
        'GOOGLE_CLOUD_API_ENDPOINT' => ['type' => 'text', 'label' => 'API Endpoint', 'hint' => 'e.g., us-central1-aiplatform.googleapis.com'],
        'GEMINI_MODEL_NAME' => ['type' => 'text', 'label' => 'Gemini Model', 'hint' => 'e.g., gemini-1.5-flash-002'],
    ],
    'Google reCAPTCHA' => [
        'GOOGLE_RECAPTCHA_SITE_KEY' => ['type' => 'text', 'label' => 'Site Key'],
        'GOOGLE_RECAPTCHA_SECRET_KEY' => ['type' => 'password', 'label' => 'Secret Key'],
    ],
    'Tracking Pixels' => [
        'GA4_MEASUREMENT_ID' => ['type' => 'text', 'label' => 'GA4 Measurement ID', 'hint' => 'G-XXXXXXXXXX'],
        'META_PIXEL_ID' => ['type' => 'text', 'label' => 'Meta Pixel ID'],
        'TIKTOK_PIXEL_ID' => ['type' => 'text', 'label' => 'TikTok Pixel ID'],
    ],
    'Printful' => [
        'PRINTFUL_API_KEY' => ['type' => 'password', 'label' => 'API Key'],
        'PRINTFUL_STORE_ID' => ['type' => 'text', 'label' => 'Store ID'],
    ],
    'PhotoRoom' => [
        'PHOTOROOM_LIVE_KEY' => ['type' => 'password', 'label' => 'Live API Key'],
        'PHOTOROOM_SANDBOX_KEY' => ['type' => 'password', 'label' => 'Sandbox API Key'],
    ],
    'Fastly CDN' => [
        'FASTLY_API_KEY' => ['type' => 'password', 'label' => 'API Key'],
        'FASTLY_ORIGIN' => ['type' => 'text', 'label' => 'Origin IP'],
    ],
];

$pageTitle = 'All Settings';
$currentPage = 'settings';
include __DIR__.'/_header.php';

include __DIR__.'/_topbar.php';
?>

      <section class="max-w-7xl mx-auto px-4 py-6 space-y-6">
        <!-- Status Bar -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Environment File</div>
            <div class="text-sm font-mono"><?= htmlspecialchars($envPath) ?></div>
          </div>
          <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Last Modified</div>
            <div class="text-sm"><?= is_file($envPath) ? date('Y-m-d H:i:s', filemtime($envPath)) : 'N/A' ?></div>
          </div>
          <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Status</div>
            <div id="statusMsg" class="text-sm text-emerald-600">Ready</div>
          </div>
        </div>

        <!-- Save Button (sticky) -->
        <div class="sticky top-0 z-10 bg-gray-50 dark:bg-[#0b1020] py-3 border-b border-gray-200 dark:border-white/10">
          <div class="flex items-center gap-3">
            <button id="saveBtn" class="inline-flex items-center px-4 h-10 rounded bg-brand text-white font-medium">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
              Save All Settings
            </button>
            <button id="reloadBtn" class="inline-flex items-center px-4 h-10 rounded bg-gray-200 dark:bg-white/10 text-gray-900 dark:text-white">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
              Reload
            </button>
            <button id="clearOpcacheBtn" class="inline-flex items-center px-4 h-10 rounded bg-gray-200 dark:bg-white/10 text-gray-900 dark:text-white">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
              Clear OPcache
            </button>
            <span class="text-xs text-gray-500 dark:text-gray-400">Changes write to .env. Reload PHP-FPM/OPcache after saving.</span>
          </div>
        </div>

        <!-- Settings Sections -->
        <form id="settingsForm" class="space-y-6">
          <?php foreach ($sections as $sectionName => $fields): ?>
          <div class="rounded-lg border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 overflow-hidden">
            <div class="px-4 py-3 bg-gray-100 dark:bg-white/5 border-b border-gray-200 dark:border-white/10">
              <h3 class="font-semibold text-sm"><?= htmlspecialchars($sectionName) ?></h3>
            </div>
            <div class="p-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <?php foreach ($fields as $key => $field): ?>
                <?php
                  $skip_field = false; // Flag to indicate if this field should be skipped.
                  if (str_starts_with($key, 'MANDRILL_')) {
                      $skip_field = true; // Mark for skipping
                  }
                ?>
              <?php if (!$skip_field): // Only render the field if not skipped ?>
              <?php // Initialize variables only if not skipping.
                  $value = $envValues[$key] ?? '';
                  $type = $field['type'] ?? 'text';
                  $label = $field['label'] ?? $key;
                  $hint = $field['hint'] ?? '';
                ?>
              <div class="flex flex-col">
                <label class="text-xs text-gray-500 dark:text-gray-400 mb-1">
                  <?= htmlspecialchars($label) ?>
                  <?php if ($hint): ?><span class="text-gray-400">(<?= htmlspecialchars($hint) ?>)</span><?php endif; ?>
                </label>
                <?php if ($type === 'bool'): ?>
                  <label class="relative inline-flex items-center cursor-pointer mt-1">
                    <input type="checkbox" name="<?= htmlspecialchars($key) ?>" class="sr-only peer" <?= in_array(strtolower($value), ['true', '1', 'on', 'yes']) ? 'checked' : '' ?>/>
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-brand/50 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-brand"></div>
                    <span class="ml-3 text-sm text-gray-700 dark:text-gray-300"><?= $value ? 'Enabled' : 'Disabled' ?></span>
                  </label>
                <?php elseif ($type === 'select'): ?>
                  <select name="<?= htmlspecialchars($key) ?>" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white dark:bg-white/10 px-3 h-10 text-sm">
                    <?php foreach ($field['options'] as $opt): ?>
                      <option value="<?= htmlspecialchars($opt) ?>" <?= $value === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php elseif ($type === 'password'): ?>
                  <div class="relative mt-1">
                    <input type="password" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>" class="w-full rounded border border-gray-300 dark:border-white/10 bg-white dark:bg-white/10 px-3 h-10 text-sm pr-10" autocomplete="off">
                    <button type="button" onclick="togglePassword(this)" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </button>
                  </div>
                <?php elseif ($type === 'number'): ?>
                  <input type="number" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white dark:bg-white/10 px-3 h-10 text-sm" <?= isset($field['min']) ? 'min="'.$field['min'].'"' : '' ?> <?= isset($field['max']) ? 'max="'.$field['max'].'"' : '' ?>/>
                <?php else: ?>
                  <input type="text" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>" class="mt-1 w-full rounded border border-gray-300 dark:border-white/10 bg-white dark:bg-white/10 px-3 h-10 text-sm">
                <?php endif; ?>
              </div>
              <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </form>
      </section>
    </main>
  </div>

<?php include __DIR__.'/_token_store.php'; ?>
  <script>
    function togglePassword(btn) {
      const input = btn.parentElement.querySelector('input');
      input.type = input.type === 'password' ? 'text' : 'password';
    }

    document.getElementById('saveBtn').addEventListener('click', async function(e) {
      e.preventDefault();
      const form = document.getElementById('settingsForm');
      const formData = new FormData(form);
      const data = {};
      
      // Collect all form values, excluding Mandrill fields that were removed
      form.querySelectorAll('input[name], select[name]').forEach(el => {
        if (!el.name) return;
        // Skip if name starts with MANDRILL_
        if (el.name.startsWith('MANDRILL_')) return;

        if (el.type === 'checkbox') {
          data[el.name] = el.checked ? 'true' : 'false';
        } else {
          data[el.name] = el.value;
        }
      });

      document.getElementById('statusMsg').textContent = 'Saving...';
      document.getElementById('statusMsg').className = 'text-sm text-yellow-600';

      try {
        const token = localStorage.getItem('ngn_admin_token') || localStorage.getItem('admin_token') || '';
        const res = await fetch('/api/v1/admin/env/settings', {
          method: 'PUT',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': token ? 'Bearer ' + token : ''
          },
          body: JSON.stringify(data)
        });
        
        const json = await res.json().catch(() => ({}));
        
        if (res.status === 200) {
          document.getElementById('statusMsg').textContent = 'Saved successfully! Reload PHP-FPM to apply.';
          document.getElementById('statusMsg').className = 'text-sm text-emerald-600';
        } else {
          document.getElementById('statusMsg').textContent = 'Error: ' + (json?.errors?.[0]?.message || res.status);
          document.getElementById('statusMsg').className = 'text-sm text-red-600';
        }
      } catch (err) {
        document.getElementById('statusMsg').textContent = 'Error: ' + err.message;
        document.getElementById('statusMsg').className = 'text-sm text-red-600';
      }
    });

    document.getElementById('reloadBtn').addEventListener('click', function() {
      location.reload();
    });

    document.getElementById('clearOpcacheBtn').addEventListener('click', async function(e) {
        e.preventDefault();
        document.getElementById('statusMsg').textContent = 'Clearing OPcache...';
        document.getElementById('statusMsg').className = 'text-sm text-yellow-600';

        try {
            const token = localStorage.getItem('ngn_admin_token') || localStorage.getItem('admin_token') || '';
            const res = await fetch('/api/v1/admin/clear-opcache', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': token ? 'Bearer ' + token : ''
                }
            });

            const json = await res.json().catch(() => ({}));

            if (res.status === 200) {
                document.getElementById('statusMsg').textContent = 'OPcache cleared successfully.';
                document.getElementById('statusMsg').className = 'text-sm text-emerald-600';
            } else {
                document.getElementById('statusMsg').textContent = 'Error clearing OPcache: ' + (json?.errors?.[0]?.message || res.status);
                document.getElementById('statusMsg').className = 'text-sm text-red-600';
            }
        } catch (err) {
            document.getElementById('statusMsg').textContent = 'Error clearing OPcache: ' + err.message;
            document.getElementById('statusMsg').className = 'text-sm text-red-600';
        }
    });
  </script>
</body>
</html>