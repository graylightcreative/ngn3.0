<?php
/**
 * Audit Logger Service
 * Tracks all state-changing operations (INSERT, UPDATE, DELETE) for security audit trail
 * Helps detect unauthorized modifications and provides data recovery capabilities
 */

namespace NGN\Lib\Services;

use PDO;
use PDOException;

class AuditLogger {
    private static array $logs = [];
    private static ?PDO $pdo = null;
    private static bool $enabled = true;

    /**
     * Initialize audit logger with database connection
     *
     * @param PDO $pdo Database connection
     * @return void
     */
    public static function initialize(PDO $pdo): void {
        self::$pdo = $pdo;
        self::$enabled = (bool)getenv('AUDIT_LOGGING_ENABLED');
    }

    /**
     * Log an INSERT operation
     *
     * @param string $table Table name
     * @param int $recordId Record ID
     * @param array $newData Data being inserted
     * @param int|null $userId User ID (optional)
     * @param string $ipAddress IP address (optional)
     * @return void
     */
    public static function logInsert(
        string $table,
        int $recordId,
        array $newData,
        ?int $userId = null,
        ?string $ipAddress = null
    ): void {
        self::log('INSERT', $table, $recordId, $newData, [], $userId, $ipAddress);
    }

    /**
     * Log an UPDATE operation
     *
     * @param string $table Table name
     * @param int $recordId Record ID
     * @param array $newData New values
     * @param array $oldData Previous values (for comparison)
     * @param int|null $userId User ID (optional)
     * @param string $ipAddress IP address (optional)
     * @return void
     */
    public static function logUpdate(
        string $table,
        int $recordId,
        array $newData,
        array $oldData = [],
        ?int $userId = null,
        ?string $ipAddress = null
    ): void {
        self::log('UPDATE', $table, $recordId, $newData, $oldData, $userId, $ipAddress);
    }

    /**
     * Log a DELETE operation
     *
     * @param string $table Table name
     * @param int $recordId Record ID
     * @param array $deletedData Data being deleted
     * @param int|null $userId User ID (optional)
     * @param string $ipAddress IP address (optional)
     * @return void
     */
    public static function logDelete(
        string $table,
        int $recordId,
        array $deletedData = [],
        ?int $userId = null,
        ?string $ipAddress = null
    ): void {
        self::log('DELETE', $table, $recordId, [], $deletedData, $userId, $ipAddress);
    }

    /**
     * Core logging function
     *
     * @param string $operation Operation type (INSERT, UPDATE, DELETE)
     * @param string $table Table name
     * @param int $recordId Record ID
     * @param array $newData New values (for INSERT/UPDATE)
     * @param array $oldData Old values (for UPDATE/DELETE)
     * @param int|null $userId User ID performing operation
     * @param string $ipAddress IP address of user
     * @return void
     */
    private static function log(
        string $operation,
        string $table,
        int $recordId,
        array $newData,
        array $oldData,
        ?int $userId,
        ?string $ipAddress
    ): void {
        if (!self::$enabled || !self::$pdo) {
            return;
        }

        // Don't log audit_log table itself to prevent recursion
        if ($table === 'audit_log') {
            return;
        }

        // Sanitize sensitive data
        $newData = self::sanitizeSensitiveData($newData);
        $oldData = self::sanitizeSensitiveData($oldData);

        try {
            $logEntry = [
                'timestamp' => date('Y-m-d H:i:s'),
                'operation' => $operation,
                'table_name' => $table,
                'record_id' => $recordId,
                'user_id' => $userId,
                'ip_address' => $ipAddress ?? self::getClientIp(),
                'new_data' => json_encode($newData),
                'old_data' => json_encode($oldData),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ];

            // Try to write to database
            $stmt = self::$pdo->prepare("
                INSERT INTO audit_log (
                    operation, table_name, record_id, user_id, ip_address,
                    new_data, old_data, user_agent, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $logEntry['operation'],
                $logEntry['table_name'],
                $logEntry['record_id'],
                $logEntry['user_id'],
                $logEntry['ip_address'],
                $logEntry['new_data'],
                $logEntry['old_data'],
                $logEntry['user_agent'],
            ]);

            // Also buffer in memory for batch reporting
            self::$logs[] = $logEntry;

            // Keep logs in memory bounded to prevent memory leak
            if (count(self::$logs) > 1000) {
                array_shift(self::$logs);
            }

        } catch (PDOException $e) {
            // Log to file if database write fails
            error_log(sprintf(
                'Audit log write failed: %s for %s.%s (record: %d)',
                $e->getMessage(),
                $table,
                $operation,
                $recordId
            ));
        }
    }

    /**
     * Remove sensitive fields from audit data
     *
     * @param array $data Data to sanitize
     * @return array Sanitized data
     */
    private static function sanitizeSensitiveData(array $data): array {
        $sensitive = ['password', 'api_key', 'token', 'secret', 'credit_card', 'ssn'];

        $sanitized = [];
        foreach ($data as $key => $value) {
            $keyLower = strtolower($key);
            $shouldHide = false;

            foreach ($sensitive as $pattern) {
                if (stripos($keyLower, $pattern) !== false) {
                    $shouldHide = true;
                    break;
                }
            }

            $sanitized[$key] = $shouldHide ? '***REDACTED***' : $value;
        }

        return $sanitized;
    }

    /**
     * Get client IP address
     *
     * @return string|null Client IP address
     */
    private static function getClientIp(): ?string {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? null;
        }
    }

    /**
     * Get audit logs for a specific table/record
     *
     * @param string $table Table name
     * @param int|null $recordId Record ID (null for all records in table)
     * @param int $limit Number of logs to retrieve
     * @return array Array of audit log entries
     */
    public static function getAuditHistory(
        string $table,
        ?int $recordId = null,
        int $limit = 100
    ): array {
        if (!self::$pdo) {
            return [];
        }

        try {
            $sql = "SELECT * FROM audit_log WHERE table_name = ?";
            $params = [$table];

            if ($recordId !== null) {
                $sql .= " AND record_id = ?";
                $params[] = $recordId;
            }

            $sql .= " ORDER BY created_at DESC LIMIT ?";
            $params[] = $limit;

            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('Audit history retrieval failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get in-memory buffered logs
     *
     * @return array Buffered audit logs
     */
    public static function getBufferedLogs(): array {
        return self::$logs;
    }

    /**
     * Clear buffered logs
     *
     * @return void
     */
    public static function clearBuffer(): void {
        self::$logs = [];
    }

    /**
     * Enable/disable audit logging
     *
     * @param bool $enabled Enable or disable
     * @return void
     */
    public static function setEnabled(bool $enabled): void {
        self::$enabled = $enabled;
    }

    /**
     * Check if audit logging is enabled
     *
     * @return bool
     */
    public static function isEnabled(): bool {
        return self::$enabled && self::$pdo !== null;
    }
}
