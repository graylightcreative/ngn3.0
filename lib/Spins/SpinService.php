<?php
namespace NGN\Lib\Spins;

use NGN\Lib\Config;
use NGN\Lib\Logging\LoggerFactory;

class SpinService
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Submit a normalized spin (no DB yet). Returns idempotency key and echo of normalized data.
     *
     * @param array $normalized Expected keys: station_id, track_id, played_at_epoch, played_at_iso, optional artist_id, notes
     * @return array
     */
    public function submit(array $normalized): array
    {
        $key = $this->idempotencyKey($normalized);
        $logger = LoggerFactory::create($this->config, 'spins');
        $logger->info('spin_submit', [
            'station_id' => $normalized['station_id'] ?? null,
            'track_id' => $normalized['track_id'] ?? null,
            'played_at' => $normalized['played_at_iso'] ?? null,
            'idem' => $key,
        ]);
        // In future, insert into DB with unique constraint on (idempotency_key)
        return [
            'accepted' => true,
            'idempotency_key' => $key,
            'spin' => $normalized,
        ];
    }

    public function idempotencyKey(array $normalized): string
    {
        $stationId = (string)($normalized['station_id'] ?? '');
        $trackId = (string)($normalized['track_id'] ?? '');
        $played = (string)($normalized['played_at_epoch'] ?? '');
        return hash('sha256', implode('|', ['spin', $stationId, $trackId, $played]));
    }
}
