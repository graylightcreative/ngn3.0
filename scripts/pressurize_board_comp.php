<?php
/**
 * PRESSURIZE BOARD COMPENSATION
 * Executes Mission: Board Compensation & Trigger Logic
 * Bible Ref: Chapter 25 // BFL 3.1
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Services\Legal\AgreementService;

$config = new Config();
$db = ConnectionFactory::read($config);
$service = new AgreementService($db);

echo "🚀 Mission Start: Board Compensation Pressurization...
";

// 1. Ensure Board Mandate Template exists
$slug = 'board-mandate';
$title = 'NGN Directorate Mandate // Board of Governors';
$body = "
    <h3>NGN Directorate Mandate</h3>
    <p>This mandate defines the responsibilities and fiduciary obligations of the undersigned Director.</p>
    [[COMPENSATION_SCHEDULE]]
    <p>By signing this document, the Director acknowledges the compensation structure anchored to the Graylight Nexus.</p>
";

try {
    $service->upsertTemplate($slug, $title, $body, '1.0.0');
    echo "✅ Mandate template initialized.
";

    // 2. Force re-signature for all Directors
    $service->forceResignature($slug);
    echo "✅ Force re-signature triggered. Next version is live.
";

    echo "🏁 Mission Complete: Board Compensation layers are live and gated.
";
} catch (\Throwable $e) {
    echo "✗ Error: " . $e->getMessage() . "
";
    exit(1);
}
