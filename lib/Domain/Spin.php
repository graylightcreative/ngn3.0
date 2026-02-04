<?php
namespace NGN\Domain;

class Spin
{
    public int $id;
    public int $stationId;
    public int $trackId;
    public ?string $playedAt;

    public function __construct(int $id, int $stationId, int $trackId, ?string $playedAt)
    {
        $this->id = $id;
        $this->stationId = $stationId;
        $this->trackId = $trackId;
        $this->playedAt = $playedAt;
    }

    public static function fromArray(array $row): self
    {
        $id = (int)($row['id'] ?? 0);
        $stationId = (int)($row['station_id'] ?? 0);
        $trackId = (int)($row['track_id'] ?? 0);
        $playedAt = isset($row['played_at']) ? (string)$row['played_at'] : null;
        return new self($id, $stationId, $trackId, $playedAt);
    }
}
