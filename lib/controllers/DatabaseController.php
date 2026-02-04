<?php



// Function to create a table based on input parameters (using PDO)
function createDynamicTable($conn, $tableName, $columns) {
	$sql = "CREATE TABLE IF NOT EXISTS `$tableName` (";
	foreach ($columns as $colName => $colDefinition) {
		$sql .= "`$colName` $colDefinition,";
	}
	$sql = rtrim($sql, ",") . ")";

	try {
		$conn->exec($sql);
		echo "Table '$tableName' created successfully or already exists.<br>";
	} catch(PDOException $e) {
		echo "Error creating table: " . $e->getMessage() . "<br>";
	}
}