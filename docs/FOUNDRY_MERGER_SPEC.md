# NGN 3.0: FOUNDRY MERGER SPECIFICATION
## VERSION: 1.0.0 // STATUS: PRESSURIZED

### 1. OVERVIEW
NGN acts as the exclusive client and storefront for the **Sovereign Foundry** (Direct-to-Film printing). This document outlines the handshake between the commerce layer (NGN) and the production layer (Foundry).

### 2. THE CREATOR PIPELINE (Product Onboarding)
Creators (Artists, Labels, Stations, Venues) can list physical merchandise based on standardized garment templates.

*   **Standard Garment:** BELLA + CANVAS 3001 (Unisex) & female equivalent.
*   **Listing Logic (The Gate):**
    *   **Sparks:** Non-subscribers must burn a determined amount of Sparks to upload a design and activate a sales slot.
    *   **Subscriptions:** Subscribers receive a monthly quota of "Design Slots" included in their tier.
*   **Asset Requirement:** High-resolution 300DPI PNG with transparency.

### 3. THE COMMERCE HANDSHAKE (Order Submission)
Upon a successful transaction via the **Chancellor Node** (Stripe One-Tap):

1.  **Order Generation:** NGN creates a localized order record.
2.  **Production Ticket:** NGN sends an automated production request email to the Foundry business address.
3.  **Data Payload:**
    *   Order ID & Garment Specs (Size/Color).
    *   Shipping Destination.
    *   High-res Artwork URL (Signed/Protected link).

### 4. THE PRODUCTION LOOP (External)
*   **Fulfillment:** The Foundry processes the order via their internal systems upon receipt of the NGN Production Ticket email. NGN does not interact with the production hardware directly.

### 5. THE SETTLEMENT (The Ledger)
*   **Trigger:** Order status moves to "Shipped" via Scan-to-Ship.
*   **Financial Split Sequence:**
    1.  **Wholesale Deduction:** Deduct the **Fixed Rate** provided by the Foundry system (Garment + Print cost). This is paid to the Vendor.
    2.  **The Board Rake:** Calculate **10%** of the remaining profit. This is distributed to the NGN Board of Directors (Note: Kieran sits on this board).
    3.  **Creator Settlement:** The final remaining balance is paid to the Artist, Label, or Entity via Stripe Connect.

### 6. BOARD SYNERGY
Kieran Kieran (Vendor Lead) holds a dual-position as both the primary manufacturing partner and an active member of the NGN Board. This ensures absolute alignment between the production capacity of the Foundry and the commerce trajectory of NGN 3.0.

---
**SOVEREIGN INDUSTRIAL PROTOCOL // 2026**
