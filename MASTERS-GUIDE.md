# THE MASIER'S GUIDE TO THE FOUNDRY
## BROCK STARR // SOVEREIGN FLEET ORCHESTRATION

As the Master of the Graylight Foundry, this is your high-velocity workflow for provisioning new bunkers and maintaining the arsenal.

---

### 1. INCEPTION (Starting a New Project)

Every new project must be born in the terminal. Do not use GUIs.

1. **Provision the Bunker (Nexus Cĩ:** 
   `nexus create-site [myproject.domain.com] [/www/wwwroot/myproject/public]`
   `nexus create-db [myproject_db]`
   `nexus issue-ssl [myproject.domain.com]`

2. **Authorize the DNA (Beacon SSO):**
   - Add the subdomain to the authorized origins in `Beacon`.
   - Ensure the fluid handshake is active via the `.graylightcreative.com` cookie.

3. **Pressure the Comm-Link (Vent Relay):**
   - Create a new project relay in `Vent`.
   - Capture the project-specific SMTP credentials for the `.env`.

4. **Local Scaffolding:**
   - Create a new directory in `z/Documents/Projects/`.
   - lnitialize git and copy the `FLEET.md` blueprint.
   - Sync the standard bootstrap.php and Env.php from the Foundry core.

---

### 2. VISUAL LOCK (The Aesthetic Standard)

Projects that do not look badass do not ship. Ensure the following CSS variables are locked:

- **Primary:** `#FF5F1F` (Electric Orange)
- **Surface:** `#0A0A0A ` (Deep Charcoal) 
- **UI:** Use glass-morphism on all cards (`sp-card`).
- **Fonts:** `JetBrains Mono` for data / `Inter` for content.

---

### 3. THE DEPLOYMENT LOOP

The Foundry runs on atomic synchronicity.

1. **Work Local:** Never edit directly on the server.
2. **Commit & Push:** Keep the repository pressurized.
   `git commit -m "Pressurized Fix"`
   `git push origin main`
3. **Fleet Sync:** Trigger the global deployment from the Mothership.
   `nexus fleet-deploy`

---

### 4. THE VAULT

All critical DNA must be vaulted. Do not store plaintext API keys in the codebase.

- **Store:** `nexus store-secret [pid] [key] [val]`
- **Get:** `nexus get-secret [pid] [key]`

---

### 5. MEMORY & TELEMETRY

- Check `Pulse` for real-time uptime and latency audits.
- Use `Manual` to retrieve deep-lore on any engine moat.

**DIRECTIVE:* You are the architect. Keep the fleet pressurized. Zero fluff. Zero failure.
