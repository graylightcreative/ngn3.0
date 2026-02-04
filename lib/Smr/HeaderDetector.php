<?php
namespace NGN\Lib\Smr;

use PhpOffice\PhpSpreadsheet\IOFactory;

class HeaderDetector
{
    private int $maxRows;
    private int $timeoutMs;

    public function __construct(int $maxRows = 5, int $timeoutMs = 500)
    {
        $this->maxRows = max(0, $maxRows);
        $this->timeoutMs = max(0, $timeoutMs);
    }

    /**
     * Detect header candidates from a spreadsheet or CSV file.
     * Returns an array with keys:
     *  - headers: string[]
     *  - sheet: string|null (primary sheet title)
     *  - rows_sampled: int
     *  - sheets: string[] (optional list of sheet names)
     *  - sample_rows: array<int, array<string, mixed>> (up to maxRows)
     */
    public function detectHeaders(string $filePath): array
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($ext === 'csv') {
            return $this->detectCsv($filePath);
        }
        // Try PhpSpreadsheet for xlsx and others
        try {
            $start = microtime(true);
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
            $sheet = $spreadsheet->getSheet(0);
            $highestColumn = $sheet->getHighestColumn();
            $headers = [];
            $row1 = $sheet->rangeToArray("A1:".$highestColumn."1", null, true, true, true);
            if (!empty($row1[1])) {
                foreach ($row1[1] as $val) {
                    $val = is_string($val) ? trim($val) : (string)$val;
                    if ($val !== '') $headers[] = $val;
                }
            }
            // Sample up to N rows following the header
            $sampleRows = [];
            $rowsToRead = $this->maxRows;
            if ($rowsToRead > 0 && $headers) {
                $endRow = 1 + $rowsToRead;
                $range = "A2:".$highestColumn.$endRow;
                $rows = $sheet->rangeToArray($range, null, true, true, true);
                foreach ($rows as $row) {
                    $assoc = [];
                    $i = 0;
                    foreach ($row as $col) {
                        $key = $headers[$i] ?? (string)($i+1);
                        $assoc[$key] = $col;
                        $i++;
                    }
                    if ($assoc) $sampleRows[] = $assoc;
                    // Timeout guard
                    if (((microtime(true) - $start) * 1000) > $this->timeoutMs) break;
                }
            }
            // Collect sheet names
            $sheetNames = [];
            foreach ($spreadsheet->getWorksheetIterator() as $ws) {
                $sheetNames[] = $ws->getTitle();
            }
            return [
                'headers' => $headers,
                'sheet' => $sheet->getTitle(),
                'rows_sampled' => count($sampleRows) > 0 ? 1 : (empty($headers) ? 0 : 1),
                'sheets' => $sheetNames,
                'sample_rows' => $sampleRows,
            ];
        } catch (\Throwable $e) {
            // Fallback: no headers
            return [
                'headers' => [],
                'sheet' => null,
                'rows_sampled' => 0,
                'sheets' => [],
                'sample_rows' => [],
            ];
        }
    }

    private function detectCsv(string $filePath): array
    {
        $headers = [];
        $sampleRows = [];
        $fh = @fopen($filePath, 'r');
        if ($fh) {
            $row = fgetcsv($fh);
            if (is_array($row)) {
                foreach ($row as $col) {
                    $col = is_string($col) ? trim($col) : (string)$col;
                    if ($col !== '') $headers[] = $col;
                }
                // Sample following lines up to maxRows
                $count = 0;
                while ($count < $this->maxRows && ($data = fgetcsv($fh)) !== false) {
                    $assoc = [];
                    foreach ($data as $i => $col) {
                        $key = $headers[$i] ?? (string)($i+1);
                        $assoc[$key] = $col;
                    }
                    if ($assoc) $sampleRows[] = $assoc;
                    $count++;
                }
            }
            fclose($fh);
        }
        return [
            'headers' => $headers,
            'sheet' => null,
            'rows_sampled' => $headers ? 1 : 0,
            'sheets' => [],
            'sample_rows' => $sampleRows,
        ];
    }
}
