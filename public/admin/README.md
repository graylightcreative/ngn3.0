NGN 2.0 Admin Preview

Overview
- This is a feature-gated preview of the NGN 2.0 Admin UI that runs on your server. It mints a temporary admin JWT and loads the /frontend preview inside an iframe. No local/dev setup is required if you manage everything on the server.

Enable on your server (preview/staging; not for production)
1) Edit your server's .env:
   - Set APP_ENV=development
   - Set FEATURE_ADMIN=true
   - Set a strong JWT_SECRET (do NOT use change-me)
   - Optional: restrict allowed origins for admin endpoints
     ADMIN_ALLOWED_ORIGINS=https://nextgennoise.com
2) Reload PHP-FPM or clear OPcache so changes take effect.
3) Visit: /admin/ngn2.php
   - You should see “Admin JWT minted” and the 2.0 preview UI.
   - If not, check /checkup/env.php and /checkup/env-required.php for diagnostics.

Validation
- /admin/ngn2.php should show “Admin JWT minted”
- The Health badge turns green after /api/v1/admin/health responds
- /api/v1/health returns 200 OK

Troubleshooting
- If you see “FEATURE_ADMIN is disabled”, verify FEATURE_ADMIN=true in .env and reload PHP-FPM.
- If you see “Admin preview is only available in development environment”, ensure APP_ENV=development.
- If you see autoload diagnostics, ensure vendor/ exists or unzip vendor.zip, then reload PHP-FPM.
- If token minting fails, ensure JWT_SECRET is set and not “change-me”.

Notes
- Keep FEATURE_ADMIN=false in production.
- Maintenance mode allowlist already permits /admin/* when enabled for NGN 1.0.
