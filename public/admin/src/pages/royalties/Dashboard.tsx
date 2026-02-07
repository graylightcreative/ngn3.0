import { useEffect, useState } from 'react'
import { DollarSign, Loader, TrendingUp, Clock, CheckCircle2 } from 'lucide-react'
import { getBalance, getPendingPayouts, calculateEQS } from '../../services/royaltyService'
import { Payout, EQSResult } from '../../types/Royalty'

export default function RoyaltiesDashboard() {
  const [pendingPayouts, setPendingPayouts] = useState<Payout[]>([])
  const [eqsResult, setEqsResult] = useState<EQSResult | null>(null)
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    (async () => {
      try {
        const [payouts, eqs] = await Promise.all([
          getPendingPayouts(),
          calculateEQS()
        ])
        setPendingPayouts(payouts)
        setEqsResult(eqs)
      } catch (err) {
        console.error('Failed to load royalty data:', err)
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

  return (
    <div className="max-w-6xl">
      <div className="card mb-6">
        <div className="flex items-center gap-3 mb-4">
          <DollarSign className="text-brand-green" size={28} />
          <h1 className="text-3xl font-bold text-gray-100">Royalty Dashboard</h1>
        </div>
        <p className="text-gray-400">
          EQS calculations, payout processing, and Stripe Connect monitoring.
        </p>
      </div>

      {/* Key Metrics */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div className="card bg-gray-800/50">
          <div className="flex items-start justify-between">
            <div>
              <p className="text-sm text-gray-400 mb-1 font-medium">Total EQS Pool</p>
              <div className="text-3xl font-bold text-brand-green">
                ${eqsResult?.total_pool.toLocaleString() || '0.00'}
              </div>
            </div>
            <TrendingUp className="text-gray-500" size={20} />
          </div>
        </div>
        
        <div className="card bg-gray-800/50">
          <div className="flex items-start justify-between">
            <div>
              <p className="text-sm text-gray-400 mb-1 font-medium">Pending Payouts</p>
              <div className="text-3xl font-bold text-yellow-500">
                {pendingPayouts.length}
              </div>
            </div>
            <Clock className="text-gray-500" size={20} />
          </div>
        </div>

        <div className="card bg-gray-800/50">
          <div className="flex items-start justify-between">
            <div>
              <p className="text-sm text-gray-400 mb-1 font-medium">Stripe Status</p>
              <div className="text-lg font-bold text-brand-green flex items-center gap-2 mt-2">
                <CheckCircle2 size={18} />
                Connected
              </div>
            </div>
            <DollarSign className="text-gray-500" size={20} />
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Recent Payout Requests */}
        <div className="card">
          <h2 className="text-xl font-bold text-gray-100 mb-4">Recent Payout Requests</h2>
          {pendingPayouts.length === 0 ? (
            <div className="text-center py-8 border-2 border-dashed border-gray-700 rounded-lg">
              <p className="text-gray-500">No pending payout requests</p>
            </div>
          ) : (
            <div className="space-y-3">
              {pendingPayouts.slice(0, 5).map(payout => (
                <div key={payout.id} className="p-4 bg-gray-800 rounded-lg border border-gray-700 flex justify-between items-center">
                  <div>
                    <p className="font-semibold text-gray-100">{payout.display_name || 'User #' + payout.user_id}</p>
                    <p className="text-sm text-brand-green font-bold">${payout.amount}</p>
                  </div>
                  <button className="btn-secondary px-4 py-2 text-sm">Review</button>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* EQS Period Status */}
        <div className="card">
          <h2 className="text-xl font-bold text-gray-100 mb-4">Current EQS Period</h2>
          <div className="p-4 bg-gray-800 rounded-lg border border-gray-700">
            <div className="flex justify-between items-center mb-4">
              <span className="text-gray-400">Status</span>
              <span className="px-2 py-1 bg-brand-green/20 text-brand-green text-xs rounded uppercase font-bold">
                {eqsResult?.status || 'Active'}
              </span>
            </div>
            <div className="flex justify-between items-center mb-2">
              <span className="text-gray-400">Starts</span>
              <span className="text-gray-100">{eqsResult?.period_start}</span>
            </div>
            <div className="flex justify-between items-center">
              <span className="text-gray-400">Ends</span>
              <span className="text-gray-100">{eqsResult?.period_end}</span>
            </div>
          </div>
          <button className="btn-primary w-full mt-6">Recalculate Current Period</button>
        </div>
      </div>
    </div>
  )
}