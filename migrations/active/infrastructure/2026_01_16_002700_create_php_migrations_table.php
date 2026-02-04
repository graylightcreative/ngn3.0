<?php

// Migrator is already autoloaded via bootstrap.php in the migration runner

use NGN\Lib\DB\Migrator;

use NGN\Lib\Config; // Add Config use statement

return new class($config) extends Migrator {
    public function __construct(Config $config)
    {
        parent::__construct($config);
    } // Inherit from Migrator to get PDO connection
    public function up(): void
    {
        // This migration will create its own table to track PHP migrations
        // The table is specifically for PHP class-based migrations, not the SQL ones managed by Migrator
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS ngn_2025.php_migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    public function down(): void
    {
        $this->db->exec("DROP TABLE IF EXISTS ngn_2025.php_migrations;");
    }
};
