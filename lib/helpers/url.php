<?php
/**
 * NGN 2.0 URL Helper Functions
 *
 * Centralized URL generation for clean, consistent URLs across the application.
 * These functions support the clean URL structure:
 *   /artist/{slug}
 *   /label/{slug}
 *   /station/{slug}
 *   /venue/{slug}
 *   /post/{slug}
 *   /video/{slug}
 *   /show/{slug}
 *   /@{username}
 *
 * Future enhancements:
 * - Custom domain support (via ProfileRouter)
 * - Subdomain routing for premium tiers
 * - Vanity URLs
 * - URL redirects for renamed entities
 */

namespace NGN\Lib\Helpers;

/**
 * Generate a generic entity URL
 *
 * @param string $type Entity type (artist, label, station, venue, etc.)
 * @param string $slug Entity slug/identifier
 * @return string Clean URL path
 */
function entity_url(string $type, string $slug): string {
    if (empty($slug)) {
        return '';
    }
    return '/' . trim($type, '/') . '/' . trim($slug, '/');
}

/**
 * Generate an artist profile URL
 *
 * @param string $slug Artist slug
 * @return string URL path: /artist/{slug}
 */
function artist_url(string $slug): string {
    return entity_url('artist', $slug);
}

/**
 * Generate a label profile URL
 *
 * @param string $slug Label slug
 * @return string URL path: /label/{slug}
 */
function label_url(string $slug): string {
    return entity_url('label', $slug);
}

/**
 * Generate a station profile URL
 *
 * @param string $slug Station slug
 * @return string URL path: /station/{slug}
 */
function station_url(string $slug): string {
    return entity_url('station', $slug);
}

/**
 * Generate a venue profile URL
 *
 * @param string $slug Venue slug
 * @return string URL path: /venue/{slug}
 */
function venue_url(string $slug): string {
    return entity_url('venue', $slug);
}

/**
 * Generate a blog post URL
 *
 * @param string $slug Post slug
 * @return string URL path: /post/{slug}
 */
function post_url(string $slug): string {
    return entity_url('post', $slug);
}

/**
 * Generate a video URL
 *
 * @param string $slug Video slug
 * @return string URL path: /video/{slug}
 */
function video_url(string $slug): string {
    return entity_url('video', $slug);
}

/**
 * Generate a show/event URL
 *
 * @param string $slug Show slug
 * @return string URL path: /show/{slug}
 */
function show_url(string $slug): string {
    return entity_url('show', $slug);
}

/**
 * Generate a release/album URL
 *
 * @param string $slug Release slug
 * @return string URL path: /release/{slug}
 */
function release_url(string $slug): string {
    return entity_url('release', $slug);
}

/**
 * Generate a song/track URL
 *
 * @param string $slug Song slug
 * @return string URL path: /song/{slug}
 */
function song_url(string $slug): string {
    return entity_url('song', $slug);
}

/**
 * Generate a product (merch) URL
 *
 * @param string $slug Product slug
 * @return string URL path: /product/{slug}
 */
function product_url(string $slug): string {
    return entity_url('product', $slug);
}

/**
 * Generate a user profile URL
 *
 * @param string $username Username
 * @return string URL path: /@{username}
 */
function user_profile_url(string $username): string {
    if (empty($username)) {
        return '';
    }
    return '/@' . trim($username, '@/');
}

/**
 * Generate a listing page URL
 *
 * @param string $view View name (artists, labels, stations, venues, posts, videos, shows, shop, etc.)
 * @param array $params Optional query parameters (page, q, sort, etc.)
 * @return string URL path with optional query string
 */
function listing_url(string $view, array $params = []): string {
    $path = '/' . trim($view, '/');

    if (empty($params)) {
        return $path;
    }

    $query = http_build_query($params);
    return $path . (empty($query) ? '' : '?' . $query);
}

/**
 * Generate a special page URL
 *
 * @param string $page Page name (charts, smr-charts, pricing, profile, etc.)
 * @return string URL path
 */
function page_url(string $page): string {
    return '/' . trim($page, '/');
}

/**
 * Generate a URL with query parameters
 *
 * @param string $base Base URL path
 * @param array $params Query parameters
 * @return string Full URL with query string
 */
function url_with_params(string $base, array $params = []): string {
    $base = '/' . trim($base, '/');

    if (empty($params)) {
        return $base;
    }

    $query = http_build_query($params);
    return $base . (empty($query) ? '' : '?' . $query);
}

/**
 * Generate an absolute URL (with protocol and domain)
 *
 * @param string $path Relative path
 * @param bool $useHttps Force HTTPS (default: true)
 * @return string Absolute URL
 */
function absolute_url(string $path, bool $useHttps = true): string {
    $protocol = $useHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'nextgennoise.com';

    // Remove protocol if already included
    $path = preg_replace('#^https?://#', '', $path);
    $path = '/' . trim($path, '/');

    return "{$protocol}://{$host}{$path}";
}

/**
 * Get current page URL for canonical and og:url tags
 *
 * @return string Current page's absolute URL
 */
function current_url(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'nextgennoise.com';
    $path = $_SERVER['REQUEST_URI'] ?? '/';

    return "{$protocol}://{$host}{$path}";
}

/**
 * Build a breadcrumb URL path
 * Useful for displaying breadcrumb navigation
 *
 * @param array $items Array of ['label' => 'Label', 'url' => '/path'] items
 * @return string HTML breadcrumb markup
 */
function breadcrumb_html(array $items = []): string {
    if (empty($items)) {
        return '';
    }

    $html = '<nav aria-label="breadcrumb">';
    $html .= '<ol class="breadcrumb">';

    foreach ($items as $index => $item) {
        $isLast = ($index === count($items) - 1);
        $label = $item['label'] ?? '';
        $url = $item['url'] ?? '#';

        if ($isLast) {
            $html .= '<li class="breadcrumb-item active" aria-current="page">' . htmlspecialchars($label) . '</li>';
        } else {
            $html .= '<li class="breadcrumb-item"><a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($label) . '</a></li>';
        }
    }

    $html .= '</ol>';
    $html .= '</nav>';

    return $html;
}

/**
 * Check if a slug is valid
 * Valid slugs contain only lowercase letters, numbers, and hyphens
 *
 * @param string $slug Slug to validate
 * @return bool True if slug is valid
 */
function is_valid_slug(string $slug): bool {
    return !empty($slug) && preg_match('/^[a-z0-9-]+$/', $slug) === 1;
}

/**
 * Sanitize a slug for use in URLs
 * Converts to lowercase, replaces spaces/underscores with hyphens,
 * removes non-alphanumeric characters except hyphens
 *
 * @param string $text Text to convert to slug
 * @return string Sanitized slug
 */
function sanitize_slug(string $text): string {
    // Convert to lowercase
    $slug = strtolower($text);

    // Replace spaces and underscores with hyphens
    $slug = preg_replace('/[\s_]+/', '-', $slug);

    // Remove non-alphanumeric characters except hyphens
    $slug = preg_replace('/[^a-z0-9-]/', '', $slug);

    // Replace multiple hyphens with single hyphen
    $slug = preg_replace('/-+/', '-', $slug);

    // Trim hyphens from start and end
    $slug = trim($slug, '-');

    return $slug;
}

/**
 * Generate a full artist profile URL with all components
 * Useful for social sharing and email templates
 *
 * @param array $artist Artist data array with 'slug' key
 * @return string Absolute URL to artist profile
 */
function artist_full_url(array $artist): string {
    $slug = $artist['slug'] ?? '';
    return absolute_url(artist_url($slug));
}

/**
 * Generate a full label profile URL with all components
 *
 * @param array $label Label data array with 'slug' key
 * @return string Absolute URL to label profile
 */
function label_full_url(array $label): string {
    $slug = $label['slug'] ?? '';
    return absolute_url(label_url($slug));
}

/**
 * Generate a full station profile URL with all components
 *
 * @param array $station Station data array with 'slug' key
 * @return string Absolute URL to station profile
 */
function station_full_url(array $station): string {
    $slug = $station['slug'] ?? '';
    return absolute_url(station_url($slug));
}

/**
 * Generate a full venue profile URL with all components
 *
 * @param array $venue Venue data array with 'slug' key
 * @return string Absolute URL to venue profile
 */
function venue_full_url(array $venue): string {
    $slug = $venue['slug'] ?? '';
    return absolute_url(venue_url($slug));
}

/**
 * Generate a full post URL with all components
 *
 * @param array $post Post data array with 'slug' key
 * @return string Absolute URL to post
 */
function post_full_url(array $post): string {
    $slug = $post['slug'] ?? '';
    return absolute_url(post_url($slug));
}

/**
 * Generate a full video URL with all components
 *
 * @param array $video Video data array with 'slug' key
 * @return string Absolute URL to video
 */
function video_full_url(array $video): string {
    $slug = $video['slug'] ?? '';
    return absolute_url(video_url($slug));
}
