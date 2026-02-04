### NGN 2.0 Scoring Model (Draft v1)

This document defines how NGN 2.0 calculates fair, reproducible scores for charts. It maps current environment weights to canonical dimensions, adds fairness controls (caps, decay, outlier handling, station tiering), and introduces versioning so every chart run is auditable.

---

#### Goals
- Fair and transparent scoring across sources (spins, posts, views, sales, adds, etc.).
- Reproducible results: every chart points to a `formula` and a `weights` version.
- Safety nets: per-dimension caps, outlier detection, and optional recency decay.
- Extensible: add new dimensions and tweak weights without breaking history.

---

#### Canonical dimensions (mapped to current .env)
Entity: Artist
- artist.show_count → `ARTIST_SHOW_COUNT_WEIGHT`
- artist.post_count → `ARTIST_POST_COUNT_WEIGHT`
- artist.release_count → `ARTIST_RELEASE_COUNT_WEIGHT`
- artist.video_count → `ARTIST_VIDEO_COUNT_WEIGHT`
- artist.merch_count → `ARTIST_MERCH_COUNT_WEIGHT`
- artist.spin_count → `ARTIST_SPIN_COUNT_WEIGHT`
- artist.social_fans → `ARTIST_SOCIAL_FANS_WEIGHT`

Conversions (Artist)
- artist.merch_sale → `ARTIST_MERCH_SALE_WEIGHT`
- artist.show_sale → `ARTIST_SHOW_SALE_WEIGHT`
- artist.video_play → `ARTIST_VIDEO_PLAY_WEIGHT`
- artist.station_add → `ARTIST_STATION_ADD_WEIGHT`
- artist.spin_view → `ARTIST_SPIN_VIEW_WEIGHT`
- artist.view → `ARTIST_VIEW_WEIGHT`
- artist.video_view → `ARTIST_VIDEO_VIEW_WEIGHT`
- artist.post_view → `ARTIST_POST_VIEW_WEIGHT`
- artist.release_view → `ARTIST_RELEASE_VIEW_WEIGHT`

Entity: Label
- label.post_count → `LABEL_POST_COUNT_WEIGHT`
- label.artists_total_boost → `LABEL_ARTISTS_TOTAL_BOOST_WEIGHT`
- label.artists_total_charting → `LABEL_ARTISTS_TOTAL_CHARTING_WEIGHT`
- label.view → `LABEL_VIEW_WEIGHT`
- label.post_view → `LABEL_POST_VIEW_WEIGHT`
- label.age (derived) → `LABEL_AGE_WEIGHT`, `AGE_LOG_BASE`, `AGE_IMPACT_MULTIPLIER`
- label.boost (manual reputation) → `LABEL_BOOST_WEIGHT`, `LABEL_REPUTATION_WEIGHT`
- label.spin_weight (spins contribution) → `LABEL_SPIN_WEIGHT`

Notes:
- All CDM text fields are utf8mb4; normalization converts legacy latin1 to utf8mb4.
- Dimensions are stored in `cdm_scoring_dimensions`.

---

#### Fairness controls
- Per-dimension caps: no single dimension may contribute more than 40% of total score by default.
- Outlier handling: winsorize extreme inputs per period (e.g., cap at 99th percentile) and log adjustments.
- Recency decay (optional): half-life of 4 weeks; apply multiplier `decay = 0.5^(weeks/4)` to historic signals.
- Station tiering (optional): Tier1=1.0, Tier2=0.7, Tier3=0.4 multipliers on spins/adds.

These are encoded in `cdm_scoring_formulas` as JSON specs and applied at run-time; the chosen parameters are versioned.

---

#### Versioning & provenance
- `cdm_scoring_formulas(slug, version)` controls logic and fairness knobs.
- `cdm_scoring_weights(formula_id, key, value)` stores numeric weights by dimension.
- `cdm_chart_runs` records each execution with `formula_id`, `weights_checksum`, and an `inputs_checksum` for audit.
- `cdm_chart_entries` reference `run_id` so each rank is traceable to the exact configuration.

---

#### Execution overview
1) Load inputs (spins windows, posts/views, conversions) from CDM tables.
2) Normalize and cap dimensions; apply decay and station tiering if enabled.
3) Apply weights to each dimension; sum to total entity score.
4) Rank entities per chart definition (weekly/monthly), tie-break consistently.
5) Persist `cdm_chart_runs` and `cdm_chart_entries`; optionally store `cdm_score_aggregates` for fast reads.

---

#### Initial defaults (can be changed per formula version)
- Decay: enabled, half-life 4 weeks.
- Caps: enabled, 40% max per single dimension.
- Station tiers: enabled (all stations default Tier1 until classified).

---

#### Next steps
- Backfill `v1` weights from current `.env` into `cdm_scoring_weights` (included as a seed migration for convenience).
- Define `cdm_chart_definitions` for NGN weekly charts; add SMR series as `smr:<series>`.
- Trial a weekly run, compare to legacy outputs, and adjust weights/caps/tiers as needed.
