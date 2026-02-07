<?php

namespace NGN\Lib\Utils;

/**
 * MerkleTree - Utility for generating Merkle roots and proofs
 * 
 * Used for batching ledger entries for blockchain anchoring.
 */
class MerkleTree
{
    private array $leaves;
    private array $tree;

    public function __construct(array $data)
    {
        // Sort data to ensure deterministic roots
        sort($data);
        
        // Hash all leaves
        $this->leaves = array_map(fn($item) => hash('sha256', $item), $data);
        $this->tree = [$this->leaves];
        $this->buildTree();
    }

    private function buildTree(): void
    {
        $currentLevel = $this->leaves;
        
        while (count($currentLevel) > 1) {
            $nextLevel = [];
            
            // If odd number of nodes, duplicate the last one
            if (count($currentLevel) % 2 !== 0) {
                $currentLevel[] = end($currentLevel);
            }
            
            for ($i = 0; $i < count($currentLevel); $i += 2) {
                $left = $currentLevel[$i];
                $right = $currentLevel[$i + 1];
                
                // Sort pair to ensure deterministic hash (standard Merkle)
                $pair = [$left, $right];
                sort($pair);
                
                $nextLevel[] = hash('sha256', $pair[0] . $pair[1]);
            }
            
            $this->tree[] = $nextLevel;
            $currentLevel = $nextLevel;
        }
    }

    public function getRoot(): ?string
    {
        if (empty($this->leaves)) return null;
        return end($this->tree)[0];
    }

    public function getLeaves(): array
    {
        return $this->leaves;
    }
}
