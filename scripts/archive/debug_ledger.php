<?php
require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Legal\ContentLedgerService;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$config = new Config();
$pdo = ConnectionFactory::write($config);
$logger = new Logger('debug_ledger');

$service = new ContentLedgerService($pdo, $config, $logger);

try {
    echo "DB Config: " . json_encode($config->db()) . "
";
    $result = $service->getList(5, 0);
    echo "Ledger Result: " . json_encode($result) . "
";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM content_ledger");
    echo "Direct PDO Count: " . $stmt->fetchColumn() . "
";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "
";
}
