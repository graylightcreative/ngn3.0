<?php
namespace NGN\Lib\Services\Institutional;

/**
 * Director Mandate Service
 * Handles dynamic PDF content injection for Board Mandates.
 * Bible Ref: Chapter 25 (Institutional Governance)
 */

use NGN\Lib\Config;
use NGN\Lib\Services\Legal\AgreementService;
use NGN\Lib\Services\Institutional\CompProfileService;
use PDO;

class DirectorMandateService
{
    private $config;
    private $pdo;
    private $agreementSvc;
    private $compSvc;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->pdo = \NGN\Lib\DB\ConnectionFactory::read($config);
        $this->agreementSvc = new AgreementService($this->pdo);
        $this->compSvc = new CompProfileService($config);
    }

    /**
     * Generate dynamic agreement body with Compensation Schedule injected
     */
    public function getDynamicMandate(int $userId, string $slug = 'board-mandate'): string
    {
        $template = $this->agreementSvc->getTemplate($slug);
        if (!$template) return "Error: Mandate template not found.";

        $comp = $this->compSvc->getCompProfile($userId);
        
        $scheduleHtml = "
            <div class='comp-schedule-injection' style='border: 2px solid #FF5F1F; padding: 20px; margin: 20px 0;'>
                <h4 style='color: #FF5F1F; text-transform: uppercase;'>Compensation Schedule // Verified</h4>
                <ul>
                    <li><strong>Revenue Share:</strong> {$comp['rev_share']['percentage']}% of net platform rake.</li>
                    <li><strong>Cumulative Earnings:</strong> $" . number_format($comp['rev_share']['total_earned'], 2) . "</li>
                    <li><strong>Pending Bounties:</strong> {$comp['bounties']['pending_count']} events ($" . number_format($comp['bounties']['pending_value'], 2) . ")</li>
                    <li><strong>Equity Vesting:</strong> " . number_format($comp['equity']['vested'], 0) . " / " . number_format($comp['equity']['total'], 0) . " shares ({$comp['equity']['percent']}%)</li>
                </ul>
                <p style='font-size: 10px; font-style: italic;'>Data anchored to Graylight Nexus API as of " . date('Y-m-d H:i:s') . "</p>
            </div>
        ";

        // Inject before the signature line or at a specific placeholder
        $body = $template['body'];
        if (strpos($body, '[[COMPENSATION_SCHEDULE]]') !== false) {
            $body = str_replace('[[COMPENSATION_SCHEDULE]]', $scheduleHtml, $body);
        } else {
            $body .= $scheduleHtml;
        }

        return $body;
    }
}
