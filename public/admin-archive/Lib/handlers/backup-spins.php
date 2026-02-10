<?php
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;

$_POST = json_decode(file_get_contents("php://input"), true);

// Necessary includes/restorations
require dirname(dirname(dirname(dirname(__DIR__)))) . '/lib/definitions/site-settings.php';

$response = new Response();

try {
    // Set backup file name and location
    $backupDir = dirname(dirname(dirname(dirname(__DIR__)))) . '/storage/backups/';
    $backupFile = $backupDir . 'spins_backup_' . date('Y-m-d_H-i-s') . '.sql';

    // Initialize database connection
    $config = new Config();
    $pdo = ConnectionFactory::named($config, 'SPINS2025');

    // Get all tables in the database
    $tables = [];
    $query = $pdo->query("SHOW TABLES");
    while ($row = $query->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    // Start the backup process
    $sqlDump = "-- Database Backup\n-- Generated on: " . date('Y-m-d H:i:s') . "\n\n";

    foreach ($tables as $table) {
        // Get table creation statement
        $createQuery = $pdo
            ->query("SHOW CREATE TABLE `$table`")
            ->fetch(PDO::FETCH_ASSOC);

        $sqlDump .= "-- Structure for table `$table`\n\n";
        $sqlDump .= $createQuery['Create Table'] . ";\n\n";

        // Get table data
        $result = $pdo->query("SELECT * FROM `$table`");
        if (empty($result)) die('no data');

        $sqlDump .= "-- Data for table `$table`\n\n";
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $columns = array_keys($row);
            $values = array_map(function ($value) use ($pdo) {
                return isset($value) ? $pdo->quote($value) : 'NULL';
            }, array_values($row));
            $sqlDump .= "INSERT INTO `$table` (" . implode(", ", array_map(fn($col) => "`$col`", $columns)) . ") VALUES (" . implode(", ", $values) . ");\n";
        }

        $sqlDump .= "\n";
    }

    // Write the dump to the backup file
    try {
        file_put_contents($backupFile, $sqlDump) or $response->killWithMessage("Could not write to file");
        $response->message = 'Success';
        $response->success = true;
        $response->code = 200;
        echo json_encode($response);
    } catch (Exception $e) {
        $response->killWithMessage($response->message = 'Could not add file');
    }

} catch (Exception $e) {
    // Handle errors
    $response->message = 'Error creating database backup: ' . $e->getMessage();
    echo json_encode($response);
}


