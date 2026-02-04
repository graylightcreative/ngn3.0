<?php

namespace NGN\Lib\DB;

use PDO;
use Exception;

/**
 * LegacySqlTransformer
 *
 * Transforms legacy SQL INSERT statements to match new schema.
 * Handles column mapping and value transformation.
 */
class LegacySqlTransformer
{
    private $columnMappings = [
        'posts' => [
            'Id' => 'Id',
            'Title' => 'Title',
            'Slug' => 'Slug',
            'Body' => 'Body',
            'Tags' => null,  // Drop
            'Summary' => null,  // Drop
            'TypeId' => null,  // Drop
            'Published' => 'Status',  // Transform: 1 → 'published', 0 → 'draft'
            'Featured' => null,  // Drop
            'Image' => null,  // Drop
            'Created' => 'CreatedAt',
            'Updated' => 'UpdatedAt',
            'Author' => null,  // Drop
            'PublishedDate' => 'PublishedAt',
            'IsUser' => null,  // Drop
        ],
        'users' => [
            'Id' => 'Id',
            'Email' => 'email',
            'Password' => 'password_hash',
            'Username' => 'username',
            'FirstName' => 'first_name',
            'LastName' => 'last_name',
            'Created' => 'created_at',
            'Updated' => 'updated_at',
            'Image' => null,  // Drop
            'Role' => null,  // Drop
        ],
        'artists' => [
            'Id' => 'Id',
            'Name' => 'name',
            'Image' => 'image_url',
            'Bio' => 'bio',
            'Website' => 'website_url',
            'Email' => 'contact_email',
            'Created' => 'created_at',
            'Updated' => 'updated_at',
            'Verified' => 'is_verified',
        ],
    ];

    private $valueMappings = [
        'posts' => [
            'Status' => 'transformPublished',  // Reference to method name
        ],
    ];

    /**
     * Transform a complete SQL statement
     */
    public function transformStatement(string $statement, string $table): ?string
    {
        // Trim whitespace that might have been added during splitting
        $statement = trim($statement);

        // Find the column list - more robust pattern that handles nested parens
        if (!preg_match('/^INSERT\s+INTO\s+`?' . preg_quote($table) . '`?\s*\(/is', $statement)) {
            error_log("  Regex 1 failed for $table");
            return null;
        }

        // Extract column list by finding the matching closing paren
        $paren_pos = strpos($statement, '(');
        if ($paren_pos === false) {
            error_log("  No opening paren found for $table");
            return null;
        }

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

        if ($depth !== 0) {
            error_log("  Mismatched parens for $table (depth=$depth)");
            return null;  // Mismatched parentheses
        }

        $columnList = substr($statement, $start, $end - $start);
        $remainingStatement = substr($statement, $end + 1);

        // Check for VALUES keyword
        if (!preg_match('/^\s*VALUES\s+(.*)/is', $remainingStatement, $matches)) {
            error_log("  VALUES keyword not found for $table. Remaining: " . substr($remainingStatement, 0, 50));
            return null;
        }

        $valuesList = $matches[1];

        // Parse column names
        $columns = array_map(function($col) {
            return trim(trim($col), '`');
        }, explode(',', $columnList));

        // Map columns and filter nulls
        $newColumns = [];
        $columnMap = [];
        foreach ($columns as $oldCol) {
            if (!isset($this->columnMappings[$table][$oldCol])) {
                // Unknown column - skip it
                continue;
            }
            $newCol = $this->columnMappings[$table][$oldCol];
            if ($newCol !== null) {
                $newColumns[] = $newCol;
                $columnMap[$oldCol] = $newCol;
            }
        }

        if (empty($newColumns)) {
            error_log("  Transform: No valid columns to insert for $table");
            return null;  // No valid columns to insert
        }

        // Transform the VALUES clause
        $transformedValues = $this->transformValues($valuesList, $columns, $columnMap, $table);
        if (!$transformedValues) {
            error_log("  Transform: transformValues returned null for $table");
            return null;
        }

        error_log("  Transform: Success for $table with " . count($newColumns) . " columns");

        // Reconstruct the INSERT statement
        $newColumnList = implode('`, `', $newColumns);
        return "INSERT INTO `$table` (`$newColumnList`) VALUES $transformedValues;";
    }

    /**
     * Transform the VALUES clause to match new column order
     */
    private function transformValues(string $valuesList, array $oldColumns, array $columnMap, string $table): ?string
    {
        // Remove leading/trailing whitespace and semicolon
        $valuesList = trim(trim($valuesList), ';');

        // Parse value sets by manually tracking parentheses and quotes
        $transformedSets = [];
        $i = 0;
        while ($i < strlen($valuesList)) {
            // Skip whitespace and commas
            while ($i < strlen($valuesList) && (ctype_space($valuesList[$i]) || $valuesList[$i] === ',')) {
                $i++;
            }

            if ($i >= strlen($valuesList)) break;

            // Expect opening paren
            if ($valuesList[$i] !== '(') {
                error_log("  Expected '(' at position $i, found '{$valuesList[$i]}' in VALUES clause");
                break;  // No more valid rows
            }

            // Find matching closing paren
            $start = $i + 1;
            $depth = 1;
            $end = $start;
            $inString = false;
            $stringChar = null;

            for ($j = $start; $j < strlen($valuesList); $j++) {
                $char = $valuesList[$j];

                if (!$inString) {
                    if ($char === '"' || $char === "'") {
                        $inString = true;
                        $stringChar = $char;
                    } elseif ($char === '(') {
                        $depth++;
                    } elseif ($char === ')') {
                        $depth--;
                        if ($depth === 0) {
                            $end = $j;
                            break;
                        }
                    }
                } else {
                    // In string - check for end, handling backslash escapes
                    if ($char === $stringChar) {
                        // Count preceding backslashes
                        $backslashes = 0;
                        $k = $j - 1;
                        while ($k >= $start && $valuesList[$k] === '\\') {
                            $backslashes++;
                            $k--;
                        }
                        // If even number of backslashes, this quote is not escaped
                        if ($backslashes % 2 === 0) {
                            $inString = false;
                            $stringChar = null;
                        }
                    }
                }
            }

            if ($depth !== 0) {
                error_log("  Unclosed paren in VALUES clause");
                return null;
            }

            $valueSet = substr($valuesList, $start, $end - $start);
            $transformed = $this->transformValueSet($valueSet, $oldColumns, $columnMap, $table);
            if ($transformed) {
                $transformedSets[] = "($transformed)";
            }

            $i = $end + 1;
        }

        return !empty($transformedSets) ? implode(', ', $transformedSets) : null;
    }

    /**
     * Transform a single row of values
     */
    private function transformValueSet(string $values, array $oldColumns, array $columnMap, string $table): ?string
    {
        // Parse individual values (handling quoted strings carefully)
        $valueArray = $this->parseValues($values);

        if (count($valueArray) !== count($oldColumns)) {
            error_log("Value count mismatch in $table: " . count($valueArray) . " values vs " . count($oldColumns) . " columns");
            return null;
        }

        $transformedValues = [];
        foreach ($oldColumns as $index => $oldCol) {
            if (!isset($columnMap[$oldCol])) {
                // Column is mapped to null (dropped), skip
                continue;
            }

            $newCol = $columnMap[$oldCol];
            $value = $valueArray[$index];

            // Apply value transformation if defined
            if (isset($this->valueMappings[$table][$newCol])) {
                $methodName = $this->valueMappings[$table][$newCol];
                if (method_exists($this, $methodName)) {
                    $value = $this->$methodName($this->extractValue($value));
                }
            }

            $transformedValues[] = $value;
        }

        return !empty($transformedValues) ? implode(', ', $transformedValues) : null;
    }

    /**
     * Parse CSV values from a VALUES clause, handling quoted strings
     */
    private function parseValues(string $values): array
    {
        $values = trim($values);
        $result = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = null;

        for ($i = 0; $i < strlen($values); $i++) {
            $char = $values[$i];

            if (!$inQuotes && ($char === "'" || $char === '"')) {
                $inQuotes = true;
                $quoteChar = $char;
                $current .= $char;
            } elseif ($inQuotes && $char === $quoteChar && ($i + 1 < strlen($values) && $values[$i + 1] !== $quoteChar)) {
                $inQuotes = false;
                $current .= $char;
            } elseif (!$inQuotes && $char === ',') {
                $result[] = trim($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }

        if (!empty($current)) {
            $result[] = trim($current);
        }

        return $result;
    }

    /**
     * Extract actual value from quoted/unquoted string
     */
    private function extractValue(string $value)
    {
        $value = trim($value);
        if ($value === 'NULL' || $value === 'null') {
            return 'NULL';
        }
        return $value;
    }

    /**
     * Check if a table has a mapping defined
     */
    public function hasMapping(string $table): bool
    {
        return isset($this->columnMappings[$table]);
    }

    /**
     * Transform Published (0/1) to Status (draft/published)
     */
    private function transformPublished($value)
    {
        $numValue = intval($value);
        return $numValue == 1 ? "'published'" : "'draft'";
    }
}
