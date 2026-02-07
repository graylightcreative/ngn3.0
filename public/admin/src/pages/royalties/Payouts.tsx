import { useEffect, useState } from 'react'
import { CheckCircle, AlertCircle, Loader, ArrowRight, ExternalLink } from 'lucide-react'
import { getPendingPayouts, processPayoutRequest } from '../../services/royaltyService'
import { Payout } from '../../types/Royalty'

export default function PayoutsPage() {
  const [payouts, setPayouts] = useState<Payout[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [processingId, setProcessingId] = useState<number | null>(null)
  const [message, setMessage] = useState<{ type: 'success' | 'error', text: string } | null>(null)

  useEffect(() => {
    loadPayouts()
  }, [])

  async function loadPayouts() {
    setIsLoading(true)
    try {
      const data = await getPendingPayouts()
      setPayouts(data)
    } catch (err) {
      console.error('Failed to load payouts:', err)
    } finally {
      setIsLoading(false)
    }
  }

  async function handleProcess(payoutId: number) {
    setProcessingId(payoutId)
    setMessage(null)
    try {
      const result = await processPayoutRequest(payoutId)
      setMessage({ type: 'success', text: `Successfully processed payout. Stripe ID: ${result.stripe_transfer_id}` })
      loadPayouts() // Refresh list
    } catch (err: any) {
      setMessage({ type: 'error', text: err.response?.data?.error || 'Failed to process payout' })
    } finally {
      setProcessingId(null)
    }
  }

  return (
    <div className="max-w-6xl">
      <div className="card mb-6">
        <h1 className="text-3xl font-bold text-gray-100">Payout Management</h1>
        <p className="text-gray-400 mt-2">
          Review and process pending payout requests via Stripe Connect.
        </p>
      </div>

      {message && (
        <div className={`mb-6 p-4 rounded-lg flex items-center gap-3 ${
          message.type === 'success' ? 'bg-green-500/20 text-green-400 border border-green-500/50' : 'bg-red-500/20 text-red-400 border border-red-500/50'
        }`}>
          {message.type === 'success' ? <CheckCircle size={20} /> : <AlertCircle size={20} />}
          {message.text}
        </div>
      )}

      <div className="card">
        <div className="flex justify-between items-center mb-6">
          <h2 className="text-xl font-bold text-gray-100">Pending Requests ({payouts.length})</h2>
          <button onClick={loadPayouts} className="text-sm text-brand-green hover:underline">Refresh</button>
        </div>

        {isLoading ? (
          <div className="flex justify-center py-12">
            <Loader className="animate-spin text-brand-green" size={32} />
          </div>
        ) : payouts.length === 0 ? (
          <div className="text-center py-12 border-2 border-dashed border-gray-700 rounded-lg">
            <p className="text-gray-500">No pending payout requests found</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left">
              <thead>
                <tr className="border-b border-gray-700">
                  <th className="pb-3 font-semibold text-gray-400">User</th>
                  <th className="pb-3 font-semibold text-gray-400">Amount</th>
                  <th className="pb-3 font-semibold text-gray-400">Requested At</th>
                  <th className="pb-3 font-semibold text-gray-400 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-800">
                {payouts.map((payout) => (
                  <tr key={payout.id} className="group hover:bg-gray-800/30">
                    <td className="py-4">
                      <div className="font-medium text-gray-100">{payout.display_name || 'User #' + payout.user_id}</div>
                      <div className="text-xs text-gray-500">{payout.email}</div>
                    </td>
                    <td className="py-4 font-mono font-bold text-brand-green">
                      ${payout.amount}
                    </td>
                    <td className="py-4 text-sm text-gray-400">
                      {new Date(payout.requested_at).toLocaleDateString()}
                    </td>
                    <td className="py-4 text-right">
                      <button 
                        onClick={() => handleProcess(payout.id)}
                        disabled={processingId !== null}
                        className="btn-primary px-4 py-2 text-sm disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 ml-auto"
                      >
                        {processingId === payout.id ? (
                          <Loader className="animate-spin" size={16} />
                        ) : (
                          <ArrowRight size={16} />
                        )}
                        Process Payout
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Stripe Monitoring */}
      <div className="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6">
        <div className="card border-l-4 border-l-brand-green">
          <h3 className="font-bold text-gray-100 mb-2">Stripe Connect Status</h3>
          <p className="text-sm text-gray-400 mb-4">The platform is currently connected to Stripe in Sandbox mode.</p>
          <a href="https://dashboard.stripe.com/test/connect/accounts" target="_blank" rel="noreferrer" className="text-brand-green text-sm flex items-center gap-1 hover:underline">
            Open Stripe Dashboard <ExternalLink size={14} />
          </a>
        </div>
        <div className="card">
          <h3 className="font-bold text-gray-100 mb-2">Payout Policy</h3>
          <p className="text-sm text-gray-400">
            Manual review is required for all payouts over $500. Processing typically takes 1-3 business days.
          </p>
        </div>
      </div>
    </div>
  )
}