import { useState, useEffect } from 'react'
import { CheckCircle, AlertCircle, Loader, Save } from 'lucide-react'
import { useSearchParams } from 'react-router-dom'
import {
  getPendingIngestions,
  getUnmatchedArtists,
  getReviewRecords,
  mapArtistIdentity,
  finalizeIngestion
} from '../../services/smrService'

export default function SMRReview() {
  const [searchParams] = useSearchParams()
  const ingestionId = searchParams.get('id')

  const [ingestions, setIngestions] = useState<any[]>([])
  const [selectedIngestion, setSelectedIngestion] = useState<any>(null)
  const [unmatched, setUnmatched] = useState<any[]>([])
  const [records, setRecords] = useState<any[]>([])
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [isFinalizing, setIsFinalizing] = useState(false)

  // Load data
  useEffect(() => {
    const loadData = async () => {
      setIsLoading(true)
      try {
        const pending = await getPendingIngestions()
        setIngestions(pending)

        // If ID in URL, select that ingestion
        if (ingestionId) {
          const selected = pending.find(i => i.id === parseInt(ingestionId))
          if (selected) {
            selectIngestion(selected)
          }
        }
      } catch (err: any) {
        setError(err.message)
      } finally {
        setIsLoading(false)
      }
    }

    loadData()
  }, [ingestionId])

  const selectIngestion = async (ingestion: any) => {
    setSelectedIngestion(ingestion)
    setIsLoading(true)

    try {
      const [unmatchedData, recordsData] = await Promise.all([
        getUnmatchedArtists(ingestion.id),
        getReviewRecords(ingestion.id)
      ])

      setUnmatched(unmatchedData)
      setRecords(recordsData)
    } catch (err: any) {
      setError(err.message)
    } finally {
      setIsLoading(false)
    }
  }

  const handleMapArtist = async (unmatchedName: string, cdmArtistId: number) => {
    try {
      await mapArtistIdentity(selectedIngestion.id, unmatchedName, cdmArtistId)

      // Reload unmatched list
      const updated = await getUnmatchedArtists(selectedIngestion.id)
      setUnmatched(updated)
    } catch (err: any) {
      setError(err.message)
    }
  }

  const handleFinalize = async () => {
    setIsFinalizing(true)
    try {
      await finalizeIngestion(selectedIngestion.id)
      // Reload ingestions
      const pending = await getPendingIngestions()
      setIngestions(pending)
      setSelectedIngestion(null)
      setUnmatched([])
      setRecords([])
    } catch (err: any) {
      setError(err.message)
    } finally {
      setIsFinalizing(false)
    }
  }

  if (isLoading && !selectedIngestion) {
    return (
      <div className="max-w-6xl">
        <div className="card text-center py-12">
          <Loader size={48} className="mx-auto text-brand-green animate-spin mb-4" />
          <p className="text-gray-400">Loading ingestions...</p>
        </div>
      </div>
    )
  }

  if (!selectedIngestion) {
    return (
      <div className="max-w-6xl">
        <div className="card mb-6">
          <div className="flex items-center gap-3 mb-6">
            <CheckCircle className="text-green-500" size={28} />
            <h1 className="text-3xl font-bold text-gray-100">SMR Review & Mapping</h1>
          </div>
          <p className="text-gray-400">
            Review uploaded SMR data, resolve artist identity mismatches, and finalize integration.
          </p>
        </div>

        {error && (
          <div className="card border-red-700 bg-red-900 bg-opacity-20 mb-6">
            <p className="text-red-400">{error}</p>
          </div>
        )}

        {ingestions.length === 0 ? (
          <div className="card border-gray-600 text-center py-12">
            <AlertCircle size={48} className="mx-auto text-gray-500 mb-4" />
            <p className="text-lg text-gray-400">No pending SMR uploads</p>
            <p className="text-sm text-gray-500 mt-2">
              Upload a CSV file to see pending records here
            </p>
          </div>
        ) : (
          <div className="card">
            <h3 className="font-semibold text-gray-100 mb-4">Pending Ingestions</h3>
            <div className="space-y-2">
              {ingestions.map(ing => (
                <button
                  key={ing.id}
                  onClick={() => selectIngestion(ing)}
                  className="w-full text-left p-4 bg-gray-800 hover:bg-gray-700 rounded-lg transition border border-gray-700"
                >
                  <p className="font-semibold text-gray-100">{ing.filename}</p>
                  <p className="text-sm text-gray-400">
                    ID: {ing.id} • Status: {ing.status} • {ing.file_size} bytes
                  </p>
                </button>
              ))}
            </div>
          </div>
        )}
      </div>
    )
  }

  return (
    <div className="max-w-6xl">
      {/* Header */}
      <div className="card mb-6">
        <div className="flex items-center justify-between mb-4">
          <div>
            <h1 className="text-3xl font-bold text-gray-100">{selectedIngestion.filename}</h1>
            <p className="text-sm text-gray-400 mt-1">ID: {selectedIngestion.id}</p>
          </div>
          <button
            onClick={() => selectIngestion(null)}
            className="btn-secondary"
          >
            Back
          </button>
        </div>
      </div>

      {error && (
        <div className="card border-red-700 bg-red-900 bg-opacity-20 mb-6">
          <p className="text-red-400">{error}</p>
        </div>
      )}

      {isLoading ? (
        <div className="card text-center py-12">
          <Loader size={48} className="mx-auto text-brand-green animate-spin mb-4" />
          <p className="text-gray-400">Loading records...</p>
        </div>
      ) : (
        <>
          {/* Unmatched Artists */}
          {unmatched.length > 0 && (
            <div className="card mb-6 border-yellow-700 bg-yellow-900 bg-opacity-20">
              <h3 className="font-semibold text-yellow-400 mb-4">Unmatched Artists ({unmatched.length})</h3>
              <div className="space-y-3">
                {unmatched.map(artist => (
                  <div key={artist.artist_name} className="p-3 bg-gray-800 rounded-lg">
                    <p className="font-semibold text-gray-100">{artist.artist_name}</p>
                    <p className="text-sm text-gray-400">{artist.record_count} record(s)</p>
                    <p className="text-xs text-yellow-400 mt-2">
                      💡 Map this artist to CDM or create new
                    </p>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Records Table */}
          <div className="card mb-6">
            <h3 className="font-semibold text-gray-100 mb-4">Records ({records.length})</h3>
            <div className="overflow-x-auto">
              <table className="table-base">
                <thead>
                  <tr>
                    <th>Artist</th>
                    <th>Track</th>
                    <th>Spins</th>
                    <th>Adds</th>
                    <th>ISRC</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  {records.slice(0, 20).map((record, i) => (
                    <tr key={i}>
                      <td className="font-medium">{record.artist_name}</td>
                      <td>{record.track_title}</td>
                      <td>{record.spin_count}</td>
                      <td>{record.add_count}</td>
                      <td className="text-xs text-gray-500">{record.isrc || '—'}</td>
                      <td>
                        <span className={`text-xs font-semibold px-2 py-1 rounded ${
                          record.status === 'mapped'
                            ? 'bg-green-500 bg-opacity-20 text-green-400'
                            : 'bg-yellow-500 bg-opacity-20 text-yellow-400'
                        }`}>
                          {record.status}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
              {records.length > 20 && (
                <p className="text-xs text-gray-500 p-3">Showing 20 of {records.length} records...</p>
              )}
            </div>
          </div>

          {/* Finalize */}
          {unmatched.length === 0 && (
            <div className="card">
              <p className="text-gray-400 mb-4">All artists are mapped. Ready to finalize?</p>
              <button
                onClick={handleFinalize}
                disabled={isFinalizing}
                className={`btn-primary flex items-center gap-2 ${
                  isFinalizing ? 'opacity-50 cursor-not-allowed' : ''
                }`}
              >
                {isFinalizing ? (
                  <>
                    <Loader size={18} className="animate-spin" />
                    Finalizing...
                  </>
                ) : (
                  <>
                    <Save size={18} />
                    Finalize Ingestion
                  </>
                )}
              </button>
            </div>
          )}
        </>
      )}
    </div>
  )
}
