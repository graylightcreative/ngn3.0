7. Product Specifications

7.1 Core User Stories

Reference: user-stories.json

The Artist

A.1 Dashboard: "I want to view my real-time spins, SMR data, and rankings."

A.3 Investor Status: "I want to see my NGN Score Influence Weighting (1.05x) if I am an investor."

A.15 Commerce: "I want to link my Printful account to sell merch directly on my profile."

The Label

B.1 Roster Management: "I want a unified view of all my signed artists' analytics."

B.2 Campaign Tracking: "I want to see the ROI of my email campaigns."

The Venue

V.1 Event Management: "I want to publish my monthly show calendar so fans know what's coming up."

V.2 QR Promotion: "I want to print a QR code for my front door that links directly to tonight's lineup/tickets."

V.3 Talent Discovery: "I want to search the Artist directory by 'Local' to find openers for my headliners."

The Fan / Listener

C.4 Subscriptions: "I want to subscribe (Gold/Silver) to an artist for exclusive content."

C.6 Tipping: "I want a 'Give Sparks' button to tip creators."

7.2 Commerce & Monetization

Reference: progress.json

Shops: Integrated via Printful API.

Services: Artists can buy "Mastering" or "Promo" services.

Workflow: User buys -> Order created (pending) -> Admin marked complete.

Investment (The Investor Loop):

Users can "Invest" in the platform.

Perk: 8% APY (simulated) + 1.05x Ranking Score multiplier for their associated Artist profile.

7.3 QR Code System

Reference: LegacyDatabaseSchema.md

Function: Generates dynamic QRs for any entity (Artist, Station, Venue).

Format: SVG or PNG.

Storage: Generated on-the-fly or cached in storage/public/qr/.

Data: Points to https://nextgennoise.com/qr/{entity_type}/{id} which redirects to the actual profile.