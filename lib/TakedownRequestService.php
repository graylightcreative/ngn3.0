<?php

namespace NGN\Lib;

use NGN\Lib\Config;
use NGN\Lib\Logging\LoggerFactory;
use PDO;
use PDOException;

class TakedownRequestService
{
    private $config;
    private $logger;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->logger = LoggerFactory::create($this->config, 'dmca_takedown');
    }

    public function createTakedownRequest(array $requestData): void
    {
        // Get DB connection
        $pdo = \NGN\Lib\DB\ConnectionFactory::write($this->config);

        $stmt = $pdo->prepare("INSERT INTO `takedown_requests` (content_id, content_type, reason) VALUES (?, ?, ?)");
        $stmt->execute([$requestData['content_id'], $requestData['content_type'], $requestData['reason']]);
        $takedownId = $pdo->lastInsertId();

        $this->logger->info('takedown_request_created', ['takedown_id' => $takedownId, 'request_data' => $requestData]);
    }

    public function getTakedownRequest(int $requestId): ?array
    {
        // Get DB connection
        $dbHost = Env::get('DEV_DB_SERVER', Env::get('DB_SERVER', 'localhost'));
        $dbUser = Env::get('DEV_DB_USER', Env::get('DB_USER', ''));
        $dbPass = Env::get('DEV_DB_PASS', Env::get('DB_PASS', ''));
        $dbName = Env::get('DEV_DB_NAME', Env::get('DB_NAME', 'nextgennoise'));
        $pdo = new \PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $stmt = $pdo->prepare("SELECT * FROM `takedown_requests` WHERE id = ?");
        $stmt->execute([$requestId]);
        return $stmt->fetch() ?: null;
    }

    public function listTakedownRequests(): array
    {
        error_log('TakedownRequestService::listTakedownRequests() called');
        // Get DB connection
        $dbHost = Env::get('DEV_DB_SERVER', Env::get('DB_SERVER', 'localhost'));
        $dbUser = Env::get('DEV_DB_USER', Env::get('DB_USER', ''));
        $dbPass = Env::get('DEV_DB_PASS', Env::get('DB_PASS', ''));
        $dbName = Env::get('DEV_DB_NAME', Env::get('DB_NAME', 'nextgennoise'));
        $pdo = new \PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        error_log('TakedownRequestService::listTakedownRequests() preparing query');
        $stmt = $pdo->prepare("SELECT * FROM `takedown_requests` ORDER BY created_at DESC");
        error_log('TakedownRequestService::listTakedownRequests() executing query');
        $stmt->execute();
        error_log('TakedownRequestService::listTakedownRequests() fetching results');
        $result = $stmt->fetchAll() ?: [];
        error_log('TakedownRequestService::listTakedownRequests() result count: ' . count($result));
        return $result;
    }

    public function processTakedownRequest(int $requestId): void
    {
        $this->logger->info('takedown_request_processed', ['request_id' => $requestId]);
    }
}