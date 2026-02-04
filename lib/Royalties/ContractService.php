<?php
namespace NGN\Lib\Royalties;

class ContractService
{
    /**
     * Deterministic mock contracts list.
     * Filters by optional labelId and/or artistId; returns array{items: array<int, array>, total:int}
     */
    public function list(?int $labelId = null, ?int $artistId = null): array
    {
        $items = [];
        // Create 10 mock contracts
        for ($i = 1; $i <= 10; $i++) {
            $row = [
                'id' => $i,
                'label_id' => ($i % 3) + 1,
                'artist_id' => $i,
                'party' => 'Artist ' . $i,
                'label' => 'Label ' . (($i % 3) + 1),
                'term' => '2025-01-01..2026-12-31',
                'territory' => 'US',
                'split_pct' => 50 + ($i % 10),
                'recoupment' => ($i % 2) === 0 ? 'recouped' : 'unrecouped',
            ];
            if ($labelId !== null && $row['label_id'] !== $labelId) continue;
            if ($artistId !== null && $row['artist_id'] !== $artistId) continue;
            $items[] = $row;
        }
        return ['items' => $items, 'total' => count($items)];
    }
}
