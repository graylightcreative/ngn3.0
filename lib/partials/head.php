<?php
// Global guard for NGN 1.0 pages: maintenance + view-mode switching (1.0 vs 2.0)
$__root = dirname(__DIR__, 2);
require_once $__root . '/lib/bootstrap.php';
// If NGN Env still isn't available, emit diagnostics now to avoid obscure fatals later in the page.
if (!class_exists('NGN\\Lib\\Env')) {
    if (function_exists('ngn_autoload_diagnostics')) { ngn_autoload_diagnostics($__root, false); }
    exit;
}

NGN\Lib\Env::load($__root);
$cfg = new NGN\Lib\Config();
$path = $_SERVER['REQUEST_URI'] ?? '/';
$pathLc = strtolower(parse_url($path, PHP_URL_PATH) ?? '/');

// Load site metadata overrides (optional)
try {
    $siteMetaPath = $__root . '/meta/site.json';
    if (is_file($siteMetaPath)) {
        $raw = @file_get_contents($siteMetaPath);
        $siteMeta = json_decode($raw, true);
        if (is_array($siteMeta)) {
            if (isset($siteMeta['AUTHOR'])) { $GLOBALS['Default']['Author'] = (string)$siteMeta['AUTHOR']; }
            if (isset($siteMeta['SUMMARY'])) { $GLOBALS['Default']['Summary'] = (string)$siteMeta['SUMMARY']; }
            if (isset($siteMeta['TAGS'])) { $GLOBALS['Default']['Tags'] = (string)$siteMeta['TAGS']; }
            if (!isset($GLOBALS['theme'])) { $GLOBALS['theme'] = []; }
            if (isset($siteMeta['THEME_COLOR_PRIMARY'])) { $GLOBALS['theme']['primary'] = (string)$siteMeta['THEME_COLOR_PRIMARY']; }
            if (isset($siteMeta['THEME_COLOR_DARK'])) { $GLOBALS['theme']['dark'] = (string)$siteMeta['THEME_COLOR_DARK']; }
        }
    }
} catch (\Throwable $e) { /* ignore */ }

// Favicon/app icon version (cache-busting)
$iconVersion = '';
$iconVersionQ = '';
try {
    $favJson = $__root . '/lib/images/site/favicon.json';
    if (is_file($favJson)) {
        $raw = @file_get_contents($favJson);
        $fav = json_decode($raw, true);
        $ver = isset($fav['version']) ? (string)$fav['version'] : '';
        if ($ver !== '') { $iconVersion = $ver; $iconVersionQ = '?v=' . $ver; }
    }
} catch (\Throwable $e) { /* ignore */ }

// Base allowlist (outside maintenance): API, admin, maintenance page, login/logout, newsletter signup, verify endpoints, robots.txt, sitemap
$allowAlways = (
    str_starts_with($pathLc, '/api/') ||
    str_starts_with($pathLc, '/admin/') ||
    $pathLc === '/admin' ||
    str_starts_with($pathLc, '/maintenance') ||
    $pathLc === '/login' ||
    $pathLc === '/login.php' ||
    $pathLc === '/logout' ||
    $pathLc === '/logout.php' ||
    $pathLc === '/admin/login' ||
    $pathLc === '/admin/login.php' ||
    $pathLc === '/admin/logout' ||
    $pathLc === '/admin/logout.php' ||
    $pathLc === '/newsletter-signup.php' ||
    str_starts_with($pathLc, '/verify') ||
    $pathLc === '/robots.txt' ||
    $pathLc === '/sitemap.xml'
);

// Maintenance guard with admin bypass; block /frontend for non-admins
$isFrontend = str_starts_with($pathLc, '/frontend/');
$isAdminBypass = false;
try {
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    $adminRoleIds = ['1'];
    try {
        if (class_exists('NGN\Lib\Config')) { $adminRoleIds = array_map('strval', (new NGN\Lib\Config())->legacyAdminRoleIds()); }
    } catch (\Throwable $e) {}
    if (!empty($_SESSION['User']['RoleId'])) {
        $rid = (string)$_SESSION['User']['RoleId'];
        if (in_array($rid, $adminRoleIds, true)) { $isAdminBypass = true; }
    }
    // Optional bearer check for admin in headers
    if (!$isAdminBypass && isset($_SERVER['HTTP_AUTHORIZATION']) && stripos($_SERVER['HTTP_AUTHORIZATION'], 'Bearer ') === 0) {
        $hdr = trim(substr($_SERVER['HTTP_AUTHORIZATION'], 7));
        if ($hdr !== '' && class_exists('NGN\\Lib\\Auth\\TokenService')) {
            $claims = (new NGN\Lib\Auth\TokenService($cfg))->decode($hdr);
            $role = strtolower((string)($claims['role'] ?? ''));
            if ($role === 'admin') { $isAdminBypass = true; }
        }
    }
} catch (\Throwable $e) { /* ignore */ }

if ($cfg->maintenanceMode()) {
    if (!$isAdminBypass) {
        // During maintenance, only allow:
        // - API
        // - Admin
        // - Maintenance landing
        // - Login pages (so admins can sign in)
        // - Logout routes
        // - Legacy login handler (AJAX)
        $allowedDuringMaint = (
            str_starts_with($pathLc, '/api/') ||
            str_starts_with($pathLc, '/admin/') ||
            str_starts_with($pathLc, '/maintenance') ||
            $pathLc === '/login' ||
            $pathLc === '/login.php' ||
            $pathLc === '/logout' ||
            $pathLc === '/logout.php' ||
            $pathLc === '/admin/login' ||
            $pathLc === '/admin/login.php' ||
            $pathLc === '/admin/logout' ||
            $pathLc === '/admin/logout.php' ||
            $pathLc === '/lib/handlers/login.php'
        );
        if (!$allowedDuringMaint) {
            include $__root . '/maintenance/index.php';
            exit;
        }
        // Explicitly block frontend for non-admins during maintenance
        if ($isFrontend) {
            include $__root . '/maintenance/index.php';
            exit;
        }
    }
}

// View-mode switching: if effective mode is "next" (2.0), redirect legacy pages to /frontend
$viewMode = $cfg->publicViewMode();
$override = $_COOKIE['NGN_VIEW_MODE'] ?? null;
$effectiveMode = (in_array($override, ['legacy','next'], true)) ? $override : $viewMode;
if ($effectiveMode === 'next' && !$allowAlways) {
    header('Location: /frontend/index.php', true, 302);
    exit;
}
?>
<!doctype html>

<?php if($GLOBALS['theme']['light']):?>
<html lang='en' data-bs-theme='light'>
<?php else:?>
<html lang='en' data-bs-theme='dark'>
<?php endif;?>
<head>
    <?php
    if(!isset($pageSettings)){
        $pageSettings = $GLOBALS['Default'];
        $pageSettings['Url'] = $baseurl;
        $defaultImage = $GLOBALS['Default']['Image'];
    } else {
        $defaultImage = $pageSettings['Image'];
    }

    ?>

    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title><?= htmlspecialchars($pageSettings['Title']); ?></title>

    <meta name='description' content="<?= htmlspecialchars($pageSettings['Summary']); ?>">
    <meta name='keywords' content='<?= $pageSettings['Tags']; ?>'>
    <meta name='author' content='<?= $pageSettings['Author']; ?>'>
    <meta name='robots' content='index, follow'>

    <meta property="og:title" content="<?= htmlspecialchars($pageSettings['Title']); ?>">
    <meta property="og:locale" content="en_EN">
    <meta property="og:description" content="<?= htmlspecialchars($pageSettings['Summary']); ?>">
    <meta property="og:image" content="<?= $defaultImage; ?>">
    <meta property="og:image:width" content="1920">
    <meta property="og:image:height" content="1080">
    <meta property="og:image:alt"
          content="<?= htmlspecialchars($pageSettings['ImageAlt'] ?? 'Image representing the website'); ?>">
    <meta property="og:image:type" content="image/jpg">
    <meta property="og:url" content="<?= $pageSettings['Url']; ?>">
    <meta property="og:site_name" content="NextGenNoise">

    <meta name='twitter:card' content='<?= $GLOBALS['Default']['Image']; ?>'>
    <meta name='twitter:title' content='<?= htmlspecialchars($pageSettings['Title']); ?>'>
    <meta name='twitter:description' content="<?= htmlspecialchars($pageSettings['Summary']); ?>">
    <meta name='twitter:image' content='<?=$defaultImage;?>'>

    <link rel='icon' type='image/x-icon' sizes='16x16'
          href='<?= $GLOBALS['Default']['Baseurl']; ?>lib/images/site/favicon-16x16.png<?= $iconVersionQ ?>'>
    <link rel='icon' type='image/x-icon' sizes='32x32'
          href='<?= $GLOBALS['Default']['Baseurl']; ?>lib/images/site/favicon-32x32.png<?= $iconVersionQ ?>'>

    <link rel='apple-touch-icon' sizes='180x180'
          href='<?= $GLOBALS['Default']['Baseurl']; ?>lib/images/site/apple-touch-icon.png<?= $iconVersionQ ?>'>

    <link rel='manifest' href='<?= $GLOBALS['Default']['Baseurl']; ?>lib/images/site/site.webmanifest<?= $iconVersionQ ?>'>
    <link rel='mask-icon' href='<?= $GLOBALS['Default']['Baseurl']; ?>lib/images/site/safari-pinned-tab.svg'
          color='<?= $GLOBALS['theme']['primary']; ?>'>
    <meta name='msapplication-TileColor' content='<?= $GLOBALS['theme']['primary']; ?>'>

    <meta name='theme-color' content='<?= $GLOBALS['theme']['primary']; ?>'>
    <meta http-equiv='Content-Type' content='text/html'>
    <meta name='referrer' content='no-referrer-when-downgrade'>
    <meta name="google-adsense-account" content="ca-pub-7337425454080680">

    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'
          integrity='sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH' crossorigin='anonymous'>
    <link rel="preload" href="https://fonts.gstatic.com/s/suse/v11/v7Y52uI2k7P0_0F-zR2s4A.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --bs-primary: <?=$GLOBALS['theme']['primary'];?>;
            --bs-primary-rgb: <?=$GLOBALS['theme']['rgb'][0];?>, <?=$GLOBALS['theme']['rgb'][1];?>,<?=$GLOBALS['theme']['rgb'][2];?>;
            --bs-secondary: <?=$GLOBALS['theme']['secondary'];?>;
            --bs-success: <?=$GLOBALS['theme']['success'];?>;
            --bs-info: <?=$GLOBALS['theme']['info'];?>;
            --bs-warning: <?=$GLOBALS['theme']['warning'];?>;
            --bs-danger: <?=$GLOBALS['theme']['danger'];?>;
            --bs-light: <?='#efefef';?>; /* Custom light color */
            --bs-dark: <?=$GLOBALS['theme']['dark'];?>;

            /* Default light and dark body backgrounds */
            --bs-body-bg-light: <?='#efefef';?>;
            --bs-body-bg-dark: <?=$GLOBALS['theme']['dark'];?>;

            /* Default body text colors */
            --bs-body-color-light: #ffffff; /* White text for light body */
            --bs-body-color-dark: #000000; /* Black text for dark body */
        }

        /* Customizing Bootstrap's light color */

        .bg-light {
            background-color: <?='#efefef';?> !important;
            color: #fff; /* Optional: adjust text color for better readability */
        }

        .text-light {
            color: <?='#efefef';?> !important;
        }

        .border-light {
            border-color: <?='#efefef';?> !important;
        }
        .btn-primary,
        .bg-primary,
        .text-primary,
        .border-primary {
            --bs-btn-bg: <?=$GLOBALS['theme']['primary'];?>; !important; /* Custom primary color for buttons */
            --bs-btn-border-color: <?=$GLOBALS['theme']['primary'];?>; !important; /* Custom primary border color for buttons */
            --bs-btn-hover-bg: <?=$GLOBALS['theme']['shades']['darker'];?>; !important;  /* Custom hover color (slightly darker) for buttons */
            --bs-btn-hover-border-color: <?=$GLOBALS['theme']['shades']['darker'];?>; !important;  /* Custom hover border color (slightly darker) for buttons */
            --bs-btn-focus-shadow-rgb: 0, 0, 0 !important; /* Adjust focus shadow color (optional) */
        }
        .btn-outline-primary {
            --bs-btn-color: <?=$GLOBALS['theme']['primary'];?>;; /* Your chosen hex color */
            --bs-btn-border-color: <?=$GLOBALS['theme']['primary'];?>;;
            --bs-btn-hover-color: #fff; /* Adjust hover text color if needed */
            --bs-btn-hover-bg: <?=$GLOBALS['theme']['primary'];?>;;
            --bs-btn-hover-border-color: <?=$GLOBALS['theme']['primary'];?>;;
            --bs-btn-focus-shadow-rgb: 0,0,0; /* Focus shadow (optional) */
            --bs-btn-active-color: #fff; /* Active text color (optional) */
            --bs-btn-active-bg: <?=$GLOBALS['theme']['primary'];?>;;
            --bs-btn-active-border-color: <?=$GLOBALS['theme']['primary'];?>;;
            --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125); /* Active shadow (optional) */
            --bs-btn-disabled-color: <?=$GLOBALS['theme']['primary'];?>;; /* Disabled text color (optional) */
            --bs-btn-disabled-bg: transparent;
            --bs-btn-disabled-border-color: <?=$GLOBALS['theme']['primary'];?>;;
        }
        /* Add this if you also want to change the link color */
        a:not(.btn,.dropdown-item) {
            color: <?=$GLOBALS['theme']['primary'];?>; !important; /* Custom primary color for links */
            /*color: #f51b1f !important; !* Custom primary color for links *!*/
        }
        a:not(.btn,.dropdown-item):hover {
            color: <?=$GLOBALS['theme']['shades']['darker'];?> !important;
        }

        .btn-primary:disabled,
        .btn-outline-primary:disabled,
        a:not(.btn,.dropdown-item):disabled {
            background-color: <?=$GLOBALS['theme']['primary'];?> !important; /* Custom primary color for disabled buttons and links */
        }
        

        /* Customizing Bootstrap Navigation */
        .navbar {
            background-color: <?=$GLOBALS['theme']['primary'];?> !important; /* Custom background color for navbar */
            color: #fff !important; /* Text color for navbar */
        }

        .navbar-light .navbar-nav .nav-link {
            color: <?=$GLOBALS['theme']['secondary'];?> !important; /* Custom link color for light navbar */
        }

        .navbar-light .navbar-nav .nav-link:hover,
        .navbar-light .navbar-nav .nav-link:focus {
            color: <?=$GLOBALS['theme']['shades']['darker'];?> !important; /* Custom hover and focus color for links */
        }

        .navbar-dark .navbar-nav .nav-link {
            color: <?=$GLOBALS['theme']['light'];?> !important; /* Custom link color for dark navbar */
        }

        .navbar-dark .navbar-nav .nav-link:hover,
        .navbar-dark .navbar-nav .nav-link:focus {
            color: <?=$GLOBALS['theme']['shades']['lighter'];?> !important; /* Custom hover and focus color for links */
        }

        .navbar-brand {
            color: <?=$GLOBALS['theme']['secondary'];?> !important; /* Custom brand color */
        }

        .navbar-brand:hover,
        .navbar-brand:focus {
            color: <?=$GLOBALS['theme']['shades']['darker'];?> !important; /* Hover and focus color for brand */
        }

        .nav-item.active .nav-link,
        .nav-item.active .nav-link:hover {
            color: #fff !important; /* Active link color */
            background-color: <?=$GLOBALS['theme']['shades']['darker'];?> !important; /* Active link background */
        }

        .navbar-toggler {
            border-color: <?=$GLOBALS['theme']['secondary'];?> !important; /* Custom border color for toggler */
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml;charset=utf8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3E%3Cpath stroke='<?=$GLOBALS['theme']['primary'];?>' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3E%3C/svg%3E"); /* Custom toggler icon color */
        }

        .dropdown-item {
            color: <?=$GLOBALS['theme']['primary'];?> !important; /* Custom color for dropdown items */
        }

        .dropdown-item:hover,
        .dropdown-item:focus {
            color: #fff !important; /* Hover and focus color for dropdown items */
            background-color: <?=$GLOBALS['theme']['primary'];?> !important; /* Hover and focus background color */
        }


        /* Customizing Nav-Pills */
        .nav-pills .nav-link {
            background-color: transparent; /* Default background for nav-pills */
            color: <?=$GLOBALS['theme']['primary'];?> !important; /* Default text color */
            border: 1px solid transparent; /* Border for better styling */
            border-radius: 5px; /* Optional: Rounded corners */
        }

        .nav-pills .nav-link:hover {
            color: #fff !important; /* Text color on hover */
            background-color: <?=$GLOBALS['theme']['primary'];?> !important; /* Background color on hover */
            border-color: <?=$GLOBALS['theme']['primary'];?> !important; /* Border color for hover */
        }

        .nav-pills .nav-link.active {
            color: #fff !important; /* Active text color */
            background-color: <?=$GLOBALS['theme']['secondary'];?> !important; /* Active background color */
            border-color: <?=$GLOBALS['theme']['secondary'];?> !important; /* Border color for active state */
            font-weight: bold; /* Optional: Emphasize active item */
            border-radius: 50px; /* Optional: Rounded corners */
        }

    </style>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.9.0/slick.css'
          integrity='sha512-wR4oNhLBHf7smjy0K4oqzdWumd+r5/+6QO/vDda76MW5iug4PT7v86FoEkySIJft3XA0Ae6axhIvHrqwm793Nw=='
          crossorigin='anonymous' referrerpolicy='no-referrer'/>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.9.0/slick-theme.css'
          integrity='sha512-6lLUdeQ5uheMFbWm3CP271l14RsX1xtx+J5x2yeIDkkiBpeVTNhTqijME7GgRKKi6hCqovwCoBTlRBEC20M8Mg=='
          crossorigin='anonymous' referrerpolicy='no-referrer'/>    <link rel="stylesheet" href="<?=$GLOBALS['baseurl'];?>lib/css/ngn.css?v=<?=strtotime('now');?>">

    <?php if($track && !$admin):?>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-LHGQG7HXKH"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());

        gtag('config', 'G-LHGQG7HXKH');
    </script>
    <meta name='facebook-domain-verification' content='wrt8y88kvxuy2eywep1s1ksxqiseyc'/>
    <!-- Meta Pixel Code -->
    <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
            n.callMethod.apply(n,arguments):n.queue.push(arguments)};
            if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
            n.queue=[];t=b.createElement(e);t.async=!0;
            t.src=v;s=b.getElementsByTagName(e)[0];
            s.parentNode.insertBefore(t,s)}(window, document,'script',
            'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '1496589657660545');
        fbq('track', 'PageView');
    </script>
    <noscript><img height="1" width="1" style="display:none"
                   src="https://www.facebook.com/tr?id=1496589657660545&ev=PageView&noscript=1"
        /></noscript>
    <!-- End Meta Pixel Code -->

    <!-- Core Web Vitals Monitoring -->
    <script async src="https://cdn.jsdelivr.net/npm/web-vitals@4.0.1/+esm"></script>
    <script type="module">
        // Import web-vitals module
        import {getCLS, getFID, getFCP, getLCP, getTTFB, getINP} from 'https://cdn.jsdelivr.net/npm/web-vitals@4.0.1/+esm';

        // Send metrics to Google Analytics
        function sendToGA(metric) {
            // Ensure gtag is available
            if (typeof gtag !== 'undefined') {
                gtag('event', metric.name, {
                    'value': Math.round(metric.value),
                    'event_category': 'web_vitals',
                    'event_label': metric.id,
                    'non_interaction': true,
                });

                // Also log to console in development
                if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                    console.log(`${metric.name}: ${metric.value.toFixed(2)}`);
                }
            }
        }

        // Register observers for each metric
        getCLS(sendToGA);  // Cumulative Layout Shift
        getFID(sendToGA);  // First Input Delay
        getFCP(sendToGA);  // First Contentful Paint
        getLCP(sendToGA);  // Largest Contentful Paint
        getTTFB(sendToGA); // Time to First Byte
        getINP(sendToGA);  // Interaction to Next Paint
    </script>
    <!-- End Core Web Vitals Monitoring -->
    <?php endif;?>