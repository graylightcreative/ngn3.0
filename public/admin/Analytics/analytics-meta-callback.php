<?php
/**
 * Meta OAuth Callback Handler
 * Handles the redirect from Facebook OAuth and stores the access token
 */
require_once dirname(__DIR__, 2) . '/_guard.php';
$root = dirname(__DIR__, 2);
require_once $root.'/lib/bootstrap.php';

use NGN\Lib\Env;
use NGN\Lib\Config;
use NGN\Lib\Analytics\MetaAnalyticsService;
use NGN\Lib\DB\ConnectionFactory;

Env::load($root);
$cfg = new Config();

// Start session for state validation
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$error = null;
$success = false;
$pages = [];

try {
    // Check for error from Facebook
    if (isset($_GET['error'])) {
        throw new Exception($_GET['error_description'] ?? $_GET['error']);
    }
    
    // Validate state parameter
    $state = $_GET['state'] ?? '';
    $savedState = $_SESSION['meta_oauth_state'] ?? '';
    if (!$state || $state !== $savedState) {
        throw new Exception('Invalid state parameter. Please try again.');
    }
    unset($_SESSION['meta_oauth_state']);
    
    // Get authorization code
    $code = $_GET['code'] ?? '';
    if (!$code) {
        throw new Exception('No authorization code received');
    }
    
    // Exchange code for token
    $svc = new MetaAnalyticsService($cfg);
    $tokenResult = $svc->exchangeCodeForToken($code);
    
    if (!$tokenResult['success']) {
        throw new Exception($tokenResult['error'] ?? 'Failed to exchange code for token');
    }
    
    $shortToken = $tokenResult['access_token'];
    
    // Exchange for long-lived token
    $longResult = $svc->getLongLivedToken($shortToken);
    $accessToken = $longResult['success'] ? ($longResult['data']['access_token'] ?? $shortToken) : $shortToken;
    $expiresIn = $longResult['success'] ? ($longResult['data']['expires_in'] ?? 5184000) : 5184000;
    
    // Get user's pages
    $pagesResult = $svc->getUserPages($accessToken);
    if ($pagesResult['success'] && !empty($pagesResult['pages'])) {
        $pages = $pagesResult['pages'];
        
        // Store in session for page selection
        $_SESSION['meta_pages'] = $pages;
        $_SESSION['meta_user_token'] = $accessToken;
        $_SESSION['meta_token_expires'] = time() + $expiresIn;
    }
    
    $success = true;
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

$pageTitle = 'Meta Connection';
$currentPage = 'analytics-meta';
include __DIR__.'/_header.php';

include __DIR__.'/_topbar.php';
?>

<main class="flex-1 p-4 md:p-6 overflow-y-auto">
  <section class="max-w-2xl mx-auto space-y-6">
    
    <?php if ($error): ?>
    <div class="rounded-lg border border-red-200 dark:border-red-500/30 bg-red-50 dark:bg-red-500/10 p-4">
      <h3 class="font-semibold text-red-800 dark:text-red-200">Connection Failed</h3>
      <p class="text-sm text-red-600 dark:text-red-300 mt-1"><?= htmlspecialchars($error) ?></p>
      <a href="/admin/analytics-meta.php" class="inline-block mt-3 px-4 py-2 rounded bg-red-600 text-white text-sm hover:bg-red-700">Try Again</a>
    </div>
    <?php elseif ($success && !empty($pages)): ?>
    <div class="rounded-lg border border-emerald-200 dark:border-emerald-500/30 bg-emerald-50 dark:bg-emerald-500/10 p-4">
      <h3 class="font-semibold text-emerald-800 dark:text-emerald-200">Connected Successfully!</h3>
      <p class="text-sm text-emerald-600 dark:text-emerald-300 mt-1">Select which pages to connect:</p>
    </div>
    
    <form id="pageSelectForm" class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
      <h3 class="font-semibold mb-4">Select Pages to Connect</h3>
      <div class="space-y-3">
        <?php foreach ($pages as $page): ?>
        <label class="flex items-center gap-3 p-3 rounded bg-gray-50 dark:bg-white/5 cursor-pointer hover:bg-gray-100 dark:hover:bg-white/10">
          <input type="checkbox" name="pages[]" value="<?= htmlspecialchars($page['id']) ?>" 
                 data-name="<?= htmlspecialchars($page['name']) ?>"
                 data-token="<?= htmlspecialchars($page['access_token']) ?>"
                 data-ig="<?= htmlspecialchars($page['instagram_business_account']['id'] ?? '') ?>"
                 class="rounded border-gray-300">
          <div>
            <div class="font-medium"><?= htmlspecialchars($page['name']) ?></div>
            <div class="text-xs text-gray-500">
              Page ID: <?= htmlspecialchars($page['id']) ?>
              <?php if (!empty($page['instagram_business_account'])): ?>
              · Instagram: <?= htmlspecialchars($page['instagram_business_account']['id']) ?>
              <?php endif; ?>
            </div>
          </div>
        </label>
        <?php endforeach; ?>
      </div>
      
      <div class="mt-4 flex gap-3">
        <button type="submit" class="px-4 py-2 rounded bg-brand text-white text-sm hover:bg-brand-dark">Save Selected Pages</button>
        <a href="/admin/analytics-meta.php" class="px-4 py-2 rounded border border-gray-200 dark:border-white/10 text-sm hover:bg-gray-50 dark:hover:bg-white/10">Cancel</a>
      </div>
      <div id="saveStatus" class="mt-3 text-sm hidden"></div>
    </form>
    <?php else: ?>
    <div class="rounded-lg border border-amber-200 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/10 p-4">
      <h3 class="font-semibold text-amber-800 dark:text-amber-200">No Pages Found</h3>
      <p class="text-sm text-amber-600 dark:text-amber-300 mt-1">No Facebook Pages were found for your account. Make sure you have admin access to at least one Facebook Page.</p>
      <a href="/admin/analytics-meta.php" class="inline-block mt-3 px-4 py-2 rounded bg-amber-600 text-white text-sm hover:bg-amber-700">Go Back</a>
    </div>
    <?php endif; ?>
    
  </section>
</main>
</div>

<?php include __DIR__.'/_footer.php'; ?>

<script>
document.getElementById('pageSelectForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const status = document.getElementById('saveStatus');
  const checked = document.querySelectorAll('input[name="pages[]"]:checked');
  
  if (checked.length === 0) {
    status.className = 'mt-3 text-sm text-red-500';
    status.textContent = 'Please select at least one page';
    status.classList.remove('hidden');
    return;
  }
  
  status.className = 'mt-3 text-sm text-gray-600';
  status.textContent = 'Saving...';
  status.classList.remove('hidden');
  
  const token = localStorage.getItem('ngn_admin_token') || localStorage.getItem('admin_token') || '';
  const pages = Array.from(checked).map(cb => ({
    page_id: cb.value,
    page_name: cb.dataset.name,
    page_token: cb.dataset.token,
    instagram_id: cb.dataset.ig || null
  }));
  
  try {
    const res = await fetch('/api/v1/admin/analytics/meta/save-pages', {
      method: 'POST',
      headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
      body: JSON.stringify({ pages })
    });
    const data = await res.json();
    if (res.ok) {
      status.className = 'mt-3 text-sm text-emerald-600';
      status.textContent = 'Pages saved! Redirecting...';
      setTimeout(() => window.location.href = '/admin/analytics-meta.php', 1500);
    } else {
      status.className = 'mt-3 text-sm text-red-500';
      status.textContent = data.errors?.[0]?.message || 'Failed to save pages';
    }
  } catch (e) {
    status.className = 'mt-3 text-sm text-red-500';
    status.textContent = 'Error: ' + e.message;
  }
});
</script>

