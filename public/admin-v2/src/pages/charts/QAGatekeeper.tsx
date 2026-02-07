import { useEffect, useState } from 'react'
import { Zap, CheckCircle2, AlertTriangle, XCircle, Loader, ArrowRight, ShieldCheck } from 'lucide-react'
import { getQAStatus, getCorrections, getScoreDisputes } from '../../services/chartService'
import { QAStatus, ScoreCorrection, ScoreDispute } from '../../types/Chart'

export default function ChartQA() {
  const [qaStatus, setQaStatus] = useState<QAStatus | null>(null)
  const [corrections, setCorrections] = useState<ScoreCorrection[]>([])
  const [disputes, setDisputes] = useState<ScoreDispute[]>([])
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    (async () => {
      try {
        const [status, corr, disp] = await Promise.all([
          getQAStatus(),
          getCorrections(5),
          getScoreDisputes('open')
        ])
        setQaStatus(status)
        setCorrections(corr)
        setDisputes(disp)
      } catch (err) {
        console.error('Failed to load QA data:', err)
      } finally {
        setIsLoading(false)
      }
    })()
  }, [])

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader className="animate-spin text-brand-green" size={32} />
      </div>
    )
  }

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'pass': return <CheckCircle2 className="text-brand-green" size={20} />
      case 'warn': return <AlertTriangle className="text-yellow-500" size={20} />
      case 'fail': return <XCircle className="text-red-500" size={20} />
      default: return null
    }
  }

  return (
    <div className="max-w-6xl">
      <div className="card mb-6">
        <div className="flex items-center gap-3 mb-4">
          <Zap className="text-yellow-500" size={28} />
          <h1 className="text-3xl font-bold text-gray-100">Chart QA Gatekeeper</h1>
        </div>
        <p className="text-gray-400">
          Weekly validation gates for chart integrity, manual corrections, and dispute resolution.
        </p>
      </div>

      {/* QA Gates */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        {qaStatus?.gates.map((gate) => (
          <div key={gate.id} className="card bg-gray-800/50">
            <div className="flex justify-between items-start mb-2">
              <p className="text-sm text-gray-400 font-medium">{gate.name}</p>
              {getStatusIcon(gate.status)}
            </div>
            <div className="text-2xl font-bold text-gray-100">{gate.value}%</div>
            <div className="mt-2 w-full bg-gray-700 h-1.5 rounded-full overflow-hidden">
              <div 
                className={`h-full ${gate.status === 'pass' ? 'bg-brand-green' : gate.status === 'warn' ? 'bg-yellow-500' : 'bg-red-500'}`}
                style={{ width: `${gate.value}%` }}
              />
            </div>
            <p className="text-[10px] text-gray-500 mt-2 uppercase font-bold tracking-wider">
              Target: {gate.target}%
            </p>
          </div>
        ))}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Active Disputes */}
        <div className="card">
          <div className="flex justify-between items-center mb-4">
            <h2 className="text-xl font-bold text-gray-100">Active Disputes</h2>
            <span className="bg-red-500/20 text-red-500 px-2 py-0.5 rounded text-xs font-bold">
              {disputes.length} OPEN
            </span>
          </div>
          
          {disputes.length === 0 ? (
            <div className="text-center py-8 border-2 border-dashed border-gray-700 rounded-lg">
              <p className="text-gray-500">No active disputes</p>
            </div>
          ) : (
            <div className="space-y-3">
              {disputes.map(dispute => (
                <div key={dispute.id} className="p-4 bg-gray-800 rounded-lg border border-gray-700">
                  <div className="flex justify-between items-start mb-2">
                    <p className="font-semibold text-gray-100">{dispute.artist_name || 'Artist #' + dispute.artist_id}</p>
                    <span className="text-[10px] text-gray-500 uppercase">{new Date(dispute.created_at).toLocaleDateString()}</span>
                  </div>
                  <p className="text-sm text-gray-400 line-clamp-2 mb-3 italic">"{dispute.reason}"</p>
                  <button className="text-brand-green text-sm font-bold flex items-center gap-1 hover:underline">
                    Resolve Dispute <ArrowRight size={14} />
                  </button>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Recent Corrections */}
        <div className="card">
          <h2 className="text-xl font-bold text-gray-100 mb-4">Recent Corrections</h2>
          
          {corrections.length === 0 ? (
            <div className="text-center py-8 border-2 border-dashed border-gray-700 rounded-lg">
              <p className="text-gray-500">No recent corrections</p>
            </div>
          ) : (
            <div className="space-y-3">
              {corrections.map(correction => (
                <div key={correction.id} className="p-4 bg-gray-800/50 rounded-lg border border-gray-700 flex items-center gap-4">
                  <div className="bg-yellow-500/10 p-2 rounded">
                    <Zap className="text-yellow-500" size={16} />
                  </div>
                  <div className="flex-1">
                    <div className="flex justify-between">
                      <p className="font-medium text-gray-100 text-sm">{correction.artist_name || 'Artist #' + correction.artist_id}</p>
                      <p className="text-[10px] text-gray-500">{new Date(correction.created_at).toLocaleDateString()}</p>
                    </div>
                    <p className="text-xs text-gray-400 mt-1">
                      {correction.original_score} → <span className="text-brand-green font-bold">{correction.corrected_score}</span>
                    </p>
                  </div>
                </div>
              ))}
            </div>
          )}
          <button className="btn-secondary w-full mt-6 py-2 text-sm">New Score Correction</button>
        </div>
      </div>

      {/* Final Verification */}
      <div className="mt-8 card border-t-4 border-t-brand-green bg-brand-green/5">
        <div className="flex items-center gap-4">
          <div className="bg-brand-green p-3 rounded-full text-black">
            <ShieldCheck size={24} />
          </div>
          <div className="flex-1">
            <h3 className="text-lg font-bold text-gray-100">Ready for Publication?</h3>
            <p className="text-sm text-gray-400">
              {qaStatus?.overall_status === 'pass' 
                ? 'All validation gates passed. This week\'s chart is ready for release.' 
                : 'Validation gates are not yet satisfied. Manual review of anomalies is required.'}
            </p>
          </div>
          <button 
            disabled={qaStatus?.overall_status !== 'pass'}
            className="btn-primary px-8 py-3 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Release Official Chart
          </button>
        </div>
      </div>
    </div>
  )
}