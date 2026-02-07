import { useEffect, useState } from 'react'
import { Briefcase, Search, Loader, Filter, Edit, MapPin } from 'lucide-react'
import { getEntities } from '../../services/entityService'

interface Label {
  id: number
  name: string
  city?: string
  state?: string
  country?: string
  status: string
  created_at: string
}

export default function LabelsPage() {
  const [labels, setLabels] = useState<Label[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [total, setTotal] = useState(0)
  
  const limit = 20

  useEffect(() => {
    loadData()
  }, [page, search])

  async function loadData() {
    setIsLoading(true)
    try {
      const data = await getEntities('labels', limit, (page - 1) * limit, search)
      setLabels(data.items)
      setTotal(data.total)
    } catch (err) {
      console.error(err)
    } finally {
      setIsLoading(false)
    }
  }

  return (
    <div className="max-w-6xl">
      <div className="card mb-6">
        <div className="flex items-center gap-3 mb-4">
          <Briefcase className="text-orange-500" size={28} />
          <h1 className="text-3xl font-bold text-gray-100">Label Management</h1>
        </div>
        <p className="text-gray-400">
          Manage record labels, headquarters, and distribution rights.
        </p>
      </div>

      <div className="card">
        <div className="flex justify-between items-center mb-6">
          <div className="relative w-64">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" size={18} />
            <input 
              type="text"
              placeholder="Search labels..."
              className="w-full bg-gray-800 border border-gray-700 rounded-lg pl-10 pr-4 py-2 text-sm text-gray-100 focus:outline-none focus:ring-2 focus:ring-orange-500"
              value={search}
              onChange={(e) => { setSearch(e.target.value); setPage(1); }}
            />
          </div>
          <button className="btn-secondary">New Label</button>
        </div>

        {isLoading ? (
          <div className="flex justify-center py-12">
            <Loader className="animate-spin text-orange-500" size={32} />
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left">
              <thead>
                <tr className="border-b border-gray-700 text-gray-400 text-sm">
                  <th className="pb-3 pl-4">Label</th>
                  <th className="pb-3">Location</th>
                  <th className="pb-3">Status</th>
                  <th className="pb-3">Created</th>
                  <th className="pb-3 text-right pr-4">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-800">
                {labels.map((label) => (
                  <tr key={label.id} className="group hover:bg-gray-800/30">
                    <td className="py-3 pl-4">
                      <p className="font-semibold text-gray-100">{label.name}</p>
                      <p className="text-xs text-gray-500">ID: {label.id}</p>
                    </td>
                    <td className="py-3">
                      <div className="flex items-center gap-1.5 text-sm text-gray-400">
                        <MapPin size={14} className="text-gray-600" />
                        {label.city && label.state ? `${label.city}, ${label.state}` : label.country || 'Unknown'}
                      </div>
                    </td>
                    <td className="py-3">
                      <span className={`px-2 py-1 rounded text-xs font-bold ${
                        label.status === 'active' 
                          ? 'bg-green-500/20 text-green-400' 
                          : 'bg-red-500/20 text-red-400'
                      }`}>
                        {label.status?.toUpperCase() || 'ACTIVE'}
                      </span>
                    </td>
                    <td className="py-3 text-sm text-gray-400">
                      {new Date(label.created_at).toLocaleDateString()}
                    </td>
                    <td className="py-3 pr-4 text-right">
                      <button className="p-1 hover:bg-gray-700 rounded text-gray-400 hover:text-white">
                        <Edit size={16} />
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        <div className="mt-6 flex justify-between items-center border-t border-gray-700 pt-4">
          <p className="text-sm text-gray-500">
            Showing {(page - 1) * limit + 1} to {Math.min(page * limit, total)} of {total} results
          </p>
          <div className="flex gap-2">
            <button 
              disabled={page === 1}
              onClick={() => setPage(p => p - 1)}
              className="px-3 py-1 bg-gray-800 border border-gray-700 rounded text-sm text-gray-300 disabled:opacity-50"
            >
              Previous
            </button>
            <button 
              disabled={page * limit >= total}
              onClick={() => setPage(p => p + 1)}
              className="px-3 py-1 bg-gray-800 border border-gray-700 rounded text-sm text-gray-300 disabled:opacity-50"
            >
              Next
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}
