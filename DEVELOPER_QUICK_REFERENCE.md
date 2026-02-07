# 🚀 Developer Quick Reference Card

**Keep this open while developing**

---

## ⚡ Instant Commands

```bash
# Setup database (ONE TIME)
php setup/create_admin_tables.php

# Test everything works
php setup/test_admin_workflows.php

# Start dev server
cd public/admin-v2
npm run dev

# Build for production
npm run build

# Check git changes
git status
git diff lib/Services/MyService.php
```

---

## 📁 Where to Add Code

### Backend Service
```
File: /lib/Services/MyService.php
Pattern: Follow SMRService.php
```

### API Endpoints
```
File: /public/api/v1/admin_routes.php
Pattern: Follow lines 75-120 (SMR endpoints)
```

### Frontend Service Client
```
File: /public/admin-v2/src/services/myService.ts
Pattern: Follow smrService.ts
```

### Type Definitions
```
File: /public/admin-v2/src/types/MyType.ts
Pattern: Follow SMR.ts or Rights.ts
```

### React Components
```
File: /public/admin-v2/src/pages/mymodule/MyPage.tsx
Pattern: Follow SMRUpload.tsx or Registry.tsx
```

---

## 🔄 Development Workflow

### 1. Create Backend Service
```php
<?php
namespace NGN\Lib\Services;
use PDO;

class MyService {
    private PDO $pdo;
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getData() {
        $stmt = $this->pdo->prepare("SELECT * FROM table");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
```

### 2. Add API Endpoint
```php
$router->get('/admin/my-endpoint', function(Request $request) use ($config) {
    try {
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\MyService($pdo);
        $data = $service->getData();
        Response::json(['success' => true, 'data' => $data]);
    } catch (Exception $e) {
        Response::json(['success' => false, 'error' => $e->getMessage()], 500);
    }
});
```

### 3. Create TypeScript Service
```typescript
import api from './api'

export async function getData() {
    const response = await api.get('/admin/my-endpoint')
    return response.data.data
}
```

### 4. Define Types
```typescript
export interface MyData {
    id: number
    name: string
    status: string
}
```

### 5. Build React Component
```typescript
import { useEffect, useState } from 'react'
import { getData } from '../../services/myService'

export default function MyPage() {
    const [data, setData] = useState<MyData[]>([])
    const [isLoading, setIsLoading] = useState(false)

    useEffect(() => {
        (async () => {
            setIsLoading(true)
            try {
                const result = await getData()
                setData(result)
            } catch (e) {
                console.error(e)
            } finally {
                setIsLoading(false)
            }
        })()
    }, [])

    return (
        <div className="max-w-6xl">
            <div className="card">
                <h1 className="text-3xl font-bold text-gray-100">Title</h1>
                {isLoading ? (
                    <p>Loading...</p>
                ) : (
                    <table className="table-base">
                        <thead><tr><th>Column</th></tr></thead>
                        <tbody>
                            {data.map(item => (
                                <tr key={item.id}><td>{item.name}</td></tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    )
}
```

---

## 🎨 Tailwind Classes Reference

```css
/* Colors */
bg-brand-darker      /* #0b1020 */
bg-brand-light       /* #191a1f */
text-brand-green     /* #1DB954 */
text-gray-100        /* Bright text */
text-gray-400        /* Medium text */
text-gray-500        /* Dim text */

/* Components */
btn-primary          /* Green button */
btn-secondary        /* Gray button */
btn-danger           /* Red button */
input-base           /* Input field */
card                 /* Card container */
table-base           /* Table styling */

/* Common Patterns */
className="max-w-6xl"                    /* Page max width */
className="grid grid-cols-3 gap-4"       /* 3 column grid */
className="flex items-center justify-between"  /* Row layout */
className="space-y-3"                    /* Vertical spacing */
className="mt-6 mb-4"                    /* Margins */
```

---

## 🐛 Debugging Checklist

### API Endpoint Not Working?
1. Check URL: `/admin/my-endpoint` (correct path?)
2. Check method: GET, POST, PUT, DELETE?
3. Check request body: JSON format?
4. Check response: HTTP 500? Parse error?
5. Check service: Exception in PHP?

### React Component Not Loading?
1. Check console (F12) for errors
2. Check Network tab (request successful?)
3. Check component props (correct types?)
4. Check state updates (useState correct?)
5. Check useEffect dependencies

### Database Errors?
1. Check table exists: `SHOW TABLES;`
2. Check columns: `DESCRIBE table_name;`
3. Check foreign keys: Constraint error?
4. Check indexes: Query slow?

---

## 📋 Testing Checklist Before Commit

- [ ] PHP syntax: `php -l filename.php`
- [ ] No TypeScript errors: `npm run build`
- [ ] Component renders: Dev server working?
- [ ] API endpoint responds: 200 status?
- [ ] Database query works: Correct data?
- [ ] Error handling: 500 errors caught?
- [ ] Logging: No console errors?

---

## 🔑 Key URLs

```
Dev Server:        http://localhost:5173
API Base:          /api/v1
Admin Endpoints:   /api/v1/admin/*
Login:             /login.php
Admin v2:          /admin-v2/
Old Admin (legacy):/admin/
Database DDL:      /docs/DATABASE_SCHEMA_ADMIN_V2.md
```

---

## 📖 Reference Files (Keep Bookmarked)

```
Architecture:           /public/admin-v2/README.md
Phase 1-2 Completed:   /SESSION_CHECKPOINT_2026-02-07.md
Your Instructions:     /HANDOFF_INSTRUCTIONS.md
Project Status:        /PROJECT_STATUS.md
Database Schema:       /docs/DATABASE_SCHEMA_ADMIN_V2.md
Design Decisions:      /MEMORY.md
Setup Guide:           /setup/README.md
Quick Start:           /public/admin-v2/QUICK_START.md
```

---

## 💡 Common Patterns

### Load Data on Mount
```typescript
useEffect(() => {
    (async () => {
        setIsLoading(true)
        try {
            const result = await getData()
            setData(result)
        } catch (e) {
            setError(e.message)
        } finally {
            setIsLoading(false)
        }
    })()
}, [])
```

### Filter List
```typescript
const [filter, setFilter] = useState<string | null>(null)
const filtered = filter
    ? data.filter(item => item.status === filter)
    : data
```

### Pagination
```typescript
const [page, setPage] = useState(1)
const limit = 50
const offset = (page - 1) * limit
const result = await getData(limit, offset)
```

### Form Submission
```typescript
const handleSubmit = async (e) => {
    e.preventDefault()
    setIsLoading(true)
    try {
        await submitData(formData)
        setSuccess(true)
        setTimeout(() => setSuccess(false), 3000)
    } catch (e) {
        setError(e.message)
    } finally {
        setIsLoading(false)
    }
}
```

---

## 🚨 Common Mistakes

❌ Hardcoding user IDs → Get from JWT
❌ Forgetting TypeScript types → Add to types/
❌ Missing error handling → Always try-catch
❌ Ignoring database indexes → Add to queries
❌ Breaking Phase 1-2 code → Leave unchanged
❌ Skipping tests → Run test suite each phase
❌ Committing without testing → Test first!

---

## 🎯 Phase 3 Checklist

### Week 5
- [ ] RoyaltyService.php created
- [ ] 6 API endpoints added
- [ ] royaltyService.ts created
- [ ] Royalty.ts types created
- [ ] Dashboard.tsx functional
- [ ] Tests passing

### Week 6
- [ ] Payouts.tsx functional
- [ ] EQSAudit.tsx functional
- [ ] All endpoints tested
- [ ] Performance: <2s load time
- [ ] No console errors

---

**Print This!** ✏️
**Bookmark These!** 🔖
**Follow Patterns!** 📋
**Test Everything!** ✅
