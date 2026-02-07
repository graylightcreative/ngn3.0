# Admin v2 Database Schema Requirements

## Tables Required for SMR Pipeline

The admin-v2 panel requires the following tables. If they don't exist, create them with these schemas:

### `smr_ingestions`
Stores metadata about uploaded SMR files.

```sql
CREATE TABLE IF NOT EXISTS smr_ingestions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  filename VARCHAR(255) NOT NULL,
  file_hash VARCHAR(64) UNIQUE,
  file_size INT,
  status ENUM('pending_review', 'pending_finalize', 'finalized', 'error') DEFAULT 'pending_review',
  uploaded_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_status (status),
  INDEX idx_created_at (created_at)
);
```

### `smr_records`
Stores individual records from uploaded SMR files.

```sql
CREATE TABLE IF NOT EXISTS smr_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ingestion_id INT NOT NULL,
  artist_name VARCHAR(255) NOT NULL,
  track_title VARCHAR(255) NOT NULL,
  spin_count INT DEFAULT 0,
  add_count INT DEFAULT 0,
  isrc VARCHAR(12),
  station_id INT,
  cdm_artist_id INT,
  status ENUM('pending_mapping', 'mapped', 'imported') DEFAULT 'pending_mapping',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_ingestion_id (ingestion_id),
  INDEX idx_status (status),
  INDEX idx_artist_name (artist_name),
  INDEX idx_cdm_artist_id (cdm_artist_id),
  FOREIGN KEY (ingestion_id) REFERENCES smr_ingestions(id) ON DELETE CASCADE
);
```

### `cdm_identity_map`
Maps artist name aliases to canonical CDM artists.

```sql
CREATE TABLE IF NOT EXISTS cdm_identity_map (
  id INT AUTO_INCREMENT PRIMARY KEY,
  artist_id INT NOT NULL,
  alias_name VARCHAR(255) NOT NULL,
  alias_type ENUM('smr_typo', 'user_submitted', 'system') DEFAULT 'system',
  verified TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY unique_alias (artist_id, alias_name),
  INDEX idx_artist_id (artist_id),
  INDEX idx_verified (verified)
);
```

### `cdm_chart_entries`
Stores chart data from finalized SMR ingestions.

```sql
CREATE TABLE IF NOT EXISTS cdm_chart_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ingestion_id INT,
  artist_id INT NOT NULL,
  track_title VARCHAR(255) NOT NULL,
  spin_count INT DEFAULT 0,
  add_count INT DEFAULT 0,
  isrc VARCHAR(12),
  week_date DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_artist_id (artist_id),
  INDEX idx_week_date (week_date),
  INDEX idx_ingestion_id (ingestion_id),
  FOREIGN KEY (artist_id) REFERENCES cdm_artists(id) ON DELETE CASCADE
);
```

## Tables Required for Rights Ledger

### `cdm_rights_ledger`
Stores ownership verification records.

```sql
CREATE TABLE IF NOT EXISTS cdm_rights_ledger (
  id INT AUTO_INCREMENT PRIMARY KEY,
  artist_id INT NOT NULL,
  track_id INT,
  isrc VARCHAR(12),
  owner_id INT NOT NULL,
  status ENUM('pending', 'verified', 'disputed', 'rejected') DEFAULT 'pending',
  verified_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_artist_id (artist_id),
  INDEX idx_status (status),
  INDEX idx_isrc (isrc),
  FOREIGN KEY (artist_id) REFERENCES cdm_artists(id) ON DELETE CASCADE
);
```

### `cdm_rights_splits`
Stores ownership split percentages.

```sql
CREATE TABLE IF NOT EXISTS cdm_rights_splits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  right_id INT NOT NULL,
  contributor_id INT NOT NULL,
  percentage DECIMAL(5,2) NOT NULL,
  role VARCHAR(50),
  verified TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_right_id (right_id),
  INDEX idx_contributor_id (contributor_id),
  FOREIGN KEY (right_id) REFERENCES cdm_rights_ledger(id) ON DELETE CASCADE
);
```

## Tables Required for Royalties

### `cdm_royalty_transactions`
Stores individual royalty calculations.

```sql
CREATE TABLE IF NOT EXISTS cdm_royalty_transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  ingestion_id INT,
  amount DECIMAL(10,2) NOT NULL,
  eqs_pool_share DECIMAL(5,4),
  calculation_type ENUM('eqs', 'flat', 'adjusted') DEFAULT 'eqs',
  period_start DATE,
  period_end DATE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_user_id (user_id),
  INDEX idx_period_start (period_start),
  INDEX idx_period_end (period_end)
);
```

### `cdm_payout_requests`
Stores pending and processed payouts.

```sql
CREATE TABLE IF NOT EXISTS cdm_payout_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
  stripe_transfer_id VARCHAR(255),
  requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  processed_at TIMESTAMP NULL,

  INDEX idx_user_id (user_id),
  INDEX idx_status (status),
  INDEX idx_requested_at (requested_at)
);
```

## Tables Required for Chart QA

### `ngn_score_corrections`
Stores manual corrections to chart scores.

```sql
CREATE TABLE IF NOT EXISTS ngn_score_corrections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ingestion_id INT,
  artist_id INT NOT NULL,
  original_score DECIMAL(8,2),
  corrected_score DECIMAL(8,2),
  reason VARCHAR(255),
  corrected_by INT,
  approved TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_ingestion_id (ingestion_id),
  INDEX idx_artist_id (artist_id),
  INDEX idx_approved (approved)
);
```

### `ngn_score_disputes`
Stores disputes about chart calculations.

```sql
CREATE TABLE IF NOT EXISTS ngn_score_disputes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ingestion_id INT,
  artist_id INT NOT NULL,
  reported_by INT,
  reason TEXT,
  resolution TEXT,
  status ENUM('open', 'resolved', 'rejected') DEFAULT 'open',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  resolved_at TIMESTAMP NULL,

  INDEX idx_artist_id (artist_id),
  INDEX idx_status (status),
  INDEX idx_created_at (created_at)
);
```

## Existing Tables (Do Not Modify)

These tables should already exist from the core NGN schema:

- `cdm_artists` - Artist profiles
- `cdm_labels` - Label profiles
- `cdm_venues` - Venue profiles
- `cdm_users` - Platform users (for royalty recipients)

## Migration Script

To create all tables at once, run:

```php
<?php
require_once __DIR__ . '/lib/bootstrap.php';

$config = new NGN\Lib\Config();
$pdo = $config->getDatabase();

$tables = <<<SQL
-- ... paste SQL from above ...
SQL;

foreach (explode(';', $tables) as $sql) {
    $sql = trim($sql);
    if (!empty($sql)) {
        $pdo->exec($sql);
    }
}

echo "All tables created successfully\n";
```

## Database Indexes

For optimal performance with admin-v2, ensure these indexes exist:

```sql
CREATE INDEX idx_smr_ingestions_status_created ON smr_ingestions(status, created_at);
CREATE INDEX idx_smr_records_ingestion_status ON smr_records(ingestion_id, status);
CREATE INDEX idx_cdm_rights_ledger_artist_status ON cdm_rights_ledger(artist_id, status);
CREATE INDEX idx_cdm_payout_requests_user_status ON cdm_payout_requests(user_id, status);
CREATE INDEX idx_ngn_score_corrections_ingestion ON ngn_score_corrections(ingestion_id);
CREATE INDEX idx_ngn_score_disputes_artist_status ON ngn_score_disputes(artist_id, status);
```

## Notes

- All timestamps use UTC
- UUIDs can be substituted for INT primary keys if needed
- Add foreign keys as needed for data integrity
- Consider partitioning large tables (smr_records, cdm_royalty_transactions) by date
