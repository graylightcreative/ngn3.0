NGN Admin v2 Frontend Migration Plan                   
                                                                                                                     
 Context                                                

 The NGN Admin v2 project currently uses React 18 + Vite + TypeScript + Tailwind CSS to power a 17-page admin SPA.
 The codebase is well-structured (3,958 lines across 35 files) with clean separation of concerns, but has several
 issues that need addressing:

 Critical Issues:
 1. Hardcoded Asset Paths: index.php lines 55-56 have hardcoded build hashes that break on every rebuild
 2. Unused Dependencies: React Query and Zustand installed but never used (16KB wasted)
 3. Manual State Management: Every page reimplements loading/error/data patterns with useState
 4. No Code Splitting: All 17 pages loaded eagerly (~260KB initial bundle)

 Why This Migration/Optimization?
 - The asset manifest issue blocks automated deployments (manual path updates required after every build)
 - Team is considering whether to optimize React or migrate to Vue 3/Svelte 5 for better DX/performance
 - Current architecture is solid but has technical debt from unused deps and repetitive patterns

 User Question:
 User asked for a "comprehensive plan on how to transfer this entire project frontend to the best-fit JS library."
 This requires evaluating whether to stay with React (optimize) or migrate to alternatives (Vue 3, Svelte 5).

 ---
 Recommended Approach: Option A - React Optimization (Lowest Risk)

 Recommendation: Fix the critical issues and modernize the React codebase rather than migrating frameworks. React 18
  is performant and the team is already familiar with it.

 Why React Optimization over Migration:
 - Lowest risk - No paradigm shift, team stays in comfort zone
 - Fastest timeline - 3.5 days vs 5.5 days (Vue) or 12 days (Svelte)
 - Fixes all issues - Asset manifest, code quality, bundle size
 - 70% bundle reduction - 260KB → 80KB via lazy loading
 - No backend changes - API layer and auth flow stay identical

 Implementation Plan

 Task 1: Fix Asset Manifest Issue (CRITICAL)

 Priority: P0 (Blocks automated deployments)
 Effort: 2 hours
 Files: index.php, vite.config.ts

 Problem: Lines 55-56 of index.php have hardcoded hashes:
 <script type="module" crossorigin src="/admin/assets/index-BzGR2KcR.js"></script>
 <link rel="stylesheet" crossorigin href="/admin/assets/index-Cryu8pA4.css">

 Solution: Dynamic manifest loading

 1. Update vite.config.ts (add line after line 18):
 build: {
   outDir: 'dist',
   sourcemap: true,
   manifest: true,  // ADD THIS - generates .vite/manifest.json
   rollupOptions: {
     input: '/src/main.tsx'
   }
 }

 2. Replace index.php lines 55-56 with:
 <?php
 $manifestPath = __DIR__ . '/dist/.vite/manifest.json';
 if (file_exists($manifestPath)) {
     $manifest = json_decode(file_get_contents($manifestPath), true);
     $jsFile = $manifest['src/main.tsx']['file'] ?? 'assets/index.js';
     $cssFiles = $manifest['src/main.tsx']['css'] ?? [];
     $cssFile = $cssFiles[0] ?? 'assets/index.css';
 } else {
     // Fallback for missing manifest
     $jsFile = 'src/main.tsx';
     $cssFile = '';
 }
 ?>
 <script type="module" crossorigin src="/admin/<?= $jsFile ?>"></script>
 <?php if ($cssFile): ?>
 <link rel="stylesheet" crossorigin href="/admin/<?= $cssFile ?>">
 <?php endif; ?>

 Verification:
 - Run npm run build
 - Check dist/.vite/manifest.json exists
 - Access /admin/ and verify assets load correctly
 - Rebuild with npm run build again and verify no manual updates needed

 ---
 Task 2: Implement React Query for Data Fetching

 Priority: P1 (High impact code quality improvement)
 Effort: 1.5 days
 Files: 17 pages, new src/hooks/useApiQuery.ts

 Problem: React Query is installed but unused. All pages manually handle loading/error/data with useState:
 const [data, setData] = useState([])
 const [isLoading, setIsLoading] = useState(false)
 const [error, setError] = useState(null)

 useEffect(() => {
   const fetchData = async () => {
     setIsLoading(true)
     try {
       const result = await apiCall()
       setData(result)
     } catch (err) {
       setError(err.message)
     } finally {
       setIsLoading(false)
     }
   }
   fetchData()
 }, [])

 Solution: Create custom hooks using React Query

 1. Create src/hooks/useApiQuery.ts:
 import { useQuery, useMutation, useQueryClient, UseQueryOptions } from '@tanstack/react-query'

 export function useApiQuery<T>(
   key: string[],
   fetcher: () => Promise<T>,
   options?: Omit<UseQueryOptions<T>, 'queryKey' | 'queryFn'>
 ) {
   return useQuery({
     queryKey: key,
     queryFn: fetcher,
     staleTime: 30000, // 30s cache
     retry: 1,
     ...options
   })
 }

 export function useApiMutation<TData, TVariables>(
   mutationFn: (vars: TVariables) => Promise<TData>,
   options?: {
     onSuccess?: (data: TData) => void
     invalidateKeys?: string[][]
   }
 ) {
   const queryClient = useQueryClient()

   return useMutation({
     mutationFn,
     onSuccess: (data) => {
       options?.onSuccess?.(data)
       options?.invalidateKeys?.forEach(key => {
         queryClient.invalidateQueries({ queryKey: key })
       })
     }
   })
 }

 2. Wrap App in QueryClientProvider (src/main.tsx):
 import { QueryClient, QueryClientProvider } from '@tanstack/react-query'

 const queryClient = new QueryClient()

 ReactDOM.createRoot(document.getElementById('root')!).render(
   <React.StrictMode>
     <QueryClientProvider client={queryClient}>
       <App />
     </QueryClientProvider>
   </React.StrictMode>
 )

 3. Convert pages to use hooks. Example for src/pages/smr/Upload.tsx (lines 40-66):

 Before:
 const [isLoading, setIsLoading] = useState(false)
 const [error, setError] = useState<string | null>(null)
 const [uploadResult, setUploadResult] = useState<any>(null)

 const handleUpload = async () => {
   setIsLoading(true)
   setError(null)
   try {
     const result = await uploadSMRFile(file!)
     setUploadResult(result.data)
     setSuccess(true)
   } catch (err: any) {
     setError(err.response?.data?.error || 'Upload failed')
   } finally {
     setIsLoading(false)
   }
 }

 After:
 const uploadMutation = useApiMutation(
   (file: File) => uploadSMRFile(file),
   {
     onSuccess: (result) => {
       setSuccess(true)
       setTimeout(() => uploadMutation.reset(), 3000)
     }
   }
 )

 const handleUpload = () => {
   if (!file) return
   uploadMutation.mutate(file)
 }

 // In JSX: use uploadMutation.isPending, uploadMutation.error, uploadMutation.data

 Pages to convert (17 total):
 - Dashboard.tsx
 - Analytics.tsx
 - smr/Upload.tsx (mutation)
 - smr/Review.tsx (query + mutation)
 - rights-ledger/Registry.tsx (query)
 - rights-ledger/Disputes.tsx (query + mutation)
 - royalties/Dashboard.tsx (query)
 - royalties/Payouts.tsx (query + mutation)
 - royalties/EQSAudit.tsx (query)
 - charts/QAGatekeeper.tsx (query + mutation)
 - charts/Corrections.tsx (mutation)
 - entities/Artists.tsx (query + mutation)
 - entities/Users.tsx (query + mutation)
 - entities/Labels.tsx (query + mutation)
 - entities/Stations.tsx (query + mutation)
 - system/Health.tsx (query)
 - system/Ledger.tsx (query)

 Expected Impact:
 - Code reduction: ~500 lines removed
 - Auto-caching between navigations
 - Automatic refetch on window focus
 - Built-in error retry logic

 ---
 Task 3: Implement Lazy Route Loading

 Priority: P1 (High impact performance)
 Effort: 2 hours
 Files: src/App.tsx

 Problem: All 17 pages imported eagerly (lines 1-19), creating 260KB initial bundle.

 Solution: Code splitting with React.lazy

 1. Replace eager imports in src/App.tsx (lines 1-19):
 import { lazy, Suspense } from 'react'
 import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom'
 import Layout from './components/layout/Layout'

 // Lazy load pages
 const Dashboard = lazy(() => import('./pages/Dashboard'))
 const Analytics = lazy(() => import('./pages/Analytics'))
 const SMRUpload = lazy(() => import('./pages/smr/Upload'))
 const SMRReview = lazy(() => import('./pages/smr/Review'))
 const RightsLedger = lazy(() => import('./pages/rights-ledger/Registry'))
 const RightsDisputes = lazy(() => import('./pages/rights-ledger/Disputes'))
 const RoyaltiesDashboard = lazy(() => import('./pages/royalties/Dashboard'))
 const RoyaltiesPayouts = lazy(() => import('./pages/royalties/Payouts'))
 const EQSAudit = lazy(() => import('./pages/royalties/EQSAudit'))
 const ChartQA = lazy(() => import('./pages/charts/QAGatekeeper'))
 const ChartCorrections = lazy(() => import('./pages/charts/Corrections'))
 const EntitiesArtists = lazy(() => import('./pages/entities/Artists'))
 const EntitiesUsers = lazy(() => import('./pages/entities/Users'))
 const EntitiesLabels = lazy(() => import('./pages/entities/Labels'))
 const EntitiesStations = lazy(() => import('./pages/entities/Stations'))
 const SystemHealth = lazy(() => import('./pages/system/Health'))
 const SystemLedger = lazy(() => import('./pages/system/Ledger'))

 2. Create loading component (src/components/ui/LoadingFallback.tsx):
 import { Loader } from 'lucide-react'

 export default function LoadingFallback() {
   return (
     <div className="flex items-center justify-center h-64">
       <Loader className="text-brand-green animate-spin" size={48} />
     </div>
   )
 }

 3. Wrap routes in Suspense (update src/App.tsx lines 24-57):
 import LoadingFallback from './components/ui/LoadingFallback'

 function App() {
   return (
     <Router basename="/admin">
       <Routes>
         <Route element={<Layout />}>
           <Route index element={
             <Suspense fallback={<LoadingFallback />}>
               <Dashboard />
             </Suspense>
           } />
           <Route path="analytics" element={
             <Suspense fallback={<LoadingFallback />}>
               <Analytics />
             </Suspense>
           } />
           {/* Repeat for all 17 routes */}
         </Route>
       </Routes>
     </Router>
   )
 }

 Expected Impact:
 - Initial bundle: 260KB → ~80KB (70% reduction)
 - Route chunks: 15-30KB each (loaded on demand)
 - Faster initial page load
 - Improved Time to Interactive metric

 ---
 Task 4: Remove Unused Dependencies

 Priority: P3 (Low impact cleanup)
 Effort: 5 minutes
 Files: package.json

 Problem: Zustand installed but never used (checked via Grep - no imports found).

 Solution:
 npm uninstall zustand

 Note: Keep React Query since we're now using it (Task 2).

 ---
 Task 5: Add Form Validation with Zod

 Priority: P2 (Medium improvement)
 Effort: 4 hours
 Files: Upload pages with forms

 Solution: Install Zod for type-safe validation
 npm install zod react-hook-form @hookform/resolvers

 Example for SMR Upload:
 import { useForm } from 'react-hook-form'
 import { zodResolver } from '@hookform/resolvers/zod'
 import { z } from 'zod'

 const uploadSchema = z.object({
   file: z.instanceof(File)
     .refine(f => f.size <= 10_000_000, 'File must be under 10MB')
     .refine(
       f => ['text/csv', 'application/vnd.ms-excel',
 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'].includes(f.type),
       'Must be CSV or Excel file'
     )
 })

 type UploadForm = z.infer<typeof uploadSchema>

 const { handleSubmit, formState: { errors } } = useForm<UploadForm>({
   resolver: zodResolver(uploadSchema)
 })

 Apply to: Upload forms, dispute resolution forms, entity creation forms.

 ---
 Task 6: Extract Reusable UI Components

 Priority: P2 (Medium DRY improvement)
 Effort: 1 day
 Files: New src/components/ui/ directory

 Create:
 1. LoadingCard.tsx - Loading state with spinner
 2. ErrorAlert.tsx - Error message display
 3. SuccessAlert.tsx - Success message display
 4. DataTable.tsx - Reusable table wrapper with sorting

 Example LoadingCard.tsx:
 import { Loader } from 'lucide-react'

 interface LoadingCardProps {
   message?: string
 }

 export default function LoadingCard({ message = 'Loading...' }: LoadingCardProps) {
   return (
     <div className="card text-center py-12">
       <Loader size={48} className="mx-auto text-brand-green animate-spin mb-4" />
       <p className="text-gray-400">{message}</p>
     </div>
   )
 }

 Reduces: ~300 lines of repetitive UI code across pages.

 ---
 Timeline & Resource Requirements

 ┌──────────────────────────┬──────────┬──────────┬─────────────────┐
 │           Task           │ Duration │ Priority │  Dependencies   │
 ├──────────────────────────┼──────────┼──────────┼─────────────────┤
 │ 1. Fix asset manifest    │ 2 hours  │ P0       │ None            │
 ├──────────────────────────┼──────────┼──────────┼─────────────────┤
 │ 2. React Query migration │ 1.5 days │ P1       │ Task 1 complete │
 ├──────────────────────────┼──────────┼──────────┼─────────────────┤
 │ 3. Lazy loading          │ 2 hours  │ P1       │ None            │
 ├──────────────────────────┼──────────┼──────────┼─────────────────┤
 │ 4. Remove unused deps    │ 5 min    │ P3       │ None            │
 ├──────────────────────────┼──────────┼──────────┼─────────────────┤
 │ 5. Zod validation        │ 4 hours  │ P2       │ Task 2 complete │
 ├──────────────────────────┼──────────┼──────────┼─────────────────┤
 │ 6. Extract components    │ 1 day    │ P2       │ Task 2 complete │
 ├──────────────────────────┼──────────┼──────────┼─────────────────┤
 │ Total                    │ ~4 days  │          │                 │
 └──────────────────────────┴──────────┴──────────┴─────────────────┘

 Resource: 1 developer, full-time
 Calendar Duration: 4-5 business days (includes testing)

 ---
 Success Metrics

 - ✅ Asset manifest loads dynamically (zero manual path updates needed)
 - ✅ All builds deploy via php bin/automate.php deploy without intervention
 - ✅ Initial bundle size: 260KB → 80KB (70% reduction)
 - ✅ Code reduction: 500+ lines removed via React Query hooks
 - ✅ All 17 pages load correctly with lazy loading
 - ✅ Form validation errors show user-friendly messages
 - ✅ No unused dependencies in package.json
 - ✅ All existing functionality preserved (SMR, Rights, Royalties, Charts, Entities, System)

 ---
 Verification Steps

 After implementation:

 1. Build Test:
 npm run build
 ls -la dist/.vite/manifest.json  # Should exist
 2. Asset Loading Test:
   - Access http://localhost:8000/admin/
   - Check browser DevTools Network tab
   - Verify assets load from /admin/assets/index-HASH.js (dynamic hash)
 3. Lazy Loading Test:
   - Open DevTools Network tab
   - Navigate to /admin/ (Dashboard loads)
   - Navigate to /admin/smr/upload (chunk loads on demand)
   - Verify only Dashboard bundle loaded initially
 4. React Query Test:
   - Navigate to Rights Ledger
   - Check data loads and displays
   - Navigate away then back (should use cache)
   - Refresh page (should refetch)
 5. Form Validation Test:
   - Try uploading file >10MB (should show error)
   - Try uploading .txt file (should reject)
   - Upload valid .csv (should succeed)
 6. Deployment Test:
 php bin/automate.php deploy --version=2.1.0
   - Should deploy without manual intervention
   - Verify production site works

 ---
 Alternative: Option B - Migrate to Vue 3 (Medium Risk)

 Only consider if:
 - Team is willing to spend 1-2 days learning Vue
 - You want better TypeScript DX than React
 - Timeline allows 5.5 days vs 3.5 days

 Advantages over React:
 - Composition API mirrors React Hooks (easy transition)
 - Smaller runtime (32KB vs React's 42KB)
 - Better TypeScript inference
 - Simpler state management with Pinia

 Migration effort: 5.5 days (see detailed conversion examples in research)

 Not recommended because: React optimization achieves most benefits without paradigm shift risk.

 ---
 Alternative: Option C - Migrate to Svelte 5 (High Risk)

 Only consider if:
 - Performance is CRITICAL (targeting <100KB bundle)
 - Team excited about learning cutting-edge tech
 - Timeline allows 12 days

 Advantages:
 - Best bundle size (61% smaller than React)
 - Fastest runtime (no virtual DOM)
 - Simpler syntax (less boilerplate)

 Migration effort: 12 days (includes 2 days training)

 NOT recommended because: Very high learning curve, small ecosystem, overkill for admin panel.

 ---
 Critical Files Reference

 Files to modify (Option A):
 1. /Users/brock/Documents/Projects/ngn_202/public/admin/index.php - Lines 55-56 (asset paths)
 2. /Users/brock/Documents/Projects/ngn_202/public/admin/vite.config.ts - Add manifest: true
 3. /Users/brock/Documents/Projects/ngn_202/public/admin/src/hooks/useApiQuery.ts - New custom hooks
 4. /Users/brock/Documents/Projects/ngn_202/public/admin/src/main.tsx - QueryClientProvider wrapper
 5. /Users/brock/Documents/Projects/ngn_202/public/admin/src/App.tsx - Lazy loading imports
 6. /Users/brock/Documents/Projects/ngn_202/public/admin/src/pages/**/*.tsx - Convert to React Query (17 files)
 7. /Users/brock/Documents/Projects/ngn_202/public/admin/package.json - Remove zustand

 Files that DON'T change:
 - All /src/services/*.ts files (API clients work as-is)
 - All /src/types/*.ts files (type definitions unchanged)
 - /src/components/layout/* (Layout, Sidebar, Topbar stay the same)
 - tailwind.config.js (styling preserved)
 - Backend PHP files (zero changes)

 ---
 Risk Assessment & Mitigations

 ┌───────────────────────────────────┬────────────┬──────────┬─────────────────────────────────────────────┐
 │               Risk                │ Likelihood │  Impact  │                 Mitigation                  │
 ├───────────────────────────────────┼────────────┼──────────┼─────────────────────────────────────────────┤
 │ Asset manifest parsing fails      │ Low        │ High     │ Fallback to dev mode in PHP, error logging  │
 ├───────────────────────────────────┼────────────┼──────────┼─────────────────────────────────────────────┤
 │ React Query breaks data fetching  │ Low        │ High     │ Thorough testing, feature flag per page     │
 ├───────────────────────────────────┼────────────┼──────────┼─────────────────────────────────────────────┤
 │ Lazy loading causes blank screens │ Low        │ Medium   │ LoadingFallback component, error boundaries │
 ├───────────────────────────────────┼────────────┼──────────┼─────────────────────────────────────────────┤
 │ Bundle size doesn't improve       │ Very Low   │ Low      │ Analyze with vite-bundle-visualizer         │
 ├───────────────────────────────────┼────────────┼──────────┼─────────────────────────────────────────────┤
 │ Team unfamiliar with React Query  │ Medium     │ Low      │ 1-hour training session, clear examples     │
 ├───────────────────────────────────┼────────────┼──────────┼─────────────────────────────────────────────┤
 │ Deployment breaks production      │ Low        │ Critical │ Test in staging first, rollback via git     │
 └───────────────────────────────────┴────────────┴──────────┴─────────────────────────────────────────────┘

 Rollback Plan:
 - All changes are additive (no breaking changes)
 - Git commit before starting: git checkout -b feature/react-optimization
 - Can revert individual tasks via git
 - Deployment via Fleet allows instant rollback to previous version

 ---
 Final Recommendation

 Choose Option A (React Optimization) because:
 1. Fixes the critical asset manifest blocker immediately
 2. Modernizes codebase without team retraining
 3. Achieves 70% bundle reduction (good enough for admin panel)
 4. Lowest risk with 4-day timeline
 5. Enables automated deployments via bin/automate.php

 Skip framework migration unless there's a compelling business reason (e.g., React skills shortage, performance is
 critical for public-facing admin). The current React stack is modern and well-suited for this use case.
╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌