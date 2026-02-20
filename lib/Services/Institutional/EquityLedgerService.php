<?php
namespace NGN\Lib\Services\Institutional;

/**
 * Automated Shareholder Equity Ledger
 * Manages immutable cap table tracking and liquidity triggers.
 * Bible Ref: BFL 3.1
 */

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Services\Legal\GoSiggyService;
use PDO;

class EquityLedgerService
{
    private $config;
    private $pdo;
    private $gosiggy;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->pdo = ConnectionFactory::read($config);
        $this->gosiggy = new GoSiggyService($config);
    }

    /**
     * Record a new equity issuance anchored to a GoSiggy signature
     */
    public function recordIssuance(int $userId, float $shares, string $agreementContent): string
    {
        // 1. Hash the shareholder agreement via GoSiggy
        $docHash = $this->gosiggy->hashDocument($agreementContent);
        $sigId = $this->gosiggy->registerSignature($userId, $docHash, ['type' => 'equity_issuance', 'shares' => $shares]);

        // 2. Log issuance in the immutable ledger
        $stmt = $this->pdo->prepare("
            INSERT INTO institutional_equity_ledger (user_id, shares_issued, gosiggy_signature_id, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $shares, $sigId]);

        return $sigId;
    }

    /**
     * Get current cap table summary
     */
    public function getCapTable(): array
    {
        $stmt = $this->pdo->query("
            SELECT user_id, SUM(shares_issued) as total_shares 
            FROM institutional_equity_ledger 
            GROUP BY user_id
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
