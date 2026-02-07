import { useState } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { Menu, X, BarChart3, FileUp, Scale, DollarSign, Zap, Users, Settings } from 'lucide-react'

export default function Sidebar() {
  const [isOpen, setIsOpen] = useState(true)
  const location = useLocation()

  const isActive = (path: string) => location.pathname === path

  const menuItems = [
    { label: 'Dashboard', path: '/', icon: BarChart3 },
    { label: 'Analytics', path: '/analytics', icon: TrendingUp },
    {
      label: 'SMR Pipeline',
      icon: FileUp,
      submenu: [
        { label: 'Upload', path: '/smr/upload' },
        { label: 'Review', path: '/smr/review' }
      ]
    },
    {
      label: 'Rights Ledger',
      icon: Scale,
      submenu: [
        { label: 'Registry', path: '/rights-ledger' },
        { label: 'Disputes', path: '/rights-ledger/disputes' }
      ]
    },
    {
      label: 'Royalties',
      icon: DollarSign,
      submenu: [
        { label: 'Dashboard', path: '/royalties' },
        { label: 'Payouts', path: '/royalties/payouts' },
        { label: 'EQS Audit', path: '/royalties/audit' }
      ]
    },
    {
      label: 'Charts QA',
      icon: Zap,
      submenu: [
        { label: 'Gatekeeper', path: '/charts/qa' },
        { label: 'Corrections', path: '/charts/corrections' }
      ]
    },
    {
      label: 'Entities',
      icon: Users,
      submenu: [
        { label: 'Artists', path: '/entities/artists' },
        { label: 'Users', path: '/entities/users' },
        { label: 'Labels', path: '/entities/labels' },
        { label: 'Stations', path: '/entities/stations' }
      ]
    },
    {
      label: 'System',
      icon: Settings,
      submenu: [
        { label: 'Health', path: '/system/health' }
      ]
    }
  ]

  return (
    <aside className={`${
      isOpen ? 'w-64' : 'w-20'
    } bg-brand-light border-r border-gray-700 transition-all duration-300 flex flex-col`}>
      {/* Header */}
      <div className="p-4 border-b border-gray-700 flex items-center justify-between">
        {isOpen && <h1 className="text-xl font-bold text-brand-green">NGN Admin</h1>}
        <button
          onClick={() => setIsOpen(!isOpen)}
          className="p-2 hover:bg-gray-700 rounded-lg transition"
        >
          {isOpen ? <X size={20} /> : <Menu size={20} />}
        </button>
      </div>

      {/* Menu Items */}
      <nav className="flex-1 overflow-auto py-4">
        {menuItems.map((item) => (
          <div key={item.label}>
            {item.submenu ? (
              <div className="px-2 mb-2">
                <div className={`flex items-center gap-3 px-3 py-2 text-gray-400 ${!isOpen && 'justify-center'}`}>
                  <item.icon size={20} />
                  {isOpen && <span className="text-sm font-semibold">{item.label}</span>}
                </div>
                {isOpen && (
                  <div className="pl-6 space-y-1">
                    {item.submenu.map((subitem) => (
                      <Link
                        key={subitem.path}
                        to={subitem.path}
                        className={`block px-3 py-2 text-sm rounded transition ${
                          isActive(subitem.path)
                            ? 'bg-brand-green text-black font-semibold'
                            : 'text-gray-400 hover:text-gray-200'
                        }`}
                      >
                        {subitem.label}
                      </Link>
                    ))}
                  </div>
                )}
              </div>
            ) : (
              <Link
                to={item.path}
                className={`mx-2 px-3 py-3 rounded-lg flex items-center gap-3 transition ${
                  isActive(item.path)
                    ? 'bg-brand-green text-black'
                    : 'text-gray-400 hover:text-gray-200'
                } ${!isOpen && 'justify-center'}`}
              >
                <item.icon size={20} />
                {isOpen && <span className="text-sm font-semibold">{item.label}</span>}
              </Link>
            )}
          </div>
        ))}
      </nav>

      {/* Footer */}
      <div className="p-4 border-t border-gray-700">
        {isOpen && (
          <div className="text-xs text-gray-500">
            <p>Admin v2.0</p>
            <p>API-first SPA</p>
          </div>
        )}
      </div>
    </aside>
  )
}
