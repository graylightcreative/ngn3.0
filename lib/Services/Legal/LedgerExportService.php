<?php

namespace NGN\Lib\Services\Legal;

use NGN\Lib\Legal\ContentLedgerService;
use Exception;
use RuntimeException;

/**
 * LedgerExportService
 * 
 * Handles exporting content ledger data to various formats (CSV, JSON).
 * Production feature for Version 2.1.
 */
class LedgerExportService
{
    private ContentLedgerService $ledgerService;

    public function __construct(ContentLedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

    /**
     * Export ledger to CSV string
     */
    public function exportToCsv(int $limit = 1000, int $offset = 0, ?int $ownerId = null): string
    {
        $data = $this->ledgerService->getList($limit, $offset, $ownerId);
        $items = $data['items'];

        if (empty($items)) {
            return "No records found";
        }

        $output = fopen('php://temp', 'r+');
        
        // Add Header
        fputcsv($output, array_keys($items[0]));

        // Add Data
        foreach ($items as $item) {
            fputcsv($output, $item);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Export ledger to JSON string
     */
    public function exportToJson(int $limit = 1000, int $offset = 0, ?int $ownerId = null): string
    {
        $data = $this->ledgerService->getList($limit, $offset, $ownerId);
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
