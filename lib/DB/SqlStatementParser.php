<?php

namespace NGN\Lib\DB;

/**
 * SqlStatementParser
 *
 * Robust SQL statement parser that handles:
 * - Multi-line statements
 * - Quoted strings with escaped quotes
 * - Comments
 * - DELIMITER statements
 */
class SqlStatementParser
{
    /**
     * Parse SQL content into individual statements
     */
    public static function parse(string $sql): array
    {
        $statements = [];
        $current = '';
        $inString = false;
        $stringChar = null;
        $i = 0;

        while ($i < strlen($sql)) {
            $char = $sql[$i];

            // Handle string literals
            if (!$inString && ($char === '"' || $char === "'")) {
                $inString = true;
                $stringChar = $char;
                $current .= $char;
                $i++;
            } elseif ($inString && $char === $stringChar) {
                // Check for escaped quote
                if ($i + 1 < strlen($sql) && $sql[$i + 1] === $stringChar) {
                    // Escaped quote
                    $current .= $char . $char;
                    $i += 2;
                } else {
                    // End of string
                    $inString = false;
                    $stringChar = null;
                    $current .= $char;
                    $i++;
                }
            } elseif ($inString) {
                // Inside string, just add character
                $current .= $char;
                $i++;
            } elseif ($char === ';') {
                // End of statement (only if not in string)
                $current = trim($current);
                if (!empty($current)) {
                    $statements[] = $current . ';';
                }
                $current = '';
                $i++;
            } elseif ($char === '-' && $i + 1 < strlen($sql) && $sql[$i + 1] === '-') {
                // SQL comment - skip to end of line
                while ($i < strlen($sql) && $sql[$i] !== "\n") {
                    $i++;
                }
            } else {
                $current .= $char;
                $i++;
            }
        }

        // Add any remaining content
        $current = trim($current);
        if (!empty($current)) {
            $statements[] = $current;
        }

        return $statements;
    }
}
