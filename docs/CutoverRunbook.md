NGN 2.0 Cutover & Rollback Runbook (v1)

Purpose
- Safely flip public view from legacy (1.0) to 2.0, with a fast rollback switch.

Preconditions
- Maintenance mode policy active and verified.
- Admin Progress: Fairness, Coverage, Integrity badges green.
- Completeness, Parity, Backups green in Admin.
- Migrations up; Schemas verified.

Flags & cookies
- FEATURE_PUBLIC_VIEW_MODE=legacy|next
- Cookie override (admin/testing): NGN_VIEW_MODE=legacy|next (Lax, 7d)

Cutover steps
1. Staging canary
   - Set FEATURE_PUBLIC_VIEW_MODE=next on staging.
   - Verify: Admin → Live Progress shows view mode “next”.
   - Smoke test public 2.0 pages: /frontend/... (Charts/Artists/Labels/Stations) via admin preview.
2. Production canary (internal)
   - Keep MAINTENANCE_MODE=true.
   - Use cookie override NGN_VIEW_MODE=next for internal testers only.
   - Monitor API logs and Admin badges for 30–60 min.
3. Public flip
   - Set FEATURE_PUBLIC_VIEW_MODE=next in production.
   - Keep maintenance guard as needed until opening to the public.
   - Validate: basic nav, charts load, no console errors.
4. Open public (optional)
   - Disable maintenance mode when ready.
   - Confirm sitemap refresh and robots.

Rollback
- Set FEATURE_PUBLIC_VIEW_MODE=legacy (or set NGN_VIEW_MODE=legacy cookie) and hard‑reload.
- Re‑enable MAINTENANCE_MODE=true if required.

Monitoring (72h)
- API error rate
- Chart compute health, verification suite greens
- Backup verifies nightly

Notes
- Tailwind CDN is acceptable during development/maintenance; switch to local build before GA if required.
- All changes are flags/cookies; no deploy required for flip.