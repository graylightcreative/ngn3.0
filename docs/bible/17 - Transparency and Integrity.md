17. Transparency & Integrity

17.1 Philosophy: The "Glass Box" Model

In an industry historically plagued by payola, fake streams, and "black box" algorithms, NGN 2.0 wins by being the only platform that offers Proof of Work for its rankings. We move from a "Trust Us" model to a "Verify Us" model, ensuring that every chart position has a digital audit trail.

17.2 Fairness Receipts (The Digital Audit)

Every weekly chart entry is accompanied by an immutable Fairness Receipt. This is a document or view that justifies an entity's rank using raw data.

17.2.1 Public Receipt

Available to any user clicking a "Verify" icon on a chart:

Attribution Breakdown: A percentage-based pie chart showing the weight of each factor (e.g., 60% Radio Spins, 20% SMR, 15% Social Velocity, 5% Managed Merch).

High-Level Stats: Aggregate spin counts and engagement totals.

17.2.2 Private (Artist) Receipt

Available only to the Artist, Label, or verified stakeholders:

Granular Logs: A list of specific stations and the exact timestamps of spins used in the calculation.

Math Proof: A step-by-step breakdown of the weights and normalizers applied to their specific data points.

Anomaly Flags: Visible notes if any data was "Capped" or "Dampened" due to integrity triggers.

17.3 Cryptographic Chart Signing

To prove that NGN staff or external hackers have not manually "tweaked" the numbers post-calculation, we implement Weekly Signatures.

The Hash: Every Monday at 06:00 UTC, as the chart job completes, the system generates a SHA-256 hash of the entire raw dataset used for that week.

Publication: This hash is stored in cdm_chart_entries and published on the public chart page.

Auditability: Any third-party auditor can request an anonymized raw data export for a specific week; if they run the SHA-256 algorithm on that data and it doesn't match our published hash, it proves the data was altered after the fact.

17.4 Anti-Gaming & Bot Protection

NGN 2.0 uses multi-layered filters to identify and neutralize "Artificial Noise."

17.4.1 Velocity Dampening

Sudden spikes in engagement (Likes/Shares) that defy organic growth patterns (e.g., 10,000 likes in 2 minutes for an unknown artist) trigger an automatic Velocity Cap.

Mechanism: The system caps the factor at the 98th percentile of the typical population for that genre until a manual review is performed.

17.4.2 Unique-ID & Verified Human Weighting

We apply a "Weighting Delta" based on the quality of the user identity:

Anonymous/New User: 0.1x Weight.

Standard User (OAuth Linked): 1.0x Weight.

Verified Human (Stripe ID or Investor): 10.0x Weight.
This makes it economically unviable for bot farms to influence the chart, as the cost of creating "Verified Humans" exceeds the potential payout.

17.5 The "Bot-Kill" Transparency Log

To prove our integrity, we don't just hide bad data; we report it.

The Log: A public dashboard showing the total volume of neutralized noise per week (e.g., "This week, NGN detected and discarded 1.2M bot-driven interactions").

Effect: This demonstrates to the industry that NGN is actively cleaning the pool, which increases the value of the "Real" numbers that remain.

17.6 The "Pay-to-Play" Firewall

We maintain a strict separation between Monetization and Rankings.

Organic Feed vs. Ads: Users can pay for "Sponsored Posts" or "Sidebars," but these are explicitly labeled.

The Shield: Financial "Boosts" or "Sparks" contribute to the EQS (Engagement Quality Score) and an artist's wallet, but they cannot artificially move an artist's position in the core SMR or NGN Global Radio charts. Those charts remain 100% data-driven.

17.7 SMR Cross-Verification

Since Secondary Market Radio (SMR) relies on manual ingestion (Ch. 5), we use "Peer-Review" logic to prevent fraudulent reporting:

Market Outliers: If an artist is reported with 50 spins on a single station in a market but 0 spins on any other stations in that same market, the system flags it as a "Manual Audit Required" anomaly.

Source Tracking: Every SMR record tracks the IP address and User ID of the uploader to ensure accountability.

17.8 The Community Oversight Committee (COC)

We reserve a dashboard for a rotating group of high-tier stakeholders (1 Label, 1 Artist, 1 Station, 1 Admin) to view anonymized "Anomaly Reports." This committee provides a human "Sniff Test" to the algorithm's decisions, ensuring that we aren't accidentally suppressing genuine viral moments.