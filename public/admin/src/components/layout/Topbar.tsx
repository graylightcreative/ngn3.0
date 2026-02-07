import { useLocation, Link } from 'react-router-dom'
import { LogOut, User } from 'lucide-react'

export default function Topbar() {
  const location = useLocation()

  // Breadcrumb labels
  const pathLabels: Record<string, string> = {
    '/': 'Dashboard',
    '/smr/upload': 'SMR > Upload',
    '/smr/review': 'SMR > Review',
    '/rights-ledger': 'Rights > Registry',
    '/rights-ledger/disputes': 'Rights > Disputes',
    '/royalties': 'Royalties > Dashboard',
    '/royalties/payouts': 'Royalties > Payouts',
    '/charts/qa': 'Charts > QA Gatekeeper',
    '/entities/artists': 'Entities > Artists'
  }

  const currentLabel = pathLabels[location.pathname] || 'Page'

  const handleLogout = () => {
    localStorage.removeItem('ngn_admin_token')
    window.location.href = '/login.php?next=/admin-v2'
  }

  return (
    <header className="bg-brand-light border-b border-gray-700 px-6 py-4 flex items-center justify-between">
      <div className="flex items-center gap-3">
        <h2 className="text-xl font-bold text-gray-100">{currentLabel}</h2>
      </div>

      <div className="flex items-center gap-4">
        {/* Admin Status */}
        <div className="flex items-center gap-2 text-sm text-gray-400">
          <User size={18} />
          <span>Admin</span>
        </div>

        {/* Logout Button */}
        <button
          onClick={handleLogout}
          className="flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition text-sm font-semibold"
        >
          <LogOut size={18} />
          Logout
        </button>
      </div>
    </header>
  )
}
