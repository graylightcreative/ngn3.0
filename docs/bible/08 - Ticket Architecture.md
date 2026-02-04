8. THIS: Ticketing Architecture

8.1 Overview

NGN Ticketing transforms the platform from a promotional tool into a high-utility "Door Tool" for venues. By integrating ticketing directly into the PWA, we eliminate reliance on high-fee third parties (Ticketmaster/Eventbrite) and capture granular "Gate Data" that fuels the NGN Score.

Core Philosophy:

Bypass the Tax: All transactions occur via Stripe in the PWA browser context to avoid the 30% Apple/Google "In-App Purchase" tax.

Instant Liquidity: Using Stripe Connect, funds are split at the moment of sale, ensuring venues and NGN are paid instantly.

The QR is the Key: High-security, cryptographically salted QR codes serve as the single source of truth for entry.

8.2 Financial Architecture (Stripe Connect)

We utilize Stripe Connect (Express/Standard) to manage multi-party splits without NGN taking on the liability of holding venue funds.

8.2.1 The Split Logic

For a standard ticket transaction:

Base Ticket Price ($P$): Set by the Venue (e.g., $20.00).

NGN Platform Fee ($F$): A fixed or percentage-based service fee (e.g., $2.00).

Total Fan Charge: $P + F = \$22.00$.

Transaction Workflow:

Charge: NGN initiates a $22.00 charge to the Fan's card.

Transfer: Stripe automatically routes $20.00 to the Venue’s Connected Account.

Commission: Stripe routes $2.00 to the NGN Platform Account.

Payout: The venue receives their funds according to their own Stripe payout schedule (Daily/Weekly).

8.3 "Bouncer Mode": The Door Tool

Bouncer Mode is a specialized, high-performance UI within the PWA designed for the high-pressure environment of a venue door.

8.3.1 Optimistic Offline Scanning

To handle "Dead Zones" or Wi-Fi failures at venue entrances:

The Manifest: When a staff member opens Bouncer Mode, the PWA downloads a "Ticket Hash Manifest" (short-codes) for that specific event to localStorage.

The Local Check: The scanner checks the scanned QR against the local manifest.

Green: Valid and Unused.

Red: Invalid or Already Redeemed.

The Sync: As soon as connectivity is restored, the PWA "Drains" the local redemption logs to the cdm_tickets table via the API.

8.3.2 The "Parking Lot Buy" Fallback

If a fan buys a ticket after the bouncer has synced their manifest:

The local check will fail.

The PWA immediately attempts a "Live API Ping."

If no internet is available, the Bouncer UI provides a "Manual Verification" toggle where the staff can enter the Fan's Order ID found on their email confirmation.

8.4 Inventory & Allocation

To prevent overselling when a venue uses multiple platforms:

NGN Allocation: Venues set a specific "Block" of tickets for NGN (e.g., 100 out of 500 capacity).

Dynamic Scarcity: As the allocation hits 90%, Niko triggers "Scarcity Alerts" to fans following the artist: "Only 10 tickets left for tonight's show at The Crocodile!"

8.5 Data Schema Extensions

cdm_tickets

id: UUID (Primary Key).

event_id: FK to cdm_events.

user_id: FK to cdm_users (The Purchaser).

qr_hash: SHA-256 string (Salted with event_id and user_secret).

status: active, redeemed, refunded, void.

redeemed_at: Timestamp (Null until scanned).

cdm_venues (Onboarding)

stripe_account_id: The ID of the connected Stripe account.

onboarding_complete: Boolean (Must be true to enable ticket sales).

8.6 The Dopamine Facet: Real-Time Sales

Ticketing is a major driver of stakeholder retention (Ch. 23).

Venue Pings: Venue owners receive an instant push notification for every ticket sold: "Cha-ching! Another $20.00 sold for [Artist Name]."

Artist Alerts: Artists see a "Sold Out" progress bar in their dashboard. When it hits 100%, Niko generates an "Auto-Hype" post: "WE ARE SOLD OUT IN SEATTLE! 🥂"

8.7 Anti-Fraud Measures

Screenshot Prevention: QR codes in the PWA include a subtle "Live Animation" or timestamp overlay to prevent fans from using static screenshots of a single ticket.

Single-Use Enforcement: Once a qr_hash is marked redeemed in the database, any subsequent scan triggers a high-contrast Red screen with the exact time of first entry.