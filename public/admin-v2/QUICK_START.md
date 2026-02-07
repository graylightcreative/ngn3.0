# Admin v2 Quick Start Guide

## Installation (5 minutes)

```bash
# 1. Navigate to admin-v2
cd public/admin-v2

# 2. Install dependencies
npm install

# 3. Start dev server
npm run dev
```

Visit `http://localhost:5173` - Vite dev server with hot reload

## Project Structure

```
src/
├── pages/          # Route components (SMR, Rights, Royalties, etc.)
├── components/     # Reusable UI components (Layout, Sidebar, Topbar)
├── services/       # API clients (smrService, rightsService, etc.)
├── hooks/          # Custom React hooks (ready for auth, pagination)
├── types/          # TypeScript definitions
├── utils/          # Utilities (formatters, validators)
├── App.tsx         # Root component with routes
└── index.css       # Global Tailwind styles
```

## Adding a New Feature

### 1. Create API Service
```typescript
// src/services/myService.ts
import api from './api'

export async function getMyData() {
  const response = await api.get('/admin/my-endpoint')
  return response.data.data
}
```

### 2. Create Types
```typescript
// src/types/MyType.ts
export interface MyData {
  id: number
  name: string
  status: 'active' | 'inactive'
}
```

### 3. Create Page Component
```typescript
// src/pages/mymodule/MyPage.tsx
import { useEffect, useState } from 'react'
import { getMyData } from '../../services/myService'

export default function MyPage() {
  const [data, setData] = useState([])
  const [isLoading, setIsLoading] = useState(false)

  useEffect(() => {
    (async () => {
      setIsLoading(true)
      try {
        const result = await getMyData()
        setData(result)
      } finally {
        setIsLoading(false)
      }
    })()
  }, [])

  return (
    <div className="max-w-6xl">
      <div className="card">
        <h1 className="text-3xl font-bold text-gray-100">My Feature</h1>
        {/* Content here */}
      </div>
    </div>
  )
}
```

### 4. Add Route
```typescript
// src/App.tsx
import MyPage from './pages/mymodule/MyPage'

// Add to Routes inside Layout element:
<Route path="mymodule/mypage" element={<MyPage />} />
```

### 5. Add Sidebar Menu Item
```typescript
// src/components/layout/Sidebar.tsx
// Add to menuItems array:
{
  label: 'My Module',
  icon: MyIcon,
  submenu: [
    { label: 'My Page', path: '/mymodule/mypage' }
  ]
}
```

## Common Patterns

### Loading State
```typescript
{isLoading ? (
  <div className="card text-center py-12">
    <Loader size={48} className="mx-auto text-brand-green animate-spin mb-4" />
    <p className="text-gray-400">Loading...</p>
  </div>
) : (
  // Content
)}
```

### Error Handling
```typescript
{error && (
  <div className="card border-red-700 bg-red-900 bg-opacity-20 mb-6">
    <p className="text-red-400">{error}</p>
  </div>
)}
```

### Data Table
```typescript
<div className="card">
  <div className="overflow-x-auto">
    <table className="table-base">
      <thead>
        <tr>
          <th>Column 1</th>
          <th>Column 2</th>
        </tr>
      </thead>
      <tbody>
        {data.map(item => (
          <tr key={item.id}>
            <td>{item.field1}</td>
            <td>{item.field2}</td>
          </tr>
        ))}
      </tbody>
    </table>
  </div>
</div>
```

### Form Input
```typescript
<input
  type="text"
  value={value}
  onChange={(e) => setValue(e.target.value)}
  className="input-base"
  placeholder="Enter value..."
/>
```

### Button
```typescript
<button className="btn-primary">Primary</button>
<button className="btn-secondary">Secondary</button>
<button className="btn-danger">Danger</button>
```

### Card
```typescript
<div className="card">
  <h3 className="font-semibold text-gray-100 mb-4">Title</h3>
  {/* Content */}
</div>
```

## Building API Endpoints

### Backend (PHP)

```php
// /api/v1/admin_routes.php
// GET /api/v1/admin/my-endpoint
$router->get('/admin/my-endpoint', function (Request $request) use ($config) {
    try {
        $pdo = $config->getDatabase();
        $service = new \NGN\Lib\Services\MyService($pdo);

        $data = $service->getData();

        Response::json([
            'success' => true,
            'data' => $data
        ]);
    } catch (Exception $e) {
        Response::json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});
```

### Service Class (PHP)

```php
// /lib/Services/MyService.php
namespace NGN\Lib\Services;

use PDO;

class MyService {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getData() {
        $stmt = $this->pdo->prepare("SELECT * FROM my_table LIMIT 50");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
```

## Styling Tips

### Colors (Tailwind)
```
bg-brand-darker    - Dark background (#0b1020)
bg-brand-light     - Card background (#191a1f)
bg-brand-green     - Accent (#1DB954)
text-gray-100      - Bright text
text-gray-400      - Dim text
text-gray-500      - Very dim text
```

### Dark Mode (Already Enabled)
All components automatically use dark mode. No need to add `dark:` prefix - just use base Tailwind classes.

### Responsive
```typescript
className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"
//        mobile         tablet          desktop
```

## Debugging

### API Errors
```bash
# Check browser console (F12)
# XHR/Fetch tab shows request/response
# Make sure Authorization header is present
```

### TypeScript Errors
```bash
# VS Code should show squiggly underlines
# Or run: npx tsc --noEmit
```

### Dev Tools
```typescript
// In React component
console.log('Debug:', data)

// Check token
console.log(window.NGN_ADMIN_TOKEN)
```

## Authentication

### How It Works
1. User logs in at `/login.php` → gets JWT
2. Browser navigates to `/admin-v2/`
3. PHP validates JWT via `_guard.php`
4. Token passed to React: `window.NGN_ADMIN_TOKEN`
5. Axios adds token to all `/api` requests
6. Server validates token on every request

### Token Required
All `/api/v1/admin/*` endpoints require valid JWT with `role: 'admin'`

### Login Again
If token expires:
```typescript
// api.ts handles 401 automatically
// Redirects to /login.php?next=/admin-v2
```

## Production Build

```bash
# Create optimized build
npm run build

# Output in /public/admin-v2/dist/
# PHP /admin-v2/index.php serves it
```

## Performance Tips

1. **Lazy Load Components**
   ```typescript
   const MyComponent = lazy(() => import('./MyComponent'))
   ```

2. **Paginate Large Lists**
   ```typescript
   const [page, setPage] = useState(1)
   const data = await getRegistry(status, 50, (page - 1) * 50)
   ```

3. **Debounce Search**
   ```typescript
   const [search, setSearch] = useState('')
   const debouncedSearch = useCallback(
     debounce((value) => { /* search */ }, 300),
     []
   )
   ```

4. **Use React Query** (ready to implement)
   ```typescript
   const { data, isLoading } = useQuery({
     queryKey: ['myData'],
     queryFn: () => getMyData()
   })
   ```

## Useful Icons (Lucide React)

```typescript
import {
  Upload,            // File upload
  Download,          // Download
  CheckCircle,       // Success
  AlertCircle,       // Warning
  XCircle,           // Error
  Loader,            // Loading spinner
  Menu,              // Hamburger menu
  X,                 // Close
  Plus,              // Add
  Trash2,            // Delete
  Edit,              // Edit
  Eye,               // View
  EyeOff,            // Hide
  ChevronDown,       // Dropdown
  ChevronRight,      // Arrow
  Search,            // Search
  Filter,            // Filter
  Settings,          // Settings
  LogOut,            // Logout
  User,              // User profile
  Scale,             // Rights
  DollarSign,        // Money
  BarChart3,         // Dashboard
  ZapOff,            // Off
} from 'lucide-react'
```

## Resources

- **Tailwind Docs:** https://tailwindcss.com/docs
- **React Docs:** https://react.dev
- **Lucide Icons:** https://lucide.dev
- **Admin v2 README:** `/public/admin-v2/README.md`
- **Database Schema:** `/docs/DATABASE_SCHEMA_ADMIN_V2.md`

## Getting Help

1. Check `README.md` for architecture details
2. Look at existing pages (SMR, Rights) for patterns
3. Check git history for implementation examples
4. Review `MEMORY.md` for technical decisions
