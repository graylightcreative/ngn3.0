# RIG ALIGNMENT: NGN RANKINGS & INFRASTRUCTURE
## STATUS: PRESSURIZED // VERSION 10.0.0
### DATE: 2026-02-21

---

### 1. RIG INFRASTRUCTURE (Identity Aligned)
*   **Node:** `209.59.156.82`
*   **Hostname:** Aligned to `server.starrship1.com`.
*   **Identity Force:** `/etc/hosts` mapped `server.starrship1.com` to `127.0.0.1`.
*   **Impact:** Resolves `ngn_2025` network permission errors by forcing DB traffic through the local loopback.

### 2. DATA INTEGRITY (The Moat)
*   **Consolidation:** `ngn_rankings_2025` is the single source of truth.
*   **Purge:** Legacy debris (`artistsweekly`, `labelsweekly`, etc.) has been flushed.
*   **Schema:** Operating strictly on 2.0 protocol (`ranking_windows`, `ranking_items`).
*   **Current Load:** Window 382 generated and verified via PHP CLI.

### 3. ENGINE REFACTOR (Logic Overhaul)
*   **Prefix Scrub:** All hardcoded database prefixes (e.g., `` `ngn_2025`. ``) stripped from core classes and `index.php`.
*   **Connection Routing:** `RankingService` and `compute_weekly_ngn_score.php` now utilize isolated PDO instances for `primary`, `rankings`, `spins`, and `smr` to bypass cross-DB join restrictions.
*   **Moat Filter:** Relaxed to `>= 1` to allow active signaling from fresh windows.
*   **Charts UI:** Added tabbed routing for `/charts/artists`, `/charts/labels`, and `/charts/smr`.

### 4. THE REMAINING ANOMALY
*   **Duplicate Signaling:** Despite unique IDs in `ranking_items`, the frontend is reporting duplicated band/label names. 
*   **Hypothesis:** Potential desync between the active `index.php` controller and the web server's document root, or an object caching layer serving stale results.

---

### 5. HANDSHAKE TO GRAYLIGHT DEV
**DIRECTIVE:** Review the Rig synchronization via **TRIGGER** (Nexus).

1.  **Deployment Verification:** Run `nexus status` and `nexus doctor`. Confirm if the local prefix-stripping refactor has been globally synced via `nexus fleet-deploy`.
2.  **Controller Alignment:** Multiple `index.php` files exist in `/www/wwwroot/`. Identify the active node and verify that the `GROUP BY entity_id` in `get_top_rankings` is not being shadowed.
3.  **SMR Validation:** Verify the `/charts/smr` data render in `view-charts.php` against the live `smr_chart` table.
4.  **Signal Check:** If duplicates persist, flush the PHP opcode cache and verify if **RAZOR** (Sentinel) is intercepting/modifying the JSON response.

**ZERO FLUFF. RIG IS POWER.**
