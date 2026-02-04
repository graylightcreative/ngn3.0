CHAPTER 32: Community Investment Notes - Technical Implementation

Subject: Fixed-Term Note API, Stripe Integration, and Investor Perks

---

## 1. THE INVESTMENT NOTE STRUCTURE

**Product Specifications:**
- **Minimum Investment:** $500 USD (50,000 cents)
- **Increment:** $100 USD (10,000 cents)
- **Term:** 5 years (fixed)
- **APY:** 8.00% (annual percentage yield)
- **Conversion:** 100 sparks = $1.00 USD

**Status Flow:**
```
initiated → pending_payment → active → completed/cancelled/refunded/failed
```

---

## 2. DATABASE SCHEMA

**Table:** `ngn_2025.investments`

**Key Fields:**
- `id` - Auto-increment primary key
- `user_id` - NGN user ID (nullable until claimed)
- `email` - Investor email
- `amount_cents` - Investment amount in cents
- `currency` - Default: 'usd'
- `term_years` - Default: 5
- `apy_percent` - Default: 8.00
- `status` - ENUM tracking payment lifecycle
- `stripe_session_id` - Checkout session ID
- `stripe_payment_intent_id` - Payment confirmation
- `stripe_customer_id` - Stripe customer ID
- `note_number` - Unique identifier (e.g., "NGN-2026-00001")
- `is_elite_perk_active` - Boolean flag for investor benefits
- `activated_at` - When payment confirmed
- `completed_at` - When term matures
- `created_at`, `updated_at` - Timestamps

**Indexes:**
- `idx_status` on (status)
- `idx_user` on (user_id)
- `idx_email` on (email)
- `idx_stripe_session` on (stripe_session_id)
- `idx_stripe_pi` on (stripe_payment_intent_id)

---

## 3. API ENDPOINTS

**Base Path:** `/api/v1/investments`

### 3.1 Create Investment & Checkout Session

**Endpoint:** `POST /api/v1/investments/checkout`

**Authentication:** Required (JWT Bearer token)

**Request Body:**
```json
{
  "amount_cents": 50000,
  "email": "investor@example.com",
  "success_url": "https://nextgennoise.com/investments/success",
  "cancel_url": "https://nextgennoise.com/investments/cancel"
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "investment_id": 1,
    "session_id": "cs_test_...",
    "url": "https://checkout.stripe.com/c/pay/cs_test_..."
  },
  "message": "Checkout session created successfully."
}
```

**Validation:**
- `amount_cents` ≥ 50000 ($500)
- `amount_cents` % 10000 = 0 (must be $100 increments)
- `email` must be valid email format

**Business Logic:**
1. Validate amount and email
2. Insert investment record (status: 'initiated')
3. Create Stripe Checkout Session with metadata
4. Update investment (status: 'pending_payment')
5. Return checkout URL for redirect

### 3.2 Get Investment Details

**Endpoint:** `GET /api/v1/investments/:id`

**Authentication:** Required (JWT Bearer token)

**Authorization:** User can only view their own investments (or admin)

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "user_id": 123,
    "email": "investor@example.com",
    "amount_cents": 50000,
    "currency": "usd",
    "term_years": 5,
    "apy_percent": "8.00",
    "status": "active",
    "note_number": "NGN-2026-00001",
    "is_elite_perk_active": 1,
    "activated_at": "2026-01-15 10:30:00",
    "created_at": "2026-01-15 10:25:00"
  }
}
```

### 3.3 List User Investments

**Endpoint:** `GET /api/v1/investments`

**Authentication:** Required (JWT Bearer token)

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "amount_cents": 50000,
      "status": "active",
      "note_number": "NGN-2026-00001",
      "activated_at": "2026-01-15 10:30:00"
    }
  ],
  "count": 1
}
```

---

## 4. STRIPE INTEGRATION

### 4.1 Checkout Session Creation

**Stripe API Call:**
```php
\Stripe\Checkout\Session::create([
    'mode' => 'payment',
    'customer_email' => $email,
    'line_items' => [
        [
            'price_data' => [
                'currency' => 'usd',
                'unit_amount' => $amountCents,
                'product_data' => [
                    'name' => 'NGN Community Funding Investment',
                    'description' => '5-year note at 8.00% APY'
                ]
            ],
            'quantity' => 1
        ]
    ],
    'success_url' => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
    'cancel_url' => $cancelUrl,
    'metadata' => [
        'investment_id' => $investmentId,
        'user_id' => $userId,
        'type' => 'investment'
    ]
]);
```

### 4.2 Webhook Handler

**Webhook Endpoint:** `/webhooks/stripe.php`

**Event Handled:** `checkout.session.completed`

**Process Flow:**
1. Verify webhook signature (Stripe-Signature header)
2. Extract `investment_id` from session metadata
3. Extract `payment_intent_id` from session
4. Call `InvestmentService::confirmInvestment()`
5. Update investment status to 'active'
6. Generate unique note number (NGN-YYYY-#####)
7. Activate elite perks (`is_elite_perk_active = 1`)
8. Update user's `IsInvestor` flag to `1`
9. Return 200 OK to Stripe

**Security:**
- Webhook signature verification using `stripe_webhook_secret`
- Idempotent processing (duplicate events ignored)
- Logging all webhook events

---

## 5. INVESTOR PERKS & BENEFITS

### 5.1 Elite Perk Activation

When investment is confirmed:
```sql
UPDATE investments
SET is_elite_perk_active = 1
WHERE id = :investment_id;

UPDATE users
SET IsInvestor = 1
WHERE Id = :user_id;
```

### 5.2 Ranking Boost

**Bible Reference:** Chapter 3 - The Ranking Engine

Investors receive a **1.05x multiplier** on their NGN Score:

```
Final Score = Base Score × Investor Multiplier
Final Score = Base Score × 1.05
```

**Implementation Status:** Pending (task: `NGN_RANK_BOOST_JOB`)

### 5.3 AI Tool Access

**Bible Reference:** AI Monetized Tools

Investors receive **unlimited access** to AI Mix Feedback tool:
- Standard users: Pay sparks per use
- Investors: Free unlimited access (gated by `IsInvestor` flag)

---

## 6. NOTE NUMBER GENERATION

**Format:** `NGN-{YEAR}-{ID}`

**Algorithm:**
```php
private function generateNoteNumber(int $investmentId): string
{
    $year = date('Y');
    $paddedId = str_pad((string)$investmentId, 5, '0', STR_PAD_LEFT);
    return "NGN-{$year}-{$paddedId}";
}
```

**Examples:**
- Investment #1 in 2026 → `NGN-2026-00001`
- Investment #42 in 2026 → `NGN-2026-00042`
- Investment #1337 in 2027 → `NGN-2027-01337`

**Uniqueness:** Guaranteed by auto-increment ID + year combination

---

## 7. SECURITY & VALIDATION

### 7.1 Authentication
- All endpoints require JWT Bearer token
- Token validation extracts `userId` and `role`
- Users can only access their own investments

### 7.2 Input Validation
- Amount must be ≥ $500 (50,000 cents)
- Amount must be in $100 increments
- Email must pass `filter_var($email, FILTER_VALIDATE_EMAIL)`
- All database inputs use prepared statements

### 7.3 Error Handling
- Custom `InvestmentException` for business logic errors
- Graceful failure with error messages
- Stripe API errors caught and logged
- Database errors don't expose sensitive data

---

## 8. FRONTEND INTEGRATION

### 8.1 Investment Page Flow

**Page 1: Investment Options**
```
/investors.php
- Display investment tiers ($500, $1000, $2500, $5000)
- Show ROI calculations
- "Invest Now" buttons
```

**Page 2: Checkout**
```
User clicks "Invest $500"
↓
Frontend calls POST /api/v1/investments/checkout
↓
API returns Stripe checkout URL
↓
Browser redirects to Stripe hosted page
↓
User completes payment
↓
Stripe redirects to success_url
```

**Page 3: Confirmation**
```
/investments/success?session_id=cs_...
- Fetch session details
- Display note number
- Show investment summary
- Activate elite perks messaging
```

### 8.2 Dashboard Integration

**User Dashboard:** `/dashboard/investments`
- List all user investments
- Show note numbers
- Display status (active, pending, completed)
- Calculate current value with accrued interest
- Download investment statement (future)

---

## 9. PAYOUT CALCULATION (Future Implementation)

**Simple Interest Formula:**
```
Total Return = Principal × (1 + (APY × Years))
Total Return = $500 × (1 + (0.08 × 5))
Total Return = $500 × 1.40
Total Return = $700
```

**Payout Schedule:**
- **Option A:** Lump sum at maturity (year 5)
- **Option B:** Annual interest payments + principal at maturity
- **Option C:** Quarterly interest payments + principal at maturity

**Implementation Status:** Pending

---

## 10. COMPLIANCE & LEGAL

**Required Disclosures:**
- Investment is **unsecured debt obligation**
- Not FDIC insured
- Not guaranteed by any government agency
- Subject to business risk
- Returns not guaranteed

**Accredited Investor Requirement:**
- Check if SEC Reg D applies
- May need to verify accredited investor status
- Consult legal counsel before launch

**State-Level Compliance:**
- Check if state securities registration required
- May need to file Form D with SEC
- Consider crowdfunding regulations (Reg CF)

**Recommendation:** Secure legal review before accepting first investment.

---

## 11. MONITORING & REPORTING

### 11.1 Key Metrics

**Track in Admin Dashboard:**
- Total capital raised
- Number of active investors
- Average investment amount
- Outstanding liabilities
- Maturity schedule (when notes come due)

**SQL Queries:**
```sql
-- Total capital raised
SELECT SUM(amount_cents) / 100 as total_raised
FROM investments
WHERE status = 'active';

-- Active investor count
SELECT COUNT(DISTINCT user_id) as investor_count
FROM investments
WHERE status = 'active';

-- Upcoming maturities (next 90 days)
SELECT COUNT(*) as maturing_soon
FROM investments
WHERE status = 'active'
AND activated_at <= DATE_SUB(NOW(), INTERVAL 5 YEAR - 90 DAY);
```

### 11.2 Financial Reporting

**Monthly Report:**
- New investments this month
- Total outstanding liabilities
- Interest accrued to date
- Projected payout obligations

**Annual Report:**
- Total investments by year
- Investor retention rate
- Platform growth correlation (does investment → engagement?)

---

## 12. FILES & DOCUMENTATION

**Service Layer:**
- `/lib/Commerce/InvestmentService.php` - Core business logic
- `/lib/Commerce/Exception/InvestmentException.php` - Custom exception

**API Layer:**
- `/api/v1/index.php` - Investment endpoints (lines 659-804)

**Webhooks:**
- `/webhooks/stripe.php` - Stripe event handler

**Database:**
- `/migrations/sql/schema/13_investments.sql` - Schema definition

**Documentation:**
- `/docs/API_INVESTMENTS.md` - API reference
- `/docs/bible/32 - Community Investment Notes Implementation.md` - This chapter

---

## 13. IMPLEMENTATION CHECKLIST

✅ **Phase 1: Infrastructure (Complete - Jan 2026)**
- [x] Database schema created
- [x] InvestmentService implemented
- [x] Stripe integration configured
- [x] API endpoints created
- [x] Webhook handler updated
- [x] Exception handling
- [x] Documentation written

⚠️ **Phase 2: Frontend (Pending)**
- [ ] Investment landing page (`/investors.php`)
- [ ] Dashboard integration
- [ ] Success/cancel pages
- [ ] Investment statements

⚠️ **Phase 3: Financial Infrastructure (Pending)**
- [ ] Payout calculation engine
- [ ] Interest accrual tracking
- [ ] Payment processing for returns
- [ ] Tax reporting (1099-INT generation)

⚠️ **Phase 4: Legal & Compliance (Pending)**
- [ ] Terms & Conditions
- [ ] Investment Agreement template
- [ ] SEC Reg D filing (if applicable)
- [ ] State securities compliance
- [ ] Legal review & approval

⚠️ **Phase 5: Perks Integration (Pending)**
- [ ] Ranking boost implementation (`NGN_RANK_BOOST_JOB`)
- [ ] AI tool access gating
- [ ] Investor-only features
- [ ] VIP support tier

---

**Status:** Phase 1 Complete ✅ | Ready for Frontend Development

**Implementation Date:** January 15, 2026

**Next Steps:** Build investor landing page and dashboard integration

---

**CRITICAL NOTICE:** Do not accept actual investments until legal counsel reviews and approves all compliance requirements. This implementation is for technical infrastructure only.
