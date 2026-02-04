NGN Acceptance Thresholds (v1.0)

This document defines pass/fail thresholds used by the Verification Suite and roadmap acceptance checks. All values are reviewed per release and changed via PR only.

Charts completeness (artists:weekly)
- Station coverage (in-scope): target ≥ 98% (pass ≥ 0.98)
- Linkage rate (names→IDs): target ≥ 95% (pass ≥ 0.95)
- Latest full ISO week must exist and be marked completed

Spins parity (last 7 days)
- Count parity vs. source within ±2%
- Daily gaps: 0 critical gaps; warnings allowed if recovered within 24h

Scoring coverage & fairness
- Factors available for ≥ 95% of top N (default N=200)
- Caps triggered within expected bounds (no systemic clamping)
- Fairness summary ok=true with no critical anomalies

Backups
- At least 7 daily restore points present for world `primary`
- `Verify latest` ok=true for all configured worlds

Migrations/Schemas
- No pending migrations on deploy
- Schema dumps present and verified

Security
- Admin endpoints require valid admin JWT
- Rate limiting active; admin routes may use higher thresholds but not unlimited

Cutover (Public 2.0)
- Canary error rate parity with legacy
- Core Web Vitals: LCP < 2.5s p75, CLS < 0.1, INP < 200ms

Change control
- Threshold changes are managed via PRs and annotated in this file with date and rationale.
