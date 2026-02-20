<?php

namespace NGN\Tests\Roles;

use PHPUnit\Framework\TestCase;
use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

/**
 * Professional (Producer/Mixer) Role Tests
 */
class ProfessionalTest extends TestCase
{
    private $pdo;
    private $config;

    protected function setUp(): void
    {
        $this->config = new Config();
        $this->pdo = ConnectionFactory::write($this->config);
    }

    /**
     * Story P.1: Marketplace Listing
     */
    public function testProfessionalMarketplaceListing(): void
    {
        $this->markTestSkipped("MarketplaceService not implemented.");
    }

    /**
     * Story P.2: AI Mix Feedback Disclaimer
     */
    public function testAiMixFeedbackDisclaimer(): void
    {
        $this->markTestSkipped("MixFeedbackService not implemented.");
    }
}
