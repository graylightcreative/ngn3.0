# 🤝 NGN Admin v2 - Developer Handoff Instructions

**Status:** Phase 1-2 Complete | Ready for Phase 3
**For:** Next Developer/Agent
**Date:** 2026-02-07

---

## 📋 YOUR JOB

You are taking over the NGN Admin v2 project at **Week 5 (Phase 3: Royalties)**.

**Weeks 1-4 are done.** Your job is:
- ✅ Implement **Royalties & Payouts** (Weeks 5-6)
- ✅ Implement **Chart QA** (Weeks 6-7)
- ✅ Implement **Expansion & Polish** (Weeks 7-8)
- ✅ Deploy to production

**DO NOT** change anything in Phases 1-2. They are locked and tested.

---

## 🚀 START HERE (5 minutes)

### Step 1: Read These First
1. This file (you're reading it)
2. `/SESSION_CHECKPOINT_2026-02-07.md` - What was completed
3. `/ADMIN_V2_IMPLEMENTATION_PROGRESS.md` - Technical details
4. `/public/admin-v2/README.md` - Architecture overview

### Step 2: Setup Database (2 minutes)
```bash
cd /Users/brock/Documents/Projects/ngn_202
php setup/create_admin_tables.php
```
Creates 7 tables. Should output:
```
✅ smr_ingestions
✅ smr_records
✅ cdm_rights_ledger
✅ cdm_rights_splits
✅ cdm_rights_disputes
✅ cdm_identity_map
✅ cdm_chart_entries
Results: 7 created, 0 failed
```

### Step 3: Validate Everything Works (2 minutes)
```bash
php setup/test_admin_workflows.php
```
Should show:
```
✅ Passed: 20
❌ Failed: 0
🎉 ALL TESTS PASSED!
```

### Step 4: Start Dev Server (1 minute)
```bash
cd public/admin-v2
npm install  # Only if not done
npm run dev
```
Visit http://localhost:5173 - Should see dashboard with all modules.

---

## 🎯 What's Already Built (DON'T CHANGE)

### Backend (PHP)
✅ `/lib/Services/SMRService.php` - Complete
✅ `/lib/Services/RightsLedgerService.php` - Complete
✅ `/public/api/v1/admin_routes.php` - 13 endpoints working

### Frontend (React)
✅ `/public/admin-v2/src/App.tsx` - Routes configured
✅ `/public/admin-v2/src/components/layout/` - Sidebar, Topbar, Layout
✅ `/public/admin-v2/src/pages/smr/` - Upload, Review (WORKING)
✅ `/public/admin-v2/src/pages/rights-ledger/` - Registry, Disputes (WORKING)
✅ `/public/admin-v2/src/services/` - Axios, SMR, Rights clients
✅ `/public/admin-v2/src/types/` - Auth, SMR, Rights types

### Configuration
✅ Vite, TypeScript, Tailwind, PostCSS all configured
✅ JWT authentication integrated
✅ Axios interceptors working
✅ Database schema documented

---

## 📍 YOUR STARTING POINT: PHASE 3 (Weeks 5-6)

### Week 5-6: Royalties & Payouts

**DO THIS IN ORDER:**

### 1️⃣ Create Backend Service
**File:** `/lib/Services/RoyaltyService.php`

**Copy this structure** from `SMRService.php`:
```php
<?php
namespace NGN\Lib\Services;
use PDO;

class RoyaltyService {
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    // Add these methods:
    public function getPendingPayouts() { }
    public function createPayout() { }
    public function getTransactions() { }
    public function calculateEQS() { }  // Bible Ch. 13
    public function getBalance() { }
}
```

**Methods to implement** (see Bible Ch. 13 for EQS formula):
- `getPendingPayouts()` - Query `cdm_payout_requests` WHERE status='pending'
- `createPayout($userId, $amount)` - Insert into `cdm_payout_requests`
- `processPayoutRequest($payoutId)` - Mark as processing, call Stripe
- `getBalance($userId)` - Sum `cdm_royalty_transactions` for user
- `getTransactions($userId, $limit)` - Paginated transaction list
- `calculateEQS($period)` - Complex calc (see Bible Ch. 13)
- `verifyStripeConnect()` - Check integration status

**Database Tables Already Exist:**
- `cdm_royalty_transactions` (DDL in DATABASE_SCHEMA_ADMIN_V2.md)
- `cdm_payout_requests` (DDL in DATABASE_SCHEMA_ADMIN_V2.md)

### 2️⃣ Add API Endpoints
**File:** `/public/api/v1/admin_routes.php`

**Add after Rights Ledger endpoints** (around line 150):
```php
// ===== ROYALTIES ROUTES =====
$router->get('/admin/royalties/pending-payouts', function(Request $request) use ($config) {
    // Get $pdo, call $service->getPendingPayouts(), return JSON
});

$router->post('/admin/royalties/process-payout/(\d+)', function(Request $request, $payoutId) use ($config) {
    // Process payout by ID
});

$router->get('/admin/royalties/eqs-breakdown/(\d+)', function(Request $request, $userId) use ($config) {
    // Get EQS breakdown for user
});

$router->get('/admin/royalties/transactions', function(Request $request) use ($config) {
    // Get transaction ledger
});

$router->post('/admin/royalties/create-payout', function(Request $request) use ($config) {
    // Create new payout request
});

$router->get('/admin/royalties/stripe-status', function(Request $request) use ($config) {
    // Check Stripe integration
});
```

**Follow the pattern** from SMR endpoints (lines 75-120):
1. Get PDO from config
2. Create service instance
3. Call method
4. Return `Response::json(['success' => true, 'data' => $result])`
5. Catch exceptions and return error

### 3️⃣ Create Frontend Service Client
**File:** `/public/admin-v2/src/services/royaltyService.ts`

**Copy pattern from** `smrService.ts`:
```typescript
import api from './api'

export async function getPendingPayouts() {
    const response = await api.get('/admin/royalties/pending-payouts')
    return response.data.data
}

export async function processPayoutRequest(payoutId: number) {
    const response = await api.post(`/admin/royalties/process-payout/${payoutId}`)
    return response.data.data
}

export async function getBalance(userId: number) {
    const response = await api.get(`/admin/royalties/balance/${userId}`)
    return response.data.data
}

export async function getTransactions(limit: number = 50, offset: number = 0) {
    const response = await api.get(`/admin/royalties/transactions?limit=${limit}&offset=${offset}`)
    return response.data.data
}

export async function calculateEQSBreakdown(userId: number) {
    const response = await api.get(`/admin/royalties/eqs-breakdown/${userId}`)
    return response.data.data
}

export async function createPayout(userId: number, amount: number) {
    const response = await api.post('/admin/royalties/create-payout', {
        user_id: userId,
        amount
    })
    return response.data.data
}
```

### 4️⃣ Create Type Definitions
**File:** `/public/admin-v2/src/types/Royalty.ts`

```typescript
export interface Payout {
    id: number
    user_id: number
    amount: number
    status: 'pending' | 'processing' | 'completed' | 'failed'
    stripe_transfer_id?: string
    requested_at: string
    processed_at?: string
}

export interface RoyaltyTransaction {
    id: number
    user_id: number
    ingestion_id?: number
    amount: number
    eqs_pool_share?: number
    calculation_type: 'eqs' | 'flat' | 'adjusted'
    period_start?: string
    period_end?: string
    created_at: string
}

export interface EQSBreakdown {
    user_id: number
    total_amount: number
    eqs_pool_share: number
    calculation: string
    details: any[]
}

export interface Balance {
    user_id: number
    current_balance: number
    pending_payout?: number
    last_payout_date?: string
}
```

### 5️⃣ Build React Components
**Update these placeholder files:**

#### A. `/public/admin-v2/src/pages/royalties/Dashboard.tsx`
```typescript
import { useEffect, useState } from 'react'
import { DollarSign, Loader } from 'lucide-react'
import { getBalance, getPendingPayouts } from '../../services/royaltyService'

export default function RoyaltiesDashboard() {
    const [balance, setBalance] = useState(null)
    const [pendingPayouts, setPendingPayouts] = useState([])
    const [isLoading, setIsLoading] = useState(true)

    useEffect(() => {
        (async () => {
            try {
                const b = await getBalance(1) // TODO: get actual user ID
                const p = await getPendingPayouts()
                setBalance(b)
                setPendingPayouts(p)
            } finally {
                setIsLoading(false)
            }
        })()
    }, [])

    return (
        <div className="max-w-6xl">
            <div className="card mb-6">
                <h1 className="text-3xl font-bold text-gray-100">Royalty Dashboard</h1>
                <p className="text-gray-400 mt-2">EQS calculations, payout processing, Stripe monitoring</p>
            </div>

            {/* Summary Cards */}
            <div className="grid grid-cols-3 gap-4 mb-6">
                <div className="card">
                    <p className="text-sm text-gray-400">Current Balance</p>
                    <div className="text-3xl font-bold text-brand-green mt-2">${balance?.current_balance || '—'}</div>
                </div>
                <div className="card">
                    <p className="text-sm text-gray-400">Pending Payouts</p>
                    <div className="text-3xl font-bold text-yellow-500 mt-2">{pendingPayouts.length}</div>
                </div>
                <div className="card">
                    <p className="text-sm text-gray-400">Stripe Status</p>
                    <div className="text-sm font-semibold text-gray-300 mt-2">Connected</div>
                </div>
            </div>

            {/* Pending Payouts */}
            <div className="card">
                <h2 className="text-xl font-bold text-gray-100 mb-4">Pending Payouts</h2>
                {isLoading ? (
                    <p className="text-gray-400">Loading...</p>
                ) : pendingPayouts.length === 0 ? (
                    <p className="text-gray-400">No pending payouts</p>
                ) : (
                    <div className="space-y-3">
                        {pendingPayouts.map(payout => (
                            <div key={payout.id} className="p-4 bg-gray-800 rounded-lg flex justify-between items-center">
                                <div>
                                    <p className="font-semibold text-gray-100">${payout.amount}</p>
                                    <p className="text-sm text-gray-400">Requested: {new Date(payout.requested_at).toLocaleDateString()}</p>
                                </div>
                                <button className="btn-primary">Process</button>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    )
}
```

#### B. `/public/admin-v2/src/pages/royalties/Payouts.tsx`
Similar structure - show payout queue, form to create payouts, process button.

#### C. `/public/admin-v2/src/pages/royalties/EQSAudit.tsx`
Show EQS calculation breakdown for a user (use `calculateEQSBreakdown`).

---

## 🎬 PHASE 4 (Weeks 6-7): Chart QA

**Follow the exact same pattern as Royalties:**

1. Create `/lib/Services/ChartQAService.php`
2. Add endpoints to `admin_routes.php`
3. Create `chartService.ts`
4. Create `Chart.ts` types
5. Build React components (QAGatekeeper, Corrections, Disputes)

**Key Methods for ChartQAService:**
- `getQAStatus()` - Return 4 gate statuses (station coverage %, linkage rate, spin parity, etc.)
- `getCorrections()` - List manual corrections
- `applyCorrection($artistId, $newScore)` - Save correction to `ngn_score_corrections`
- `getDisputes()` - List score disputes
- `resolveDispute()` - Mark resolved

**Tables Already Exist:**
- `ngn_score_corrections` (DDL in DATABASE_SCHEMA_ADMIN_V2.md)
- `ngn_score_disputes` (DDL in DATABASE_SCHEMA_ADMIN_V2.md)

---

## 🌐 PHASE 5 (Weeks 7-8): Expansion

**Same pattern. Create services for:**
- Entity Management (Artists, Labels, Stations, Venues)
- Content Moderation (Reports, Takedowns)
- System Operations (Cron, Health, Features)
- Platform Analytics (Revenue, Engagement, Fraud)

**No new database tables needed** - use existing CDM tables.

---

## 📚 KEY REFERENCES

**Architecture:** `/public/admin-v2/README.md`
**Tech Stack:** React 18, TypeScript, Tailwind, Axios, React Router
**API Pattern:** See SMRService.php + smrService.ts
**Component Pattern:** See SMRUpload.tsx or Registry.tsx
**Type Pattern:** See types/SMR.ts

**Bible References:**
- Ch. 1 - Vision & Architecture (API-first SPA)
- Ch. 13 - Royalty System (EQS formula - CRITICAL)
- Ch. 14 - Rights Ledger
- Ch. 5 - Operations Manual (QA gatekeeper gates)

---

## ⚠️ CRITICAL RULES

### DO:
✅ Follow existing patterns (SMRService → RoyaltyService pattern)
✅ Use TypeScript types everywhere
✅ Add error handling (try-catch in services)
✅ Use Tailwind classes from existing theme
✅ Add loading states in React components
✅ Paginate large datasets server-side
✅ Index database queries
✅ Test with test_admin_workflows.php after each phase

### DON'T:
❌ Don't modify Phases 1-2 code
❌ Don't break existing routes
❌ Don't use hardcoded user IDs (use from JWT)
❌ Don't add new npm packages without discussion
❌ Don't skip error handling
❌ Don't forget TypeScript types
❌ Don't ignore database indexes
❌ Don't deploy without testing

---

## 🧪 Testing Strategy

After each phase:
```bash
# 1. Verify database
mysql -u root ngn_2025 -e "SHOW TABLES LIKE '%royalty%';"

# 2. Test endpoints manually
curl -H "Authorization: Bearer TOKEN" http://localhost:8000/api/v1/admin/royalties/pending-payouts

# 3. Test React components
# - Upload test data
# - Verify UI loads
# - Click buttons
# - Check network tab

# 4. Run integration tests
php setup/test_admin_workflows.php
# (May need updates for new tables)
```

---

## 📊 Timeline

| Week | Phase | What To Build |
|------|-------|---------------|
| 5-6 | Phase 3 | RoyaltyService + Payouts UI |
| 6-7 | Phase 4 | ChartQAService + QA UI |
| 7-8 | Phase 5 | Entities, Moderation, System, Analytics |
| 8 | Cutover | Testing, optimization, deploy |

---

## 🆘 If You Get Stuck

1. **Check existing code** - Look at SMRService.php for pattern
2. **Read the Bible** - Architecture is documented there
3. **Look at types** - SMR.ts shows the pattern
4. **Check git log** - See what changes were made and why
5. **Review MEMORY.md** - Architecture decisions explained

---

## 📝 Before You Deploy (Week 8)

**CHECKLIST:**
- [ ] All 5 phases complete
- [ ] test_admin_workflows.php passes (may need updates)
- [ ] SMR workflow tested end-to-end (upload → finalize)
- [ ] Rights workflow tested (create → dispute → resolve)
- [ ] Royalties tested (create payout → process)
- [ ] Chart QA tested (corrections apply correctly)
- [ ] Performance: pages load <2s
- [ ] Security: All endpoints require JWT
- [ ] Errors: No 500 errors in logs
- [ ] Update README.md with all endpoints

---

## 🚀 Good Luck!

You're taking over a well-structured project:
- ✅ Foundation is solid
- ✅ Patterns are clear
- ✅ Database is ready
- ✅ Auth works
- ✅ Documentation is complete

Follow the patterns. Don't break Phase 1-2. Test as you go.

**Questions?** Check MEMORY.md or SESSION_CHECKPOINT_2026-02-07.md first.

You've got this! 🎉

---

**Handoff Date:** 2026-02-07
**Status:** Ready for Phase 3
**Next Developer:** [Your Name]
