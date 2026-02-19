# Appendix A: Technical Architecture Deep-Dive

## For Technical Investors & CTOs

This appendix provides engineering-level detail on NGN's architecture (readers: skip if non-technical)

### System Topology

```
Frontend (React SPA)           API Layer (PHP)              Backend Services
├─ Web (Vite)                 ├─ v1 REST endpoints        ├─ RankingService
├─ iOS (React Native)         ├─ JWT auth                 ├─ RoyaltyService
└─ Android (React Native)     ├─ Rate limiting            ├─ RightsService
                              └─ Error handling            ├─ ArtistService
                                                          └─ NotificationService

                              ↓↓↓ (API calls)

Database Layer (MySQL 8.0)    Cache Layer (Redis)
├─ cdm_users                  ├─ Charts (hourly)
├─ cdm_artists                ├─ Session tokens
├─ cdm_songs                  └─ Ranking scores
├─ cdm_spins
├─ cdm_rights_ledger
├─ cdm_royalty_transactions
└─ cdm_engagements
```

### Data Flow Example: Artist Posts Song

```
1. Artist uploads via React → POST /api/v1/artists/{id}/posts
2. API validates + stores in db
3. Notification service sends push to followers
4. Engagement signals trigger (new post = +1 signal)
5. Ranking service adds to next calculation
6. Cache invalidated (new charts coming)
```

### Scaling Numbers

**Current scale (2024)**:
- 2,847 artists, 50K songs, 1M monthly events
- Database: 50GB, queries <100ms p99
- API: <200ms response time p99
- Deploy frequency: 5-10x per week

**Target scale (2027)**:
- 10,000 artists, 500K songs, 50M monthly events
- Database: 500GB (needs sharding by 2026)
- API: <200ms response time p99 (maintained)
- Deploy frequency: 5-10x per week (maintained)

### Technology Choices Rationale

**PHP**: Mature, scalable (Facebook, Slack use it), easy hiring, low operational cost

**MySQL**: ACID transactions (artist money = critical), proven at scale, no licensing cost

**React**: 80% of web devs know it, component reusability, strong hiring pool

**Vite**: 3x faster builds than Webpack, modern, Airbnb/Netflix use it

### Performance Optimization

**Database**:
- Indexing strategy (all foreign keys + rankings indexed)
- Query optimization (N+1 queries eliminated)
- Caching (Redis for hot data)

**API**:
- Pagination (don't load 10K results)
- Compression (gzip all responses)
- CDN (CloudFront for static assets)

**Frontend**:
- Code splitting (load features on-demand)
- Image optimization (WebP, lazy loading)
- Tree shaking (remove unused code)

### Deployment Pipeline

```
Developer pushes → GitHub → CircleCI (tests)
    ↓ (if all tests pass)
Auto-merge to staging → Deploy to staging (5 min)
    ↓ (manual smoke test)
Approved → Deploy to production (10 min)
    ↓
Monitoring (CloudWatch, Sentry)
    ↓ (if errors spike)
Auto-rollback (revert to previous version)
```

### Reliability Targets

- **Uptime**: 99.9% SLA (11.6 hours downtime/year allowed)
- **RTO** (Recovery Time Objective): 1 hour (restore service)
- **RPO** (Recovery Point Objective): 15 minutes (no data loss beyond 15 min)
- **Latency**: <200ms p99 (user experience)

### Security Architecture

- **In Transit**: HTTPS/TLS 1.3 (all connections encrypted)
- **At Rest**: Field-level encryption (sensitive artist data)
- **Auth**: JWT (stateless, scalable)
- **API Rate Limiting**: 10,000 req/day per user (prevent abuse)

---

### For CTOs: Technical Concerns & Answers

**Q: Can this scale to 100K artists?**
A: Yes. Need database sharding (by artist_id) by 50K artists. Estimated 2027 Q2.

**Q: What's the tech debt?**
A: (1) SMR ingestion semi-manual (50 hours/month), (2) Ranking calc is batch (not real-time), (3) No international payment support (yet).

**Q: How many engineers needed by 2027?**
A: 12 (4 backend, 3 frontend, 2 mobile, 1 DevOps, 1 PM, 1 QA). Budget: $2M/year salaries + benefits.

**Q: What's the biggest technical risk?**
A: Database performance at 500GB scale. Mitigation: Proactive sharding, load testing in 2026.

---

This appendix provides sufficient technical depth for engineering-focused investors; full architecture details available upon NDA.
