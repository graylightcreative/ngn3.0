import { useEffect, useState } from 'react'
import { Zap, Search, AlertCircle, CheckCircle2, Loader, Save } from 'lucide-react'
import { getCorrections, applyCorrection } from '../../services/chartService'
import { ScoreCorrection } from '../../types/Chart'

export default function CorrectionsPage() {
  const [corrections, setCorrections] = useState<ScoreCorrection[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [message, setMessage] = useState<{ type: 'success' | 'error', text: string } | null>(null)

  // Form state
  const [formData, setFormData] = useState({
    artist_id: '',
    original_score: '',
    corrected_score: '',
    reason: '',
    ingestion_id: ''
  })

  useEffect(() => {
    loadCorrections()
  }, [])

  async function loadCorrections() {
    setIsLoading(true)
    try {
      const data = await getCorrections()
      setCorrections(data)
    } catch (err) {
      console.error('Failed to load corrections:', err)
    } finally {
      setIsLoading(false)
    }
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setIsSubmitting(true)
    setMessage(null)

    try {
      await applyCorrection({
        artist_id: parseInt(formData.artist_id),
        original_score: parseFloat(formData.original_score),
        corrected_score: parseFloat(formData.corrected_score),
        reason: formData.reason,
        ingestion_id: formData.ingestion_id ? parseInt(formData.ingestion_id) : undefined
      })
      
      setMessage({ type: 'success', text: 'Score correction applied successfully' })
      setFormData({ artist_id: '', original_score: '', corrected_score: '', reason: '', ingestion_id: '' })
      loadCorrections()
    } catch (err: any) {
      setMessage({ type: 'error', text: err.response?.data?.error || 'Failed to apply correction' })
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <div className="max-w-6xl">
      <div className="card mb-6">
        <h1 className="text-3xl font-bold text-gray-100">Score Corrections</h1>
        <p className="text-gray-400 mt-2">
          Manually override chart scores for specific artists. These changes are logged for audit.
        </p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Form */}
        <div className="lg:col-span-1">
          <div className="card sticky top-6">
            <h2 className="text-xl font-bold text-gray-100 mb-6 flex items-center gap-2">
              <Zap className="text-yellow-500" size={20} />
              New Correction
            </h2>

            {message && (
              <div className={`mb-4 p-3 rounded text-sm flex items-center gap-2 ${
                message.type === 'success' ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'
              }`}>
                {message.type === 'success' ? <CheckCircle2 size={16} /> : <AlertCircle size={16} />}
                {message.text}
              </div>
            )}

            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-400 mb-1">Artist ID</label>
                <input 
                  type="number" 
                  required
                  value={formData.artist_id}
                  onChange={e => setFormData({...formData, artist_id: e.target.value})}
                  className="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-gray-100 focus:outline-none focus:ring-2 focus:ring-brand-green"
                />
              </div>
              
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-400 mb-1">Original</label>
                  <input 
                    type="number" 
                    step="0.01"
                    required
                    value={formData.original_score}
                    onChange={e => setFormData({...formData, original_score: e.target.value})}
                    className="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-gray-100 focus:outline-none focus:ring-2 focus:ring-brand-green"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-400 mb-1">Corrected</label>
                  <input 
                    type="number" 
                    step="0.01"
                    required
                    value={formData.corrected_score}
                    onChange={e => setFormData({...formData, corrected_score: e.target.value})}
                    className="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-gray-100 focus:outline-none focus:ring-2 focus:ring-brand-green"
                  />
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-400 mb-1">Reason</label>
                <textarea 
                  required
                  rows={3}
                  value={formData.reason}
                  onChange={e => setFormData({...formData, reason: e.target.value})}
                  className="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-gray-100 focus:outline-none focus:ring-2 focus:ring-brand-green"
                  placeholder="Explain why this correction is necessary..."
                />
              </div>

              <button 
                type="submit" 
                disabled={isSubmitting}
                className="btn-primary w-full py-3 flex items-center justify-center gap-2"
              >
                {isSubmitting ? <Loader className="animate-spin" size={18} /> : <Save size={18} />}
                Apply Correction
              </button>
            </form>
          </div>
        </div>

        {/* List */}
        <div className="lg:col-span-2">
          <div className="card h-full">
            <h2 className="text-xl font-bold text-gray-100 mb-6">Correction History</h2>

            {isLoading ? (
              <div className="flex justify-center py-12">
                <Loader className="animate-spin text-brand-green" size={32} />
              </div>
            ) : corrections.length === 0 ? (
              <div className="text-center py-12 border-2 border-dashed border-gray-700 rounded-lg">
                <p className="text-gray-500">No score corrections on record</p>
              </div>
            ) : (
              <div className="space-y-4">
                {corrections.map((corr) => (
                  <div key={corr.id} className="p-4 bg-gray-800 rounded-lg border border-gray-700">
                    <div className="flex justify-between items-start mb-2">
                      <div>
                        <h3 className="font-bold text-gray-100">{corr.artist_name || 'Artist #' + corr.artist_id}</h3>
                        <p className="text-xs text-gray-500">
                          Corrected by <span className="text-gray-300">{corr.admin_name || 'Admin'}</span> on {new Date(corr.created_at).toLocaleDateString()}
                        </p>
                      </div>
                      <div className="text-right">
                        <span className="text-sm text-gray-400">{corr.original_score}</span>
                        <span className="mx-2 text-gray-600">→</span>
                        <span className="text-lg font-bold text-brand-green">{corr.corrected_score}</span>
                      </div>
                    </div>
                    <div className="mt-3 p-3 bg-gray-900/50 rounded text-sm text-gray-400 italic">
                      "{corr.reason}"
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
