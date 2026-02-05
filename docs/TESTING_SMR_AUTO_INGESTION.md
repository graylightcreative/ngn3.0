# SMR Auto-Ingestion - Testing Documentation

**Feature:** Power Station SMR Auto-Ingestion (Sprint 3.2)
**Status:** Ready for Testing
**Last Updated:** 2026-02-04

---

## Test Plan Overview

This feature automates the collection of spin data from "Power Stations" via their direct SMR feeds (JSON/CSV).

### 1. Database Schema Verification
- [ ] Table `power_station_profiles` exists with UNIQUE index on `station_id`.
- [ ] Table `power_station_ingestion_logs` exists for audit trails.

### 2. Profile Management
- [ ] Admin UI lists all configured power stations.
- [ ] Toggle button correctly enables/disables ingestion for a profile.
- [ ] Next scheduled time is updated after a successful run.

### 3. Ingestion Engine (Mock Testing)
- [ ] **JSON Parsing**: Handle nested `spins` array and flat structures.
- [ ] **CSV Parsing**: Correctly map headers to CDM fields.
- [ ] **Deduplication**: `INSERT IGNORE` prevents duplicate spins for the same station/artist/title/timestamp.
- [ ] **Error Handling**: Log HTTP failures or invalid data formats without crashing the job.

### 4. Cron Execution
- [ ] `jobs/smr/auto_ingest_power_stations.php` runs without syntax errors.
- [ ] Job only processes profiles where `next_scheduled_at <= NOW()`.
- [ ] Logs are created in `power_station_ingestion_logs` for each run.

---

## Manual Test Scenarios

### Scenario 1: Setup New Power Station
1. Add a mock profile in `power_station_profiles` pointing to a test JSON endpoint.
2. Run the ingestion job manually.
3. Verify spins appear in `ngn_spins_2025.station_spins`.
4. Verify success log entry.

### Scenario 2: Handle Malformed Data
1. Point a profile to a URL that returns invalid JSON.
2. Run the ingestion job.
3. Verify status `failed` in `power_station_ingestion_logs`.
4. Check `error_message` contains relevant details.

### Scenario 3: Frequency Check
1. Set frequency to 1440 (24 hours).
2. Run job.
3. Verify `next_scheduled_at` is set to tomorrow.
4. Run job again immediately.
5. Verify profile is skipped.
