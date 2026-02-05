<?php
/**
 * Admin Header Partial
 * Include this in all 2.0 admin pages
 * 
 * Required variables before include:
 *   $pageTitle - title for the page header
 *   $env - current environment (from Config)
 *   $mintedToken - optional JWT token for API calls
 */
$pageTitle = $pageTitle ?? 'Admin';
?>
<!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>NGN Admin · <?php echo htmlspecialchars($pageTitle); ?></title>
  <script>
    window.tailwind = { config: { darkMode: 'class', theme: { extend: { colors: { brand: { DEFAULT: '#1DB954', dark: '#169c45' } } } } } };
    (function(){
      const key = 'ngn_admin_theme';
      const saved = localStorage.getItem(key);
      const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
      const dark = (saved ? saved === 'dark' : prefersDark);
      if (dark) document.documentElement.classList.add('dark');
      window.__toggleTheme = function(){
        const el = document.documentElement;
        const isDark = el.classList.toggle('dark');
        localStorage.setItem(key, isDark ? 'dark' : 'light');
      };
    })();
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="/js/ngn-tour.js"></script>
  <?php if (!empty($mintedToken)): ?>
  <script>
    (function(){
      try {
        var t = <?php echo json_encode($mintedToken); ?>;
        localStorage.setItem('ngn_admin_token', t);
        localStorage.setItem('admin_token', t);
        var cookie = 'NGN_ADMIN_BEARER=' + encodeURIComponent(t) + '; Path=/; SameSite=Lax';
        if (location.protocol === 'https:') cookie += '; Secure';
        document.cookie = cookie;
      } catch (e) {}
    })();
  </script>
  <?php endif; ?>
</head>
<body class="h-full bg-gray-50 text-gray-900 dark:bg-[#0b1020] dark:text-gray-100">
  <div class="min-h-screen grid grid-cols-1 lg:grid-cols-[260px,1fr]">
    <?php include __DIR__.'/System/_sidebar.php'; ?>

