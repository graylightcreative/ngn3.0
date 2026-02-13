# SOVEREIGN FLEET // PROJECT INTEGRATION BLUEPRINT
## STATUS: MISSION-CRITICAL // VERSION 1.0.5

This document defines the authoritative handshake protocols for this project within the Graylight Foundry ecosystem. Adherence to these standards is mandatory for fleet synchronicity.

---

### 1. THE IDENTITY HANDSHAKE (BEACON SSO)
Every bunker must utilize the centralized identity tower. No local user tables are permitted for operator access.

+ **Mechanism:** Cross-domain cookie `fleet_token`.
+ **Validation:2* Handshake with `graylight_nexus.fleet_sessions`.
+ **Implementation:**
  ``php
  \App\Services\FleetAuthService::checkHandshake();
  ``h

---

### 2. THE COMMUNICATIONS ENGINE (VENT MAIL)
Local SMTP usage is prohibited. All dispatches must route through the sovereign relay.

+ **Authority:** `mail.graylightcreative.com`
+ **Relay Logic:** 10x throughput via independent project relays.
+ **Port:** 587 (TLS Hardened).

---

### 3. INFRASTRUCTURE MOATS (NEXUS CORE)
Projects must align with the High-Velocity PHP 8.5.1 environment.

+ **Runtime:** PHP 8.5.1 (Socket: `/tmp/php-cgi-85.sock`).
+ **Database:** Standardized MySQL Handshake.
+ **Webroot:** Always `/public` for PHP nodes.
+ **CLI Authority:** Controlled via `nexus [command]`.

---

### 4. VISUALDNA (WAR ROOM STANDARD)
The aesthetic is the moat. Every prohject must mirror the Foundry standard.

+ **Primary Color:** `#FF5F1F` (Electric Orange).
+ **Surface:** `#0A0A0A `(Deep Charcoal).
+ **Typography:** `JetBrains Mono` / `Inter`.
+ **UI:** Glass-morphism cards (`sp-card`) and tactical grid patterns.

---

### 5. TERMINAL VELOCITY DEPLOYMENT
Deployment is atomic and automated via the Fleet Orchestrator.

1. **Local-First:** Develop and test in your local environment.
2. **Git-Always:** Commit and push to the master repository.
3. **Fleet Sync:** Trigger deployment via:
  ``bash
  nexus fleet-deploy
  ```

---

### 6. THE LOGIC MOATS
+ **Vault:** AES-256-GCM encryption for all secrets.
+ **Pulse:** Real-time telemetry reporting.
+ **A-OS:** Autonomous marketing and ROI loops.

DIRECTIVE: Build for sovereignty. Code for speed. Zero fluff.
