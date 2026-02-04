<?php

use NGN\Lib\TakedownRequestService;
use NGN\Lib\Config;
use PHPUnit\Framework\TestCase;

class TakedownRequestServiceTest extends TestCase
{
    public function testCreateTakedownRequest(): void
    {
        $config = new Config();
        $takedownSvc = new TakedownRequestService($config);
        $requestData = ['content_id' => 1, 'reason' => 'Copyright infringement'];
        $takedownSvc->createTakedownRequest($requestData);
        $this->assertTrue(true, 'Logging should not fail');
    }

    public function testProcessTakedownRequest(): void
    {
        $config = new Config();
        $takedownSvc = new TakedownRequestService($config);
        $takedownSvc->processTakedownRequest(1);
        $this->assertTrue(true, 'Logging should not fail');
    }
}
