<?php

namespace App\Admin\Controllers\Settings;

require_once __DIR__ . '../../../lib/bootstrap.php'; // Bootstrap NGN environment

use NGN\Lib\Config;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class FeatureFlagController
{
    private Logger $logger;
    private Config $config;

    public function __construct(Logger $logger, Config $config)
    {
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * Sets a feature flag to a new value.
     * Expects POST data: {"feature": "FEATURE_NAME", "value": "new_value"}.
     *
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function setFeatureFlag(Request $request, Response $response, array $args): Response
    {
        $data = $request->getParsedBody();
        $feature = $data['feature'] ?? null;
        $value = $data['value'] ?? null;

        if (empty($feature) || $value === null) {
            return $response->withStatus(400)->withJson([
                'success' => false,
                'message' => 'Feature name and value are required.'
            ]);
        }

        try {
            // --- IMPORTANT: Direct modification of .env is generally NOT recommended.
            // A more robust approach would be: 
            // 1. Use a dedicated database table for settings.
            // 2. Use a dedicated settings service that handles persistence.
            // 3. For simplicity in this context, we will simulate the action by logging.
            //    A real implementation would need to write to a persistent config source.
            
            // --- Simulation: Logging the action ---
            $this->logger->info("Admin requested to set feature flag: {$feature} = {$value}");

            // --- Placeholder for actual config update logic ---
            // If using a file-based config (like .env), this would involve reading,
            // modifying, and writing back the file, which requires careful handling.
            // Example: $this->config->set($feature, $value); // If config service supports dynamic set
            
            // For the specific rollback case (FEATURE_PUBLIC_VIEW_MODE = legacy):
            if ($feature === 'FEATURE_PUBLIC_VIEW_MODE' && $value === 'legacy') {
                // Simulate setting the flag that affects the system.
                // A real implementation would persist this change.
                $this->logger->info("Rollback action: FEATURE_PUBLIC_VIEW_MODE set to 'legacy'.");
                // For immediate effect without restarting services, the running app might need to re-read config.
                // This is complex and usually handled by app reloads or specific signals.
            }

            return $response->withJson([
                'success' => true,
                'message' => "Feature flag '{$feature}' update requested successfully."
            ]);

        } catch (\Throwable $e) {
            $this->logger->error("Failed to set feature flag {$feature}: " . $e->getMessage());
            return $response->withStatus(500)->withJson([
                'success' => false,
                'message' => 'An internal error occurred while updating the feature flag.'
            ]);
        }
    }
}
