# NGN Admin v2 - React SPA

Modern API-first admin panel for the NGN 2.0 music platform, replacing 226 legacy PHP files with a clean, purpose-built SPA.

## Overview

This is a React 18 + TypeScript + Tailwind CSS SPA that implements the workflows documented in the NGN Bible:

- **SMR Pipeline** - Erik's radio data ingestion workflow
- **Rights Ledger** - Ownership verification and ISRC management
- **Royalty Management** - EQS calculations and payout processing
- **Chart QA** - Quality assurance gatekeeper
- **Entity Management** - Artists, Labels, Stations, Venues
- **Content Moderation** - User reports, takedowns, bot detection
- **System Operations** - Monitoring and feature flags
- **Platform Analytics** - Revenue tracking and insights

## Architecture

### Frontend Stack
- **Framework:** React 18 + TypeScript
- **Build Tool:** Vite
- **Styling:** Tailwind CSS (dark mode)
- **HTTP Client:** Axios with JWT interceptors
- **Routing:** React Router v6
- **State Management:** React Query + Zustand

### Backend
- **API:** Extends `/api/v1/admin_routes.php`
- **Auth:** JWT validation via `_guard.php`
- **Services:** Reuses `/lib/Services/`

## Getting Started

### Prerequisites
- Node.js >= 18.0.0
- npm or yarn

### Installation

```bash
cd public/admin-v2
npm install
```

### Development

```bash
npm run dev
```

The dev server runs on `http://localhost:5173` and proxies API calls to `/api`.

### Production Build

```bash
npm run build
```

Output goes to `dist/`, served by PHP's `index.php`.

## Project Structure

```
src/
├── components/         # Reusable UI components
│   └── layout/        # Layout shells (Sidebar, Topbar)
├── pages/             # Route components
│   ├── smr/           # SMR Pipeline pages
│   ├── rights-ledger/ # Rights Management pages
│   ├── royalties/     # Royalty pages
│   ├── charts/        # Chart QA pages
│   └── entities/      # Entity Management pages
├── hooks/             # Custom React hooks
├── services/          # API client functions
├── types/             # TypeScript definitions
├── utils/             # Utilities
└── App.tsx            # Root component
```

## Authentication

The admin panel uses JWT-based authentication:

1. **PHP Entry Point** (`index.php`)
   - Validates token via `_guard.php`
   - Passes token to React via `window.NGN_ADMIN_TOKEN`

2. **Axios Interceptor** (`src/services/api.ts`)
   - Adds JWT to all API requests
   - Redirects to login on 401 responses

3. **JWT Validation**
   - All admin endpoints require `role: 'admin'` in JWT payload
   - Token is validated server-side on each request

## API Endpoints

All endpoints are under `/api/v1/admin/`:

### SMR Pipeline
- `POST /admin/smr/upload` - Upload CSV
- `GET /admin/smr/pending` - Get pending uploads
- `POST /admin/smr/map-identity` - Map artist identities
- `POST /admin/smr/finalize` - Finalize and commit

### Rights Ledger
- `GET /admin/rights-ledger` - List registrations
- `GET /admin/rights-ledger/disputes` - List disputes
- `PUT /admin/rights-ledger/{id}/status` - Update status
- `POST /admin/rights-ledger/{id}/resolve-dispute` - Resolve

### Royalties
- `GET /admin/royalties/pending-payouts` - Pending queue
- `POST /admin/royalties/process-payout/{id}` - Process payout
- `GET /admin/royalties/eqs-breakdown/{user_id}` - EQS audit
- `GET /admin/royalties/transactions` - Transaction ledger

### Charts & QA
- `GET /admin/charts/qa-status` - QA status
- `POST /admin/charts/corrections` - Apply correction
- `GET /admin/charts/disputes` - List disputes
- `POST /admin/charts/approve-publish` - Publish

### Entities
- `GET /admin/entities/{type}` - List (type: artists|labels|stations|venues)
- `POST /admin/entities/merge` - Merge duplicates

### Moderation
- `GET /admin/moderation/reports` - User reports
- `POST /admin/moderation/reports/{id}/resolve` - Resolve
- `GET /admin/moderation/takedowns` - DMCA requests

### System
- `GET /admin/system/cron-status` - Cron monitoring
- `GET /admin/system/api-health` - API health
- `GET /admin/system/feature-flags` - List flags
- `PUT /admin/system/feature-flags/{flag}` - Update flag

## Development Workflow

### Adding a New Page

1. Create page component in `src/pages/`
2. Add route to `src/App.tsx`
3. Add sidebar menu item in `src/components/layout/Sidebar.tsx`

### Adding a New Module

1. Create service file in `src/services/` (e.g., `smrService.ts`)
2. Define types in `src/types/`
3. Create page components in `src/pages/{module}/`
4. Add routes and menu items

### API Integration

Example service function:

```typescript
// src/services/smrService.ts
import api from './api'

export async function uploadSMRFile(file: File) {
  const formData = new FormData()
  formData.append('file', file)
  return api.post('/admin/smr/upload', formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  })
}
```

## Database

No schema changes required. The admin panel works with existing CDM tables:

- `cdm_artists`, `cdm_labels`, `cdm_venues` - Entities
- `smr_ingestions`, `cdm_chart_entries` - SMR data
- `cdm_rights_ledger`, `cdm_rights_splits` - Rights
- `cdm_royalty_transactions`, `cdm_payout_requests` - Royalties
- `ngn_score_corrections`, `ngn_score_disputes` - Chart QA
- `cdm_identity_map` - Alias resolution

## Security

- All requests validated via JWT
- Role-based access control (admin only)
- XSS prevention through React's default escaping
- CSRF protected by SameSite cookies
- Security headers set by PHP guard

## Deployment

1. Build the React app: `npm run build`
2. PHP serves SPA from `public/admin-v2/index.php`
3. React Router handles client-side routing
4. API calls proxied to `/api/v1/admin/`

## Migration Path

**Phase 1 (Weeks 1-2):** Foundation
- ✅ Project setup, authentication, layout

**Phase 2 (Weeks 3-5):** Core modules
- SMR Pipeline, Rights Ledger, Royalties, Chart QA

**Phase 3 (Weeks 6-7):** Expansion
- Entity Management, Moderation, System, Analytics

**Phase 4 (Week 8):** Cutover
- Redirect `/admin/` to `/admin-v2/`
- Archive old admin files

## References

- Bible Ch. 1 - Vision and Architecture
- Bible Ch. 5 - Operations Manual (SMR workflow)
- Bible Ch. 13 - Royalty System
- Bible Ch. 14 - Rights Ledger

## TODO

- [ ] Implement API endpoints in PHP
- [ ] Wire up SMR upload/review pages
- [ ] Add data tables with pagination
- [ ] Implement rights ledger UI
- [ ] Add royalty auditing
- [ ] Build chart QA interface
- [ ] Add entity management
- [ ] Implement moderation tools
- [ ] Build system dashboards
- [ ] Performance optimization
- [ ] Comprehensive testing
- [ ] Documentation
