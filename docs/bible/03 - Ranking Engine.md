3. The Ranking Engine

3.1 Philosophy

Fairness, Transparency, and Anti-Gaming.
The NGN Ranking Engine is the heart of the platform. Unlike traditional charts that rely solely on reported spins, NGN aggregates data from multiple verticals (Terrestrial Radio, SMR, Social Media, Streaming) to create a weighted "NGN Score."

Core Principles:

Granularity: We do not aggregate metrics early. We store raw counts (e.g., facebook_likes, spotify_streams) and apply weights at calculation time.

Normalization: A spin on a Tier 1 station (KEXP) is weighted differently than a Tier 3 college station.

Transparency: Every ranking calculation produces a fairness_summary (audit log) detailing why an artist ranked where they did.

3.2 Scoring Algorithm (v2.0)

Reference: Factors.json, Scoring.md

The score for an Entity $E$ in Week $W$ is calculated as:

$$Score(E, W) = \sum (Factor_{raw} \times Normalizer \times Weight \times Cap)$$

3.2.1 Active Factors

Data is pulled from cdm_spins, smr_ingestions, and cdm_analytics.

Factor Key

Source

Granularity

Normalizer

Weight Config Key

Spins

cdm_spins

Per Station

per_station_z

RADIO_SPINS_WEIGHT

SMR Spins

smr_ingestions

Per Track

log1p_scale

SMR_SCORE_WEIGHT

Streams

Spotify API

Per Track

log1p_scale

ARTIST_VIDEO_PLAY_WEIGHT

Social

Meta/TikTok APIs

Granular

log1p_scale

(See Below)

Views

Internal

Page Hits

log1p_scale

ARTIST_VIEW_WEIGHT

Commerce

Stripe/Printful

Unit Sales

linear

ARTIST_MERCH_SALE_WEIGHT

3.2.2 Granular Social Weights

Instead of a generic "Social Score", we apply specific weights to raw metrics found in Factors.json:

FACEBOOK_PAGE_POST_ENGAGEMENTS_WEIGHT

FACEBOOK_PAGE_LIFETIME_ENGAGED_FOLLOWERS_UNIQUE_WEIGHT

SOCIAL_SHARE_WEIGHT

SOCIAL_COMMENTS_WEIGHT

3.2.3 Caps & Dampeners

To prevent "gaming" (e.g., buying 10,000 bot views):

Percentile Cap: No single factor can exceed the 98th percentile of the population's score for that factor.

Velocity Dampening: Sudden spikes (>300% week-over-week) trigger an "Anomaly" flag and are capped until manual review.

3.3 The SMR Ingestion Pipeline

Targeting the "Erik" Workflow

Since Secondary Market Radio (SMR) data arrives via Excel/CSV, we treat ingestion as a semi-automated pipeline.

Upload: Admin (or Erik) uploads CSV to POST /api/v1/smr/upload.

Parsing: System detects headers using fuzzy matching (Levenshtein distance) against known schemas (Artist, Title, Spins, TW, LW).

Mapping:

Incoming Artist Name -> cdm_artists.name (Exact Match).

Incoming Artist Name -> cdm_identity_map (Alias Match).

Unmatched: Flagged for "Manual Resolution" in Admin UI.

Commit: Once resolved, data is written to smr_ingestions and cdm_chart_entries (Chart Slug: smr:legacy).

3.4 Compute Schedule

Reference: CronSettings.md

Real-time: Raw spins and views are ingested continuously.

Hourly: "Trend" scores (lightweight calc) run to show "Up/Down" arrows.

Weekly (The Chart):

Trigger: Monday 06:00 AM (jobs/rankings/compute_weekly_ngn_score.php).

Process:

Lock Chart Window.

Run Normalizers.

Calculate Scores.

Generate fairness_summary.

Write to cdm_chart_entries.

Unlock & Publish.