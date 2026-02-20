<?php
namespace NGN\Lib\Services\Hardware;

/**
 * Hardware Beacon Handshake Service (Node 48)
 * Manages secure connection between NGN and physical venue hardware.
 * Bible Ref: Chapter 48 (NGN Verified Venues)
 */

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

class BeaconHandshakeService
{
    private $config;
    private $pdo;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->pdo = ConnectionFactory::read($config);
    }

    /**
     * Verify a hardware beacon handshake
     */
    public function verifyHandshake(string $beaconId, string $signature): bool
    {
        $stmt = $this->pdo->prepare("SELECT secret_key FROM venue_beacons WHERE beacon_id = ? AND status = 'active'");
        $stmt->execute([$beaconId]);
        $secret = $stmt->fetchColumn();

        if (!$secret) return false;

        // Verify HMAC signature
        $expected = hash_hmac('sha256', $beaconId, $secret);
        return hash_equals($expected, $signature);
    }

    /**
     * Log a beacon heartbeat (Pulse)
     */
    public function logPulse(string $beaconId, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO beacon_pulses (beacon_id, pulse_data, created_at)
            VALUES (?, ?, NOW())
        ");
        
        return $stmt->execute([
            $beaconId,
            json_encode($data)
        ]);
    }
}
