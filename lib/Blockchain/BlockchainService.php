<?php

namespace NGN\Lib\Blockchain;

use NGN\Lib\Config;
use Monolog\Logger;
use Exception;

/**
 * BlockchainService - PHP wrapper for Hardhat/Ethers.js integration
 */
class BlockchainService
{
    private Config $config;
    private Logger $logger;
    private string $projectRoot;

    public function __construct(Config $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->projectRoot = dirname(__DIR__, 2);
    }

    /**
     * Submit a Merkle root to the blockchain via Hardhat script
     * 
     * @param string $merkleRoot Root hash (0x...)
     * @return array{success:bool, tx_hash:string, block_number:int, merkle_root:string}
     * @throws Exception
     */
    public function anchorRoot(string $merkleRoot): array
    {
        // Support simulation mode for development/testing
        if ($this->config->appEnv() === 'development' && getenv('BLOCKCHAIN_SIMULATE') === 'true') {
            $this->logger->info('blockchain_service_anchoring_simulated', ['merkle_root' => $merkleRoot]);
            return [
                'success' => true,
                'tx_hash' => '0x' . bin2hex(random_bytes(32)),
                'block_number' => rand(1000000, 9999999),
                'merkle_root' => $merkleRoot
            ];
        }

        $this->logger->info('blockchain_service_anchoring_start', ['merkle_root' => $merkleRoot]);

        $network = getenv('BLOCKCHAIN_NETWORK') ?: 'amoy';
        $command = sprintf(
            'npx hardhat anchor --network %s --root %s 2>&1',
            escapeshellarg($network),
            escapeshellarg($merkleRoot)
        );

        $output = [];
        $returnVar = 0;
        
        // Execute from project root
        $currentDir = getcwd();
        chdir($this->projectRoot);
        exec($command, $output, $returnVar);
        chdir($currentDir);

        $rawOutput = implode("
", $output);

        if ($returnVar !== 0) {
            $this->logger->error('blockchain_service_anchoring_failed', [
                'error' => $rawOutput,
                'merkle_root' => $merkleRoot
            ]);
            throw new Exception("Blockchain anchoring failed: " . $rawOutput);
        }

        // Find the JSON line in the output
        $json = null;
        foreach ($output as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded) && isset($decoded['success']) && $decoded['success']) {
                $json = $decoded;
                break;
            }
        }

        if (!$json) {
            $this->logger->error('blockchain_service_output_invalid', [
                'output' => $rawOutput,
                'merkle_root' => $merkleRoot
            ]);
            throw new Exception("Blockchain anchoring returned invalid response: " . $rawOutput);
        }

        $this->logger->info('blockchain_service_anchoring_success', $json);

        return $json;
    }
}
