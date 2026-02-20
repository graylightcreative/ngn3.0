<?php

namespace NGN\Tests\Automation;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Services\Automation\WebhookService;
use PHPUnit\Framework\TestCase;
use Monolog\Logger;
use Monolog\Handler\TestHandler;
use PDO;

class WebhookServiceTest extends TestCase
{
    private PDO $pdo;
    private WebhookService $service;
    private TestHandler $logHandler;

    protected function setUp(): void
    {
        $config = new Config();
        $this->pdo = ConnectionFactory::write($config);
        
        $logger = new Logger('test');
        $this->logHandler = new TestHandler();
        $logger->pushHandler($this->logHandler);

        $this->service = new WebhookService($this->pdo, $logger);

        // Reset tables
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS=0;");
        $this->pdo->exec("TRUNCATE TABLE webhook_subscriptions");
        $this->pdo->exec("TRUNCATE TABLE webhook_delivery_logs");
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
    }

    protected function tearDown(): void
    {
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS=0;");
        $this->pdo->exec("TRUNCATE TABLE webhook_subscriptions");
        $this->pdo->exec("TRUNCATE TABLE webhook_delivery_logs");
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
    }

    public function testSubscribe_CreatesSubscription()
    {
        $userId = 1;
        $url = 'https://example.com/webhook';
        $eventType = 'test.event';

        $subId = $this->service->subscribe($userId, $url, $eventType);
        $this->assertGreaterThan(0, $subId);

        $stmt = $this->pdo->prepare("SELECT * FROM webhook_subscriptions WHERE id = ?");
        $stmt->execute([$subId]);
        $sub = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals($userId, $sub['user_id']);
        $this->assertEquals($url, $sub['target_url']);
        $this->assertNotEmpty($sub['secret_key']);
    }

    public function testDispatch_SendsWebhookToSubscriber()
    {
        // For this test, we can't make a real HTTP request easily.
        // We will verify that the delivery log is created correctly.
        // A more advanced test could use a mock HTTP client.
        
        $userId = 2;
        $url = 'https://localhost/fake-endpoint';
        $eventType = 'content.registered';

        $subId = $this->service->subscribe($userId, $url, $eventType);
        
        $payload = ['content_id' => 123, 'status' => 'live'];
        $this->service->dispatch($eventType, $payload);
        
        $stmt = $this->pdo->prepare("SELECT * FROM webhook_delivery_logs WHERE subscription_id = ?");
        $stmt->execute([$subId]);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($log);
        $this->assertEquals($subId, $log['subscription_id']);
        $this->assertEquals($eventType, $log['event_type']);
        $this->assertJsonStringEqualsJsonString(json_encode($payload), $log['payload']);
        
        // Since localhost is likely not running a receiver, expect failure
        $this->assertEquals('failed', $log['status']);
    }
}
