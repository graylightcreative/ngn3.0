import { useState, useEffect } from 'react'
import { Scale, Loader, CheckCircle, AlertCircle } from 'lucide-react'
import { getRegistry } from '../../services/rightsService'

interface RightsRegistration {
  id: number
  artist_id: number
  artist_name: string
  track_id?: number
  isrc?: string
  status: string
  created_at: string
}

interface Summary {
  pending: number
  verified: number
  disputed: number
  rejected: number
}

export default function RightsLedger() {
  const [registry, setRegistry] = useState<RightsRegistration[]>([])
  const [summary, setSummary] = useState<Summary>({
    pending: 0,
    verified: 0,
    disputed: 0,
    rejected: 0
  })
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [filter, setFilter] = useState<string | null>(null)

  useEffect(() => {
    loadData()
  }, [filter])

  const loadData = async () => {
    setIsLoading(true)
    try {
      const result = await getRegistry(filter)
      setRegistry(result.data.registry)
      setSummary(result.data.summary)
    } catch (err: any) {
      setError(err.message)
    } finally {
      setIsLoading(false)
    }
  }

  const statusColor = (status: string) => {
    switch (status) {
      case 'verified':
        return 'bg-green-500 bg-opacity-20 text-green-400'
      case 'disputed':
        return 'bg-red-500 bg-opacity-20 text-red-400'
      case 'rejected':
        return 'bg-gray-500 bg-opacity-20 text-gray-400'
      default:
        return 'bg-yellow-500 bg-opacity-20 text-yellow-400'
    }
  }

  const total = summary.pending + summary.verified + summary.disputed + summary.rejected

  return (
    <div className="max-w-7xl">
      <div className="card mb-6">
        <div className="flex items-center gap-3 mb-6">
          <Scale className="text-purple-500" size={28} />
          <h1 className="text-3xl font-bold text-gray-100">Rights Ledger Registry</h1>
        </div>

        <p className="text-gray-400">
          Manage rights registrations, ISRC verification, ownership splits, and dispute resolution.
        </p>
      </div>

      {error && (
        <div className="card border-red-700 bg-red-900 bg-opacity-20 mb-6">
          <p className="text-red-400">{error}</p>
        </div>
      )}

      {/* Summary Stats */}
      <div className="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div className="card">
          <div className="text-2xl font-bold text-gray-100">{total}</div>
          <p className="text-sm text-gray-400 mt-2">Total Rights</p>
        </div>
        <div className="card hover:border-green-700 cursor-pointer transition" onClick={() => setFilter('verified')}>
          <div className="text-2xl font-bold text-green-500">{summary.verified}</div>
          <p className="text-sm text-gray-400 mt-2">Verified</p>
        </div>
        <div className="card hover:border-yellow-700 cursor-pointer transition" onClick={() => setFilter('pending')}>
          <div className="text-2xl font-bold text-yellow-500">{summary.pending}</div>
          <p className="text-sm text-gray-400 mt-2">Pending</p>
        </div>
        <div className="card hover:border-red-700 cursor-pointer transition" onClick={() => setFilter('disputed')}>
          <div className="text-2xl font-bold text-red-500">{summary.disputed}</div>
          <p className="text-sm text-gray-400 mt-2">Disputed</p>
        </div>
        <div className="card hover:border-gray-700 cursor-pointer transition" onClick={() => setFilter(null)}>
          <div className="text-2xl font-bold text-gray-400">{summary.rejected}</div>
          <p className="text-sm text-gray-400 mt-2">Rejected</p>
        </div>
      </div>

      {/* Filters */}
      <div className="mb-6 flex gap-2">
        {['pending', 'verified', 'disputed', 'rejected'].map(status => (
          <button
            key={status}
            onClick={() => setFilter(filter === status ? null : status)}
            className={`px-3 py-2 rounded-lg text-sm font-semibold transition ${
              filter === status
                ? 'bg-brand-green text-black'
                : 'bg-gray-800 text-gray-300 hover:bg-gray-700'
            }`}
          >
            {status.charAt(0).toUpperCase() + status.slice(1)}
          </button>
        ))}
        {filter && (
          <button
            onClick={() => setFilter(null)}
            className="px-3 py-2 rounded-lg text-sm text-gray-400 hover:text-gray-200"
          >
            Clear
          </button>
        )}
      </div>

      {/* Registry Table */}
      <div className="card">
        {isLoading ? (
          <div className="text-center py-12">
            <Loader size={48} className="mx-auto text-brand-green animate-spin mb-4" />
            <p className="text-gray-400">Loading registry...</p>
          </div>
        ) : registry.length === 0 ? (
          <div className="text-center py-12">
            <AlertCircle size={48} className="mx-auto text-gray-500 mb-4" />
            <p className="text-gray-400">No rights registrations found</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="table-base">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Artist</th>
                  <th>ISRC</th>
                  <th>Status</th>
                  <th>Verified</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {registry.map(right => (
                  <tr key={right.id}>
                    <td className="font-mono text-sm">#{right.id}</td>
                    <td className="font-semibold">{right.artist_name}</td>
                    <td className="font-mono text-xs text-gray-400">{right.isrc || '—'}</td>
                    <td>
                      <span className={`text-xs font-semibold px-2 py-1 rounded ${statusColor(right.status)}`}>
                        {right.status}
                      </span>
                    </td>
                    <td>
                      {right.status === 'verified' ? (
                        <CheckCircle size={16} className="text-green-500" />
                      ) : (
                        <AlertCircle size={16} className="text-yellow-500" />
                      )}
                    </td>
                    <td>
                      <button className="text-brand-green hover:text-brand-green text-sm font-semibold">
                        View
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Info */}
      <div className="mt-6 card border-blue-700 bg-blue-900 bg-opacity-20">
        <p className="text-sm text-blue-400">
          💡 Click on status counts to filter. Click "View" to see splits, verify ISRC, or resolve disputes.
        </p>
      </div>
    </div>
  )
}
