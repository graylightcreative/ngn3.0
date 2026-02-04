18. Gap Analysis & Missing Verticals

18.1 Overview

While the core architecture for rankings, ticketing, and royalties is formalized, several high-value verticals remain in the "Gap" phase. These features are critical for long-term artist retention and global market penetration.

18.2 The "Discovery" Engine (Beyond Rankings)

The Gap: The Ranking Engine (Ch. 3) identifies what is currently popular. We lack a "Recommendation Engine" to predict what a user will like.

Requirements:

Affinity Clustering: An AI service that groups artists by sub-genre tags, sonic characteristics, and overlapping fanbases.

Niko’s Discovery: A weekly personalized notification sent to fans featuring 3 "Emerging" artists that match their Spark history but are not yet in their "Followed" list.

Technical Path: Implementation of a vector-based similarity search (e.g., using Redis or a dedicated Vector DB) to find "Nearest Neighbor" artists.

18.3 Direct Interaction & Social Connectivity

The Gap: We have public engagement (Comments/Shares), but lack private or specialized social connective tissue.

Requirements:

Direct Messaging (DM): Secure, role-based messaging. Artists can message fans (Blast) or other stakeholders (Labels/Venues).

Collaborative Playlists: Allowing users to build shared playlists that utilize the PWA’s background playback (Ch. 15).

Community Threads: Genre-specific boards where AI Writers (Ch. 10) can interact directly with fans to trigger Engagement Velocity (Ch. 22).

18.4 The Electronic Press Kit (EPK) Export

The Gap: Artists need to leverage their NGN data to book shows outside the NGN ecosystem.

Requirements:

Dynamic EPK: A one-click generator that creates a public, high-performance web-link and a PDF export.

Data Points: Inclusion of NGN Score history, SMR heatmaps, top "Riff" snippets, and "Verified Fan" demographics.

Utility: This serves as the primary "Resume" for indie artists when pitching to major festivals or non-NGN venues.

18.5 Ad-Tech & Inventory Specification

The Gap: We have "Advertiser Stories," but lack the technical specification for ad delivery and bidding.

Requirements:

Inventory Mapping: Defining slots in the PWA feed, station interstitials, and sidebar banners.

Self-Serve Bidding: A dashboard where Artists or Labels can bid Sparks (Ch. 13) to secure "Sponsored" visibility.

Targeting Logic: Ability to target ads by Genre Affinity, Geography (SMR Market), or EQS status.

18.6 Internationalization (i18n) & Localization

The Gap: The system is currently optimized for a US-centric (English/USD) model.

Requirements:

Currency Conversion: Real-time conversion of Spark values and Merch prices based on the user's local currency.

Multi-Language UI: Localization files for Spanish, Japanese, and German markets to support global radio clusters.

Global Shipping: Integration with Printful’s global routing to handle VAT and international shipping rates for Managed Merch.

18.7 Security, PII, and Data Sovereignty

The Gap: Handling Stripe Connect and Rights Ledger data requires high-tier regulatory compliance.

Requirements:

Field-Level Encryption: Sensitive data (SSNs for KYC, Bank account fragments) must be encrypted at rest within MySQL 8.0.

GDPR/CCPA Workflow: Automated "Right to be Forgotten" tools for users to delete their PII while maintaining the integrity of the Rights Ledger.

Data Residency: Planning for "Split-Database" architecture to store European user data on EU-based servers if required by law.

18.8 Content Moderation & Safety

The Gap: We have AI safety for writers, but need robust tools for user-generated content.

Requirements:

Reporting System: A mechanism for fans to report abusive comments or copyright infringement.

Moderation Dashboard: An Admin tool for Erik/Staff to review reports, "Shadowban" bots, or "Freeze" disputed assets in the Rights Ledger.

18.9 Managed Merch Admin Layer

The Gap: The workflow for moving from "Artist Artwork" to a "Live Product" is not yet codified in the Admin UI.

Requirements:

Submission Queue: A portal for Admins to review high-res PNGs submitted by artists.

Template Mapping: Tools to apply approved art to Printful product templates (T-shirts, Hoodies, Posters) and set the NGN/Artist split margins.