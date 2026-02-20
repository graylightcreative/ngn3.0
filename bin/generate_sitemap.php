<?php
/**
 * NGN Sitemap Generator
 * Dynamically generates sitemap.xml for nextgennoise.com
 * Bible Ref: Chapter 18 - SEO & Discovery Engine
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

echo "🚀 NGN Sitemap Generator v1.0
";

$config = new Config();
$pdo = ConnectionFactory::read($config);
$baseUrl = 'https://nextgennoise.com';

$urls = [];

// 1. Static Pages
$staticPages = [
    '',
    '/artists',
    '/labels',
    '/stations',
    '/venues',
    '/charts',
    '/smr-charts',
    '/posts',
    '/videos',
    '/releases',
    '/songs',
    '/shop',
    '/pricing',
    '/beta',
    '/?view=investors'
];

foreach ($staticPages as $page) {
    $urls[] = [
        'loc' => $baseUrl . $page,
        'priority' => ($page === '') ? '1.0' : '0.8',
        'changefreq' => 'daily'
    ];
}

// 2. Dynamic Artists (15k+)
echo "Inching artists...
";
$stmt = $pdo->query("SELECT slug, updated_at FROM artists WHERE status = 'active'");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $urls[] = [
        'loc' => $baseUrl . '/artist/' . $row['slug'],
        'lastmod' => date('Y-m-d', strtotime($row['updated_at'] ?? 'now')),
        'priority' => '0.7',
        'changefreq' => 'weekly'
    ];
}

// 3. Dynamic Labels
echo "Inching labels...
";
$stmt = $pdo->query("SELECT slug, updated_at FROM labels WHERE status = 'active'");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $urls[] = [
        'loc' => $baseUrl . '/label/' . $row['slug'],
        'lastmod' => date('Y-m-d', strtotime($row['updated_at'] ?? 'now')),
        'priority' => '0.6',
        'changefreq' => 'weekly'
    ];
}

// 4. Dynamic Posts (News)
echo "Inching posts...
";
$stmt = $pdo->query("SELECT slug, updated_at FROM posts WHERE status = 'published'");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $urls[] = [
        'loc' => $baseUrl . '/post/' . $row['slug'],
        'lastmod' => date('Y-m-d', strtotime($row['updated_at'] ?? 'now')),
        'priority' => '0.6',
        'changefreq' => 'weekly'
    ];
}

// 5. Build XML
echo "Building XML set (" . count($urls) . " URLs)...
";

$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "
";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "
";

foreach ($urls as $url) {
    $xml .= "  <url>
";
    $xml .= "    <loc>" . htmlspecialchars($url['loc']) . "</loc>
";
    if (isset($url['lastmod'])) {
        $xml .= "    <lastmod>" . $url['lastmod'] . "</lastmod>
";
    }
    $xml .= "    <changefreq>" . ($url['changefreq'] ?? 'weekly') . "</changefreq>
";
    $xml .= "    <priority>" . ($url['priority'] ?? '0.5') . "</priority>
";
    $xml .= "  </url>
";
}

$xml .= '</urlset>';

// 6. Write to File
$filePath = dirname(__DIR__) . '/sitemap.xml';
file_put_contents($filePath, $xml);

echo "✅ Sitemap generated successfully: " . $filePath . "
";
