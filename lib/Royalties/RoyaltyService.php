<?php
namespace NGN\Lib\Royalties;

class RoyaltyService
{
    /**
     * Deterministic mock royalty statements.
     * @return array{items: array<int, array>, total:int}
     */
    public function statements(?int $labelId = null, ?int $artistId = null, ?string $period = null): array
    {
        $items = [];
        $periods = ['2025-Q1','2025-Q2','2025-Q3'];
        for ($i = 1; $i <= 12; $i++) {
            $row = [
                'id' => $i,
                'artist_id' => $i,
                'label_id' => ($i % 3) + 1,
                'period' => $periods[$i % count($periods)],
                'territory' => 'US',
                'spins' => 100 + ($i * 7),
                'royalty_amount' => round(100.0 + $i * 12.34, 2),
                'currency' => 'USD',
                'status' => ($i % 2) ? 'draft' : 'final',
            ];
            if ($labelId !== null && $row['label_id'] !== $labelId) continue;
            if ($artistId !== null && $row['artist_id'] !== $artistId) continue;
            if ($period !== null && $row['period'] !== $period) continue;
            $items[] = $row;
        }
        return ['items' => $items, 'total' => count($items)];
    }
}
