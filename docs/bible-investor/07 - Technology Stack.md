# Chapter 07: Technology Stack & Architecture

## Executive Summary

NGN's architecture is **API-first, cloud-native, and scalable**: Backend handles business logic (rankings, payouts, rights); Frontend is a modern SPA that works on web, iOS, Android. This separation enables rapid feature deployment, third-party integrations, and white-label partnerships. Technology was deliberately chosen for **low operational cost** (no expensive licensing), **high reliability** (mature tools), and **fast hiring** (industry-standard stack). Total infrastructure cost per artist is sub-$0.10, enabling profitable business at scale.

---

## 1. Business Context

### 1.1 Why Architecture Matters to Investors

**Tech architecture is not just "how we build things"—it's a business constraint.**

**Bad architecture consequences**:
- Slow to add features (can't compete with Spotify)
- Expensive to run (operational costs kill margins)
- Hard to hire (nobody knows the tech stack)
- Fragile (crashes lose customer trust)

**Good architecture advantages**:
- Fast iteration (get features to market quickly)
- Cost-efficient (low operational overhead)
- Attractive to engineers (industry-standard tools)
- Reliable (built on proven technology)

**NGN chose the "boring" stack that works at scale.** Not bleeding-edge; proven.

### 1.2 Key Architectural Decisions

| Decision | Choice | Why? | Business Impact |
|----------|--------|------|-----------------|
| **Backend Language** | PHP 8.4 | Mature, stable, low cost | Reliable, fast hiring |
| **Database** | MySQL 8.0 | Battle-tested, transactional | Zero data loss, compliance-ready |
| **Frontend** | React + Vite | Industry standard, SPA | Fast iteration, modern UX |
| **Cloud Provider** | Flexible (AWS, DigitalOcean, self-hosted) | No vendor lock-in | Cost optimization, choice |
| **Authentication** | JWT | Stateless, scalable | No session management overhead |

**These choices optimize for: scalability, reliability, and cost.**

---

## 2. Backend Architecture: The Engine

### 2.1 Why PHP?

**Investors often ask**: "Why not Node.js/Python/Go?"

**Answer**: PHP is optimal for NGN's specific constraints.

**Advantages**:
- **Mature ecosystem**: 30+ years of battle-testing
- **Affordable hosting**: Cheapest per-transaction cost of any platform
- **Hiring pool**: 80%+ of web developers know PHP
- **Performance**: Modern PHP (8.4) is as fast as Go for I/O-bound operations
- **No vendor lock-in**: Runs anywhere (shared hosting → cloud → on-prem)

**Disadvantages**:
- Reputation: "Legacy language" (unfair, but common perception)
- Cultural: Less trendy than JavaScript/Python (doesn't matter to business)

**For NGN's use case** (high transaction volume, need to scale cheaply, need to hire quickly): PHP is optimal choice.

**Precedent**: YouTube (PHP initially), Facebook (PHP at scale), Slack (PHP backend), Etsy (PHP core). Proved it works.

### 2.2 Database: MySQL 8.0

**Why MySQL?**

- **Transactional integrity**: ACID guarantees = money is never lost
- **Affordable licensing**: Zero licensing cost (open source)
- **Proven at scale**: Used by Facebook, Google, Netflix (for non-core)
- **Compliance-ready**: Audit trails, backup recovery standard

**Alternative considered**: PostgreSQL
- Slightly more advanced, but NGN's queries don't need advanced features
- MySQL sufficient + cheaper operational cost

**Database design philosophy**:
- **Normalized schema** (no data duplication)
- **Clear relationships** (artist → songs → rights → payouts)
- **Audit trails** (every transaction logged, immutable)
- **Partition-ready** (can shard by artist_id at scale)

**Business impact**: Data integrity is non-negotiable (if artists lose payment records, platform dies). MySQL enforces this at database level.

### 2.3 Core Business Logic: Service-Oriented Architecture

**Backend is organized into logical services**:

```
Backend
├── AuthService (JWT, permissions)
├── RankingService (EQS calculation, chart generation)
├── RoyaltyService (payout calculation, splits)
├── RightsService (ownership verification)
├── SMRService (radio data ingestion)
├── ArtistService (profile, analytics)
├── PaymentService (Stripe integration)
└── NotificationService (email, push)
```

**Each service is independent**:
- Can be tested separately
- Can be scaled independently
- Can be deployed independently

**Business advantage**: Update RoyaltyService without touching RankingService. Reduce deployment risk.

### 2.4 Job Queue: Background Processing

**Time-consuming operations run asynchronously**:
- Ranking calculation (happens weekly, takes minutes)
- Payout processing (happens monthly, takes hours)
- SMR data ingestion (happens on-demand, takes minutes)
- Email notifications (happens real-time, but asynchronous)

**Architecture**:
- Client submits job
- Job queued immediately
- Client gets confirmation
- Job processes in background
- Client can check status anytime

**Business advantage**: Responsive system (users don't wait). Backend can handle heavy computational work without blocking.

---

## 3. Frontend Architecture: User Experience

### 3.1 Why React SPA?

**NGN Frontend is a Single Page Application (SPA)**:
- App loads once
- Subsequent navigation is instant (no page reload)
- Feels like native app (responsive, smooth)
- Works offline (with service worker)

**Technology choices**:
- **React**: Industry standard framework, massive hiring pool
- **Vite**: Fast build tool (3x faster than Webpack)
- **Tailwind CSS**: Utility-first CSS (rapid UI development)
- **Axios**: HTTP client with interceptors (handles auth tokens automatically)

**Why this stack?**
- React: Most popular UI framework (easy to hire)
- Vite: Fastest build tool (developer productivity)
- Tailwind: Fastest UI development (constraints → speed)
- Axios: Simple, reliable (not over-engineered)

### 3.2 Platform Coverage

**NGN must work on all platforms**:

```
Web
├── Desktop browser (Chrome, Firefox, Safari)
└── Mobile browser (iOS Safari, Chrome Mobile)

iOS Native
├── Built with React Native or native Swift
└── App Store distribution

Android Native
├── Built with React Native or native Kotlin
└── Google Play distribution
```

**Strategy**:
- **Phase 1 (2024-2025)**: Web + PWA (Progressive Web App) = works on mobile browser
- **Phase 2 (2026)**: Native iOS app (based on demand)
- **Phase 3 (2027)**: Native Android app (based on demand)

**PWA advantage**: App-like experience (installable, offline, push notifications) without native app development cost.

### 3.3 State Management: Zustand

**Frontend state is managed with Zustand** (lightweight state management):
- Store artists, songs, user data in memory
- Persist key data to localStorage
- Sync with backend via API

**Why Zustand (not Redux)?**
- Simpler API (easier for new developers)
- Less boilerplate (faster development)
- Smaller bundle size (faster load time)
- Adequate for NGN's complexity

**Business advantage**: Smaller code = faster load = better conversion = higher revenue.

### 3.4 Design System: Dark Mode + Spotify Aesthetic

**NGN's UI is inspired by Spotify**:
- Dark background (reduces eye strain)
- Accent color: `#1DB954` (Spotify green)
- Card-based layout (modular, scalable)
- Consistent typography (professional look)

**Design philosophy**:
- Artist-centric (not distracted by ads)
- Dark mode by default (signals premium feel)
- Minimal friction (clear CTA buttons)
- Fast load times (optimized assets)

**Business advantage**: Professional appearance builds trust. Artist perceives NGN as "serious platform" not "hobby project."

---

## 4. API Architecture: The Bridge

### 4.1 RESTful JSON API (v1)

**Backend exposes RESTful API**:

```
POST /api/v1/auth/login
GET /api/v1/artists
GET /api/v1/artists/{id}
GET /api/v1/artists/{id}/earnings
POST /api/v1/sparks
GET /api/v1/rankings/charts/{slug}/current
```

**Standards**:
- JSON only (no XML, no HTML)
- Standard HTTP methods (GET, POST, PUT, DELETE)
- Consistent error messages
- ISO 8601 timestamps

**Authentication**:
- JWT (JSON Web Token) via Bearer header
- Tokens expire (refresh token for extension)
- Role-based access control (Artist vs Venue vs Admin)

### 4.2 Why API-First Architecture?

**Separates concerns**:
- Backend: Business logic, data, security
- Frontend: UI/UX, user interaction
- Third parties: Can build their own UI on NGN's API

**Benefits**:
- **Rapid iteration**: Update backend without redeploying frontend
- **Multi-client support**: Web, iOS, Android all use same API
- **Third-party integrations**: Labels, aggregators, venues can integrate
- **White-label partnerships**: Other companies can reskin NGN

**Business advantage**: API is defensible asset. Once integrated, switching costs are high.

### 4.3 API Versioning

**Current version: v1**

**Strategy for v2** (if needed):
- Launch v2 alongside v1
- Support both for 2+ years
- Gradually migrate clients to v2
- Retire v1 once no longer used

**Business advantage**: Don't force clients to update (reduces friction). Support multiple versions (more stable partnerships).

---

## 5. Infrastructure: Where NGN Runs

### 5.1 Cloud Provider Flexibility

**NGN is not locked to single cloud**:

**Option 1: AWS (Production Today)**
- EC2 instances (app servers)
- RDS (managed MySQL)
- S3 (file storage)
- CloudFront (CDN)
- Cost: ~$X/month at current scale

**Option 2: DigitalOcean (Cost-Effective Alternative)**
- Droplets (app servers)
- Managed MySQL
- Spaces (S3-compatible storage)
- Cost: ~30% less than AWS

**Option 3: Self-Hosted (When Profitable)**
- Run on own servers (complete ownership)
- No cloud vendor fees
- Cost: Dramatically lower at scale

**Business advantage**: Multiple hosting options = cost optimization + vendor independence.

### 5.2 Scaling Architecture

**As users grow, architecture scales horizontally**:

```
2024 (2,847 artists):
  ├── 1 app server
  ├── 1 database server
  └── Total: ~$5K/month

2027 (10,000 artists):
  ├── 5 app servers (load balanced)
  ├── MySQL cluster (3 nodes)
  ├── Redis cache
  ├── CDN (CloudFront)
  └── Total: ~$15K/month

2030+ (50K+ artists):
  ├── 20+ app servers (auto-scaling)
  ├── Sharded MySQL (by region)
  ├── Elasticsearch (search)
  ├── CDN + edge cache
  └── Total: ~$40K/month
```

**Per-artist infrastructure cost**:
- 2024: $1.76/artist/month
- 2027: $1.50/artist/month (improved efficiency)
- 2030: $0.80/artist/month (scale economics)

**With artist LTV of $1,500+/3 years, infrastructure cost is tiny fraction of revenue.**

### 5.3 Disaster Recovery & Backup

**Data safety is critical** (artists' earnings = their livelihood):

**Backup strategy**:
- Hourly snapshots (last 24 hours recoverable)
- Daily backups (last 30 days recoverable)
- Weekly offsite backup (AWS S3 cross-region)
- Quarterly full backup retention (compliance)

**Recovery time objective (RTO)**: 1 hour
**Recovery point objective (RPO)**: 15 minutes

**Business advantage**: Artists trust NGN with their money. Proven backup/recovery plan is essential.

---

## 6. Security Architecture

### 6.1 Authentication & Authorization

**JWT (JSON Web Token) flow**:

1. Artist logs in → Backend generates JWT
2. JWT includes artist ID + permissions + expiry
3. Frontend stores JWT in secure cookie (httpOnly)
4. Every API request includes JWT in Authorization header
5. Backend verifies JWT signature (cannot forge)
6. Backend checks permissions (role-based access control)

**Benefits**:
- Stateless (no session management needed)
- Scalable (any server can verify token)
- Secure (cryptographic signature)
- Standard (industry best practice)

### 6.2 Data Protection

**In transit**:
- HTTPS only (no unencrypted connections)
- TLS 1.3 (latest encryption standard)
- Strict security headers (prevent common attacks)

**At rest**:
- Password hashing: Argon2id (industry standard)
- Sensitive data: Encrypted at field level (GDPR compliance)
- Audit logs: All access tracked and immutable

**API Security**:
- Rate limiting (prevent API abuse/DDoS)
- Input validation (prevent SQL injection, XSS)
- CORS (prevent cross-site attacks)
- CSP (prevent malicious scripts)

### 6.3 Compliance

**Standards NGN adheres to**:
- **PCI DSS** (payment processing): Required for Stripe integration
- **SOC 2 Type II** (operational controls): Plan to achieve by 2025
- **GDPR** (EU privacy): Full compliance for European users
- **CCPA** (California privacy): Full compliance for US users

**Business advantage**: Institutional-grade security enables enterprise partnerships (labels, venues).

---

## 7. Monitoring & Observability

### 7.1 Uptime Monitoring

**NGN must be reliable** (artists' payouts depend on platform availability):

**Monitoring stack**:
- Uptime tracking: 99.9% SLA target (11.6 hours/year downtime acceptable)
- Error tracking: Sentry (captures all exceptions)
- Performance monitoring: New Relic (tracks page load, API response time)
- Log aggregation: CloudWatch (centralized logs)

**Alerts**:
- Service down → Page ops immediately
- API response time > 1s → Alert
- Error rate > 1% → Alert
- Database connection pool exhausted → Alert

**Business advantage**: Proactive monitoring prevents downtime before users notice.

### 7.2 Performance Optimization

**Key metrics tracked**:
- API response time (target: <200ms p99)
- Page load time (target: <2s on 4G)
- Chart calculation time (target: <5 minutes weekly)
- Payout processing time (target: <2 hours monthly)

**Optimization techniques**:
- Database indexing (faster queries)
- Query caching (Redis)
- CDN for static assets (faster delivery)
- API rate limiting (prevent abuse)

**Business advantage**: Fast system = happy users = higher engagement = more revenue.

---

## 8. Development Workflow

### 8.1 Code Organization

**Clean separation of concerns**:

```
ngn_202/
├── api/                    # Public API endpoints
│   └── v1/
│       ├── artists/
│       ├── rankings/
│       ├── payments/
│       └── admin/
├── lib/                    # Business logic
│   ├── Services/          # RankingService, RoyaltyService, etc.
│   ├── Domain/            # Data models
│   ├── DB/                # Database abstraction
│   └── Utils/             # Helpers
├── public/admin-v2/       # React frontend
│   ├── src/
│   │   ├── pages/
│   │   ├── components/
│   │   ├── services/
│   │   └── styles/
│   ├── package.json
│   └── vite.config.ts
├── docs/                   # Documentation (This Bible)
└── tests/                 # Test suite
```

### 8.2 Testing Strategy

**Test pyramid** (focus on automated testing):
- **Unit tests** (80%): Test individual functions/methods
- **Integration tests** (15%): Test API endpoints
- **E2E tests** (5%): Test full workflows

**Target**: 80%+ code coverage (high confidence in deployments)

### 8.3 Deployment

**CI/CD pipeline** (automated deployment):

```
Developer pushes code to GitHub
  ↓
Automated tests run (fail = rollback)
  ↓
Code review (another dev approves)
  ↓
Tests pass + reviewed = auto-deploy to staging
  ↓
Manual smoke test (basic checks)
  ↓
Approved = auto-deploy to production
```

**Deployment frequency**: 5-10x per week (rapid iteration)
**Rollback capability**: Any bad deployment reverted in <5 minutes

**Business advantage**: Fast, safe deployments = features reach users quickly + reduced risk.

---

## 9. Technical Debt & Future Evolution

### 9.1 Known Limitations (Acceptable Today)

**Limitation 1**: SMR ingestion is semi-manual
- Current state: Admins upload CSV, system parses, humans resolve ambiguous artist names
- Future state: Fully automated with AI disambiguation
- Timeline: 2026
- Impact: Limits scale, but acceptable at current volume

**Limitation 2**: Ranking calculation is batch (weekly)
- Current state: Charts update weekly
- Future state: Real-time or hourly updates
- Timeline: 2026 (requires more sophisticated infrastructure)
- Impact: Good enough for artist needs (weekly chart sufficient)

**Limitation 3**: No international payment support
- Current state: Payments via Stripe (supports 135+ countries)
- Future state: Local payment methods (alipay, paytm, bkash) by region
- Timeline: 2027
- Impact: Limits non-US artist adoption, but US/EU sufficient for 2026

### 9.2 Roadmap: Technical Evolution

**2025: Optimization Phase**
- 🔧 Database query optimization (10x faster queries)
- 🔧 API response time improvements (sub-100ms p99)
- 🔧 Frontend performance tuning (faster load times)

**2026: Scale Phase**
- 🔧 Database sharding (horizontal scaling)
- 🔧 Kubernetes (container orchestration)
- 🔧 Microservices (break monolith into services)

**2027: Advanced Phase**
- 🔧 AI-powered recommendations (ML models)
- 🔧 Real-time ranking (live chart updates)
- 🔧 Blockchain ledger (immutable rights records)

---

## 10. Competitive Technical Advantages

| Aspect | Spotify | Apple | YouTube | NGN |
|--------|---------|-------|---------|-----|
| **Update Frequency** | Continuous | Daily | Real-time | Weekly (sufficient) |
| **API Availability** | Restricted | No public API | Limited | Full, documented ✅ |
| **Uptime SLA** | 99.95% | 99.9% | 99.9% | 99.9% target ✅ |
| **Payment Integration** | Proprietary | Proprietary | Proprietary | Stripe (standard) ✅ |
| **Cost Per Transaction** | Proprietary | Proprietary | Proprietary | <$0.20 ✅ |
| **Hosting Flexibility** | AWS only | AWS only | GCP only | Any cloud ✅ |

**NGN's technical advantages**:
- Open API (partners can build on it)
- Cost-effective (no expensive licensing)
- Flexible hosting (avoid vendor lock-in)
- Rapid iteration (proven deployment pipeline)

---

## 11. Technology Risk Assessment

### 11.1 Risk: Technology Becomes Obsolete

**Risk**: PHP falls out of favor; becomes hard to hire developers

**Mitigation**:
- PHP has 30-year track record (won't disappear)
- Can transition to Python/Go if needed (logic is in services, not language)
- 80%+ of web developers know PHP (hiring not a constraint)

**Probability**: Very low

### 11.2 Risk: Infrastructure Costs Exceed Revenue

**Risk**: As NGN scales, cloud costs grow faster than revenue

**Mitigation**:
- Infrastructure cost is $0.80/artist/month at 50K artists
- Artist LTV is $1,500+/3 years = $41.67/month
- Infrastructure is tiny fraction of revenue (1.9%)

**Probability**: Very low

### 11.3 Risk: Major Security Breach

**Risk**: Hacker steals artist payment data

**Mitigation**:
- PCI DSS compliance (prevents payment data breach)
- Regular security audits (third-party validation)
- Insurance (cyber liability coverage)

**Probability**: Low (compliance + insurance covers)

---

## 12. Conclusion: Boring, Proven Technology Wins

**NGN's technology stack is intentionally "boring":**
- Not trendy (boring = stable)
- Not novel (boring = proven)
- Not cutting-edge (boring = reliable)

**This is optimal for:**
✅ Rapid scaling (need proven tools)
✅ Cost efficiency (avoid experimental tech licensing)
✅ Team hiring (industry-standard stack)
✅ Reliability (boring = battle-tested)

**Investor takeaway**: NGN's technical foundation is solid, scalable, and sustainable. Technology will not be a constraint on growth.

---

## 13. Read Next

- **Chapter 08**: Core Data Model (How NGN organizes data)
- **Chapter 09**: Ranking Engine (How the algorithm works technically)
- **Chapter 10**: API Strategy (How partners integrate)

---

*Related Chapters: 06 (Product Overview), 08 (Core Data Model), 09 (Ranking Engine), 10 (API Strategy), 13 (Royalty System)*
