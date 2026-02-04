# NGN Scoring Model (v2.0)

This document defines NGN 2.0 scoring for charts (initially `ngn:artists:weekly`).

Goals
- Transparent, reproducible, fair rankings aligned with NGN Fairness Policy.
- Stable week-over-week behavior with guardrails against manipulation.
- Support artist-label associations for roster-based scoring.

Factors (v2.0)
- spins — normalized radio spins from station submissions (primary factor)
- smr_spins — SMR (Secondary Market Radio) chart spins (marketing/industry data, admin-ingested via Excel)
- plays — normalized streaming plays (when available)
- adds — station adds (new adds within the window)
- views — normalized page/social views
- posts — normalized editorial/posts
- social — connected social accounts (Facebook, Instagram, TikTok, YouTube, Spotify)
- releases — published releases/albums
- videos — music videos
- mentions — editorial mentions
- claimed — profile claimed bonus
- label_association — artists signed to labels receive roster boost

Normalization & Caps
- Normalizers:
  - per_station_z (center/scale per station; cap z at ±z_cap)
  - minmax (min–max per source)
  - log1p_scale (compress heavy tails)
- Caps:
  - percentile(p) — clamp values above percentile p
  - absolute(max) — hard cap
  - per_source_percent(limit) — any single source contribution capped at N%

Coverage requirements
- Station coverage (in scope): ≥ 98%
- Linkage rate (names→IDs): ≥ 95%
- If coverage fails, affected factors are down-weighted or excluded for that week.

Scoring formula
```
For artist a, week w:
  V_f(a,w)  = raw factor values from CDM
  N_f(a,w)  = normalize_f(V_f(a,w))
  T_f(a,w)  = cap_f(N_f(a,w))
  S(a,w)    = Σ_f [ w_f * T_f(a,w) ],  Σ w_f = 1.0
Rank by S(a,w); apply tie-breakers (recency, breadth) when needed.
```

Fairness Summary (per run)
- coverage: station %, linkage %
- mix: effective factor weights observed
- caps_triggered: counts per factor
- anomalies: outlier flags
- ok/reasons: boolean and failing guardrails

Configuration
- Machine-readable defaults live in `docs/Factors.json` and are loaded by the compute jobs.
- Chart-specific profiles can override weights/normalizers/caps.

Change control
- Policy and weights are updated by PR only. The Admin UI is read-only.
