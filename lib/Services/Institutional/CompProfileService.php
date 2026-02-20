<?php
namespace NGN\Lib\Services\Institutional;

/**
 * Board Compensation Profile Service
 * Manages compensation, bounties, and equity vesting for the Directorate.
 * Bible Ref: BFL 3.1 // Graylight Nexus Integration
 */

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

class CompProfileService
{
    private $config;
    private $pdo;
    private $nexusPdo;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->pdo = ConnectionFactory::read($config);
        // Greyight Nexus API is pressurized via direct DB link for Board members
        $this->nexusPdo = ConnectionFactory::nexus($config);
    }

    /**
     * Fetch complete compensation profile for a Board Member
     */
    public function getCompProfile(int $userId): array
    {
        return [
            'rev_share' => $this->getRevShareData($userId),
            'bounties' => $this->getPendingBounties($userId),
            'equity' => $this->getEquityVesting($userId),
            'last_updated' => date('c')
        ];
    }

    /**
     * Variable A: Rev_Share % and cumulative "Rake" earnings
     */
    private function getRevShareData(int $userId): array
    {
        // Query Nexus for institutional rake data
        $stmt = $this->nexusPdo->prepare("
            SELECT rev_share_pct, cumulative_rake_cents 
            FROM board_member_comp 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['rev_share_pct' => 0, 'cumulative_rake_cents' => 0];

        return [
            'percentage' => (float)$data['rev_share_pct'],
            'total_earned' => $data['cumulative_rake_cents'] / 100
        ];
    }

    /**
     * Variable B: Pending "Bounties" for Event_Completion
     */
    private function getPendingBounties(int $userId): array
    {
        $stmt = $this->nexusPdo->prepare("
            SELECT COUNT(*) as count, SUM(amount_cents) as total 
            FROM pending_bounties 
            WHERE user_id = ? AND status = 'pending'
        ");
        $stmt->execute([$userId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'pending_count' => (int)$data['count'],
            'pending_value' => ($data['total'] ?? 0) / 100
        ];
    }

    /**
     * Variable C: Equity_Vesting progress
     */
    private function getEquityVesting(int $userId): array
    {
        $stmt = $this->nexusPdo->prepare("
            SELECT total_shares, vested_shares 
            FROM equity_vesting_schedules 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) return ['total' => 0, 'vested' => 0, 'percent' => 0];

        $total = (float)$data['total_shares'];
        $vested = (float)$data['vested_shares'];
        $percent = $total > 0 ? ($vested / $total) * 100 : 0;

        return [
            'total' => $total,
            'vested' => $vested,
            'unvested' => $total - $vested,
            'percent' => round($percent, 2)
        ];
    }
}
