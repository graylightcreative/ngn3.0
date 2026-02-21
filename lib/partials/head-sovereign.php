<?php
/**
 * Sovereign Head Protocol v3.0.0
 * Pure high-velocity metadata and assets.
 */
$iconVersionQ = '?v=3.0.0';
?>
<link rel="canonical" href="<?= "https://nextgennoise.com" . $_SERVER['REQUEST_URI'] ?>">

<!-- PWA Meta Tags -->
<link rel="manifest" href="/lib/images/site/site.webmanifest<?= $iconVersionQ ?>">
<meta name="theme-color" content="#050505">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="NGN">

<!-- Favicons (NGN 2026 Brand - Emblem) -->
<link rel="icon" type="image/png" sizes="16x16" href="/lib/images/site/favicon-16x16.png<?= $iconVersionQ ?>">
<link rel="icon" type="image/png" sizes="32x32" href="/lib/images/site/favicon-32x32.png<?= $iconVersionQ ?>">
<link rel="apple-touch-icon" sizes="180x180" href="/lib/images/site/apple-touch-icon.png<?= $iconVersionQ ?>">

<!-- Fonts & Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@800&family=Space+Grotesk:wght@300;500;700&display=swap" rel="stylesheet">

<!-- Engine Scripts -->
<script>
    window.tailwind = { 
        config: { 
            darkMode: 'class', 
            theme: { 
                extend: { 
                    colors: { 
                        brand: { DEFAULT: '#FF5F1F', dark: '#E64A00' } 
                    },
                    fontFamily: {
                        sans: ['Space Grotesk', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace']
                    }
                } 
            } 
        } 
    };
</script>
<script src="https://cdn.tailwindcss.com"></script>
<script src="/js/toast.js?v=<?= time() ?>"></script>
<script src="/js/commerce.js?v=<?= time() ?>"></script>
<script src="/js/pwa-installer.js?v=<?= time() ?>"></script>
