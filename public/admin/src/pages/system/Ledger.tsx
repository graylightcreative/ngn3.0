import { useEffect, useState } from 'react'
import { Shield, Search, Loader, Filter, ExternalLink, FileText, Download } from 'lucide-react'
import { getLedgerEntries } from '../../services/ledgerService'

interface LedgerEntry {
  id: number
  certificate_id: string
  content_hash: string
  owner_name: string
  upload_source: string
  title: string
  artist_name: string
  created_at: string
  status: string
}

export default function LedgerDashboard() {
  const [entries, setEntries] = useState<LedgerEntry[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [page, setPage] = useState(1)
  const [total, setTotal] = useState(0)
  
  const limit = 20

  useEffect(() => {
    loadData()
  }, [page])

  async function loadData() {
    setIsLoading(true)
    try {
      const data = await getLedgerEntries(limit, (page - 1) * limit)
      setEntries(data.items)
      setTotal(data.total)
    } catch (err) {
      console.error(err)
    } finally {
      setIsLoading(false)
    }
  }

  return (
    <div className="max-w-7xl">
      <div className="card mb-6">
        <div className="flex items-center gap-3 mb-4">
          <Shield className="text-brand-green" size={28} />
          <h1 className="text-3xl font-bold text-gray-100 font-brand">Content Ownership Ledger</h1>
        </div>
        <p className="text-gray-400">
          Cryptographic registry of all content uploads. Immutable proof of ownership and authenticity.
        </p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div className="card">
          <p className="text-xs text-gray-500 uppercase font-bold tracking-widest mb-1">Total Registered</p>
          <p className="text-3xl font-bold text-gray-100">{total}</p>
        </div>
        <div className="card">
          <p className="text-xs text-gray-500 uppercase font-bold tracking-widest mb-1">Blockchain Anchored</p>
          <p className="text-3xl font-bold text-blue-500">0</p>
        </div>
        <div className="card">
          <p className="text-xs text-gray-500 uppercase font-bold tracking-widest mb-1">Disputed Items</p>
          <p className="text-3xl font-bold text-red-500">0</p>
        </div>
      </div>

      <div className="card">
        <div className="flex justify-between items-center mb-6">
          <h2 className="text-xl font-bold text-gray-100">Registry Log</h2>
          <div className="flex gap-2">
            <button className="btn-secondary flex items-center gap-2 text-xs">
              <Download size={14} /> Export CSV
            </button>
            <button className="btn-secondary flex items-center gap-2 text-xs">
              <Filter size={14} /> Filter
            </button>
          </div>
        </div>

        {isLoading ? (
          <div className="flex justify-center py-12">
            <Loader className="animate-spin text-brand-green" size={32} />
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left">
              <thead>
                <tr className="border-b border-gray-700 text-gray-400 text-xs uppercase tracking-wider">
                  <th className="pb-3 pl-4">Certificate ID</th>
                  <th className="pb-3">Content</th>
                  <th className="pb-3">Owner</th>
                  <th className="pb-3">Source</th>
                  <th className="pb-3">Date</th>
                  <th className="pb-3 text-right pr-4">Verify</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-800">
                {entries.map((entry) => (
                  <tr key={entry.id} className="group hover:bg-gray-800/30 transition-colors">
                    <td className="py-4 pl-4 font-mono text-xs text-brand-green">
                      {entry.certificate_id}
                    </td>
                    <td className="py-4">
                      <p className="font-semibold text-gray-100 text-sm">{entry.title}</p>
                      <p className="text-xs text-gray-500">{entry.artist_name}</p>
                    </td>
                    <td className="py-4 text-sm text-gray-300">
                      {entry.owner_name}
                    </td>
                    <td className="py-4">
                      <span className="bg-gray-700 px-2 py-0.5 rounded text-[10px] uppercase font-bold text-gray-400">
                        {entry.upload_source.replace('_', ' ')}
                      </span>
                    </td>
                    <td className="py-4 text-xs text-gray-500">
                      {new Date(entry.created_at).toLocaleDateString()}
                    </td>
                    <td className="py-4 pr-4 text-right">
                      <a 
                        href={`/storage/certificates/${entry.certificate_id}.html`} 
                        target="_blank" 
                        rel="noreferrer"
                        className="inline-flex items-center gap-1 text-xs text-gray-400 hover:text-white transition-colors"
                      >
                        <FileText size={14} /> View <ExternalLink size={10} />
                      </a>
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
