<?php

namespace NGN\Lib\Legal;

use PDO;
use Exception;
use NGN\Lib\Config;
use NGN\Lib\Utils\MerkleTree;
use NGN\Lib\Blockchain\BlockchainService;
use Monolog\Logger;

/**
 * BlockchainAnchoringService - Manages anchoring of ledger entries to Ethereum/Polygon
 */
class BlockchainAnchoringService
{
    private PDO $pdo;
    private Config $config;
    private Logger $logger;
    private BlockchainService $blockchain;

    public function __construct(PDO $pdo, Config $config, Logger $logger)
    {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->logger = $logger;
        $this->blockchain = new BlockchainService($config, $logger);
    }

    /**
     * Create a batch anchor for all pending ledger entries
     */
    public function anchorPendingEntries(): array
    {
        try {
            $this->pdo->beginTransaction();

            // 1. Get all entries that haven't been anchored yet
            $stmt = $this->pdo->query("
                SELECT id, content_hash 
                FROM content_ledger 
                WHERE blockchain_tx_hash IS NULL 
                FOR UPDATE
            ");
            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($entries)) {
                $this->pdo->rollBack();
                return ['success' => true, 'message' => 'No pending entries to anchor', 'count' => 0];
            }

            // 2. Generate Merkle Tree
            $hashes = array_column($entries, 'content_hash');
            $merkle = new MerkleTree($hashes);
            $root = $merkle->getRoot();

            // 3. Submit to Blockchain
            $result = $this->blockchain->anchorRoot($root);
            $txHash = $result['tx_hash'];
            $anchoredAt = date('Y-m-d H:i:s');

            // 4. Update Ledger Entries
            $ids = array_column($entries, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            
            $updateStmt = $this->pdo->prepare("
                UPDATE content_ledger 
                SET blockchain_tx_hash = ?, blockchain_anchored_at = ?, blockchain_status = 'confirmed'
                WHERE id IN ($placeholders)
            ");
            
            $params = array_merge([$txHash, $anchoredAt], $ids);
            $updateStmt->execute($params);

            $this->pdo->commit();

            $this->logger->info('blockchain_anchoring_batch_complete', [
                'merkle_root' => $root,
                'tx_hash' => $txHash,
                'entry_count' => count($entries)
            ]);

            return [
                'success' => true,
                'merkle_root' => $root,
                'tx_hash' => $txHash,
                'count' => count($entries),
                'anchored_at' => $anchoredAt
            ];

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->logger->error('blockchain_anchoring_failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get anchoring status for a specific entry
     */
    public function getStatus(int $entryId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT blockchain_tx_hash, blockchain_anchored_at 
            FROM content_ledger 
            WHERE id = ?
        ");
        $stmt->execute([$entryId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
