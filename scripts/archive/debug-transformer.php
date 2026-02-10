<?php
/**
 * Debug transformer issues
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\DB\SqlStatementParser;
use NGN\Lib\DB\LegacySqlTransformer;

$filePath = '/Users/brock/Library/CloudStorage/GoogleDrive-brock@brockstarr.com/Shared drives/Sites/ngn2.0/storage/uploads/legacy backups/032925.sql';
$sql = file_get_contents($filePath);

// Get first INSERT INTO posts statement
$statements = SqlStatementParser::parse($sql);

foreach ($statements as $stmt) {
    if (stripos($stmt, 'INSERT INTO `posts`') === 0) {
        echo "Testing with first posts INSERT statement\n";
        echo "Length: " . strlen($stmt) . " characters\n\n";

        echo "First 200 chars: " . substr($stmt, 0, 200) . "...\n\n";
        echo "Last 200 chars: " . substr($stmt, -200) . "\n\n";

        // Manual parsing to debug
        $statement = trim($stmt);

        // Find opening paren for columns
        $tableCheckPass = preg_match('/^INSERT\s+INTO\s+`?posts`?\s*\(/i', $statement);
        echo "Regex match for table: " . ($tableCheckPass ? "PASS" : "FAIL") . "\n";

        $paren_pos = strpos($statement, '(');
        echo "First paren position: $paren_pos\n";

        if ($paren_pos !== false) {
            $start = $paren_pos + 1;
            $depth = 1;
            $end = $start;
            for ($i = $start; $i < strlen($statement); $i++) {
                if ($statement[$i] === '(') $depth++;
                elseif ($statement[$i] === ')') {
                    $depth--;
                    if ($depth === 0) {
                        $end = $i;
                        break;
                    }
                }
            }

            echo "Closing paren position: $end (depth=$depth)\n";
            echo "Depth after search: $depth\n";

            if ($depth === 0) {
                $columnList = substr($statement, $start, $end - $start);
                echo "\nColumn list (first 100 chars): " . substr($columnList, 0, 100) . "...\n";

                $remainingStatement = substr($statement, $end + 1);
                echo "Remaining after columns (first 100 chars): " . substr($remainingStatement, 0, 100) . "\n";

                // Check for VALUES
                $valuesMatch = preg_match('/^\s*VALUES\s+(.*)/is', $remainingStatement, $matches);
                echo "\nVALUES regex match: " . ($valuesMatch ? "PASS" : "FAIL") . "\n";

                if ($valuesMatch) {
                    echo "VALUES content (first 150 chars): " . substr($matches[1], 0, 150) . "...\n";
                }
            }
        }

        echo "\n--- Now testing with transformer ---\n";
        $transformer = new LegacySqlTransformer();
        $result = $transformer->transformStatement($stmt, 'posts');
        echo "Transform result: " . ($result ? "SUCCESS" : "NULL") . "\n";

        break;
    }
}
