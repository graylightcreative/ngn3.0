<?php
namespace NGN\Lib\Governance;

/**
 * DAO Governance Service - NGN 3.0 Sovereign Governance
 * Orchestrates On-Chain voting, proposals, and treasury execution.
 * Logic: Quadratic Voting (Power = sqrt(Voter Stake)).
 */

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Blockchain\BlockchainService;
use PDO;

class DAOGovernanceService
{
    private $pdo;
    private $blockchain;

    public function __construct(Config $config)
    {
        $this->pdo = ConnectionFactory::read($config);
        // $this->blockchain = new BlockchainService($config, ...);
    }

    /**
     * Submit a New Governance Proposal
     */
    public function submitProposal(int $userId, string $title, string $description, float $fundingTarget = 0): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `ngn_2025`.`dao_proposals` (user_id, title, description, funding_target_cents, status, created_at)
            VALUES (?, ?, ?, ?, 'voting', NOW())
        ");
        $stmt->execute([$userId, $title, $description, (int)($fundingTarget * 100)]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Cast a Quadratic Vote
     */
    public function castVote(int $userId, int $proposalId, bool $support): bool
    {
        $voterStake = $this->getUserStake($userId);
        $votingPower = sqrt($voterStake); // Quadratic Voting

        $stmt = $this->pdo->prepare("
            INSERT INTO `ngn_2025`.`dao_votes` (user_id, proposal_id, power, support, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([$userId, $proposalId, $votingPower, $support ? 1 : 0]);
    }

    private function getUserStake(int $userId): float
    {
        // 1. Get Total Active Investments
        $stmt = $this->pdo->prepare("SELECT SUM(amount_cents) FROM `ngn_2025`.`investments` WHERE user_id = ? AND status = 'active'");
        $stmt->execute([$userId]);
        $totalInvested = (float)($stmt->fetchColumn() ?: 0);

        // 2. Subtract Sold Equity (Secondary Market)
        $stmtSold = $this->pdo->prepare("SELECT SUM(amount_cents) FROM `ngn_2025`.`equity_market_listings` WHERE user_id = ? AND status = 'sold'");
        $stmtSold->execute([$userId]);
        $totalSold = (float)($stmtSold->fetchColumn() ?: 0);

        $netStake = $totalInvested - $totalSold;

        return $netStake > 0 ? $netStake : 1.0; // Min stake 1.0
    }
}
