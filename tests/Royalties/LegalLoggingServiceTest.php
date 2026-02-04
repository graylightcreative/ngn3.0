<?php

use NGN\Lib\Royalties\LegalLoggingService;
use NGN\Lib\Config;
use PHPUnit\Framework\TestCase;

class LegalLoggingServiceTest extends TestCase
{
    public function testLogRoyaltyStatement(): void
    {
        $config = new Config();
        $loggingSvc = new LegalLoggingService($config);
        $statementData = ['statement_id' => 1, 'artist_id' => 123, 'amount' => 100];
        $loggingSvc->logRoyaltyStatement($statementData);
        $this->assertTrue(true, 'Logging should not fail');
    }

    public function testLogContract(): void
    {
        $config = new Config();
        $loggingSvc = new LegalLoggingService($config);
        $contractData = ['contract_id' => 1, 'artist_id' => 123, 'label_id' => 456];
        $loggingSvc->logContract($contractData);
        $this->assertTrue(true, 'Logging should not fail');
    }
}
