5. Operations & Infrastructure

5.1 The Cron Registry

Reference: CronSettings.md

The NGN 2.0 heartbeat relies on the following schedule. Times are UTC.

Timing

Script

Purpose

*/5 * * * *

jobs/data/linkage_resolver.php

Links loose names to IDs (SMR/Spins).

*/10 * * * *

jobs/spins_sync.php

Syncs external station feeds.

*/30 * * * *

jobs/ingestion/ingest_smr.php

Processes uploaded SMR CSVs.

0 3 * * *

jobs/backup_db.php

Nightly Database Dump.

30 3 * * *

jobs/backup_verify.php

Critical: Verifies the dump is restorable.

0 6 * * 1

jobs/rankings/compute_weekly_ngn_score.php

The Big Job: Calculates weekly chart.

5 6 * * 1

scripts/chart_qa_gatekeeper.php

Verifies Chart Integrity (Fairness Checks).

5.2 Deployment & Cutover

Reference: CutoverRunbook.md

NGN 2.0 uses a Feature Flag deployment strategy to allow safe "Public Flips".

5.2.1 Environment Flags

FEATURE_PUBLIC_VIEW_MODE:

legacy: Serves old PHP headers/content.

next: Serves the new Vite/Tailwind SPA.

MAINTENANCE_MODE:

true: Blocks non-admin traffic.

5.2.2 The "Erik" SMR Workflow

To update the SMR chart manually:

Prepare: Erik/Admin formats the Excel sheet (Cols: Artist, Title, Spins, Adds).

Upload: Go to Admin -> SMR Ingestion -> "Upload New Chart".

Review: System will parse and show "Unmatched Artists" (e.g., "Metalica" vs "Metallica").

Map: Click to map typos to Canonical Artists.

Commit: Click "Finalize". The data enters cdm_chart_entries.

5.3 Quality Assurance Gates

Reference: Acceptance.md

Before any chart is published, the QA Gatekeeper (chart_qa_gatekeeper.php) must pass:

Station Coverage: > 98% of active stations reported data.

Linkage Rate: > 95% of Artist names are linked to IDs.

Spin Parity: Total spin volume is within ±2% of last week (prevents data loss anomalies).

5.4 Disaster Recovery

Backups: Stored in /storage/backups/. Retained for 30 days.

Verification: The backup_verify job restores the DB to a temp table to ensure SQL validity.

Logs: All anomalies logged to storage/logs/error.log and cron-*.log.