9. Touring & Ecosystem

9.1 Overview

This chapter defines the "Connective Tissue" of NGN 2.0. In the legacy industry, touring is a high-risk gamble based on gut feeling. NGN 2.0 transforms touring into a data-driven science, allowing Artists to book shows where they have proven "Heat" and allowing Venues to guarantee a "Data-Backed Draw."

The Vision:

The Heatmap: Mapping the NGN Score to physical geography.

Seamless Syncing: Tour dates automatically populate Venue calendars and NGN Ticketing.

The Living Poster: A single physical asset (QR) that stays relevant throughout the tour lifecycle.

9.2 The "Tour" Entity

A "Tour" is a high-level container in the cdm_tours table that groups multiple cdm_events.

9.2.1 Ownership & Management

Owner: An Artist or a Label Manager.

Permissions: Labels can manage tours for their entire roster; individual Artists can manage their own DIY runs.

Financial Aggregation: The Tour Dashboard provides a unified view of Gross Ticket Sales, Merch Sales, and Spark Tips across the entire run of dates.

9.3 The Booking Protocol (Moneyball for Live)

The Booking Protocol replaces the "Cold Email" with a "Data Handshake."

9.3.1 Discovery: The Heatmap

Artists find Venues through the Affinity Heatmap:

The Data: The system overlays NGN Score growth, SMR spins, and local fan engagement on a map.

The Insight: An artist might see they are Rank #500 nationally, but in Boise, Idaho, they are in the Top 10 for their genre.

Action: The system suggests: "You have high affinity at 'The Olympic' in Boise. 85% of local SMR listeners are currently engaging with your tracks."

9.3.2 The Handshake (The Hold)

The Request: The Artist sends a "Booking Request" via the NGN Dashboard.

The Pitch: The Venue receives a "Media Kit" containing the Artist’s NGN Score, estimated draw (based on local followers), and a "Fairness Receipt" of their growth.

The Approval: Once the Venue accepts, the cdm_events record is generated, and NGN Ticketing (Ch. 8) is automatically provisioned for that date.

9.4 QR Strategy: The "Living" Poster

Physical marketing is usually static and wasteful. NGN "Smart Posters" solve this through dynamic redirection.

Target: nextgennoise.com/qr/tour/{slug}

Pre-Tour (Hype Phase): Redirects to a "Tour Landing Page" with a countdown, mailing list signup, and "Vote for my City" poll.

In-Progress (Location-Aware): * If a user scans the QR in Seattle on the day of the Seattle show, it redirects directly to the NGN Checkout for tonight's tickets.

If scanned elsewhere, it shows the closest upcoming date.

Post-Tour (Memory Phase): Redirects to a "Recap Page" with tour merch and a link to the artist's latest "Riff" snippets.

9.5 NGN Branded Tours (The "Netflix Original" Strategy)

This is NGN’s primary tool for market disruption. We act as the "Promoter" using our own data.

9.5.1 The Packaging Algorithm

Niko (Editor-in-Chief) identifies "Clusters" of rising stars:

Identify: Find 3 Artists with high Engagement Velocity in the same region (e.g., Southeast Metalcore).

Package: Bundle them into an "NGN Heavyweights" tour.

Guarantee: NGN pitches this package to partnered venues with a Data-Backed Draw Guarantee.

Amplification: NGN’s AI Writers (Alex/Sam) provide 10x editorial coverage for these branded tours, ensuring a sell-out.

9.6 Talent Discovery for Venues

Venues use the "Ecosystem" to fill empty nights or find openers:

Local Support Finder: A venue with a touring headliner can search for "Local Support" with a specific NGN Score threshold to ensure the "local opener" actually brings a crowd.

Market Trends: Venue owners check the "Market Leaderboard" daily to see which local acts are trending on SMR, allowing them to book the "next big thing" before their competitors.

9.7 The Dopamine Facet (Touring)

The "Tour Progress" Bar: Artists see a visual bar filling up as tickets are sold across all cities.

Sold-Out Alerts: Every time a city on the tour sells out, Niko triggers a global "Hype Post" to increase the "Clout" of the tour.

Fan Check-ins: Fans get "I Was There" badges on their profiles after their ticket is scanned via Bouncer Mode (Ch. 8).