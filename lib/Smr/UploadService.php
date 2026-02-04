<?php
namespace NGN\Lib\Smr;

use NGN\Lib\Config;
use NGN\Lib\Logging\LoggerFactory;
use Monolog\Logger;

class UploadService
{
    private Config $config;
    private Logger $logger;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->logger = LoggerFactory::create($config, 'smr');
    }

    /**
     * Dev: Accept a stub JSON payload (legacy) and return an upload record shape.
     * Prefer handleMultipart() for real uploads.
     */
    public function createUpload(array $input): array
    {
        $now = time();
        $id = $this->generateId();
        $stationId = isset($input['station_id']) ? (int)$input['station_id'] : null;
        $filename = isset($input['filename']) ? (string)$input['filename'] : null;
        $size = isset($input['size_bytes']) ? (int)$input['size_bytes'] : null;

        $record = [
            'id' => $id,
            'station_id' => $stationId,
            'filename' => $filename,
            'size_bytes' => $size,
            'status' => 'received',
            'created_at' => gmdate('c', $now),
            'updated_at' => gmdate('c', $now),
        ];

        $this->logger->info('smr_upload_received', [
            'id' => $id,
            'station_id' => $stationId,
            'filename' => $filename,
            'size_bytes' => $size,
        ]);

        // Write ledger for compatibility with GET status
        $this->writeLedger($record);

        return $record;
    }

    /**
     * Handle multipart/form-data upload with $_FILES entry shape.
     * Expected $file array has keys: name, type, tmp_name, error, size
     * $fields may contain station_id.
     */
    public function handleMultipart(array $file, array $fields = []): array
    {
        $stationId = isset($fields['station_id']) ? (int)$fields['station_id'] : null;
        if ($stationId !== null && $stationId <= 0) {
            throw new \InvalidArgumentException('station_id must be a positive integer');
        }
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $code = $file['error'] ?? -1;
            throw new \RuntimeException('File upload error (code '.$code.')');
        }
        $allowed = $this->config->uploadAllowedMime();
        $maxBytes = $this->config->uploadMaxBytes();
        $mime = (string)($file['type'] ?? '');
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0) throw new \InvalidArgumentException('Empty file');
        if ($size > $maxBytes) throw new \InvalidArgumentException('File too large');
        if ($allowed && !in_array($mime, $allowed, true)) {
            throw new \InvalidArgumentException('Unsupported MIME type');
        }
        $id = $this->generateId();
        $uploadDir = rtrim($this->config->uploadDir(), '/');
        $dateDir = $uploadDir.'/'.date('Ymd');
        $ledgerDir = $uploadDir.'/ledger';
        if (!is_dir($dateDir)) @mkdir($dateDir, 0775, true);
        if (!is_dir($ledgerDir)) @mkdir($ledgerDir, 0775, true);
        $safeBase = $this->sanitizeBaseName((string)($file['name'] ?? 'upload'));
        $ext = pathinfo($safeBase, PATHINFO_EXTENSION);
        $target = $dateDir.'/'.$id.($ext ? ('.'.$ext) : '');
        if (!@move_uploaded_file($file['tmp_name'], $target)) {
            // Fallback for environments where move_uploaded_file fails in tests
            if (!@rename($file['tmp_name'], $target)) {
                throw new \RuntimeException('Failed to store uploaded file');
            }
        }
        $detector = new HeaderDetector($this->config->previewMaxRows(), $this->config->previewTimeoutMs());
        $headers = $detector->detectHeaders($target);

        // Suggestions based on detected headers
        $suggester = new MappingSuggester();
        $suggestions = isset($headers['headers']) && is_array($headers['headers']) ? $suggester->suggest($headers['headers']) : [];

        $record = [
            'id' => $id,
            'station_id' => $stationId,
            'filename' => basename($target),
            'original_name' => $safeBase,
            'mime' => $mime,
            'size_bytes' => $size,
            'path' => $target,
            'status' => 'received',
            'created_at' => gmdate('c'),
            'updated_at' => gmdate('c'),
            'header_candidates' => $headers,
            'mapping_suggestions' => $suggestions,
        ];
        $this->logger->info('smr_upload_stored', [
            'id' => $id,
            'station_id' => $stationId,
            'mime' => $mime,
            'size' => $size,
            'path' => $target,
        ]);
        $this->writeLedger($record);
        return $record;
    }

    public function getStatus(string $id): array
    {
        // If ledger exists, return it; else canned status
        $rec = $this->readLedger($id);
        if ($rec !== null) return $rec;
        return [
            'id' => $id,
            'status' => 'received',
            'progress' => 0,
            'message' => 'Processing not yet implemented',
            'created_at' => gmdate('c', time() - 60),
            'updated_at' => gmdate('c'),
            'preview_available' => false,
            'rows_count' => 0,
        ];
    }

    private function writeLedger(array $record): void
    {
        $uploadDir = rtrim($this->config->uploadDir(), '/');
        $ledgerDir = $uploadDir.'/ledger';
        if (!is_dir($ledgerDir)) @mkdir($ledgerDir, 0775, true);
        $file = $ledgerDir.'/'.($record['id'] ?? 'unknown').'.json';
        @file_put_contents($file, json_encode($record, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
    }

    private function updateLedger(string $id, callable $mutator): ?array
    {
        $rec = $this->readLedger($id);
        if ($rec === null) return null;
        $updated = $mutator($rec) ?? $rec;
        $updated['updated_at'] = gmdate('c');
        $this->writeLedger($updated);
        return $updated;
    }

    private function readLedger(string $id): ?array
    {
        $file = rtrim($this->config->uploadDir(), '/').'/ledger/'.$id.'.json';
        if (is_file($file)) {
            $json = file_get_contents($file);
            $arr = json_decode($json, true);
            if (is_array($arr)) return $arr;
        }
        return null;
    }

    /**
     * Generate a preview for an existing upload (dev-only helper).
     * Produces a preview JSON file and updates the ledger with preview fields.
     */
    public function generatePreview(string $id): array
    {
        $rec = $this->readLedger($id);
        if ($rec === null) {
            throw new \InvalidArgumentException('Unknown upload id');
        }
        $path = $rec['path'] ?? null;
        if (!$path || !is_file($path)) {
            throw new \RuntimeException('Upload file not found');
        }
        $detector = new HeaderDetector($this->config->previewMaxRows(), $this->config->previewTimeoutMs());
        $det = $detector->detectHeaders($path);
        $preview = [
            'headers' => $det['headers'] ?? [],
            'sample_rows' => $det['sample_rows'] ?? [],
        ];
        $uploadDir = rtrim($this->config->uploadDir(), '/');
        $previewDir = $uploadDir.'/previews';
        if (!is_dir($previewDir)) @mkdir($previewDir, 0775, true);
        $previewPath = $previewDir.'/'.$id.'.json';
        @file_put_contents($previewPath, json_encode($preview, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));

        $rowsCount = is_array($preview['sample_rows']) ? count($preview['sample_rows']) : 0;
        $updated = $this->updateLedger($id, function(array $r) use ($previewPath, $rowsCount) {
            $r['preview_path'] = $previewPath;
            $r['rows_count'] = $rowsCount;
            $r['progress'] = 100;
            $r['status'] = 'preview_ready';
            $r['preview_available'] = true;
            return $r;
        });
        return $updated ?? $rec;
    }

    private function generateId(): string
    {
        // Simple ksuid-like: date + random
        $rand = bin2hex(random_bytes(6));
        return 'upl_'.date('YmdHis')."_$rand";
    }

    private function sanitizeBaseName(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
        return substr($name, 0, 200) ?: 'upload';
    }
}
