<?php
/**
 * Test Page: Agreement Signing
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Services\Legal\AgreementService;

// Mock login for testing if not logged in
if (!isset($_SESSION['user_id'])) {
    // Just for testing, in a real scenario you'd redirect to login
    die("Please log in to test agreement signing.");
}

$config = new Config();
$db = ConnectionFactory::write($config);
$service = new AgreementService($db);

$userId = (int)$_SESSION['user_id'];
$templateSlug = 'artist-onboarding';

// Check if already signed
$isSigned = $service->hasSigned($userId, $templateSlug);
$template = $service->getTemplate($templateSlug);

// Header
$view = 'legal';
require_once __DIR__ . '/index.php'; // Use index.php as shell if possible, or just include header
?>

<div class="container mx-auto px-6 py-12">
    <div class="text-center mb-12">
        <h1 class="text-4xl font-black text-white mb-4">Legal Verification</h1>
        <p class="text-zinc-500 max-w-2xl mx-auto">To access premium distribution features, you must review and sign the latest Artist Agreement.</p>
    </div>

    <?php 
    // Include the agreement viewer partial
    include __DIR__ . '/../lib/partials/legal/agreement-viewer.php';
    ?>
</div>

<?php
// Footer
// ... usually handled by index.php shell
?>
