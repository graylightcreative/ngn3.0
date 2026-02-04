<?php

namespace NGN\Lib\Royalties;

use NGN\Lib\Config;
use NGN\Lib\Logging\LoggerFactory;

class LegalLoggingService
{
    private $config;
    private $logger;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->logger = LoggerFactory::create($this->config, 'legal_logging');
    }

    public function logRoyaltyStatement(array $statementData):
void
    {
        $this->logger->info('royalty_statement', $statementData);
    }

    public function logContract(array $contractData):
void
    {
        $this->logger->info('contract', $contractData);
    }
}
