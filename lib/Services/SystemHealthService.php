<?php

namespace NGN\Lib\Services;

use PDO;
use Exception;

class SystemHealthService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getHealthStatus(): array
    {
        return [
            'database' => $this->checkDatabase(),
            'disk_space' => $this->checkDiskSpace(),
            'php_version' => PHP_VERSION,
            'server_load' => sys_getloadavg(),
            'memory_usage' => $this->formatBytes(memory_get_usage(true)),
            'timestamp' => date('c')
        ];
    }

    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            $this->pdo->query("SELECT 1");
            $duration = (microtime(true) - $start) * 1000;
            
            return [
                'status' => 'ok',
                'latency_ms' => round($duration, 2)
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    private function checkDiskSpace(): array
    {
        $path = __DIR__;
        $total = disk_total_space($path);
        $free = disk_free_space($path);
        $used = $total - $free;
        $percent = ($used / $total) * 100;

        return [
            'status' => $percent > 90 ? 'warn' : 'ok',
            'free_gb' => round($free / 1024 / 1024 / 1024, 2),
            'total_gb' => round($total / 1024 / 1024 / 1024, 2),
            'percent_used' => round($percent, 1)
        ];
    }

    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
