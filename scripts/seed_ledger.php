<?php
require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Legal\ContentLedgerService;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$config = new Config();
$pdo = ConnectionFactory::write($config);
$logger = new Logger('seeder');
$logger->pushHandler(new StreamHandler(__DIR__ . '/../storage/logs/seeder.log', Logger::DEBUG));

$service = new ContentLedgerService($pdo, $config, $logger);

try {
    echo "Seeding content_ledger...
";
    
    // Seed from SMR
    $stmt = $pdo->query("SELECT * FROM smr_ingestions WHERE id = 1");
    $smr = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($smr) {
        echo "Registering SMR upload #1...
";
        $record = $service->registerContent(
            ownerId: 1,
            contentHash: $smr['file_hash'] ?? hash('sha256', 'test_content_1'),
            uploadSource: 'smr_ingestion',
            metadata: [
                'title' => 'SMR Data: ' . ($smr['filename'] ?? 'test_upload.csv'),
                'artist_name' => 'Various'
            ],
            fileInfo: [
                'size_bytes' => $smr['file_size'] ?? 1024,
                'mime_type' => 'text/csv',
                'filename' => $smr['filename'] ?? 'test_upload.csv'
            ],
            sourceRecordId: 1
        );
        echo "Created Ledger ID: {$record['id']} with Cert: {$record['certificate_id']}
";
    }

    // Seed some fake station content
    echo "Registering synthetic station content...
";
    for ($i = 2; $i <= 5; $i++) {
        $hash = hash('sha256', "content_sample_$i");
        $record = $service->registerContent(
            ownerId: 1,
            contentHash: $hash,
            uploadSource: 'station_content',
            metadata: [
                'title' => "Sample Track $i",
                'artist_name' => "NGN Artist $i",
                'credits' => ['Producer' => 'NGN Studio'],
                'rights_split' => [['user_id' => 1, 'share' => 100]]
            ],
            fileInfo: [
                'size_bytes' => rand(1000000, 10000000),
                'mime_type' => 'audio/mpeg',
                'filename' => "track_$i.mp3"
            ]
        );
        echo "Created Ledger ID: {$record['id']} with Cert: {$record['certificate_id']}
";
    }

    echo "Seeding complete.
";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "
";
}
