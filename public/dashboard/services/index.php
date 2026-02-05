<?php

// Include necessary NGN bootstrap and configurations
require_once __DIR__ . '/../../../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\Env;
use NGN\Lib\Http\Request;
use NGN\Lib\Auth\TokenService;
// Other services might be needed for actual functionality, but not for UI scaffold

// Basic page setup for Admin theme
$config = new Config();
$pageTitle = 'Professional Services';

$currentUserId = null;
$userRoleId = null;
$isProUser = false; // Placeholder for checking if user has Pro tier or investor status

// --- Authentication and Authorization ---
try {
    // Check session and roles
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

    $currentUser = $_SESSION['User'] ?? null;
    if ($currentUser) {
        $currentUserId = $currentUser['Id'] ?? null;
        $userRoleId = (int)($currentUser['RoleId'] ?? 0);
        // Placeholder check for Pro user or investor status needed for some services.
        // This would likely involve checking the user's subscription tier or investor flag.
        // For now, assuming all services are visible.
    }

    // Basic check if user is logged in; specific service access checks will be per-service.
    if (!$currentUserId) {
        // If not logged in, redirect to login or show a message.
        // For simplicity, let's allow viewing the marketplace, but actions will require login.
    }

} catch (\Throwable $e) {
    error_log("Service Marketplace: Error during auth check - " . $e->getMessage());
    // Handle error display if necessary
}

// --- Services Data (Static for now) ---
$allServices = [
    [
        'id' => 'mix_feedback',
        'name' => 'AI Mix Feedback Assistant',
        'description' => "Get AI-powered insights on your track's mix, loudness, and balance.",
        'cost_sparks' => 15,
        'icon' => 'bi-soundwave',
        'details_page' => '/dashboard/services/mix-feedback.php',
        'is_ai' => true
    ],
    [
        'id' => 'service_blurb',
        'name' => 'AI Service Blurb Generator',
        'description' => 'Generate compelling descriptions for your music services or profile.',
        'cost_sparks' => 5,
        'icon' => 'bi-chat-quote',
        'details_page' => '/dashboard/services/blurb-generator.php',
        'is_ai' => true
    ],
    // Add more services here as they are developed
];

$services = array_filter($allServices, function($s) use ($config) {
    if (!empty($s['is_ai']) && !$config->featureAiEnabled()) {
        return false;
    }
    return true;
});

?>
<!-- Include Admin theme header partial -->
<?php require __DIR__ . '/../_header.php'; ?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($pageTitle); ?></h1>

    <!-- Services Marketplace Grid -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($services as $service):
                    // Basic check for Pro/Elite tier or investor status if required
                    // $requiresTier = $service['requires_tier'] ?? null;
                    // $canAccess = (!$requiresTier || ($requiresTier === 'pro' && $userIsPro) || ($requiresTier === 'elite' && $userIsElite)); 
                    // For now, all services are visible.
                ?>
                    <div class="service-card bg-white dark:bg-white/5 rounded-xl border border-gray-200 dark:border-white/10 p-6 flex flex-col justify-between transition-colors hover:border-brand">
                        <div>
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-10 h-10 flex items-center justify-center rounded-full bg-brand/10">
                                    <i class="bi-<?= htmlspecialchars($service['icon']) ?> text-xl text-brand"></i>
                                </div>
                                <h5 class="text-xl font-semibold truncate"><?= htmlspecialchars($service['name']) ?></h5>
                            </div>
                            <p class="text-gray-500 text-sm mb-4 line-clamp-3"><?= htmlspecialchars($service['description']) ?></p>
                        </div>
                        <div class="mt-auto pt-4">
                            <div class="flex items-center justify-between mb-3">
                                <div class="text-lg font-bold text-brand">
                                    <?= $service['cost_sparks'] ?> Sparks
                                </div>
                                <a href="<?= htmlspecialchars($service['details_page']) ?>" class="btn btn-primary btn-sm">Get Started</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<style>
    .service-card {
        /* Add specific styles for service cards if needed, e.g., hover effects */
    }
</style>

<!-- Include Admin theme footer partial -->
<?php require __DIR__ . '/../_footer.php'; ?>
