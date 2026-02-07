import { useState, useEffect } from 'react'
import { AlertCircle, Loader, CheckCircle } from 'lucide-react'
import { getDisputes, resolveDispute } from '../../services/rightsService'

interface Dispute {
  id: number
  right_id: number
  reason: string
  resolution?: string
  status: string
  created_at: string
  resolved_at?: string
  artist_name?: string
}

export default function RightsDisputes() {
  const [disputes, setDisputes] = useState<Dispute[]>([])
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [selectedDispute, setSelectedDispute] = useState<Dispute | null>(null)
  const [resolution, setResolution] = useState('')
  const [finalStatus, setFinalStatus] = useState('verified')
  const [isResolving, setIsResolving] = useState(false)

  useEffect(() => {
    loadDisputes()
  }, [])

  const loadDisputes = async () => {
    setIsLoading(true)
    try {
      const data = await getDisputes('open')
      setDisputes(data)
    } catch (err: any) {
      setError(err.message)
    } finally {
      setIsLoading(false)
    }
  }

  const handleResolve = async () => {
    if (!selectedDispute || !resolution) {
      setError('Please enter a resolution')
      return
    }

    setIsResolving(true)
    try {
      await resolveDispute(selectedDispute.right_id, resolution, finalStatus)
      setResolution('')
      setSelectedDispute(null)
      setFinalStatus('verified')
      await loadDisputes()
    } catch (err: any) {
      setError(err.message)
    } finally {
      setIsResolving(false)
    }
  }

  if (selectedDispute) {
    return (
      <div className="max-w-4xl">
        <div className="card mb-6">
          <div className="flex items-center justify-between mb-4">
            <h1 className="text-3xl font-bold text-gray-100">Resolve Dispute</h1>
            <button
              onClick={() => setSelectedDispute(null)}
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

        <div className="card mb-6">
          <h3 className="font-semibold text-gray-100 mb-4">Dispute Details</h3>
          <div className="space-y-3 text-gray-400">
            <p><span className="text-gray-300">Artist:</span> {selectedDispute.artist_name}</p>
            <p><span className="text-gray-300">Right ID:</span> {selectedDispute.right_id}</p>
            <p><span className="text-gray-300">Reason:</span> {selectedDispute.reason}</p>
            <p><span className="text-gray-300">Reported:</span> {new Date(selectedDispute.created_at).toLocaleDateString()}</p>
          </div>
        </div>

        <div className="card">
          <h3 className="font-semibold text-gray-100 mb-4">Resolution</h3>

          <div className="mb-4">
            <label className="block text-sm font-semibold text-gray-300 mb-2">
              Resolution Notes
            </label>
            <textarea
              value={resolution}
              onChange={(e) => setResolution(e.target.value)}
              className="input-base w-full h-32 resize-none"
              placeholder="Document how this dispute was resolved..."
            />
          </div>

          <div className="mb-6">
            <label className="block text-sm font-semibold text-gray-300 mb-2">
              Final Status
            </label>
            <select
              value={finalStatus}
              onChange={(e) => setFinalStatus(e.target.value)}
              className="input-base"
            >
              <option value="verified">Verified</option>
              <option value="rejected">Rejected</option>
              <option value="pending">Pending Further Review</option>
            </select>
          </div>

          <button
            onClick={handleResolve}
            disabled={isResolving || !resolution}
            className={`btn-primary flex items-center gap-2 ${
              isResolving || !resolution ? 'opacity-50 cursor-not-allowed' : ''
            }`}
          >
            {isResolving ? (
              <>
                <Loader size={18} className="animate-spin" />
                Resolving...
              </>
            ) : (
              <>
                <CheckCircle size={18} />
                Resolve Dispute
              </>
            )}
          </button>
        </div>
      </div>
    )
  }

  return (
    <div className="max-w-6xl">
      <div className="card mb-6">
        <div className="flex items-center gap-3 mb-6">
          <AlertCircle className="text-red-500" size={28} />
          <h1 className="text-3xl font-bold text-gray-100">Rights Disputes</h1>
        </div>

        <p className="text-gray-400">
          Handle disputed rights registrations and resolve conflicts.
        </p>
      </div>

      {error && (
        <div className="card border-red-700 bg-red-900 bg-opacity-20 mb-6">
          <p className="text-red-400">{error}</p>
        </div>
      )}

      <div className="card">
        {isLoading ? (
          <div className="text-center py-12">
            <Loader size={48} className="mx-auto text-brand-green animate-spin mb-4" />
            <p className="text-gray-400">Loading disputes...</p>
          </div>
        ) : disputes.length === 0 ? (
          <div className="text-center py-12">
            <CheckCircle size={48} className="mx-auto text-green-500 mb-4" />
            <p className="text-lg text-gray-400">No active disputes</p>
            <p className="text-sm text-gray-500 mt-2">
              All rights disputes have been resolved
            </p>
          </div>
        ) : (
          <div className="space-y-3">
            {disputes.map(dispute => (
              <button
                key={dispute.id}
                onClick={() => setSelectedDispute(dispute)}
                className="w-full text-left p-4 bg-gray-800 hover:bg-gray-700 rounded-lg transition border border-red-700 border-opacity-50"
              >
                <div className="flex items-start justify-between">
                  <div>
                    <p className="font-semibold text-gray-100">{dispute.artist_name}</p>
                    <p className="text-sm text-gray-400 mt-1">{dispute.reason}</p>
                    <p className="text-xs text-gray-500 mt-2">
                      ID: {dispute.id} • Right: {dispute.right_id} • Reported: {new Date(dispute.created_at).toLocaleDateString()}
                    </p>
                  </div>
                  <span className="text-xs font-semibold px-2 py-1 rounded bg-red-500 bg-opacity-20 text-red-400 flex-shrink-0">
                    {dispute.status}
                  </span>
                </div>
              </button>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}
