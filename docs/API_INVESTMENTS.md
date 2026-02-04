# Investment API Documentation

## Overview
The Investment API allows authenticated users to create investment notes, track their investments, and process payments through Stripe.

## Endpoints

### 1. Create Investment Checkout Session

**POST** `/api/v1/investments/checkout`

Creates an investment record and returns a Stripe checkout session URL.

**Authentication:** Required (Bearer token)

**Request Body:**
```json
{
  "amount_cents": 50000,
  "email": "investor@example.com",
  "success_url": "https://nextgennation.com/investments/success",
  "cancel_url": "https://nextgennation.com/investments/cancel"
}
```

**Parameters:**
- `amount_cents` (integer, required): Investment amount in cents. Minimum: 50000 ($500), must be in increments of 10000 ($100)
- `email` (string, required): Investor's email address
- `success_url` (string, optional): URL to redirect after successful payment. Defaults to `/investments/success`
- `cancel_url` (string, optional): URL to redirect if payment is cancelled. Defaults to `/investments/cancel`

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "investment_id": 1,
    "session_id": "cs_test_a1b2c3...",
    "url": "https://checkout.stripe.com/c/pay/cs_test_..."
  },
  "message": "Checkout session created successfully."
}
```

**Example using cURL:**
```bash
curl -X POST https://nextgennation.com/api/v1/investments/checkout \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "amount_cents": 50000,
    "email": "investor@example.com"
  }'
```

---

### 2. Get Investment Details

**GET** `/api/v1/investments/:id`

Retrieves details of a specific investment. Users can only view their own investments (unless admin).

**Authentication:** Required (Bearer token)

**URL Parameters:**
- `id` (integer): Investment ID

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
    "stripe_session_id": "cs_test_...",
    "stripe_payment_intent_id": "pi_test_...",
    "stripe_customer_id": "cus_test_...",
    "note_number": "NGN-2026-00001",
    "is_elite_perk_active": 1,
    "next_payout_date": null,
    "activated_at": "2026-01-15 10:30:00",
    "completed_at": null,
    "cancelled_at": null,
    "refunded_at": null,
    "created_at": "2026-01-15 10:25:00",
    "updated_at": "2026-01-15 10:30:00"
  }
}
```

**Example using cURL:**
```bash
curl -X GET https://nextgennation.com/api/v1/investments/1 \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

---

### 3. List User Investments

**GET** `/api/v1/investments`

Retrieves all investments for the authenticated user.

**Authentication:** Required (Bearer token)

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "user_id": 123,
      "email": "investor@example.com",
      "amount_cents": 50000,
      "status": "active",
      "note_number": "NGN-2026-00001",
      "activated_at": "2026-01-15 10:30:00",
      "created_at": "2026-01-15 10:25:00"
    },
    {
      "id": 2,
      "user_id": 123,
      "email": "investor@example.com",
      "amount_cents": 100000,
      "status": "pending_payment",
      "note_number": null,
      "activated_at": null,
      "created_at": "2026-01-15 11:00:00"
    }
  ],
  "count": 2
}
```

**Example using cURL:**
```bash
curl -X GET https://nextgennation.com/api/v1/investments \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

---

## Investment Status Flow

1. **initiated** - Investment record created, before Stripe session
2. **pending_payment** - Stripe checkout session created, awaiting payment
3. **active** - Payment received, note is active and earning returns
4. **completed** - Investment term completed, principal + interest paid out
5. **cancelled** - Investment cancelled before payment
6. **refunded** - Payment refunded
7. **failed** - Payment failed

---

## Webhook Integration

The system processes Stripe webhooks at `/webhooks/stripe.php` to automatically activate investments when payment is confirmed.

**Event Handled:** `checkout.session.completed`

When Stripe confirms payment:
1. Webhook extracts `investment_id` from session metadata
2. Updates investment status to `active`
3. Generates unique note number (e.g., "NGN-2026-00001")
4. Activates elite perks (`is_elite_perk_active = 1`)
5. Updates user's `IsInvestor` flag to `1`

---

## Database Schema

**Table:** `ngn_2025.investments`

Key fields:
- `amount_cents` - Investment amount in cents
- `term_years` - Investment term (default: 5)
- `apy_percent` - Annual percentage yield (default: 8.00)
- `note_number` - Unique note identifier (e.g., "NGN-2026-00001")
- `is_elite_perk_active` - Boolean flag for investor perks
- Stripe IDs: `stripe_session_id`, `stripe_payment_intent_id`, `stripe_customer_id`

---

## Error Codes

- **400 Bad Request** - Invalid parameters or amounts
- **401 Unauthorized** - Missing or invalid authentication token
- **403 Forbidden** - Attempting to access another user's investment
- **404 Not Found** - Investment not found
- **500 Internal Server Error** - Server error during processing

---

## Notes

- Minimum investment: **$500** (50,000 cents)
- Investment increments: **$100** (10,000 cents)
- All amounts are stored and processed in **cents** to avoid floating-point precision issues
- Investments are tied to both user ID and email for flexibility
- The `IsInvestor` flag grants 1.05x ranking boost (per NGN Ranking Engine spec)
