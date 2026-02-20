<?php
namespace NGN\Lib\Services\Social;

/**
 * Uplink Service - Social Media Management (SMM)
 * Automates social signaling across the Graylight Fleet.
 * Bible Ref: Chapter 54 (Uplink Node)
 */

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

class UplinkService
{
    private $config;
    private $pdo;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->pdo = ConnectionFactory::read($config);
    }

    /**
     * Queue a post for automated social distribution
     */
    public function queuePost(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO social_posts (content, platform, schedule_at, status, created_at)
            VALUES (?, ?, ?, 'pending', NOW())
        ");
        
        $stmt->execute([
            $data['content'],
            $data['platform'] ?? 'all',
            $data['schedule_at'] ?? date('Y-m-d H:i:s')
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Get status of queued social posts
     */
    public function getQueueStatus(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM social_posts WHERE status != 'completed' ORDER BY schedule_at ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
