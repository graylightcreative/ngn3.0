# THE MASTER'S GUIDE TO THE FOUNDRY
## BROCK STARR // SOVEREIGN RIG ORCHESTRATION
### STATUS: PRESSURIZED // VERSION 10.0.0

As the Master of the Graylight Foundry, this is your high-velocity workflow for the **Sovereign Rig**. The fleet has evolved from passive hosts into an active production engine.

---

### 1. THE RIG MANIFEST (Identity Alignment)

The fleet is now organized into functional divisions. Every node is a specialist in the **Rig**.

| Division | Callsign | System Role | Legacy Node |
| :--- | :--- | :--- | :--- |
| **THE SHIELD** | **FLARE** | Identity and Session Flare | Beacon |
| | **VAULT** | AES-256 Secret/DNA Tomb | Vault |
| | **RAZOR** | Perimeter & CDN Defense | Sentinel |
| | **HAMMER** | Protocol Enforcement | Judge |
| | **SIPHON** | Transactional Fuel Extraction | Ledger |
| | **STRIKE** | Asset/Value Minting | Mint |
| **THE INTEL** | **OVERLORD** | Fleet Brain / SYMPHONY Core | A-OS |
| | **RATTLER** | Scraping & Script Extraction | Reception |
| | **HOUND** | Neural Discovery & Search | Search |
| | **WARGAME** | ROI & Simulation Modeling | Simulator |
| **THE RIG** | **FORGE** | Infrastructure & Bunker Inception | Forge |
| | **RAM** | Data Intake & Entry Breach | Oracle |
| | **TRIGGER** | Deployment & Global Execution | Nexus |
| | **THUMPER** | Real-time Telemetry | Pulse |
| | **TICK** | Job Scheduling & Survival Loops | Clock |
| | **WELD** | Visual Asset & Cartoon Smith | Studio |
| | **SCRAP** | Archive & DNA Storage | Depot |
| **THE RELAY** | **BURST** | Omnichannel Data Dispatches | Vent |
| | **TETHER** | P2P Inter-Bunker Networking | Uplink |
| | **FUSE** | Event-Driven Triggers | Signal |
| | **STATIC** | Encrypted Internal Comms | Messenger |

---

### 2. CORE UPGRADE: SYMPHONY (Logic: OVERLORD)

The fleet’s audio processing is handled by the **SYMPHONY** engine, residing in **OVERLORD**.

- **Source:** Trained on synthetic DNA.
- **Function:** Automated extraction of "Emotion Tags" (Anger, Grit, Sarcasm) from raw audio.
- **Output:** Spectral targets fed directly to visual nodes for frame-perfect animation sync.

---

### 3. THE PRODUCTION LOOP: CARTOON GENERATION

1. **RATTLER (Scavenger):** Rips the script and audio DNA.
2. **OVERLORD (Symphony):** Processes the emotional frequency and emotion tags.
3. **WELD (Studio):** Hammers the visuals onto the audio bones (Character Rigging).
4. **TRIGGER (Nexus):** Deploys the finished episode to the Bunkers.

---

### 4. THE DEPLOYMENT LOOP

The Rig runs on atomic synchronicity via **TRIGGER**.

#### LOCAL DEVELOPMENT
1. **Work Local:** Keep the repository pressurized.
2. **Commit & Push:**
   ```bash
   git add .
   git commit -m "Rig Alignment: [Component Name]"
   git push origin main
   ```

#### DEPLOYMENT
3. **Trigger Sync:**
   ```bash
   nexus fleet-deploy
   ```
   Synchronizes all 21 Rig nodes instantly.

---

### 5. NEXUS CLI QUICK REFERENCE (TRIGGER)

```bash
nexus fleet-deploy                  # Global sync
nexus status                        # Rig health check
nexus doctor                        # Environment diagnostic
nexus create-site [domain] [path]   # Provision vHost
nexus issue-ssl [domain]            # Certbot handshake
```

**DIRECTIVE:** You are the architect. The cartoon is the output; the Rig is the power. Zero fluff.
