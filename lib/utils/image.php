<?php
/**
 * Image Utility Functions
 * Optimized for Core Web Vitals and performance
 */

/**
 * Render an img tag with lazy loading optimization
 *
 * @param string $src Image source URL
 * @param string $alt Alternative text
 * @param string $classes CSS classes (optional)
 * @param bool $lazy Enable lazy loading (default: true)
 * @param string $loading 'lazy', 'eager', or 'auto' (default: 'lazy')
 * @return string HTML img tag
 */
function ngn_image($src, $alt = '', $classes = '', $lazy = true, $loading = 'lazy') {
    if (empty($src)) {
        return '';
    }

    $src = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
    $alt = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');

    $attrs = [
        'src="' . $src . '"',
        'alt="' . $alt . '"',
    ];

    if (!empty($classes)) {
        $attrs[] = 'class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '"';
    }

    if ($lazy && $loading !== 'eager') {
        $attrs[] = 'loading="' . htmlspecialchars($loading, ENT_QUOTES, 'UTF-8') . '"';
        // Add decoding attribute for performance
        $attrs[] = 'decoding="async"';
    }

    return '<img ' . implode(' ', $attrs) . '>';
}

/**
 * Render a picture element with WebP support and lazy loading
 *
 * @param string $src Fallback image URL
 * @param string $webpSrc WebP image URL (optional)
 * @param string $alt Alternative text
 * @param string $classes CSS classes (optional)
 * @param array $srcset Responsive image set for srcset attribute (optional)
 * @param array $sizes Media query sizes for responsive images (optional)
 * @return string HTML picture element
 */
function ngn_picture($src, $webpSrc = '', $alt = '', $classes = '', $srcset = [], $sizes = []) {
    if (empty($src)) {
        return '';
    }

    $src = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
    $alt = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');

    $html = '<picture>';

    // WebP source for modern browsers
    if (!empty($webpSrc)) {
        $webpSrc = htmlspecialchars($webpSrc, ENT_QUOTES, 'UTF-8');
        $html .= '<source type="image/webp" srcset="' . $webpSrc . '"';
        if (!empty($sizes)) {
            $html .= ' sizes="' . htmlspecialchars(implode(', ', $sizes), ENT_QUOTES, 'UTF-8') . '"';
        }
        $html .= '>';
    }

    // Fallback img tag
    $imgAttrs = [
        'src="' . $src . '"',
        'alt="' . $alt . '"',
        'loading="lazy"',
        'decoding="async"',
    ];

    if (!empty($classes)) {
        $imgAttrs[] = 'class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '"';
    }

    if (!empty($srcset)) {
        $srcsetStr = implode(', ', array_map(function($url, $size) {
            return htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . ' ' . htmlspecialchars($size, ENT_QUOTES, 'UTF-8');
        }, $srcset, array_keys($srcset)));
        $imgAttrs[] = 'srcset="' . $srcsetStr . '"';
    }

    if (!empty($sizes)) {
        $imgAttrs[] = 'sizes="' . htmlspecialchars(implode(', ', $sizes), ENT_QUOTES, 'UTF-8') . '"';
    }

    $html .= '<img ' . implode(' ', $imgAttrs) . '>';
    $html .= '</picture>';

    return $html;
}

/**
 * Generate a WebP version filename from a standard image
 *
 * @param string $imagePath Original image path
 * @return string WebP image path (same path with .webp extension)
 */
function ngn_webp_src($imagePath) {
    if (empty($imagePath)) {
        return '';
    }

    $pathInfo = pathinfo($imagePath);
    return $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.webp';
}

/**
 * Get responsive image sizes for common breakpoints
 *
 * @param string $context Context: 'hero', 'card', 'thumbnail' (default: 'card')
 * @return array Sizes array for responsive images
 */
function ngn_responsive_sizes($context = 'card') {
    switch ($context) {
        case 'hero':
            // Full-width hero image
            return [
                '(max-width: 640px) 100vw',
                '(max-width: 1024px) 90vw',
                '(min-width: 1025px) 1200px',
            ];
        case 'thumbnail':
            // Small thumbnail images
            return [
                '(max-width: 640px) 120px',
                '(max-width: 1024px) 160px',
                '(min-width: 1025px) 200px',
            ];
        case 'card':
        default:
            // Card/grid images
            return [
                '(max-width: 640px) calc(100vw - 32px)',
                '(max-width: 1024px) calc(50vw - 24px)',
                '(min-width: 1025px) calc(33vw - 20px)',
            ];
    }
}
