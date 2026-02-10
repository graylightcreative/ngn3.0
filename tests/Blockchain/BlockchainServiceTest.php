<?php

use PHPUnit\Framework\TestCase;
use NGN\Lib\Config;
use NGN\Lib\Blockchain\BlockchainService;
use Monolog\Logger;
use Monolog\Handler\NullHandler;

class BlockchainServiceTest extends TestCase
{
    private $config;
    private $logger;
    private $service;

    protected function setUp(): void
    {
        $this->config = new Config();
        $this->logger = new Logger('test');
        $this->logger->pushHandler(new NullHandler());
        $this->service = new BlockchainService($this->config, $this->logger);
        
        putenv('APP_ENV=development');
        putenv('BLOCKCHAIN_SIMULATE=true');
    }

    protected function tearDown(): void
    {
        putenv('APP_ENV');
        putenv('BLOCKCHAIN_SIMULATE');
    }

    public function testAnchorRootSimulation(): void
    {
        $root = '0x' . bin2hex(random_bytes(32));
        $result = $this->service->anchorRoot($root);

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['tx_hash']);
        $this->assertEquals($root, $result['merkle_root']);
        $this->assertIsInt($result['block_number']);
    }
}
