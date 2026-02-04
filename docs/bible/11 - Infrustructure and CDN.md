11. Infrastructure & CDN Strategy

11.1 The Hybrid Model

NGN 2.0 uses a "Split-Brain" architecture to handle high traffic loads while keeping the backend simple.

The Edge (Fastly): The "Shield." Handles 90% of traffic, SSL termination, DDoS protection, and static asset delivery.

The Origin (Liquid Web): The "Brain." Handles 10% of traffic, consisting of complex writes, authentication, and background data processing.

Decision: We do not choose "Liquid Web vs. Fastly." We use Fastly to balance traffic before it hits Liquid Web.

11.2 Traffic Flow & Caching

A. The "Hot" Read (Charts & Profiles)

Path: User -> Fastly Edge -> (Cache Hit) -> Return.

Strategy: stale-while-revalidate.

Logic: Charts are calculated weekly. We set a TTL of 1 year, but trigger a Purge event when a new chart is computed. This ensures 0ms latency for users.

B. The "Dynamic" Write (Auth & Orders)

Path: User -> Fastly (Pass) -> Liquid Web Origin.

Strategy: Cache-Control: private, no-store.

Logic: Login, Ticket Purchases (/orders), and Admin Dashboard bypass the cache entirely.

C. Media (Images & Video)

Path: img.nextgennoise.com -> Fastly Image Optimizer (IO) -> Storage.

Feature: We stop generating thumbnails in PHP.

Implementation: We store one high-res master. Fastly resizes it on the fly via URL params (e.g., ?width=300&format=webp).

11.3 Load Balancing Layers

Layer 1: Global Balancing (Fastly)

Mechanism: Anycast DNS.

Function: Routes the user to the nearest physical datacenter (e.g., London user -> London Node).

Benefit: Reduces latency and absorbs localized DDoS attacks.

Layer 2: Application Balancing (Liquid Web)

Mechanism: Local Load Balancer (e.g., HAProxy or Liquid Web LB).

Function: Distributes the "missed" traffic (uncached writes) across our PHP nodes.

Setup:

Node A (Primary): Handles Writes + Cron Jobs (Niko).

Node B (Replica/Web): Handles Read overflows.

DB Node: Dedicated MySQL 8.

11.4 Operational Workflows

The Purge Protocol

When the Writer Engine (Niko) publishes a story or the Ranking Engine updates a chart, the application must invalidate the Edge Cache.

Surrogate Keys: Every API response is tagged.

GET /artists/5 -> Header: Surrogate-Key: artist-5

Purge Action:

Event: Artist 5 updates bio.

App: Sends PURGE request to Fastly for key artist-5.

Result: Next user gets fresh data; all others serve from cache.

Deployment (Atomic Swaps)

Frontend: Upload new assets to Liquid Web / Storage.

Cutover: Update the index.html pointer.

Cache: Purge index.html on Fastly.

Result: Users get the new app instantly; no "maintenance mode" needed for frontend deploys.

11.5 Security (WAF)

Fastly WAF: Blocks SQL Injection and XSS at the edge, protecting the PHP app from malformed requests.

Origin Locking: The Liquid Web firewall is configured to only accept traffic from known Fastly IP ranges. This prevents attackers from bypassing the CDN.