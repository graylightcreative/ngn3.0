<?php
namespace NGN\Lib\Services\Infrastructure;

/**
 * Node Health Service - NGN 3.0 Advanced Infrastructure
 * Performs node-level health checks for Anycast Load Balancing.
 */

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

class NodeHealthService
{
    private $pdo;
    private $nodeId;

    public function __construct(Config $config)
    {
        $this->pdo = ConnectionFactory::read($config);
        $this->nodeId = gethostname();
    }

    /**
     * Get Comprehensive Node Health Status
     */
    public function getHealthStatus(): array
    {
        return [
            'node_id' => $this->nodeId,
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => $this->isHealthy() ? 'HEALTHY' : 'DEGRADED',
            'metrics' => [
                'cpu_load' => $this->getCPULoad(),
                'memory_usage' => $this->getMemoryUsage(),
                'disk_free' => $this->getDiskFreeSpace(),
                'db_connected' => $this->checkDatabase()
            ]
        ];
    }

    private function isHealthy(): bool
    {
        return $this->checkDatabase() && ($this->getDiskFreeSpace() > 1024 * 1024 * 100); // 100MB min
    }

    private function getCPULoad(): float
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return $load[0];
        }
        return 0.0;
    }

    private function getMemoryUsage(): float
    {
        return round(memory_get_usage(true) / 1024 / 1024, 2); // MB
    }

    private function getDiskFreeSpace(): float
    {
        return disk_free_space(__DIR__);
    }

    private function checkDatabase(): bool
    {
        try {
            return (bool)$this->pdo->query("SELECT 1");
        } catch (\Throwable $e) {
            return false;
        }
    }
}
