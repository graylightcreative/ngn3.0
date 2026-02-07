import { Link } from 'react-router-dom'
import { FileUp, Scale, DollarSign, Zap, Users, AlertCircle } from 'lucide-react'

export default function Dashboard() {
  const modules = [
    {
      title: 'SMR Pipeline',
      description: 'Upload and manage radio data (Erik\'s workflow)',
      icon: FileUp,
      color: 'bg-blue-500',
      link: '/smr/upload',
      status: 'Active'
    },
    {
      title: 'Rights Ledger',
      description: 'Manage ownership verification & ISRC status',
      icon: Scale,
      color: 'bg-purple-500',
      link: '/rights-ledger',
      status: 'Active'
    },
    {
      title: 'Royalties',
      description: 'EQS calculations & payout processing',
      icon: DollarSign,
      color: 'bg-green-500',
      link: '/royalties',
      status: 'Active'
    },
    {
      title: 'Chart QA',
      description: 'Quality assurance gatekeeper & corrections',
      icon: Zap,
      color: 'bg-yellow-500',
      link: '/charts/qa',
      status: 'Active'
    },
    {
      title: 'Entities',
      description: 'Artist, Label, Station, Venue management',
      icon: Users,
      color: 'bg-cyan-500',
      link: '/entities/artists',
      status: 'Coming Soon'
    }
  ]

  return (
    <div className="max-w-7xl">
      {/* Header */}
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-gray-100 mb-2">Admin Dashboard</h1>
        <p className="text-gray-400">NGN 2.0 - API-First Music Platform Administration</p>
      </div>

      {/* Quick Status */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div className="card">
          <div className="text-3xl font-bold text-brand-green mb-2">—</div>
          <p className="text-gray-400 text-sm">Pending SMR Uploads</p>
        </div>
        <div className="card">
          <div className="text-3xl font-bold text-yellow-500 mb-2">—</div>
          <p className="text-gray-400 text-sm">Rights Disputes</p>
        </div>
        <div className="card">
          <div className="text-3xl font-bold text-blue-500 mb-2">—</div>
          <p className="text-gray-400 text-sm">Pending Payouts</p>
        </div>
        <div className="card">
          <div className="text-3xl font-bold text-red-500 mb-2">—</div>
          <p className="text-gray-400 text-sm">QA Issues</p>
        </div>
      </div>

      {/* Module Grid */}
      <div className="mb-8">
        <h2 className="text-2xl font-bold text-gray-100 mb-4">Core Modules</h2>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {modules.map((module) => (
            <Link
              key={module.title}
              to={module.link}
              className="card group hover:border-brand-green transition cursor-pointer"
            >
              <div className="flex items-start justify-between mb-3">
                <div className={`${module.color} p-3 rounded-lg`}>
                  <module.icon size={24} className="text-white" />
                </div>
                <span className={`text-xs font-semibold px-2 py-1 rounded ${
                  module.status === 'Active'
                    ? 'bg-green-500 bg-opacity-20 text-green-400'
                    : 'bg-gray-700 bg-opacity-50 text-gray-400'
                }`}>
                  {module.status}
                </span>
              </div>
              <h3 className="text-lg font-bold text-gray-100 mb-2 group-hover:text-brand-green transition">
                {module.title}
              </h3>
              <p className="text-sm text-gray-400">{module.description}</p>
            </Link>
          ))}
        </div>
      </div>

      {/* System Info */}
      <div className="card border-yellow-700 bg-yellow-900 bg-opacity-20">
        <div className="flex items-start gap-3">
          <AlertCircle className="text-yellow-500 mt-1 flex-shrink-0" size={20} />
          <div>
            <h3 className="font-bold text-yellow-400 mb-1">Development Mode</h3>
            <p className="text-sm text-gray-300">
              This admin panel is under development. Some features may be incomplete or subject to change.
              Database API endpoints are being wired up. Refer to the plan for implementation status.
            </p>
          </div>
        </div>
      </div>
    </div>
  )
}
