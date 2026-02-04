# NGN 2.0 — Role‑Driven Roadmap (Live Skeleton)

This roadmap mirrors the Roles in the platform. Each role lists the core deliverables: Database, API, Frontend (App), and Admin/Tools. Check items move to Done when shipped.

Note: Live status also available at /admin/progress.php

## Administrator
- [ ] Admin settings hardening (feature flags, maintenance) — API/UI
- [ ] Update Database console presets (Core/Shards/ETL/Checks)
- [ ] Access controls & RBAC overview page

## Artist
- [ ] DB: ngn_2025.artists exists and seeded
- [ ] API: GET /artists, /artists/{id}
- [ ] App: Artists list (cards), Artist detail (top tracks, releases)

## Label
- [ ] DB: ngn_2025.labels exists and seeded
- [ ] API: GET /labels, /labels/{id}, /labels/{id}/artists
- [ ] App: Labels list (cards), Label detail (artists)

## Station
- [ ] DB: ngn_2025.stations exists and seeded
- [ ] Shard: spins_2025.station_spins exists
- [ ] API: GET /stations, /stations/{id}, /stations/{id}/spins
- [ ] App: Stations list (cards), Station detail (recent spins)

## Venue
- [ ] DB: ngn_2025.venues exists and seeded
- [ ] API: GET /venues, /venues/{id}
- [ ] App: Venues list (cards), Venue detail (events)

## Writer / Editor / Contributor / Moderator
- [ ] DB: ngn_2025.writers exists
- [ ] API: /posts editorial endpoints (list/detail, status)
- [ ] App: Posts list (cards), Post detail (rich)
- [ ] Admin: moderation tools basics
- [ ] AI: Writer console assistant (draft chart write-ups, posts, campaigns; human-in-the-loop)



## Readers / VIPs (audiences)
- [ ] App: Landing, Charts, SMR Charts
- [ ] Library: favorites, follows, history
- [ ] Playlists: create, add/remove tracks

## Advertisers
- [ ] Media kit page/section
- [ ] Role dashboards: basic analytics panels + "Suggest a post" / "Draft campaign" (powered by AI) for Artists, Labels, Venues, Stations


- [ ] Campaign intake form (admin‑reviewed)

## Charts (Rankings)
- [ ] DB: rankings_2025 (windows/items) exists
- [ ] API: /rankings/artists|labels
- [ ] App: Charts view (intervals), deltas

## SMR
- [ ] DB: smr_2025 (smr_chart) exists
- [ ] API: /smr/charts (bridge → native)
- [ ] App: SMR charts with date navigator

## Media & Playback
- [ ] DB: ngn_2025.media_assets, playback_events
- [ ] API: media serving, playback events ingest
- [ ] App: PlayerBar → Now Playing, queue

---

Backlog hygiene: keep tasks small, link code/migrations, and mark done with commit/migration id.
