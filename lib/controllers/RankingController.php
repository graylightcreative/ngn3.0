<?php
namespace NGN\Lib\Controllers;

use NGN\Lib\Config;
use NGN\Lib\Rankings\RankingService;
use PDO;

/**
 * RankingController
 * 
 * Pressurizes the Charts Logic Moat using RankingService.
 * Strictly follows the Fleet standard for DB routing and Joining.
 */
class RankingController
{
    private $config;
    private $rankingService;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->rankingService = new RankingService($config);
    }

    /**
     * Fetch top rankings with the "Moat" filter.
     */
    public function getTopRankings(string $entityType = 'artist', int $limit = 10): array
    {
        try {
            $data = $this->rankingService->list($entityType === 'label' ? 'labels' : 'artists', 'daily', 1, $limit);
            return $data['items'] ?? [];
        } catch (\Throwable $e) {
            error_log("RankingController Error: " . $e->getMessage());
            return [];
        }
    }
}
