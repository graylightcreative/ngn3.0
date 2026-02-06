<?php
// Start session first
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 86400 * 7,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

$root = dirname(__DIR__) . '/';
require_once $root . 'lib/bootstrap.php';
require_once $root . 'lib/definitions/site-settings.php';

use NGN\Lib\Env;

// Redirect if already logged in
if (!empty($_SESSION['LoggedIn']) && $_SESSION['LoggedIn'] === 1) {
    $user = $_SESSION['User'] ?? [];
    $roleId = (int)($user['role_id'] ?? 0);
    $slug = $user['slug'] ?? '';
    
    // Redirect based on role
    $redirect = match($roleId) {
        1 => '/admin/',
        3 => "/dashboard/artist/",
        7 => "/dashboard/label/",
        4, 15 => "/dashboard/station/",
        5, 17 => "/dashboard/venue/",
        default => '/'
    };
    header('Location: ' . $redirect);
    exit;
}

$return = isset($_GET['r']) ? urldecode($_GET['r']) : '';
$error = isset($_GET['e']) ? urldecode($_GET['e']) : '';

// Facebook OAuth URL - use env vars
$fbAppId = Env::get('FACEBOOK_APP_ID', Env::get('META_APP_ID', ''));
$fbRedirectUri = $GLOBALS['baseurl'] . 'meta/fb-callback';
$fbPermissions = ['public_profile', 'email'];
$fbLoginUrl = $fbAppId ? 'https://www.facebook.com/v22.0/dialog/oauth?client_id=' . $fbAppId
    . '&redirect_uri=' . urlencode($fbRedirectUri)
    . '&scope=' . implode(',', $fbPermissions)
    . '&state=' . urlencode($return) : '';
?>
<!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login | NextGenNoise</title>
  <meta name="description" content="Sign in to your NextGenNoise account">
  <script>
    window.tailwind = { config: { darkMode: 'class', theme: { extend: { colors: { brand: { DEFAULT: '#1DB954', dark: '#169c45' } } } } } };
    (function(){
      const saved = localStorage.getItem('ngn_theme');
      const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      if (saved ? saved === 'dark' : prefersDark) document.documentElement.classList.add('dark');
    })();
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="h-full bg-gray-50 dark:bg-[#0a0a0f]">
  <div class="min-h-full flex flex-col justify-center py-12 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
      <a href="/" class="flex justify-center">
        <img src="/lib/images/site/web-light-1.png" alt="NextGenNoise" class="h-12 hidden dark:block">
        <img src="/lib/images/site/web-dark-1.png" alt="NextGenNoise" class="h-12 dark:hidden">
      </a>
      <h2 class="mt-6 text-center text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
        Sign in to your account
      </h2>
      <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">
        Or <a href="/" class="font-medium text-brand hover:text-brand/80">browse as a guest</a>
      </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
      <div class="bg-white dark:bg-white/5 py-8 px-4 shadow-xl sm:rounded-xl sm:px-10 border border-gray-200 dark:border-white/10">
        
        <?php if ($error): ?>
        <div class="mb-6 rounded-lg bg-red-50 dark:bg-red-900/20 p-4 text-sm text-red-700 dark:text-red-400">
          <i class="bi-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <div id="login-error" class="hidden mb-6 rounded-lg bg-red-50 dark:bg-red-900/20 p-4 text-sm text-red-700 dark:text-red-400"></div>

        <form id="login-form" class="space-y-6">
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email address</label>
            <div class="mt-1 relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i class="bi-envelope text-gray-400"></i>
              </div>
              <input id="email" name="email" type="email" autocomplete="email" required
                class="block w-full pl-10 pr-3 py-3 border border-gray-300 dark:border-white/20 rounded-lg bg-white dark:bg-white/5 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent sm:text-sm"
                placeholder="you@example.com">
            </div>
          </div>

          <div>
            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
            <div class="mt-1 relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i class="bi-lock text-gray-400"></i>
              </div>
              <input id="password" name="password" type="password" autocomplete="current-password" required
                class="block w-full pl-10 pr-10 py-3 border border-gray-300 dark:border-white/20 rounded-lg bg-white dark:bg-white/5 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent sm:text-sm"
                placeholder="••••••••">
              <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                <i class="bi-eye text-gray-400 hover:text-gray-600" id="toggle-pw-icon"></i>
              </button>
            </div>
          </div>

          <input type="hidden" name="return" id="return" value="<?= htmlspecialchars($return) ?>">

          <div>
            <button type="submit" id="login-btn"
              class="w-full flex justify-center items-center gap-2 py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-brand hover:bg-brand/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
              <i class="bi-box-arrow-in-right"></i>
              Sign in
            </button>
          </div>
        </form>

        <div class="mt-6">
          <div class="relative">
            <div class="absolute inset-0 flex items-center">
              <div class="w-full border-t border-gray-300 dark:border-white/20"></div>
            </div>
            <div class="relative flex justify-center text-sm">
              <span class="px-2 bg-white dark:bg-[#0a0a0f] text-gray-500">Or continue with</span>
            </div>
          </div>

          <?php if ($fbLoginUrl): ?>
          <div class="mt-6">
            <a href="<?= htmlspecialchars($fbLoginUrl) ?>"
              class="w-full flex justify-center items-center gap-3 py-3 px-4 border border-gray-300 dark:border-white/20 rounded-lg shadow-sm bg-white dark:bg-white/5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/10 transition-colors">
              <i class="bi-facebook text-[#1877F2] text-lg"></i>
              Continue with Facebook
            </a>
          </div>
          <?php endif; ?>
        </div>

        <div class="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">
          Don't have an account? 
          <a href="/claim" class="font-medium text-brand hover:text-brand/80">Claim your profile</a>
        </div>
      </div>

      <div class="mt-8 text-center">
        <button onclick="toggleTheme()" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
          <i class="bi-moon-stars dark:hidden"></i>
          <i class="bi-sun hidden dark:inline"></i>
          <span class="ml-1 dark:hidden">Dark mode</span>
          <span class="ml-1 hidden dark:inline">Light mode</span>
        </button>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
  <script>
    function toggleTheme() {
      const html = document.documentElement;
      if (html.classList.contains('dark')) {
        html.classList.remove('dark');
        localStorage.setItem('ngn_theme', 'light');
      } else {
        html.classList.add('dark');
        localStorage.setItem('ngn_theme', 'dark');
      }
    }

    function togglePassword() {
      const pw = document.getElementById('password');
      const icon = document.getElementById('toggle-pw-icon');
      if (pw.type === 'password') {
        pw.type = 'text';
        icon.className = 'bi-eye-slash text-gray-400 hover:text-gray-600';
      } else {
        pw.type = 'password';
        icon.className = 'bi-eye text-gray-400 hover:text-gray-600';
      }
    }

    document.getElementById('login-form').addEventListener('submit', async function(e) {
      e.preventDefault();
      
      const btn = document.getElementById('login-btn');
      const errorEl = document.getElementById('login-error');
      const email = document.getElementById('email').value.trim();
      const password = document.getElementById('password').value;
      const redirect = document.getElementById('return').value;

      if (!email || !password) {
        errorEl.innerHTML = '<i class="bi-exclamation-circle mr-2"></i>Please enter email and password';
        errorEl.classList.remove('hidden');
        return;
      }

      btn.disabled = true;
      btn.innerHTML = '<i class="bi-arrow-repeat animate-spin"></i> Signing in...';
      errorEl.classList.add('hidden');

      try {
        const res = await axios.post('/api/auth/login.php', {
          email: email,
          password: password,
          redirect: redirect
        });
        if(res.data){
            console.log(res.data);
        }
        if (res.data && res.data.success) {
          const redirectUrl = res.data.redirect;
          if (redirectUrl && typeof redirectUrl === 'string' && redirectUrl.trim() !== '') {
            window.location.href = redirectUrl;
          } else {
            // Fallback to homepage if server-provided redirect is invalid or empty
            window.location.href = '/';
          }
        } else {
          errorEl.innerHTML = '<i class="bi-exclamation-circle mr-2"></i>' + (res.data.message || 'Login failed');
          errorEl.classList.remove('hidden');
        }
      } catch (err) {
        errorEl.innerHTML = '<i class="bi-exclamation-circle mr-2"></i>An error occurred. Please try again.';
        errorEl.classList.remove('hidden');
      } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi-box-arrow-in-right"></i> Sign in';
      }
    });
  </script>
</body>
</html>