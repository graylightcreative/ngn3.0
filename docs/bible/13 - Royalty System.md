13. Royalty & Payout Ecosystem

13.1 Philosophy: The Artist-First Mandate

The NGN 2.0 Royalty System is designed to solve the "fractional penny" problem of traditional DSPs. By shifting from a purely stream-based model to a Multi-Stream Revenue Engine, we ensure artists are compensated for the total value of their community, not just passive background listening.

The 90/10 Rule: For all direct fan support (Sparks, Subscriptions, Merch), NGN maintains a flat 10% platform fee, routing 90% of gross revenue directly to the creator.

13.2 Revenue Streams

13.2.1 The Engagement Pool (EQS Payouts)

NGN allocates a fixed percentage of platform-wide revenue (Ads + General Subscriptions) into a monthly Creator Pool. Distribution is determined by the Engagement Quality Score (EQS).

The EQS Formula:
The system calculates an artist's share of the pool based on weighted interactions:


$$EQS = (V \cdot w_1) + (L \cdot w_2) + (C \cdot w_3) + (S \cdot w_4) + (N_{spin} \cdot w_5)$$

V (Views): Unique profile/post views.

L (Likes): Positive sentiment signals.

C (Comments): High-intent community interaction (weighted 3x higher than likes).

S (Shares): Platform growth signals (weighted 10x higher than likes).

N_spin (NGN Spins): Verified airplay on NGN-hosted stations only.

13.2.2 Direct Fan Support (Sparks)

Sparks are the internal currency of the NGN ecosystem.

Fixed Value: 1 Spark = $0.01 USD.

Usage: Fans "tip" Sparks on Riffs, posts, or during live streams.

Velocity: Sparks are credited to an Artist's "Pending Balance" instantly upon receipt.

13.2.3 Managed Merch & Media

Managed Merch: Sales from the NGN-built Printful shop (Ch. 7).

External Links: Sales tracked via affiliate or redirect tokens (Amazon/Bandcamp).

Digital Sales: Exclusive "Riff" downloads or EP sales hosted on NGN.

13.3 The "NGN Spin" Rule

To ensure legal compliance and financial sustainability:

Ranking vs. Royalties: All verified radio spins (SMR/API) count toward an artist's NGN Score (Rankings).

Monetary Eligibility: Only spins originating from NGN-hosted stations (where NGN manages the broadcast infrastructure and licensing) contribute to the Monetary Royalty Pool.

Verification: A song must have a verified ISRC in the Rights Ledger (Ch. 14) to be eligible for spin-based payouts.

13.4 Payout Architecture (Stripe Connect)

NGN utilizes Stripe Connect to automate complex multi-party splits.

A. The Settlement Timeline

Direct Revenue (Sparks/Merch): Settled to the creator's Stripe account as they clear (typically 2-7 days).

Pool Revenue (EQS): Calculated on the last day of the month; distributed on the 5th of the following month.

B. Transactional Splits

For every transaction (e.g., a $20 Merch item):

Stripe processes the $20 payment.

Production Cost: $12 is routed to the manufacturer (Printful).

Platform Fee: $0.80 (10% of the $8 margin) is routed to NGN.

Artist Profit: $7.20 is routed to the Artist's Connected Account.

13.5 Transparency & Auditing: The "Pay Stub"

Trust is built through visibility. Every artist dashboard includes a "Royalties Audit" tab:

Granular View: See exactly which post or riff generated which Spark.

Fairness Summary: A breakdown of how their EQS was calculated relative to the global pool.

Exportable Data: CSV exports for accounting and tax purposes.

13.6 Data Schema: Financial Tracking

cdm_royalty_balances

Tracks the current state of a creator's earnings before payout.

user_id: FK to cdm_users.

pending_balance: Credits not yet cleared by Stripe.

available_balance: Credits ready for withdrawal.

lifetime_earnings: Aggregate total.

cdm_royalty_transactions

The immutable ledger of every cent moved.

id: PK (UUID)

source_type: spark_tip, merch_sale, eqs_distribution.

source_id: Polymorphic ID to the triggering event.

amount_gross: Original USD value.

amount_net: Value after platform fees/production costs.

status: pending, cleared, failed, paid_out.

13.7 Fraud & Integrity

To prevent "Payout Gaming":

Bot-Dampening: Engagement from accounts flagged by the Anti-Cheat Protocol (Ch. 21) is stripped from EQS calculations.

Identity Requirement: Creators must complete Stripe Identity Verification (KYC) before their first payout can be triggered.