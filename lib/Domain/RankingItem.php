<?php
namespace NGN\Domain;

class RankingItem
{
    public int $entityId;
    public int $rank;
    public float $score;
    public string $name;
    public string $interval;

    public function __construct(int $entityId, int $rank, float $score, string $name, string $interval)
    {
        $this->entityId = $entityId;
        $this->rank = $rank;
        $this->score = $score;
        $this->name = $name;
        $this->interval = $interval;
    }

    public static function fromArray(array $row, string $interval): self
    {
        $id = (int)($row['artist_id'] ?? $row['label_id'] ?? $row['id'] ?? 0);
        $rank = (int)($row['rank'] ?? 0);
        $score = (float)($row['score'] ?? 0);
        $name = trim((string)($row['name'] ?? ($row['title'] ?? '')));
        return new self($id, $rank, $score, $name, $interval);
    }
}
