<?php
namespace NGN\Lib\Services\Advertiser;

/**
 * Foundry Global Ad-Serving Engine (Self-Serve)
 * Manages active ad creative and programmatic delivery.
 * Bible Ref: Chapter 18.5
 */

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

class SelfServeAdService
{
    private $config;
    private $pdo;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->pdo = ConnectionFactory::read($config);
    }

    /**
     * Get an available ad for a specific placement
     */
    public function getAdForPlacement(string $placement, array $context = []): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM ads 
            WHERE placement = ? 
            AND status = 'active' 
            AND (start_date IS NULL OR start_date <= NOW())
            AND (end_date IS NULL OR end_date >= NOW())
            ORDER BY RAND() LIMIT 1
        ");
        $stmt->execute([$placement]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Log an ad event (impression or click)
     */
    public function logAdEvent(int $adId, string $type, array $metadata = []): bool
    {
        $write = ConnectionFactory::write($this->config);
        $stmt = $write->prepare("
            INSERT INTO ad_event_logs (ad_id, event_type, metadata, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        
        return $stmt->execute([
            $adId,
            $type,
            json_encode($metadata)
        ]);
    }
}
