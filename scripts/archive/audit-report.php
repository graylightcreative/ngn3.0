<?php
/**
 * Comprehensive Audit Report Generator
 *
 * Creates a detailed HTML/text report of migration audit.
 * Usage: php scripts/audit-report.php [format]
 * Formats: text (default), html, json
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

class AuditReportGenerator
{
    private $config;
    private $newDb;
    private $reportData = [];

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->newDb = ConnectionFactory::write($config);
    }

    public function generate(string $format = 'text'): string
    {
        $this->reportData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'databases' => $this->auditDatabases(),
            'tables' => $this->auditTables(),
            'recordCounts' => $this->getRecordCounts(),
            'dataQuality' => $this->auditDataQuality(),
        ];

        match ($format) {
            'html' => return $this->formatHtml(),
            'json' => return json_encode($this->reportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            default => return $this->formatText(),
        };
    }

    private function auditDatabases(): array
    {
        return [
            'ngn_2025' => [
                'purpose' => 'Main database (users, posts, videos, releases, tracks)',
                'expected_tables' => ['cdm_users', 'cdm_posts', 'cdm_media', 'cdm_artists', 'cdm_labels'],
            ],
            'ngn_rankings_2025' => [
                'purpose' => 'Rankings and chart data',
                'expected_tables' => ['cdm_chart_entries'],
            ],
            'ngn_smr_2025' => [
                'purpose' => 'SMR (Secondary Market Rock) chart data',
                'expected_tables' => ['cdm_chart_entries'],
            ],
            'ngn_spins_2025' => [
                'purpose' => 'Station spin/play events',
                'expected_tables' => ['cdm_spins'],
            ],
        ];
    }

    private function auditTables(): array
    {
        $tables = [];
        $result = $this->newDb->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME");

        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $tableName = $row['TABLE_NAME'];
            $colResult = $this->newDb->query("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$tableName'");
            $columns = $colResult->fetchAll(\PDO::FETCH_ASSOC);

            $tables[$tableName] = [
                'column_count' => count($columns),
                'columns' => array_map(fn($c) => $c['COLUMN_NAME'], $columns),
                'primary_key' => $this->getPrimaryKey($tableName),
                'indexes' => $this->getIndexes($tableName),
            ];
        }

        return $tables;
    }

    private function getPrimaryKey(string $table): ?string
    {
        $result = $this->newDb->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND CONSTRAINT_NAME = 'PRIMARY'");
        $row = $result->fetch(\PDO::FETCH_ASSOC);
        return $row['COLUMN_NAME'] ?? null;
    }

    private function getIndexes(string $table): array
    {
        $result = $this->newDb->query("SHOW INDEXES FROM `$table`");
        $indexes = [];
        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $indexes[] = $row['Key_name'];
        }
        return array_unique($indexes);
    }

    private function getRecordCounts(): array
    {
        $counts = [];
        $tables = ['cdm_users', 'cdm_posts', 'cdm_media', 'cdm_artists', 'cdm_labels', 'cdm_stations', 'cdm_spins', 'cdm_chart_entries', 'cdm_post_media', 'cdm_notes'];

        foreach ($tables as $table) {
            try {
                $result = $this->newDb->query("SELECT COUNT(*) as cnt FROM `$table`");
                $row = $result->fetch(\PDO::FETCH_ASSOC);
                $counts[$table] = $row['cnt'] ?? 0;
            } catch (\Exception $e) {
                $counts[$table] = 0;
            }
        }

        return $counts;
    }

    private function auditDataQuality(): array
    {
        $issues = [];

        // Check users
        $result = $this->newDb->query("SELECT COUNT(*) as cnt FROM cdm_users WHERE email IS NULL");
        $row = $result->fetch(\PDO::FETCH_ASSOC);
        if ($row['cnt'] > 0) {
            $issues[] = "Users with NULL email: {$row['cnt']}";
        }

        // Check for duplicate emails
        $result = $this->newDb->query("SELECT COUNT(*) as cnt FROM (SELECT email FROM cdm_users WHERE email IS NOT NULL GROUP BY email HAVING COUNT(*) > 1) t");
        $row = $result->fetch(\PDO::FETCH_ASSOC);
        if ($row['cnt'] > 0) {
            $issues[] = "Duplicate emails found: {$row['cnt']}";
        }

        // Check for orphaned posts
        $result = $this->newDb->query("SELECT COUNT(*) as cnt FROM cdm_posts WHERE author_user_id IS NOT NULL AND author_user_id NOT IN (SELECT id FROM cdm_users)");
        $row = $result->fetch(\PDO::FETCH_ASSOC);
        if ($row['cnt'] > 0) {
            $issues[] = "Orphaned posts (invalid author): {$row['cnt']}";
        }

        // Check posts without timestamps
        $result = $this->newDb->query("SELECT COUNT(*) as cnt FROM cdm_posts WHERE created_at IS NULL OR updated_at IS NULL");
        $row = $result->fetch(\PDO::FETCH_ASSOC);
        if ($row['cnt'] > 0) {
            $issues[] = "Posts missing timestamps: {$row['cnt']}";
        }

        return $issues;
    }

    private function formatText(): string
    {
        $output = "NGN 2.0 MIGRATION AUDIT REPORT\n";
        $output .= "Generated: {$this->reportData['timestamp']}\n";
        $output .= str_repeat("=", 80) . "\n\n";

        $output .= "DATABASE OVERVIEW\n";
        $output .= str_repeat("-", 80) . "\n";
        foreach ($this->reportData['databases'] as $db => $info) {
            $output .= "\n$db\n";
            $output .= "  Purpose: {$info['purpose']}\n";
            $output .= "  Expected Tables: " . implode(", ", $info['expected_tables']) . "\n";
        }

        $output .= "\n\nRECORD COUNTS\n";
        $output .= str_repeat("-", 80) . "\n";
        $total = 0;
        foreach ($this->reportData['recordCounts'] as $table => $count) {
            $output .= sprintf("  %-30s %10d records\n", $table, $count);
            $total += $count;
        }
        $output .= sprintf("  %-30s %10d records (TOTAL)\n", "", $total);

        $output .= "\n\nDATA QUALITY CHECKS\n";
        $output .= str_repeat("-", 80) . "\n";
        if (empty($this->reportData['dataQuality'])) {
            $output .= "  ✓ All checks passed!\n";
        } else {
            foreach ($this->reportData['dataQuality'] as $issue) {
                $output .= "  ⚠ $issue\n";
            }
        }

        $output .= "\n\nTABLE SCHEMA\n";
        $output .= str_repeat("-", 80) . "\n";
        foreach ($this->reportData['tables'] as $table => $info) {
            $output .= "\n$table\n";
            $output .= "  Columns: {$info['column_count']}\n";
            $output .= "  Primary Key: {$info['primary_key']}\n";
            if (!empty($info['indexes'])) {
                $output .= "  Indexes: " . implode(", ", $info['indexes']) . "\n";
            }
        }

        return $output;
    }

    private function formatHtml(): string
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>NGN 2.0 Migration Audit Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; }
        h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #007bff; color: white; }
        tr:hover { background: #f9f9f9; }
        .success { color: green; }
        .warning { color: orange; }
        .error { color: red; }
        .timestamp { color: #666; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="container">
        <h1>NGN 2.0 Migration Audit Report</h1>
        <p class="timestamp">Generated: {$this->reportData['timestamp']}</p>

        <h2>Record Counts</h2>
        <table>
            <tr><th>Table</th><th>Record Count</th></tr>
HTML;

        $total = 0;
        foreach ($this->reportData['recordCounts'] as $table => $count) {
            $html .= "<tr><td>$table</td><td>" . number_format($count) . "</td></tr>";
            $total += $count;
        }
        $html .= "<tr><th>TOTAL</th><th>" . number_format($total) . "</th></tr>";
        $html .= "</table>";

        $html .= "<h2>Data Quality</h2>";
        if (empty($this->reportData['dataQuality'])) {
            $html .= "<p class=\"success\">✓ All checks passed!</p>";
        } else {
            $html .= "<ul>";
            foreach ($this->reportData['dataQuality'] as $issue) {
                $html .= "<li class=\"warning\">$issue</li>";
            }
            $html .= "</ul>";
        }

        $html .= "</div></body></html>";
        return $html;
    }
}

// Run report
$format = $argv[1] ?? 'text';
if (!in_array($format, ['text', 'html', 'json'])) {
    echo "Invalid format. Use: text, html, or json\n";
    exit(1);
}

try {
    $config = new Config();
    $generator = new AuditReportGenerator($config);
    $report = $generator->generate($format);

    // Save to file
    $timestamp = date('Y-m-d_His');
    $filename = match ($format) {
        'html' => "storage/logs/audit_report_$timestamp.html",
        'json' => "storage/logs/audit_report_$timestamp.json",
        default => "storage/logs/audit_report_$timestamp.txt",
    };

    @mkdir(dirname($filename), 0755, true);
    file_put_contents($filename, $report);

    echo $report;
    echo "\n\nReport saved to: $filename\n";
} catch (\Exception $e) {
    echo "Error generating report: " . $e->getMessage() . "\n";
    exit(1);
}
