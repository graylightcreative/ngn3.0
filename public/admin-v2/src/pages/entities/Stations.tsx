import { useEffect, useState } from 'react'
import { Radio, Search, Loader, Map, Play } from 'lucide-react'
import { getEntities } from '../../services/entityService'

interface Station {
  id: number
  name: string
  call_sign: string
  region: string
  format: string
  created_at: string
}

export default function StationsPage() {
  const [stations, setStations] = useState<Station[]>([])
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
      const data = await getEntities('stations', limit, (page - 1) * limit, search)
      setStations(data.items)
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
          <Radio className="text-green-500" size={28} />
          <h1 className="text-3xl font-bold text-gray-100">Station Management</h1>
        </div>
        <p className="text-gray-400">
          Monitor reporting radio stations and stream availability.
        </p>
      </div>

      <div className="card">
        <div className="flex justify-between items-center mb-6">
          <div className="relative w-64">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" size={18} />
            <input 
              type="text"
              placeholder="Search stations..."
              className="w-full bg-gray-800 border border-gray-700 rounded-lg pl-10 pr-4 py-2 text-sm text-gray-100 focus:outline-none focus:ring-2 focus:ring-green-500"
              value={search}
              onChange={(e) => { setSearch(e.target.value); setPage(1); }}
            />
          </div>
          <div className="flex gap-2">
            <button className="btn-secondary flex items-center gap-2">
              <Map size={16} /> Coverage Map
            </button>
          </div>
        </div>

        {isLoading ? (
          <div className="flex justify-center py-12">
            <Loader className="animate-spin text-green-500" size={32} />
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left">
              <thead>
                <tr className="border-b border-gray-700 text-gray-400 text-sm">
                  <th className="pb-3 pl-4">Station</th>
                  <th className="pb-3">Region</th>
                  <th className="pb-3">Format</th>
                  <th className="pb-3">Status</th>
                  <th className="pb-3 text-right pr-4">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-800">
                {stations.map((station) => (
                  <tr key={station.id} className="group hover:bg-gray-800/30">
                    <td className="py-3 pl-4">
                      <p className="font-semibold text-gray-100">{station.name}</p>
                      <p className="text-xs text-brand-green font-mono uppercase">{station.call_sign || 'WEB'}</p>
                    </td>
                    <td className="py-3 text-sm text-gray-300">
                      {station.region || 'National'}
                    </td>
                    <td className="py-3">
                      <span className="bg-gray-700 px-2 py-0.5 rounded text-[10px] uppercase font-bold text-gray-300">
                        {station.format || 'Variety'}
                      </span>
                    </td>
                    <td className="py-3">
                      <span className="flex items-center gap-1.5 text-green-400 text-xs">
                        <span className="w-2 h-2 bg-green-500 rounded-full animate-pulse" />
                        Live
                      </span>
                    </td>
                    <td className="py-3 pr-4 text-right">
                      <button className="p-2 hover:bg-green-500/20 rounded-full text-green-500 transition-colors">
                        <Play size={16} fill="currentColor" />
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
