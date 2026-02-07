import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom'
import Layout from './components/layout/Layout'
import Dashboard from './pages/Dashboard'
import Analytics from './pages/Analytics'
import SMRUpload from './pages/smr/Upload'
import SMRReview from './pages/smr/Review'
import RightsLedger from './pages/rights-ledger/Registry'
import RightsDisputes from './pages/rights-ledger/Disputes'
import RoyaltiesDashboard from './pages/royalties/Dashboard'
import RoyaltiesPayouts from './pages/royalties/Payouts'
import EQSAudit from './pages/royalties/EQSAudit'
import ChartQA from './pages/charts/QAGatekeeper'
import ChartCorrections from './pages/charts/Corrections'
import EntitiesArtists from './pages/entities/Artists'
import EntitiesUsers from './pages/entities/Users'
import EntitiesLabels from './pages/entities/Labels'
import EntitiesStations from './pages/entities/Stations'
import SystemHealth from './pages/system/Health'

function App() {
  return (
    <Router basename="/admin-v2">
      <Routes>
        <Route element={<Layout />}>
          <Route index element={<Dashboard />} />
          <Route path="analytics" element={<Analytics />} />

          {/* SMR Pipeline */}
          <Route path="smr/upload" element={<SMRUpload />} />
          <Route path="smr/review" element={<SMRReview />} />

          {/* Rights Ledger */}
          <Route path="rights-ledger" element={<RightsLedger />} />
          <Route path="rights-ledger/disputes" element={<RightsDisputes />} />

          {/* Royalties */}
          <Route path="royalties" element={<RoyaltiesDashboard />} />
          <Route path="royalties/payouts" element={<RoyaltiesPayouts />} />
          <Route path="royalties/audit" element={<EQSAudit />} />

          {/* Charts */}
          <Route path="charts/qa" element={<ChartQA />} />
          <Route path="charts/corrections" element={<ChartCorrections />} />

          {/* Entities */}
          <Route path="entities/artists" element={<EntitiesArtists />} />
          <Route path="entities/users" element={<EntitiesUsers />} />
          <Route path="entities/labels" element={<EntitiesLabels />} />
          <Route path="entities/stations" element={<EntitiesStations />} />

          {/* System */}
          <Route path="system/health" element={<SystemHealth />} />

          <Route path="*" element={<Navigate to="/" replace />} />
        </Route>
      </Routes>
    </Router>
  )
}

export default App
