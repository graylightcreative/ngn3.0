# NGN 2.0 Monetization & Ticketing Plan 2025

## EXECUTIVE SUMMARY

NGN has a solid foundation with Stripe Connect, ticket infrastructure, and subscription tiers. This plan focuses on:

1. **IMMEDIATE (Phase 1)**: Surface existing subscription tiers & upsell opportunities across all profiles
2. **Q1 2025 (Venue Tickets)**: Launch venue-hosted ticket sales with analytics & revenue tracking
3. **Q2 2025 (Artist PPV)**: Plan infrastructure for artist PPV events (launch 2027)
4. **Infrastructure**: Build CDN/load balancing management dashboard

---

## PHASE 1: TIER COMPARISON & PROFILE UPSELLS (Week 1-2)

### 1.1 Tier Comparison Page

**Route**: `/?view=pricing` or dedicated `/pricing.php`

**Components**:

```
┌─────────────────────────────────────────────────────────────────┐
│                    NGN TIER COMPARISON                           │
├──────────────┬──────────────┬──────────────┬──────────────────┤
│ Free         │ Pro          │ Premium      │ Enterprise       │
│ $0/mo        │ $9.99/mo     │ $24.99/mo    │ Custom           │
├──────────────┼──────────────┼──────────────┼──────────────────┤
│              │              │              │                  │
│ Basic        │ + Advanced   │ + White-     │ + Custom         │
│ Profile      │   Analytics  │   Label      │   Integration    │
│              │ + Priority   │ + API Access │ + Dedicated      │
│              │   Support    │ + No Ads     │   Support        │
│              │              │              │                  │
│ [Sign Up]    │ [Upgrade]    │ [Upgrade]    │ [Contact Sales]  │
└──────────────┴──────────────┴──────────────┴──────────────────┘
```

**Features by Tier**:

| Feature | Free | Pro | Premium | Enterprise |
|---------|------|-----|---------|------------|
| **ARTISTS** |
| Basic Profile | ✓ | ✓ | ✓ | ✓ |
| Releases | ✓ | ✓ | ✓ | ✓ |
| Videos | ✓ | ✓ | ✓ | ✓ |
| Basic Analytics | ✗ | ✓ | ✓ | ✓ |
| Monthly Reports | ✗ | ✗ | ✓ | ✓ |
| Fan Tiers (Patreon-style) | ✗ | ✗ | ✓ | ✓ |
| API Access | ✗ | ✗ | ✓ | ✓ |
| **LABELS** |
| Basic Profile | ✓ | ✓ | ✓ | ✓ |
| Artist Roster | ✓ | ✓ | ✓ | ✓ |
| Release Management | ✗ | ✓ | ✓ | ✓ |
| Analytics Dashboard | ✗ | ✓ | ✓ | ✓ |
| White-Label Domain | ✗ | ✗ | ✓ | ✓ |
| Custom Branding | ✗ | ✗ | ✓ | ✓ |
| API Access | ✗ | ✗ | ✓ | ✓ |
| **VENUES** |
| Basic Profile | ✓ | ✓ | ✓ | ✓ |
| Create Events | ✓ | ✓ | ✓ | ✓ |
| Ticket Sales | ✓ | ✓ | ✓ | ✓ |
| Basic Analytics | ✗ | ✓ | ✓ | ✓ |
| Revenue Reports | ✗ | ✗ | ✓ | ✓ |
| Coupon/Discount Codes | ✗ | ✗ | ✓ | ✓ |
| **STATIONS** |
| Basic Profile | ✓ | ✓ | ✓ | ✓ |
| Show Listings | ✓ | ✓ | ✓ | ✓ |
| Advanced Analytics | ✗ | ✓ | ✓ | ✓ |
| Listener Reports | ✗ | ✗ | ✓ | ✓ |
| API Access | ✗ | ✗ | ✓ | ✓ |

### 1.2 Profile Upsell Sections

Each profile (artist, label, venue, station) will have:

```
┌─ UPGRADE BANNER ─────────────────────────────┐
│                                              │
│  🚀 Unlock Advanced Features                 │
│  Upgrade to Pro for analytics, priority      │
│  support & more                              │
│                                              │
│  [View Plans]  [Upgrade Now]                 │
└──────────────────────────────────────────────┘

┌─ TIER BADGE ──────────────────────────────────┐
│ You're on: Free Plan                          │
│ [Upgrade to Pro for $9.99/mo]                 │
│                                               │
│ • Get basic analytics                         │
│ • Priority email support                      │
│ • Remove NGN ads from profile                 │
└───────────────────────────────────────────────┘

┌─ FEATURE LOCK BADGES ────────────────────────┐
│ 📊 Analytics Dashboard                        │
│ [Upgrade to unlock]                           │
│                                               │
│ 📈 Advanced Reports                           │
│ [Upgrade to unlock]                           │
└──────────────────────────────────────────────┘
```

### 1.3 Feature Gating

**Free tier limitations**:
- Can view own analytics (last 7 days only)
- Cannot export reports
- Cannot create custom domain
- Cannot access API
- NGN branding on all content
- No priority support (30-day response SLA)

**Pro tier unlock**:
- Full analytics history
- Export to CSV/PDF
- Remove NGN ads
- Standard API access (1000 requests/day)
- 7-day response SLA

**Premium tier unlock**:
- Advanced analytics (cohort analysis, trends)
- White-label custom domain
- Premium API access (10,000 requests/day)
- Enhanced API features (webhooks, batching)
- 24-hour response SLA

---

## VENUE TICKETING SYSTEM (Q1 2025)

### 2.1 Current Infrastructure

**Already Implemented**:
- ✅ Events table (venues, dates, capacity, location)
- ✅ Tickets table (QR codes, status tracking, Stripe integration)
- ✅ EventService & TicketService (core logic)
- ✅ Bouncer mode (offline ticket scanning)
- ✅ Stripe Connect (venue payouts)

**Missing**:
- ❌ UI to sell tickets on venue profile
- ❌ Ticket purchase flow/checkout
- ❌ Venue analytics dashboard
- ❌ Ticket revenue reporting

### 2.2 Venue Profile Enhancement

On each venue page (`/?view=venue&slug=venue-name`):

```
┌─ VENUE PROFILE ──────────────────────────────┐
│ [Image] Venue Name                           │
│ Address | Capacity: 500                      │
│                                              │
│ [Website] [Facebook] [Instagram] [Phone]    │
└──────────────────────────────────────────────┘

┌─ UPCOMING EVENTS ────────────────────────────┐
│                                              │
│ 📅 Mar 15 - The Darkness                    │
│    Doors: 8pm | Capacity: 250/500           │
│    [General $25] [VIP $50]                  │
│    [Buy Tickets] [Learn More]               │
│                                              │
│ 📅 Mar 22 - Dropkick Murphys               │
│    Doors: 7pm | Capacity: 450/500           │
│    [General $35] [VIP $75]                  │
│    [Buy Tickets] [Learn More]               │
│                                              │
│ [View All Events →]                         │
└──────────────────────────────────────────────┘

┌─ TICKET TIERS ───────────────────────────────┐
│ • General Admission                          │
│ • VIP (Front Row + Merch)                   │
│ • Early Bird (Discount)                     │
│ • Student (with ID)                         │
│ • Comp (Free for performers)                │
└──────────────────────────────────────────────┘

┌─ VENUE STATS (if Pro/Premium) ───────────────┐
│ Total Events Hosted: 24                      │
│ Tickets Sold: 4,250                         │
│ Revenue: $145,000                           │
│ Avg Attendance: 177                         │
│ [View Dashboard →]                          │
└──────────────────────────────────────────────┘
```

### 2.3 Ticket Purchase Flow

```
FLOW:
1. User clicks [Buy Tickets] on event
2. Select ticket tier & quantity
3. Add to cart (show remaining inventory)
4. Checkout via Stripe
5. Receive QR-code ticket via email
6. Day-of: Scan at door with bouncer mode
7. Post-event: Download ticket receipt
```

**Checkout Page** (`/checkout/tickets/{event_id}`):

```
┌─────────────────────────────────────────────┐
│           TICKET CHECKOUT                   │
├─────────────────────────────────────────────┤
│                                             │
│ Event: The Darkness                         │
│ Date: March 15, 2025 @ 8pm                 │
│ Venue: Fillmore [Address]                   │
│                                             │
│ Ticket Selection:                           │
│ ☐ General Admission - $25 x [1] qty        │
│ ☐ VIP (Front Row) - $50 x [1] qty          │
│                                             │
│ Subtotal:           $25.00                  │
│ Service Fee (2.9%): $0.73                  │
│ Tax (8.875%):       $2.29                  │
│ ─────────────────────────────────           │
│ Total:              $28.02                  │
│                                             │
│ [Continue to Payment]                       │
│                                             │
│ ℹ️ Tickets sent to email (no printing!)     │
└─────────────────────────────────────────────┘
```

### 2.4 Venue Dashboard Features

**Route**: `/dashboard/venue/tickets` (new page)

```
┌─────── VENUE DASHBOARD: TICKETS ──────────────┐
│                                               │
│ 📊 QUICK STATS                               │
│ ┌──────────┬──────────┬──────────┬──────────┐
│ │ Tickets  │ Revenue  │ Attendance│ Cap. %  │
│ │ Sold: 127│ $4,250   │ 89%      │ 62%    │
│ └──────────┴──────────┴──────────┴──────────┘
│                                               │
│ 📈 SALES BY EVENT                            │
│ ┌────────────────────────────────────────────┐
│ │ Event Name       │ Sold │ Revenue │ Status │
│ ├────────────────────────────────────────────┤
│ │ The Darkness     │ 127  │ $4,250  │ Live   │
│ │ Dropkick M.      │ 45   │ $1,575  │ Active │
│ │ Foo Fighters     │ 0    │ $0      │ Draft  │
│ └────────────────────────────────────────────┘
│                                               │
│ 💰 REVENUE BREAKDOWN                         │
│ ┌────────────────────────────────────────────┐
│ │ Gross Revenue:    $5,825                   │
│ │ Service Fees:     -$169 (2.9%)            │
│ │ Payout (70%):     $3,947                   │
│ │ NGN Cut (30%):    $1,697                   │
│ │ Status: Pending payout (Friday)            │
│ └────────────────────────────────────────────┘
│                                               │
│ 🎟️ INVENTORY MANAGEMENT                     │
│ ┌────────────────────────────────────────────┐
│ │ [+ New Event]  [Tier Settings]  [Reports] │
│ └────────────────────────────────────────────┘
│                                               │
└───────────────────────────────────────────────┘
```

### 2.5 Tier Benefits for Venues

| Feature | Free | Pro ($19.99/mo) | Premium ($49.99/mo) |
|---------|------|---|---|
| Host Events | ✓ | ✓ | ✓ |
| Ticket Sales | ✓ | ✓ | ✓ |
| Venues Keep | 65% | 70% | 75% |
| Events Hosted | Unlimited | Unlimited | Unlimited |
| Analytics | Basic | Full | Full + Export |
| Coupons/Discounts | ✗ | ✓ | ✓ |
| Custom Domain | ✗ | ✗ | ✓ |
| Priority Support | ✗ | ✓ | ✓ |

---

## PPV PLANNING (2027)

### 3.1 Future Artist PPV Architecture

**NOT LAUNCHING UNTIL 2027** - Just planning:

```
ARTIST PPV EVENT FLOW:
1. Artist creates "Virtual Event" (concert, Q&A, exclusive release)
2. Sets price: $9.99-$49.99 per view
3. Streams via HLS/RTMP infrastructure (TBD 2026)
4. Customers purchase ticket via checkout
5. Get unique viewing link for event time
6. Watch live stream with chat (optional)
7. Post-event: Can rewatch for 30 days (optional)

ARTIST EARNINGS:
- Artists Keep: 60%
- NGN/Processing: 40%
  - Stripe: 2.9% + $0.30
  - Infrastructure: 15%
  - NGN Platform: 22.1%

EXAMPLE:
- Artist charges $19.99
- 500 viewers = $9,995 gross
- Artist earns: $5,997
- NGN earns: $3,998
```

### 3.2 Live Streaming Infrastructure (2026)

**Required for artist PPV**:
- HLS ingest points (multiple regions)
- Adaptive bitrate encoding
- CDN delivery
- Failover/redundancy
- Chat/interactive features (optional)
- VOD archiving

**Providers to evaluate**:
- Wowza Streaming Cloud
- AWS Elemental MediaLive
- Mux (simpler, higher cost)
- OVP (On-Premise, expensive but flexible)

---

## REVENUE PROJECTIONS

### Phase 1 (Tier Comparison + Upsells)

**Conservative First Month**:
- 1,000 artists × 3% conversion to Pro = 30 @ $9.99/mo = **$300/mo**
- 300 labels × 5% conversion to Pro = 15 @ $29.99/mo = **$450/mo**
- Subtotal: **$750/mo first month**

**Month 3-6 (After launch):**
- Improved messaging = 8-10% artist conversion = **$1,200/mo**
- Improved messaging = 10-15% label conversion = **$900/mo**
- Subtotal: **$2,100/mo**

### Phase 2 (Venue Ticketing)

**Conservative Projection**:
- 50 active venues hosting events
- Avg 2 events/month = 100 events
- Avg 200 tickets/event @ avg $30 = $6,000/event
- Total: 100 events × $6,000 = **$600,000/mo gross**
- NGN cut (30%): **$180,000/mo**

**More Realistic (Year 2)**:
- 200 venues, 4 events/month = 800 events
- $600,000 × 8 = **$4,800,000/mo gross**
- NGN cut: **$1,440,000/mo**

### Phase 3 (Artist PPV - 2027)

**Conservative Projection**:
- 50 artists doing PPV events/month
- Avg 300 viewers @ $19.99 = $5,997/event
- 50 × $5,997 = **$299,850/mo gross**
- NGN cut (40%): **$119,940/mo**

**Year 2 (2028)**:
- 500 artists, multiple events
- **$1,200,000+/mo gross**
- NGN cut: **$480,000+/mo**

---

## IMPLEMENTATION ROADMAP

### WEEK 1-2 (Phase 1)
- [ ] Design pricing page UI
- [ ] Build tier comparison component
- [ ] Add upgrade CTAs to profiles
- [ ] Create feature-lock system
- [ ] Test paywall logic

### WEEK 3-4 (Venue Tickets)
- [ ] Build "Buy Tickets" button on event pages
- [ ] Create ticket checkout flow
- [ ] Build venue dashboard
- [ ] Integrate payout tracking
- [ ] QA ticket purchase end-to-end

### MONTH 2 (Polish & Launch)
- [ ] Email campaign for upgrades
- [ ] Analytics tracking (conversion rates)
- [ ] Customer support docs
- [ ] Launch Phase 1 (pricing page + upsells)
- [ ] Launch Phase 2 (venue ticketing)

### 2026 (Streaming Prep)
- [ ] Evaluate streaming platforms
- [ ] Build live stream infrastructure
- [ ] Test HLS/RTMP ingest
- [ ] Develop VOD archiving

### 2027 (Artist PPV)
- [ ] Launch artist PPV system
- [ ] Marketing campaign
- [ ] Initial artist onboarding

---

## PPV EVENT STRUCTURE (Planned 2027)

### Artist-Hosted PPV Events

**Architecture Overview**:

```
┌─────────────────────────────────────────────────────────────┐
│                   ARTIST PPV EVENT FLOW                      │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  Artist Creates Event   → Sets Price ($15-50)                │
│  ↓                                                             │
│  Configures Stream     → Test HLS/RTMP Ingress                │
│  ↓                                                             │
│  Sells Tickets         → Dynamic pricing, bundles              │
│  ↓                                                             │
│  Goes Live             → NGN handles video delivery            │
│  ↓                                                             │
│  Revenue Split         → 70% artist, 30% NGN                  │
│                          (minus Stripe fees ~2.9%)             │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

### Event Configuration

**What Artists Control**:

1. **Event Details**
   - Title, description, banner image
   - Start time, duration estimate
   - Ticket tiers (GA, VIP, Meet & Greet, etc.)
   - Capacity/ticket limits

2. **Pricing Strategy**
   - Base price ($15-50 range)
   - Early bird pricing (30 days out)
   - VIP tier with premium pricing
   - Group discounts
   - Pay-what-you-want option

3. **Access Control**
   - Fan tier gating (fans-only, tier 2+, etc.)
   - Geographic restrictions
   - Rewatch rights (24hrs, 7 days, forever)
   - Download availability

4. **Marketing**
   - Pre-event countdown
   - Social media integration
   - Email notification to fans
   - Notification bell for followers

### Ticket Types (PPV)

```
┌─────────────────────────────────────────────────────────────┐
│                 ARTIST PPV TICKET TIERS                       │
├──────────────┬──────────┬──────────┬─────────────────────────┤
│ Tier         │ Price    │ Qty      │ Benefits                │
├──────────────┼──────────┼──────────┼─────────────────────────┤
│ General      │ $29.99   │ 5000     │ Stream access           │
│              │          │          │ 24hr rewatch            │
│              │          │          │                         │
│ VIP          │ $79.99   │ 500      │ Above +                 │
│              │          │          │ 1:1 chat during stream  │
│              │          │          │ Digital poster (NFT?)   │
│              │          │          │ 30 days rewatch         │
│              │          │          │                         │
│ Meet & Greet │ $199.99  │ 50       │ Above +                 │
│ (Premium)    │          │          │ Virtual meet & greet    │
│              │          │          │ Exclusive merch codes   │
│              │          │          │ Forever rewatch         │
│              │          │          │ Special Discord role    │
│              │          │          │                         │
│ Bundle       │ $99.99   │ 500      │ GA ticket +             │
│ (GA + Merch) │          │          │ $50 merch credit        │
│              │          │          │ 7 days rewatch          │
└──────────────┴──────────┴──────────┴─────────────────────────┘
```

### Revenue Model (PPV)

**Price Example: $29.99 Ticket**

```
Total Ticket Price:        $29.99
├─ Stripe Processing Fee:  -$0.87 (2.9% + $0.30)
├─ Artist PPV Fee:         -$9.00 (30% of $30)
└─ Artist Payout:          +$20.12

Monthly PPV at 1000 tickets:
├─ Gross Revenue:          $29,990
├─ Stripe Fees:            -$870
├─ NGN Revenue:            -$9,000
└─ Artist Payout:          +$20,120
```

### PPV Event Lifecycle

**7 Days Before**:
- Artist finalizes details
- Marketing assets locked in
- Email to fanbase begins
- 3-day early bird pricing starts

**2 Days Before**:
- Stream test runs
- Backup ingress configured
- Support team on alert
- Last marketing push

**Event Day**:
- Pre-event wait room (30 min early)
- Technical support monitoring
- Real-time analytics dashboard
- Chat moderation active

**Post-Event**:
- VOD available for rewatch (based on tier)
- Revenue dashboard updates
- Payout processed within 3-5 business days
- Post-event metrics email

---

## VENUE TIER BENEFITS & PRICING

### Ticketing Feature Overview by Tier

```
┌────────────────────────────────────────────────────────────────┐
│           VENUE TIER BENEFITS - TICKETING & EVENTS              │
├──────────────┬──────────────┬──────────────┬──────────────────┤
│ Feature      │ Free         │ Pro          │ Premium          │
│              │ $0/mo        │ $19.99/mo    │ $49.99/mo        │
├──────────────┼──────────────┼──────────────┼──────────────────┤
│ Ticket Sales │ ✗            │ ✓ (90%*)     │ ✓ (95%*)         │
│ Capacity     │ —            │ 500/event    │ Unlimited        │
│              │              │ Limited to   │                  │
│              │              │ 4 events/mo  │                  │
│              │              │              │                  │
│ Ticket Types │ —            │ GA + VIP     │ Up to 5 tiers    │
│              │              │ (2 tiers)    │                  │
│              │              │              │                  │
│ Analytics    │ ✗            │ ✓ Basic      │ ✓ Advanced       │
│              │              │ (attendance, │ (revenue trends, │
│              │              │  revenue)    │  buyer analysis) │
│              │              │              │                  │
│ QR Entry     │ ✗            │ ✓ (online)   │ ✓ (online +      │
│              │              │              │ offline app)     │
│              │              │              │                  │
│ Payouts      │ —            │ Weekly       │ Daily            │
│              │              │ (7 days)     │ (next day)       │
│              │              │              │                  │
│ Support      │ Email        │ Email +      │ Priority         │
│              │ Community    │ Chat         │ Phone            │
│              │              │ (24 hrs)     │ Dedicated rep    │
│              │              │              │                  │
│ Integrations │ ✗            │ Ticketmaster │ All + custom API │
│              │              │ API (basic)  │ + webhooks       │
│              │              │              │                  │
│ Refunds      │ —            │ Fixed        │ Flexible         │
│              │              │ (until 24 hrs│ (until 48 hrs    │
│              │              │ before)      │ before + promos) │
│              │              │              │                  │
│ Email Blast  │ ✗            │ 2 campaigns  │ 5+ campaigns     │
│              │              │ /event       │ /event           │
│              │              │              │                  │
│ Dynamic Price│ ✗            │ ✗            │ ✓ (surge pricing,│
│              │              │              │ early bird %)    │
│              │              │              │                  │
│ Combo Tickets│ ✗            │ ✗            │ ✓ (GA+VIP+merch) │
│              │              │              │                  │
│ Fee Splitting│ —            │ NGN takes    │ Configurable     │
│              │              │ 10%          │ down to 5%       │
│              │              │              │                  │
│ Monthly Cost │ $0           │ $19.99       │ $49.99           │
│ Per Ticket   │ —            │ $0.50        │ $0.30            │
│ Platform Fee │              │ (to NGN)     │ (to NGN)         │
└──────────────┴──────────────┴──────────────┴──────────────────┘
```

*Revenue split: 90% Pro venues, 95% Premium venues (5-10% NGN platform fee)

### Venue Tier ROI Examples

**Free Tier Venue**:
- Monthly: $0 revenue
- Limitation: No online ticket sales
- Use case: Promotion/discovery only

**Pro Tier Venue** (~500 capacity, 4 events/month):
```
4 events × 300 avg attendance × $30 avg ticket = $36,000 gross
Revenue split (90% to venue):
├─ Stripe fees (-2.9%):              -$1,044
├─ NGN platform fee (-10%):          -$3,456
└─ Venue keeps:                      +$31,500

Net after monthly subscription:      $31,480 (at $19.99/mo)
```

**Premium Tier Venue** (unlimited capacity, advanced features):
```
8 events × 600 avg attendance × $40 avg ticket = $192,000 gross
Revenue split (95% to venue):
├─ Stripe fees (-2.9%):              -$5,568
├─ NGN platform fee (-5%):           -$9,600
└─ Venue keeps:                      +$176,832

Net after monthly subscription:      $176,582 (at $49.99/mo)
```

### Tier Upgrade Path

**Marketing to Venues**:

1. **Phase 1**: "Free tier forever" - no credit card needed
2. **Phase 2**: "Pro tier unlocked $3K/month for 1 event"
3. **Phase 3**: "Upgrade to Premium and keep 95% of ticket sales"
4. **Phase 4**: "Annual Premium" - save 2 months (pay $499.99/yr)

**Conversion Strategy**:
- In-app notification after first event created
- Email campaign: "See how much you could earn"
- A/B test pricing messages
- Free trial for Premium (first 30 days)

### Venue Tier Implementation Roadmap

**Q1 2025**:
- ✓ Basic infrastructure (Pro tier launch)
- ✓ Ticket creation/management UI
- ✓ QR code generation
- ✓ Simple analytics dashboard
- ✓ Stripe Connect integration

**Q2 2025**:
- Offline QR scanning app
- Advanced analytics (buyer segmentation)
- Dynamic pricing engine
- Combo ticket bundles
- Email blast campaigns

**Q3 2025**:
- Premium tier launch
- Dedicated support staff
- API access for custom integrations
- White-label options for enterprise

**Q4 2025**:
- International venue support
- Multi-currency support
- Custom branding
- Enterprise SLA agreements

---

## TECHNICAL NOTES

### Database Changes Needed

```sql
-- Venue tier preferences
ALTER TABLE venues ADD COLUMN tier_id INT;
ALTER TABLE venues ADD COLUMN stripe_payout_percentage INT DEFAULT 70;

-- Ticket tier pricing
ALTER TABLE tickets ADD COLUMN tier_name VARCHAR(50); -- 'general', 'vip', 'early_bird', 'student', 'comp'
ALTER TABLE tickets ADD COLUMN tier_price DECIMAL(8,2);

-- Event tier requirements
ALTER TABLE events ADD COLUMN min_tier_required VARCHAR(20) DEFAULT 'free'; -- free, pro, premium, enterprise

-- Venue analytics
CREATE TABLE venue_analytics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    venue_id INT,
    event_id INT,
    date DATE,
    tickets_sold INT,
    revenue DECIMAL(10,2),
    capacity_filled INT,
    avg_price DECIMAL(8,2),
    KEY (venue_id, date)
);

-- Revenue tracking
CREATE TABLE revenue_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    entity_type VARCHAR(50), -- 'artist', 'label', 'venue', 'station'
    entity_id INT,
    transaction_type VARCHAR(50), -- 'ticket_sale', 'subscription', 'ppv'
    gross_amount DECIMAL(10,2),
    fees DECIMAL(10,2),
    ngn_cut DECIMAL(10,2),
    entity_cut DECIMAL(10,2),
    payout_status VARCHAR(20), -- 'pending', 'scheduled', 'paid'
    stripe_payout_id VARCHAR(255),
    KEY (entity_type, entity_id, date)
);
```

### API Endpoints Needed

```
GET  /api/v1/venues/:id/events           - List venue events
POST /api/v1/venues/:id/events           - Create event
GET  /api/v1/events/:id/tickets/available - Get available ticket tiers
POST /api/v1/checkout/tickets            - Create ticket checkout
GET  /api/v1/venues/:id/analytics        - Get venue analytics
GET  /api/v1/venues/:id/revenue          - Get revenue summary
```

---

## SUCCESS METRICS

**Phase 1 KPIs**:
- Pricing page conversion rate (target: 2-5%)
- Tier comparison view rate (target: 20% of visitors)
- Profile upsell CTA click rate (target: 15%)

**Phase 2 KPIs**:
- Ticket sales per venue (target: 100-200/month)
- Venue revenue per event (target: $5,000+)
- Payout accuracy (target: 100%)

**Phase 3 KPIs (2027)**:
- Artist PPV take rate (target: 50+ events/month)
- Avg viewers per PPV (target: 300+)
- Repeat viewer rate (target: 40%+)

---

## RISKS & MITIGATION

| Risk | Impact | Mitigation |
|------|--------|-----------|
| Low adoption of tiers | Revenue miss | Email campaigns, in-app notifications, feature gating |
| Ticket fraud/QR hacking | Security breach | QR salt rotation, device fingerprinting, offline manifest validation |
| Venue payout delays | Churn | Automated payouts, transparent reporting, priority support |
| PPV stream failures | Bad UX | Failover servers, adaptive bitrate, technical support on-call |
| Chargebacks on tickets | Revenue loss | Clear refund policy, email confirmations, Stripe fraud detection |

---

**STATUS**: Ready for implementation. Start Phase 1 immediately.
