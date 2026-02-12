<?php

/**
 * extract-workbook-sheets.php - Extract sheets from Master SMR XLSX to CSV
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

ini_set('memory_limit', '1G');

echo "📂 SMR Workbook Extractor (LoadSheetsOnly)\n";
echo "============================================\n";

$inputFile = __DIR__ . '/../storage/uploads/20260210/SMR TOP 50 CHART Master Week 13-2025.xlsx';
$outputDir = __DIR__ . '/../storage/archives/smr';

if (!file_exists($inputFile)) {
    echo "❌ Error: Master workbook not found at $inputFile\n";
    exit(1);
}

try {
    echo "Discovering sheet names...\n";
    $reader = IOFactory::createReader('Xlsx');
    $sheetNames = $reader->listWorksheetNames($inputFile);
    echo "Found " . count($sheetNames) . " sheets.\n";

    foreach ($sheetNames as $sheetName) {
        if (strpos($sheetName, 'Sheet') === 0 && count($sheetNames) > 1) continue;
        
        echo "Processing Sheet: $sheetName\n";
        
        if (preg_match('/(\d{1,2})-(\d{4})/', $sheetName, $matches)) {
            $weekYear = $matches[1] . '-' . $matches[2];
        } else {
            $weekYear = "unknown-" . rand(100, 999);
        }
        
        $outputFile = $outputDir . '/' . basename($inputFile) . " - $weekYear Top 200.csv";
        
        $itemReader = IOFactory::createReader('Xlsx');
        $itemReader->setReadDataOnly(true);
        $itemReader->setLoadSheetsOnly($sheetName);
        $spreadsheet = $itemReader->load($inputFile);
        
        $writer = new Csv($spreadsheet);
        $writer->save($outputFile);
        
        echo "   ✅ Extracted to: " . basename($outputFile) . "\n";
        
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet, $itemReader);
        gc_collect_cycles();
    }

} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n✅ Extraction complete.\n";