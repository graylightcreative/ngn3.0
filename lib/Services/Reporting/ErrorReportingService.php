<?php
namespace NGN\Lib\Services\Reporting;

/**
 * Error Reporting Service - NGN 3.0 Sovereignty
 * Centralized error aggregation and prioritized alerting.
 * Bible Ref: Chapter 12 - System Integrity & Monitoring.
 */

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Logging\LoggerFactory;
use PDO;

class ErrorReportingService
{
    private $pdo;
    private $logger;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->pdo = ConnectionFactory::write($config);
        $this->logger = LoggerFactory::create($config, 'error_terminal');
    }

    /**
     * Capture and Prioritize Error
     */
    public function capture(\Throwable $e, string $severity = 'ERROR', array $context = []): void
    {
        $payload = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'context' => $context
        ];

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO `ngn_2025`.`system_errors` (severity, message, payload, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$severity, $e->getMessage(), json_encode($payload)]);
        } catch (\Throwable $dbError) {
            // Fallback to local logger if DB is down
            $this->logger->critical("Critical System Error (DB LOG FAILED): " . $e->getMessage(), $payload);
        }
    }

    /**
     * Get High Priority Alerts
     */
    public function getActiveAlerts(int $limit = 10): array
    {
        return $this->pdo->query("
            SELECT * FROM `ngn_2025`.`system_errors` 
            WHERE status = 'active' 
            ORDER BY created_at DESC 
            LIMIT {$limit}
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
