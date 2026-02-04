# NGN Fairness Policy (v1.0)

Purpose: Ensure NGN rankings are fair, transparent, and resistant to manipulation across sources (stations, streaming, editorial, social).

Principles
- Coverage first: rankings are only valid when data coverage meets thresholds (station coverage, linkage rate).
- Normalization: per-source normalization to prevent any single source from dominating due to scale or bias.
- Caps and guardrails: cap extreme values and enforce per-source/tier mix constraints.
- Transparency: every weekly run publishes a fairness summary (coverage, caps triggered, anomalies, factor mix).

Thresholds (defaults)
- Station coverage (in-scope): ≥ 98%
- Linkage rate (names→IDs): ≥ 95%
- Parity (vs. source counts): within ±2% on 7-day comparisons for spins

Guardrails
- Per-station normalization (z-score with cap) or minmax/log scaling where appropriate
- Percentile caps on heavy-tailed factors
- Anomaly flags for sigma outliers (investigate stations/sources that trigger)

Outputs
- Fairness summary JSON per run (stored with chart runs and surfaced in Admin)

Governance
- Policy changes via PRs only; Admin is read-only. See docs/Scoring.md and docs/Factors.json.
