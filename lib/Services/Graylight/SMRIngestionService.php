<?php

namespace NGN\Lib\Services\Graylight;

use NGN\Lib\Config;
use PDO;
use Exception;
use DateTime;

/**
 * SMRIngestionService
 * 
 * Handles mapping and ingestion of Erik Baker's "Top 200" SMR Archive files.
 * Pushes data to Graylight Ingest Node.
 */
class SMRIngestionService
{
    private PDO $pdo;
    private Config $config;
    private GraylightServiceClient $glClient;

    public function __construct(PDO $pdo, Config $config, GraylightServiceClient $glClient)
    {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->glClient = $glClient;
    }

    /**
     * Ingest and push a single SMR Archive CSV file to the Graylight Vault
     * 
     * @param string $filePath Path to the CSV file
     * @return array Ingestion result
     * @throws Exception
     */
    public function push(string $filePath): array
    {
        $filename = basename($filePath);
        
        // 1. Temporal Anchoring - Extract Week and Year from filename
        $temporalData = $this->extractTemporalData($filename);
        $spinAt = $this->calculateSpinAt($temporalData['week'], $temporalData['year']);

        // 2. Parse CSV and Map Columns
        $rows = $this->parseAndMapCsv($filePath, $spinAt);

        // 3. Store Locally for Matching (Optional but recommended for CDM_Match)
        $this->storeLocally($filename, $rows);

        // 4. Prepare Payload for Graylight
        $payload = [
            'namespace' => 'NGN_SMR_DUMP',
            'schema_version' => 'v1.1.0',
            'metadata' => [
                'report_week' => $temporalData['week'],
                'report_year' => $temporalData['year'],
                'source' => 'Erik_Baker_Archive',
                'integrity_check' => 'pre_push',
                'filename' => $filename
            ],
            'data' => $rows
        ];

        // 5. The Push to Graylight
        $result = $this->glClient->call('ingest/push', $payload);

        if (!isset($result['success']) || !$result['success']) {
            throw new Exception("Graylight Ingestion Failed: " . ($result['message'] ?? 'unknown_error'));
        }

        return array_merge($result, [
            'report_date' => $spinAt,
            'week' => $temporalData['week'],
            'year' => $temporalData['year']
        ]);
    }

    /**
     * Store records in local smr_records table for CDM_Match
     */
    private function storeLocally(string $filename, array $rows): void
    {
        // Create an ingestion record if not exists or use filename
        $stmt = $this->pdo->prepare("
            INSERT INTO smr_ingestions (filename, status, created_at)
            VALUES (?, 'pending_review', NOW())
        ");
        $stmt->execute([$filename]);
        $ingestionId = $this->pdo->lastInsertId();

        $recordStmt = $this->pdo->prepare("
            INSERT INTO smr_records (
                ingestion_id, artist_name, track_title, spin_count, reach_count, last_week_spin_count, status
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending_mapping')
        ");

        foreach ($rows as $row) {
            $recordStmt->execute([
                $ingestionId,
                $row['raw_artist_name'],
                $row['raw_track_title'],
                $row['spin_count'],
                $row['reach_count'],
                $row['last_week_spin_count']
            ]);
        }
    }

    /**
     * Extract Week and Year from the filename
     */
    private function extractTemporalData(string $filename): array
    {
        // Pattern: SMR TOP 50 CHART Master Week [Week]-[Year].xlsx - [Week]-[Year] Top 200.csv
        // Extract from the suffix as per directive
        if (preg_match('/ - (\d{1,2})-(\d{4}) Top 200/i', $filename, $matches)) {
            return [
                'week' => (int)$matches[1],
                'year' => (int)$matches[2]
            ];
        }
        
        throw new Exception("Could not extract temporal data from filename: $filename");
    }

    /**
     * Calculate ISO-8601 Monday of the given week/year
     */
    private function calculateSpinAt(int $week, int $year): string
    {
        $dto = new DateTime();
        $dto->setISODate($year, $week);
        return $dto->format('c'); // ISO-8601
    }

    /**
     * Parse source CSV and map to CDM
     */
    private function parseAndMapCsv(string $filePath, string $spinAt): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) throw new Exception("Cannot open file: $filePath");

        $header = fgetcsv($handle);
        if (!$header) throw new Exception("Empty CSV: $filePath");

        // Map column names to indexes
        $headerTrimmed = array_map('trim', $header);
        $map = array_flip($headerTrimmed);
        
        $required = ['ARTIST', 'TITLE', 'TW SPIN', 'LW SPIN', 'STATIONS ON', 'LABEL'];
        foreach ($required as $col) {
            if (!isset($map[$col])) {
                $found = implode(', ', $headerTrimmed);
                throw new Exception("Missing required column: $col. Found: [$found]");
            }
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (empty(array_filter($row))) continue;

            // STATIONS ON Logic: "32 of 34" -> 32
            $stationsRaw = $row[$map['STATIONS ON']] ?? '0';
            $reachCount = (int)preg_replace('/[^0-9].*$/', '', $stationsRaw);

            $rows[] = [
                'raw_artist_name' => $row[$map['ARTIST']] ?? '',
                'raw_track_title' => $row[$map['TITLE']] ?? '',
                'spin_count' => (int)($row[$map['TW SPIN']] ?? 0),
                'last_week_spin_count' => (int)($row[$map['LW SPIN']] ?? 0),
                'rank_position' => (int)($row[$map['TW POS']] ?? 0), // Include if exists
                'reach_count' => $reachCount,
                'raw_label_name' => $row[$map['LABEL']] ?? '',
                'spin_at' => $spinAt
            ];
        }

        fclose($handle);
        return $rows;
    }
}
