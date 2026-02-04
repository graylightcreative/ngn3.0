<?php

use NGN
Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

class DatabaseManager
{
	private PDO $pdo;
	private Config $config;

	public function __construct(Config $config)
	{
		$this->config = $config;
		// Assuming we want to generate BREAD for the main ngn_2025 database
		$this->pdo = ConnectionFactory::read($config);
	}

	private function getColumnNames($table)
	{
		$tableName = '`ngn_2025`.`' . $table . '`';
		$stmt = $this->pdo->query("SHOW COLUMNS FROM {$tableName}");
		return $stmt->fetchAll(PDO::FETCH_COLUMN);
	}

	public function generateBREADMethods()
	{
		// Get tables from ngn_2025 schema
		$stmt = $this->pdo->query('SHOW TABLES FROM `ngn_2025`');
		$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

		echo "<?php\n\n";
		echo "class GeneratedDataManager {\n"; // Renamed class to avoid conflict
		echo "    private \$pdo;\n\n";
		echo "    public function __construct(\$pdo) {\n";
		echo "        \$this->pdo = \$pdo;\n";
		echo "    }\n\n";

		foreach ($tables as $table) {
			$columnNames = $this->getColumnNames($table);
            $ngn2025Table = '`ngn_2025`.`' . $table . '`';

			// Browse
			echo "    public function browse" . ucfirst($table) . "() {\n";
			echo "        \$stmt = \$this->pdo->query(\"SELECT * FROM {\$ngn2025Table}\");\n";
			echo "        return \$stmt->fetchAll();\n";
			echo "    }\n\n";

			// Read
			echo "    public function read" . ucfirst($table) . "(\$id) {\n";
			echo "        \$stmt = \$this->pdo->prepare(\"SELECT * FROM {\$ngn2025Table} WHERE id = ?\");\n";
			echo "        \$stmt->execute([\$id]);\n";
			echo "        return \$stmt->fetch();\n";
			echo "    }\n\n";

			// Edit (Update)
			echo "    public function edit" . ucfirst($table) . "(\$id, \$data) {\n";
			echo "        \$updateFields = [];\n";
			echo "        \$values = [];\n";
			foreach ($columnNames as $column) {
				if ($column !== 'id' && isset($data[$column])) {
					$updateFields[] = "$column = ?";
					$values[] = $data[$column];
				}
			}
            $values[] = $id; // Bind ID last

			echo "        \$stmt = \$this->pdo->prepare(\"UPDATE {\$ngn2025Table} SET \" . implode(', ', \$updateFields) . \" WHERE id = ?\");\n";
			echo "        \$stmt->execute(\$values);\n";
			echo "    }\n\n";

			// Add (Insert)
			echo "    public function add" . ucfirst($table) . "(\$data) {\n";
			$insertColumns = [];
			$placeholders = [];
			$values = [];
			foreach ($columnNames as $column) {
				if ($column !== 'id' && isset($data[$column])) {
					$insertColumns[] = $column;
					$placeholders[] = '?';
					$values[] = $data[$column];
				}
			}
			echo "        \$stmt = \$this->pdo->prepare(\"INSERT INTO {\$ngn2025Table} (\" . implode(', ', \$insertColumns) . \") VALUES (\" . implode(', ', \$placeholders) . \")\");\n";
			echo "        \$stmt->execute(\$values);\n";
			echo "    }\n\n";

			// Delete
			echo "    public function delete" . ucfirst($table) . "(\$id) {\n";
			echo "        \$stmt = \$this->pdo->prepare(\"DELETE FROM {\$ngn2025Table} WHERE id = ?\");\n";
			echo "        \$stmt->execute([\$id]);\n";
			echo "    }\n\n";
		}

		echo "}\n";
	}
}

// Usage Example for generation:
// $config = new Config();
// $dbManager = new DatabaseManager($config);
// $dbManager->generateBREADMethods();