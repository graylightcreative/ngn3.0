<?php

// This script synchronizes spins data and is intended to be run by cron.
// It now includes API Key Governance for data ingestion.

require_once __DIR__ . '/../../lib/bootstrap.php'; // Bootstrap NGN environment

use NGN\Lib\Config;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// --- Configuration ---
$logFile = __DIR__ . '/../storage/logs/spins_sync.log';
$ingestionApiKeyType = 'NGN_INGESTION';

// --- Setup Logger ---
try {
    $logger = new Logger('spins_sync');
    $logger->pushHandler(new StreamHandler($logFile, Logger::INFO));

    // Assume $pdo and $config are available from bootstrap.php
    if (!isset($pdo) || !($pdo instanceof \PDO)) {
        if (class_exists('NGN\Lib\Database\ConnectionFactory')) {
            $pdo = NGN\Lib\Database\ConnectionFactory::read(new Config());
        } else {
            throw new \RuntimeException("PDO connection not available and ConnectionFactory not found.");
        }
    }
    if (!isset($config) || !($config instanceof Config)) {
         $config = new Config();
    }

} catch (\Throwable $e) {
    error_log("Spins Sync setup error: " . $e->getMessage());
    exit("Spins Sync setup failed.");
}

$logger->info('Spins Sync job started.');

// --- API Key Governance Logic ---
$apiKey = null;
try {
    // 1. Key Retrieval: Fetch API key from apikeys table
    $keyStmt = $pdo->prepare(
        "SELECT api_key, expires_at 
         FROM apikeys 
         WHERE Type = :type AND status = 'active' 
         ORDER BY expires_at DESC LIMIT 1"
    );
    $keyStmt->execute([':type' => $ingestionApiKeyType]);
    $apiKeyData = $keyStmt->fetch(PDO::FETCH_ASSOC);

    if (!$apiKeyData) {
        throw new \RuntimeException("API Key not found or is inactive for Type: {$ingestionApiKeyType}.");
    }

    $apiKey = $apiKeyData['api_key'];
    $expiresAt = $apiKeyData['expires_at'];

    // 2. Error Handling: Validate key expiration
    if (empty($apiKey) || ( $expiresAt !== null && strtotime($expiresAt) < strtotime('now') )) {
        // Key is expired or missing
        throw new \RuntimeException("API Key for Type: {$ingestionApiKeyType} is invalid or expired.");
    }

    $logger->info("API Key retrieved and validated successfully.");

} catch (\Throwable $e) {
    $logger->critical("API Key Governance failed: " . $e->getMessage());
    // Immediately fail the job if API key is invalid or missing.
    exit("Job failed due to invalid or expired API key.");
}

// --- Spins Sync Logic ---
// The original logic for syncing spins would go here.
// This logic should now use the validated $apiKey for any external API calls.
// Example: $externalService = new ExternalSpinService($apiKey); ...

// Placeholder for the actual spins sync logic.
$logger->info("Proceeding with spins synchronization using fetched API key.");

// Example: Simulate API call with the key
// $syncResult = $externalService->syncSpins();
// if ($syncResult['success']) { ... }

$logger->info("Spins synchronization process completed (simulated).");

?>
