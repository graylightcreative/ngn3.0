<?php
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;

$_POST = json_decode(file_get_contents("php://input"), true);

require dirname(dirname(dirname(dirname(__DIR__)))) . '/lib/definitions/site-settings.php';

$response = new Response();

try {
    $backupDir = dirname(dirname(dirname(dirname(__DIR__)))) . '/storage/backups/';
    $backupFile = $backupDir . 'rankings_backup_' . date('Y-m-d_H-i-s') . '.sql';

    // Initialize database connection
    $config = new Config();
    $pdo = ConnectionFactory::write($config);

    // Fetch all tables from the database
    $tables = getDatabaseTables($pdo);

    // Open file for incremental writing
    $fileHandle = fopen($backupFile, 'w');
    if (!$fileHandle) {
        throw new Exception("Could not open file for writing.");
    }

    // Write header to backup file
    fwrite($fileHandle, "-- Database Backup\n-- Generated on: " . date('Y-m-d H:i:s') . "\n\n");

    foreach ($tables as $table) {
        // Backup table structure
        backupTableStructure($pdo, $table, $fileHandle);

        // Backup table data
        backupTableData($pdo, $table, $fileHandle);
    }

    // Close the file
    fclose($fileHandle);

    // Send success response
    $response->message = 'Backup created successfully.';
    $response->success = true;
    $response->code = 200;
    echo json_encode($response);

} catch (Exception $e) {
    // Handle errors
    $response->message = 'Error creating database backup: ' . $e->getMessage();
    $response->success = false;
    $response->code = 500;
    echo json_encode($response);
}

/**
 * Fetch all tables in the database.
 *
 * @param PDO $pdo
 * @return array
 * @throws Exception
 */
function getDatabaseTables($pdo)
{
    $tables = [];
    $query = $pdo->query("SHOW TABLES");

    if (!$query) {
        throw new Exception("Failed to retrieve database tables.");
    }

    while ($row = $query->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    return $tables;
}

/**
 * Write the table structure (CREATE TABLE) to the backup file.
 *
 * @param PDO $pdo
 * @param string $table
 * @param resource $fileHandle
 * @throws Exception
 */
function backupTableStructure($pdo, $table, $fileHandle)
{
    $query = $pdo->query("SHOW CREATE TABLE `$table`");
    if (!$query) {
        throw new Exception("Failed to retrieve CREATE TABLE statement for table: $table.");
    }

    $createQuery = $query->fetch(PDO::FETCH_ASSOC);
    fwrite($fileHandle, "-- Structure for table `$table`\n\n");
    fwrite($fileHandle, $createQuery['Create Table'] . ";\n\n");
}

/**
 * Write the table data (INSERT INTO statements) incrementally to the file.
 *
 * @param PDO $pdo
 * @param string $table
 * @param resource $fileHandle
 */
function backupTableData($pdo, $table, $fileHandle)
{
    $result = $pdo->query("SELECT * FROM `$table`");

    if (!$result) {
        fwrite($fileHandle, "-- No data available for table `$table`\n\n");
        return;
    }

    fwrite($fileHandle, "-- Data for table `$table`\n\n");

    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $columns = array_keys($row);
        $values = array_map(function ($value) use ($pdo) {
            return isset($value) ? $pdo->quote($value) : 'NULL';
        }, array_values($row));

        fwrite($fileHandle, "INSERT INTO `$table` (" . implode(", ", array_map(fn($col) => "`$col`", $columns)) . ") VALUES (" . implode(", ", $values) . ");\n");
    }

    fwrite($fileHandle, "\n");
}