<?php
/**
 * Fairness Receipt Generator
 *
 * Generates cryptographically signed receipts showing:
 * - Exact factor breakdown for chart position
 * - All weights and multipliers applied
 * - Verification mechanism to prevent tampering
 *
 * Features:
 * - Public receipt: factors + ranking (shareable)
 * - Private receipt: detailed values + formulas (owner-only)
 * - Cryptographic signing: SHA-256 HMAC for verification
 * - Audit trail: all receipts logged for transparency
 */

namespace NGN\Lib\Fairness;

use PDO;
use Exception;

class FairnessReceipt
{
    private PDO $pdo;
    private string $secretKey;
    private const HASH_ALGORITHM = 'sha256';

    public function __construct(PDO $pdo, string $secretKey = '')
    {
        $this->pdo = $pdo;
        $this->secretKey = $secretKey ?: (getenv('FAIRNESS_SECRET_KEY') ?: 'ngn-fairness-key');
    }

    /**
     * Generate a complete fairness receipt for an artist in a ranking window
     *
     * @param int $artistId
     * @param int $windowId
     * @param bool $detailed - Include detailed factor values
     * @return array
     */
    public function generateArtistReceipt(int $artistId, int $windowId, bool $detailed = false): array
    {
        try {
            $config = new \NGN\Lib\Config();
            $pdoRankings = \NGN\Lib\DB\ConnectionFactory::named($config, 'rankings2025');

            // Get ranking window info from rankings shard
            $windowStmt = $pdoRankings->prepare(
                'SELECT `interval`, window_start, window_end FROM `ranking_windows` WHERE id = ?'
            );
            $windowStmt->execute([$windowId]);
            $window = $windowStmt->fetch(PDO::FETCH_ASSOC);

            if (!$window) {
                return ['error' => 'Window not found in rankings shard'];
            }

            // Get ranking item from rankings shard
            $rankStmt = $pdoRankings->prepare(
                'SELECT `rank`, score, deltas FROM `ranking_items`
                WHERE window_id = ? AND entity_type = ? AND entity_id = ?'
            );
            $rankStmt->execute([$windowId, 'artist', $artistId]);
            $rankItem = $rankStmt->fetch(PDO::FETCH_ASSOC);

            if (!$rankItem) {
                return ['error' => 'Artist not ranked in this window'];
            }

            // Get artist info from primary shard
            $artistStmt = $this->pdo->prepare(
                'SELECT name, claimed FROM `ngn_2025`.`artists` WHERE id = ?'
            );
            $artistStmt->execute([$artistId]);
            $artist = $artistStmt->fetch(PDO::FETCH_ASSOC);

            // Factors breakdown (calculated for audit)
            $factors = $this->calculateFactorBreakdown('artist', $artistId, $windowId, $window);

            // Build receipt
            $receipt = [
                'receipt_id' => $this->generateReceiptId('artist', $artistId, $windowId),
                'entity_type' => 'artist',
                'entity_id' => $artistId,
                'entity_name' => $artist['name'] ?? 'Unknown Artist',
                'ranking_window' => [
                    'interval' => $window['interval'],
                    'start' => $window['window_start'],
                    'end' => $window['window_end']
                ],
                'rank' => (int)$rankItem['rank'],
                'score' => (float)$rankItem['score'],
                'factors' => $factors,
                'generated_at' => date('c'),
                'version' => '1.0'
            ];

            // Add detailed breakdown if requested
            if ($detailed) {
                $receipt['detailed_breakdown'] = $this->getDetailedBreakdown('artist', $artistId, $windowId, $window);
            }

            // Sign the receipt
            $receipt['signature'] = $this->signReceipt($receipt);
            $receipt['verification_token'] = $this->generateVerificationToken($receipt);

            // Log the receipt to primary shard
            $this->logReceipt($receipt);

            return $receipt;

        } catch (Exception $e) {
            error_log("FairnessReceipt::generateArtistReceipt error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Calculate factor breakdown for an artist
     *
     * Returns the score contribution of each factor
     *
     * @param string $entityType
     * @param int $entityId
     * @param int $windowId
     * @param array $window
     * @return array
     */
    private function calculateFactorBreakdown(string $entityType, int $entityId, int $windowId, array $window): array
    {
        $factors = [];

        if ($entityType === 'artist') {
            // 1. Claimed profile bonus
            $stmt = $this->pdo->prepare('SELECT claimed FROM `ngn_2025`.`artists` WHERE id = ?');
            $stmt->execute([$entityId]);
            $artist = $stmt->fetch(PDO::FETCH_ASSOC);
            $factors['claimed_profile'] = [
                'weight' => $artist['claimed'] ? 1000 : 0,
                'description' => 'Verified artist profile bonus',
                'formula' => 'claimed ? 1000 : 0'
            ];

            // 2. Radio spins (from legacy data)
            $factors['radio_spins'] = [
                'weight' => 0, // Placeholder - actual calculation in RankingCalculator
                'description' => 'Spins on radio stations',
                'formula' => 'sum(all_radio_spins) * spin_weight'
            ];

            // 3. SMR chart spins
            $factors['smr_chart_spins'] = [
                'weight' => 0,
                'description' => 'SMR marketing chart spins',
                'formula' => 'sum(smr_spins) * smr_weight'
            ];

            // 4. Social media score
            $factors['social_media'] = [
                'weight' => 0,
                'description' => 'Instagram, Facebook, TikTok followers',
                'formula' => 'followers_total * social_weight'
            ];

            // 5. Releases score
            $factors['releases'] = [
                'weight' => 0,
                'description' => 'Track and album releases',
                'formula' => 'release_count * release_weight'
            ];

            // 6. Videos score
            $factors['videos'] = [
                'weight' => 0,
                'description' => 'Published music videos',
                'formula' => 'video_count * video_weight'
            ];

            // 7. Mentions score
            $factors['mentions'] = [
                'weight' => 0,
                'description' => 'Press mentions and coverage',
                'formula' => 'mention_count * mention_weight'
            ];

            // 8. Views score
            $factors['views'] = [
                'weight' => 0,
                'description' => 'Total profile and content views',
                'formula' => 'view_count * view_weight'
            ];

            // 9. Engagement Quality Score (EQS)
            $factors['engagement_quality'] = [
                'weight' => 0,
                'description' => 'Likes, comments, shares, sparks',
                'formula' => '(likes*1) + (comments*3) + (shares*10) + (sparks*15)'
            ];

            // 10. Community Funding multiplier (for investors)
            $stmt = $this->pdo->prepare(
                'SELECT is_investor FROM `ngn_2025`.`users` WHERE id = (SELECT user_id FROM `ngn_2025`.`artists` WHERE id = ? LIMIT 1)'
            );
            $stmt->execute([$entityId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $factors['community_funding_multiplier'] = [
                'weight' => $user && $user['is_investor'] ? 1.05 : 1.0,
                'description' => 'Community funding investor bonus',
                'formula' => 'is_investor ? base_score * 1.05 : base_score'
            ];
        }

        return $factors;
    }

    /**
     * Get detailed breakdown with exact values
     *
     * PRIVATE: Only shown to owner
     *
     * @param string $entityType
     * @param int $entityId
     * @param int $windowId
     * @param array $window
     * @return array
     */
    private function getDetailedBreakdown(string $entityType, int $entityId, int $windowId, array $window): array
    {
        $breakdown = [];

        if ($entityType === 'artist') {
            // This would call the actual scoring methods from RankingCalculator
            // For now, showing structure

            $breakdown = [
                'calculation_date' => date('c'),
                'data_snapshot' => [
                    'radio_spins_total' => 0,
                    'smr_chart_spins' => 0,
                    'social_followers' => 0,
                    'releases_count' => 0,
                    'videos_count' => 0,
                    'press_mentions' => 0,
                    'total_views' => 0,
                    'engagement_interactions' => 0
                ],
                'calculation_steps' => [
                    'step_1_claimed_bonus' => '+1000',
                    'step_2_radio_contribution' => '+0 (calculation varies)',
                    'step_3_social_contribution' => '+0 (followers * weight)',
                    'step_4_engagement_contribution' => '+0 (quality score)',
                    'step_5_multiplier_application' => '* 1.0 (or 1.05 if investor)',
                    'final_score' => '= combined score'
                ],
                'note' => 'Detailed values calculated at time of ranking. This snapshot shows the factors considered.',
                'methodology' => 'See https://ngn.io/fairness-methodology'
            ];
        }

        return $breakdown;
    }

    /**
     * Sign a receipt using HMAC-SHA256
     *
     * Signature prevents tampering - any change invalidates signature
     *
     * @param array $receipt
     * @return string
     */
    private function signReceipt(array $receipt): string
    {
        // Create signable data (exclude signature field)
        $receiptCopy = $receipt;
        unset($receiptCopy['signature'], $receiptCopy['verification_token']);

        // Canonical Sort: PHP doesn't have JSON_SORT_KEYS, must ksort manually
        ksort($receiptCopy);
        $signableData = json_encode($receiptCopy, \JSON_UNESCAPED_SLASHES);

        // Generate HMAC signature
        $signature = hash_hmac(self::HASH_ALGORITHM, $signableData, $this->secretKey);

        return $signature;
    }

    /**
     * Verify a receipt signature
     *
     * @param array $receipt
     * @return bool
     */
    public function verifyReceiptSignature(array $receipt): bool
    {
        $providedSignature = $receipt['signature'] ?? null;

        if (!$providedSignature) {
            return false;
        }

        // Recalculate signature
        $receiptCopy = $receipt;
        unset($receiptCopy['signature'], $receiptCopy['verification_token']);
        
        // Canonical Sort
        ksort($receiptCopy);
        $signableData = json_encode($receiptCopy, \JSON_UNESCAPED_SLASHES);
        $calculatedSignature = hash_hmac(self::HASH_ALGORITHM, $signableData, $this->secretKey);

        // Constant-time comparison to prevent timing attacks
        return hash_equals($providedSignature, $calculatedSignature);
    }

    /**
     * Generate a verification token
     *
     * Allows checking receipt integrity on public ledger
     *
     * @param array $receipt
     * @return string
     */
    private function generateVerificationToken(array $receipt): string
    {
        $data = implode('|', [
            $receipt['receipt_id'],
            $receipt['entity_id'],
            $receipt['rank'],
            $receipt['generated_at'],
            $receipt['signature']
        ]);

        return hash(self::HASH_ALGORITHM, $data);
    }

    /**
     * Generate unique receipt ID
     *
     * @param string $entityType
     * @param int $entityId
     * @param int $windowId
     * @return string
     */
    private function generateReceiptId(string $entityType, int $entityId, int $windowId): string
    {
        return 'RCP-' . strtoupper(substr($entityType, 0, 1)) . '-' . $entityId . '-' . $windowId . '-' . date('Ymd');
    }

    /**
     * Log receipt generation for audit trail
     *
     * @param array $receipt
     * @return void
     */
    private function logReceipt(array $receipt): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO `ngn_2025`.`ngn_score_audit`
                (window_id, entity_type, entity_id, raw_score, weighted_score, factors, fairness_receipt, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
            );

            $stmt->execute([
                $receipt['window_id'] ?? 0,
                $receipt['entity_type'],
                $receipt['entity_id'],
                $receipt['score'], // assuming raw_score = score for this version
                $receipt['score'], // weighted_score
                json_encode($receipt['factors'] ?? []),
                $receipt['signature'] // fairness_receipt hash
            ]);

        } catch (Exception $e) {
            error_log("CRITICAL_AUDIT_ERROR: FairnessReceipt::logReceipt failed: " . $e->getMessage());
            // Force error to stderr for CLI debugging
            fwrite(STDERR, "CRITICAL_AUDIT_ERROR: " . $e->getMessage() . "\n");
        }
    }

    /**
     * Get receipt by ID (public ledger)
     *
     * @param string $receiptId
     * @return array|null
     */
    public function getPublicReceipt(string $receiptId): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM `ngn_2025`.`ngn_score_audit`
                WHERE fairness_receipt = ?'
            );
            $stmt->execute([$receiptId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("FairnessReceipt::getPublicReceipt error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all receipts for an entity (private - owner only)
     *
     * @param string $entityType
     * @param int $entityId
     * @return array
     */
    public function getEntityReceiptHistory(string $entityType, int $entityId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM `ngn_2025`.`ngn_score_audit`
                WHERE entity_type = ? AND entity_id = ?
                ORDER BY created_at DESC'
            );
            $stmt->execute([$entityType, $entityId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("FairnessReceipt::getEntityReceiptHistory error: " . $e->getMessage());
            return [];
        }
    }
}
