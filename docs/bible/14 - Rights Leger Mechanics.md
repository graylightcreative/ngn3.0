CHAPTER 14: The Rights Ledger & Compliance Vault

Subject: Intellectual Property Verification, ISRC/ISWC Governance, and the Escrow Trigger

1. THE COMPLIANCE MANDATE

The Rights Ledger is the central registry for Intellectual Property (IP) within NGN 2.0. Its purpose is to map every cdm_spins event and cdm_royalty_event to a verified group of rightsholders.

The Gatekeeper Principle: Without a "Verified" status in the Ledger, an artist remains in Ranking-Only Mode. In this state, their data influences the charts to build "Heat," but they accumulate no monetary value. Payouts are only unlocked once the Ledger is cryptographically cleared.

2. THE VERIFICATION LIFECYCLE

Every track indexed by the NGN Engine must move through four distinct states in the Ledger:

DRAFT: Metadata (ISRC/ISWC) is provided by the uploader, but splits (percentages) are not yet defined.

PENDING HANDSHAKE: Splits are defined by the uploader, but one or more parties (Labels, Producers, Featured Artists) have not yet digitally "Accepted" their share.

ACTIVE (Royalty-Eligible): All splits are verified, and the digital contract is signed by all parties. The track is now eligible for "Spark" tips and revenue pool distributions.

DISPUTED (Frozen): Conflicting claims have been made on the ISRC. Revenue is immediately diverted to the Compliance Escrow Vault.

3. THE "HANDSHAKE" WORKFLOW (Double-Opt-In)

To ensure absolute legal protection and zero liability for NGN LLC, we utilize a digital "Double-Opt-In" for all revenue splits:

Initiation: The uploader (Artist or Label) enters track metadata and defines the splits (e.g., Artist 50%, Label 30%, Producer 20%).

Notification: Niko (The Rights Concierge) sends an automated invite to the verified ID/email of the named parties.

Acceptance: Each party must log in and click "Accept Split." This action constitutes a binding digital signature on the NGN Royalty Agreement.

Auto-Verification: Once the split totals 100.00% and all parties have accepted, the track flag is_royalty_eligible is set to TRUE.

4. CONFLICT RESOLUTION & ESCROW (Rule 5.2)

The Ledger is engineered to handle the "Wild West" of independent rights management without slowing down platform growth:

Duplicate ISRC Detection: If two users attempt to register the same ISRC with different splits, the Ledger triggers an Ownership Dispute.

The Freeze: Both entries are moved to status = disputed.

The Escrow: Any revenue generated (Spins or Tips) during the dispute is held in the cdm_escrow_ledger.

Resolution: Niko prompts both parties to upload "Proof of Ownership" (e.g., distribution screenshots or copyright filings). An Admin (Erik Baker) performs the final manual resolution to unlock the funds to the rightful owner.

5. TECHNICAL SCHEMA: AUDIT-READY DATA

For institutional due diligence (Stripe/VCs), we maintain a rigid relational schema:

Table: cdm_rights_ledger

isrc: International Standard Recording Code (Primary Index for industry matching).

iswc: International Standard Musical Work Code (Composition rights).

status: Enum (draft, pending, active, disputed).

contract_version: Reference to the specific legal terms signed at the time of verification.

Table: cdm_rights_splits

user_id: FK to cdm_users.

role: Enum (primary_artist, featured_artist, label, publisher, producer).

percentage: Decimal (e.g., 25.0000).

accepted_at: Datetime (The SHA-256 digital signature timestamp).

6. NIKO’S ROLE: THE RIGHTS CONCIERGE

Niko (Editor-in-Chief) monitors the "Pending" queue to prevent revenue leakage for artists:

Nudges: "Sam noticed your track 'Heavy Riff' has 5,000 spins this week, but your Producer hasn't accepted their split yet. Ping them so you can unlock your Spark payouts!"

Pre-Audit: Niko performs a fuzzy-match check between the ISRC provided and global databases to flag metadata typos before they reach the Ledger.

7. INTEGRATION WITH THE ROYALTY ENGINE (Ch. 13)

The Royalty Engine queries the Ledger for every micro-transaction:

IF ACTIVE: Iterates through cdm_rights_splits and generates individual cdm_royalty_payouts.

IF DISPUTED: Creates one payout record to the escrow_user_id.

IF DRAFT/PENDING: Logs the event for data purposes but allocates $0.00.

Status: Compliance Logic Finalized | Escrow Protocol Active | Chapter Filed