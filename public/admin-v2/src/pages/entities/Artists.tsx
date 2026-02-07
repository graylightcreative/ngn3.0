import { useEffect, useState } from 'react'
import { Users, Search, Loader, Filter, Edit, CheckCircle, XCircle } from 'lucide-react'
import { getEntities, updateEntity } from '../../services/entityService'

interface Artist {
  id: number
  name: string
  slug: string
  status: string
  claimed: number
  created_at: string
  image_url?: string
}

export default function ArtistsPage() {
  const [artists, setArtists] = useState<Artist[]>([])
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
      const data = await getEntities('artists', limit, (page - 1) * limit, search)
      setArtists(data.items)
      setTotal(data.total)
    } catch (err) {
      console.error(err)
    } finally {
      setIsLoading(false)
    }
  }

  async function toggleStatus(id: number, currentStatus: string) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active'
    try {
      await updateEntity('artists', id, { status: newStatus })
      setArtists(artists.map(a => a.id === id ? { ...a, status: newStatus } : a))
    } catch (err) {
      alert('Failed to update status')
    }
  }

  return (
    <div className="max-w-6xl">
      <div className="card mb-6">
        <div className="flex items-center gap-3 mb-4">
          <Users className="text-blue-500" size={28} />
          <h1 className="text-3xl font-bold text-gray-100">Artist Management</h1>
        </div>
        <p className="text-gray-400">
          Manage artist profiles, claims, and verification status.
        </p>
      </div>

      <div className="card">
        <div className="flex justify-between items-center mb-6">
          <div className="relative w-64">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" size={18} />
            <input 
              type="text"
              placeholder="Search artists..."
              className="w-full bg-gray-800 border border-gray-700 rounded-lg pl-10 pr-4 py-2 text-sm text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
              value={search}
              onChange={(e) => { setSearch(e.target.value); setPage(1); }}
            />
          </div>
          <button className="btn-secondary flex items-center gap-2">
            <Filter size={16} /> Filters
          </button>
        </div>

        {isLoading ? (
          <div className="flex justify-center py-12">
            <Loader className="animate-spin text-blue-500" size={32} />
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left">
              <thead>
                <tr className="border-b border-gray-700 text-gray-400 text-sm">
                  <th className="pb-3 pl-4">Artist</th>
                  <th className="pb-3">Status</th>
                  <th className="pb-3">Claimed</th>
                  <th className="pb-3">Joined</th>
                  <th className="pb-3 text-right pr-4">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-800">
                {artists.map((artist) => (
                  <tr key={artist.id} className="group hover:bg-gray-800/30">
                    <td className="py-3 pl-4">
                      <div className="flex items-center gap-3">
                        <div className="w-10 h-10 bg-gray-700 rounded-full overflow-hidden flex items-center justify-center text-xs font-bold text-gray-400">
                          {artist.image_url ? (
                            <img src={artist.image_url} alt={artist.name} className="w-full h-full object-cover" />
                          ) : (
                            artist.name.substring(0, 2).toUpperCase()
                          )}
                        </div>
                        <div>
                          <p className="font-semibold text-gray-100">{artist.name}</p>
                          <p className="text-xs text-gray-500">ID: {artist.id}</p>
                        </div>
                      </div>
                    </td>
                    <td className="py-3">
                      <span className={`px-2 py-1 rounded text-xs font-bold ${
                        artist.status === 'active' 
                          ? 'bg-green-500/20 text-green-400' 
                          : 'bg-red-500/20 text-red-400'
                      }`}>
                        {artist.status.toUpperCase()}
                      </span>
                    </td>
                    <td className="py-3">
                      {artist.claimed ? (
                        <span className="flex items-center gap-1 text-blue-400 text-xs">
                          <CheckCircle size={14} /> Claimed
                        </span>
                      ) : (
                        <span className="text-gray-600 text-xs">Unclaimed</span>
                      )}
                    </td>
                    <td className="py-3 text-sm text-gray-400">
                      {new Date(artist.created_at).toLocaleDateString()}
                    </td>
                    <td className="py-3 pr-4 text-right">
                      <div className="flex items-center justify-end gap-2">
                        <button 
                          onClick={() => toggleStatus(artist.id, artist.status)}
                          className="p-1 hover:bg-gray-700 rounded text-gray-400 hover:text-white"
                          title={artist.status === 'active' ? 'Deactivate' : 'Activate'}
                        >
                          {artist.status === 'active' ? <XCircle size={16} /> : <CheckCircle size={16} />}
                        </button>
                        <button className="p-1 hover:bg-gray-700 rounded text-gray-400 hover:text-white">
                          <Edit size={16} />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {/* Pagination */}
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