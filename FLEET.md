# SOVEREIGN FLEET // PROJECT INTEGRATION BLUEPRINT
## STATUS: MISSION-CRITICAL // VERSION 2.0.0

This document defines the authoritative handshake protocols for this project within the Graylight Foundry ecosystem. Adherence to these standards is mandatory for fleet synchronicity.

---

### 1. THE IDENTITY HANDSHAKE (BEACON SSO)
Every bunker must utilize the centralized identity tower. No local user tables are permitted for operator access.

+ **Mechanism:** Cross-domain cookie `fleet_token`.
+ **Portal:** `https://beacon.graylightcreative.com/auth`
+ **Validation:** Handshake with `graylight_nexus.fleet_sessions`.
+ **Implementation:** All requests must pass through the `SovereignIntegrity` middleware.

---

### 2. THE COMMUNICATIONS ENGINE (VENT MAIL)
Local SMTP usage is prohibited. All dispatches must route through the sovereign relay.

+ **Authority:** `https://vent.graylightcreative.com/v1/vent/relay`
+ **Security:** Generates SHA-256 integrity receipts for every dispatch.

---

### 3. INFRASTRUCTURE MOATS (UNIFIED CORE)
Projects must align with the Unified Core PHP 8.5.1 environment.

+ **Runtime:** PHP 8.5.1
+ **Webroot:** Always `/public`
+ **CLI Authority:** Controlled via `nexus [command]`.

---

### 4. VISUAL DNA (FOUNDRY STANDARD)
The aesthetic is the moat. Every project must mirror the Foundry standard.

+ **Primary Color:** `#FF5F1F` (Electric Orange).
+ **Surface:** `#0A0A0A` (Deep Charcoal).
+ **Typography:** `JetBrains Mono` / `Space Grotesk`.
+ **UI:** Glass-morphism cards (`sp-card`) and tactical grid patterns.

---

### 5. TERMINAL VELOCITY DEPLOYMENT
Deployment is atomic and automated via the Nexus Orchestrator.

1. **Local-First:** Develop and test in your local environment.
2. **Git-Always:** Commit and push to the master repository.
3. **Fleet Sync:** Trigger global deployment via:
  ```bash
  nexus fleet-deploy
  ```

---

### 6. THE 21 SOVEREIGN NODES
The backbone is pressurized across 21 specialized engines:

| Secure | Intelligence | Core |
| :--- | :--- | :--- |
| **Beacon** (ID) | **A-OS** (Brain) | **Forge** (Provision) |
| **Vault** (Secrets) | **Oracle** (Truth) | **Reception** (Airlock) |
| **Ledger** (Finance) | **Search** (Discovery) | **Judge** (Enforce) |
| **Sentinel** (CDN) | **Simulator** (ROI) | **Manual** (Protocols) |
| **Pulse** (Metric) | **A-OS** (Logic) | **Studio** (Assets) |
| **Mint** (Economy) | **Uplink** (Bridge) | **Depot** (Backup) |
| **Vent** (Email) | **Signal** (Events) | **Clock** (Jobs) |
| **Messenger** (Chat) | | |

DIRECTIVE: Build for sovereignty. Code for speed. Zero fluff.
