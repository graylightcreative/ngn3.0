<?php

namespace NGN\Lib\Smr;

use PDO;
use Exception;
use DateTime;

/**
 * Power Station SMR Auto-Ingestion Service
 *
 * Manages automated data fetching and ingestion for high-volume terrestrial stations.
 * Integrates directly with external station APIs/feeds to populate NGN spin data.
 *
 * Bible Ch. 28: SMR Data Ingestion & Integrity Workflow
 */
class PowerStationIngestionService
{
    private PDO $pdo;
    private $config;

    public function __construct(PDO $pdo, $config = null)
    {
        $this->pdo = $pdo;
        $this->config = $config;
    }

    /**
     * Process all scheduled power station ingestions
     */
    public function processScheduled(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `ngn_2025`.`power_station_profiles`
            WHERE is_active = 1
              AND (next_scheduled_at IS NULL OR next_scheduled_at <= NOW())
        ");
        $stmt->execute();
        $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($profiles as $profile) {
            try {
                $results[$profile['id']] = $this->ingestFromProfile($profile);
            } catch (Exception $e) {
                $results[$profile['id']] = ['success' => false, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Ingest data for a single profile
     */
    public function ingestFromProfile(array $profile): array
    {
        $startTime = microtime(true);
        $recordsCount = 0;

        try {
            // 1. Fetch feed data
            $rawData = $this->fetchFeed($profile['feed_url'], $profile['auth_token']);
            
            // 2. Parse data
            $parsedData = $this->parseData($rawData, $profile['feed_type']);
            
            // 3. Map and Insert into station_spins
            $recordsCount = $this->insertSpins($profile['station_id'], $parsedData);

            // 4. Update profile schedule
            $this->updateSchedule($profile);

            // 5. Log success
            $duration = (int)((microtime(true) - $startTime) * 1000);
            $this->logIngestion($profile['id'], 'success', $recordsCount, null, $duration);

            return ['success' => true, 'count' => $recordsCount];
        } catch (Exception $e) {
            $duration = (int)((microtime(true) - $startTime) * 1000);
            $this->logIngestion($profile['id'], 'failed', 0, $e->getMessage(), $duration);
            throw $e;
        }
    }

    private function fetchFeed(string $url, ?string $token): string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        if ($token) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$token}"]);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Feed returned HTTP {$httpCode}");
        }

        if (empty($response)) {
            throw new Exception("Empty response from feed");
        }

        return $response;
    }

    private function parseData(string $raw, string $type): array
    {
        switch ($type) {
            case 'json':
                $data = json_decode($raw, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception("Invalid JSON data");
                }
                return $data['spins'] ?? $data; // Handle common wrappers
            
            case 'csv':
                $lines = explode("
", $raw);
                $header = str_getcsv(array_shift($lines));
                $rows = [];
                foreach ($lines as $line) {
                    if (empty(trim($line))) continue;
                    $rows[] = array_combine($header, str_getcsv($line));
                }
                return $rows;

            default:
                throw new Exception("Unsupported feed type: {$type}");
        }
    }

    private function insertSpins(int $stationId, array $data): int
    {
        $count = 0;
        
        // Prepare statement for station_spins
        // Using ngn_spins_2025 shard
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO `ngn_spins_2025`.`station_spins` (
                station_id, artist_name, track_title, isrc, 
                played_at, duration_seconds, metadata, created_at
            ) VALUES (
                :station_id, :artist, :title, :isrc,
                :played_at, :duration, :metadata, NOW()
            )
        ");

        foreach ($data as $row) {
            // Map common fields, handle variations
            $artist = $row['artist'] ?? $row['artist_name'] ?? null;
            $title = $row['title'] ?? $row['track_title'] ?? null;
            $playedAt = $row['played_at'] ?? $row['timestamp'] ?? date('Y-m-d H:i:s');
            
            if (!$artist || !$title) continue;

            $stmt->execute([
                ':station_id' => $stationId,
                ':artist' => $artist,
                ':title' => $title,
                ':isrc' => $row['isrc'] ?? null,
                ':played_at' => $playedAt,
                ':duration' => $row['duration'] ?? 0,
                ':metadata' => json_encode($row)
            ]);

            if ($stmt->rowCount() > 0) {
                $count++;
            }
        }

        return $count;
    }

    private function updateSchedule(array $profile): void
    {
        $next = new DateTime();
        $next->modify("+{$profile['ingestion_frequency_minutes']} minutes");

        $stmt = $this->pdo->prepare("
            UPDATE `ngn_2025`.`power_station_profiles`
            SET last_ingested_at = NOW(),
                next_scheduled_at = :next
            WHERE id = :id
        ");
        $stmt->execute([
            ':next' => $next->format('Y-m-d H:i:s'),
            ':id' => $profile['id']
        ]);
    }

    private function logIngestion(int $profileId, string $status, int $count, ?string $error, int $duration): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `ngn_2025`.`power_station_ingestion_logs` (
                profile_id, status, records_count, error_message, duration_ms, created_at
            ) VALUES (
                :profile_id, :status, :count, :error, :duration, NOW()
            )
        ");
        $stmt->execute([
            ':profile_id' => $profileId,
            ':status' => $status,
            ':count' => $count,
            ':error' => $error,
            ':duration' => $duration
        ]);
    }
}
