<?php
/**
 * Update Erik Baker Advisor Agreement Template
 * Bible Ref: Chapter 28 // Version 1.1.0
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Services\Legal\AgreementService;

$config = new Config();
$db = ConnectionFactory::read($config);
$service = new AgreementService($db);

$body = file_get_contents(__DIR__ . '/../docs/legal/erik-baker-agreement.html');

$slug = 'erik-baker-advisor';
$name = 'Erik Baker Advisor Agreement';
$version = '1.1.0';

echo "🚀 Updating Erik Baker Agreement to v{$version}...
";

try {
    $service->upsertTemplate($slug, $name, $body, $version);
    echo "✅ Successfully updated '{$slug}' in agreement_templates.
";
} catch (\Throwable $e) {
    echo "✗ Error: " . $e->getMessage() . "
";
    exit(1);
}
