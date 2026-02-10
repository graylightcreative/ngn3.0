<?php

namespace NGN\Lib\Blockchain;

use NGN\Lib\Config;
use Monolog\Logger;
use Exception;
use PDO;

/**
 * NFTMintingService - Handles minting of ERC-721 certificates
 */
class NFTMintingService
{
    private Config $config;
    private Logger $logger;
    private PDO $pdo;
    private string $projectRoot;

    public function __construct(PDO $pdo, Config $config, Logger $logger)
    {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->logger = $logger;
        $this->projectRoot = dirname(__DIR__, 2);
    }

    /**
     * Mint a certificate for a ledger entry
     * 
     * @param int $ledgerId ID from content_ledger table
     * @param string $artistWallet Public wallet address of the artist
     * @return array
     * @throws Exception
     */
    public function mintForEntry(int $ledgerId, string $artistWallet): array
    {
        $this->logger->info('nft_minting_start', ['ledger_id' => $ledgerId, 'artist' => $artistWallet]);

        // 1. Get ledger entry data
        $stmt = $this->pdo->prepare("SELECT * FROM content_ledger WHERE id = ?");
        $stmt->execute([$ledgerId]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$entry) {
            throw new Exception("Ledger entry {$ledgerId} not found");
        }

        if ($entry['nft_status'] === 'minted') {
            return [
                'success' => true,
                'message' => 'Already minted',
                'token_id' => $entry['nft_token_id'],
                'tx_hash' => $entry['nft_tx_hash']
            ];
        }

        // 2. Prepare Metadata (In a real app, upload this to IPFS first)
        // For now, we'll create a simulated IPFS URI
        $metadata = [
            'name' => "NGN Certificate: " . ($entry['title'] ?: 'Untitled'),
            'description' => "Official digital ownership certificate for content registered on NextGenNoise.",
            'image' => "https://beta.nextgennoise.com/api/v1/certificates/" . $entry['certificate_id'] . "/image",
            'attributes' => [
                ['trait_type' => 'Content Hash', 'value' => $entry['content_hash']],
                ['trait_type' => 'Artist', 'value' => $entry['artist_name']],
                ['trait_type' => 'Registration Date', 'value' => $entry['created_at']],
                ['trait_type' => 'Certificate ID', 'value' => $entry['certificate_id']]
            ]
        ];
        
        $tokenURI = "ipfs://QmSimulatedHash" . bin2hex(random_bytes(16));

        // 3. Call Hardhat to Mint
        $network = getenv('BLOCKCHAIN_NETWORK') ?: 'amoy';
        $command = sprintf(
            'npx hardhat mint-certificate --network %s --artist %s --hash %s --uri %s 2>&1',
            escapeshellarg($network),
            escapeshellarg($artistWallet),
            escapeshellarg($entry['content_hash']),
            escapeshellarg($tokenURI)
        );

        $output = [];
        $returnVar = 0;
        
        $currentDir = getcwd();
        chdir($this->projectRoot);
        exec($command, $output, $returnVar);
        chdir($currentDir);

        $rawOutput = implode("
", $output);

        if ($returnVar !== 0) {
            $this->pdo->prepare("UPDATE content_ledger SET nft_status = 'failed' WHERE id = ?")
                ->execute([$ledgerId]);
                
            $this->logger->error('nft_minting_failed', [
                'error' => $rawOutput,
                'ledger_id' => $ledgerId
            ]);
            throw new Exception("NFT minting failed: " . $rawOutput);
        }

        // 4. Parse Result
        $json = null;
        foreach ($output as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded) && isset($decoded['success']) && $decoded['success']) {
                $json = $decoded;
                break;
            }
        }

        if (!$json) {
            throw new Exception("NFT minting returned invalid response: " . $rawOutput);
        }

        // 5. Update Database
        $updateStmt = $this->pdo->prepare("
            UPDATE content_ledger 
            SET nft_token_id = ?, nft_tx_hash = ?, nft_status = 'minted' 
            WHERE id = ?
        ");
        $updateStmt->execute([$json['token_id'], $json['tx_hash'], $ledgerId]);

        $this->logger->info('nft_minting_success', $json);

        return $json;
    }
}
