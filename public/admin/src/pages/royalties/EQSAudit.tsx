import { useState } from 'react'
import { Calculator, Search, Download, Filter } from 'lucide-react'
import { calculateEQS } from '../../services/royaltyService'
import { EQSResult } from '../../types/Royalty'

export default function EQSAuditPage() {
  const [startDate, setStartDate] = useState(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0])
  const [endDate, setEndDate] = useState(new Date().toISOString().split('T')[0])
  const [result, setResult] = useState<EQSResult | null>(null)
  const [isCalculating, setIsCalculating] = useState(false)

  async function handleCalculate() {
    setIsCalculating(true)
    try {
      const data = await calculateEQS(startDate, endDate)
      setResult(data)
    } catch (err) {
      console.error('Calculation failed:', err)
    } finally {
      setIsCalculating(false)
    }
  }

  return (
    <div className="max-w-6xl">
      <div className="card mb-6">
        <h1 className="text-3xl font-bold text-gray-100 font-brand">EQS Audit Tool</h1>
        <p className="text-gray-400 mt-2">
          Audit and recalculate Engagement Quality Scores (EQS) for royalty distribution.
        </p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Controls */}
        <div className="lg:col-span-1 space-y-6">
          <div className="card">
            <h2 className="text-lg font-bold text-gray-100 mb-4 flex items-center gap-2">
              <Filter size={20} className="text-brand-green" />
              Audit Parameters
            </h2>
            
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-400 mb-1">Start Date</label>
                <input 
                  type="date" 
                  value={startDate}
                  onChange={(e) => setStartDate(e.target.value)}
                  className="w-full bg-gray-800 border border-gray-700 rounded-md px-3 py-2 text-gray-100 focus:outline-none focus:ring-2 focus:ring-brand-green"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-400 mb-1">End Date</label>
                <input 
                  type="date" 
                  value={endDate}
                  onChange={(e) => setEndDate(e.target.value)}
                  className="w-full bg-gray-800 border border-gray-700 rounded-md px-3 py-2 text-gray-100 focus:outline-none focus:ring-2 focus:ring-brand-green"
                />
              </div>
              
              <button 
                onClick={handleCalculate}
                disabled={isCalculating}
                className="btn-primary w-full py-3 flex items-center justify-center gap-2"
              >
                {isCalculating ? (
                  <span className="animate-spin inline-block w-4 h-4 border-2 border-white/20 border-t-white rounded-full" />
                ) : (
                  <Calculator size={18} />
                )}
                Run Audit Calculation
              </button>
            </div>
          </div>

          <div className="card bg-brand-green/10 border-brand-green/30">
            <h3 className="font-bold text-brand-green mb-2 flex items-center gap-2">
              <Calculator size={16} />
              EQS Formula (Ch. 13)
            </h3>
            <p className="text-xs text-gray-300 leading-relaxed">
              Royalty = (Σ Quality Engagements / Total Pool) × Period Revenue Share.
              <br /><br />
              Weights: 
              <br />• Plays: 1.0x
              <br />• Shares: 2.5x
              <br />• Comments: 1.5x
            </p>
          </div>
        </div>

        {/* Results */}
        <div className="lg:col-span-2">
          <div className="card h-full">
            <div className="flex justify-between items-center mb-6">
              <h2 className="text-xl font-bold text-gray-100">Audit Results</h2>
              {result && (
                <button className="text-brand-green flex items-center gap-1 text-sm hover:underline">
                  <Download size={16} /> Export CSV
                </button>
              )}
            </div>

            {!result ? (
              <div className="flex flex-col items-center justify-center h-64 border-2 border-dashed border-gray-700 rounded-lg text-gray-500">
                <Search size={48} className="mb-4 opacity-20" />
                <p>Run a calculation to see audit details</p>
              </div>
            ) : (
              <div className="space-y-6">
                <div className="grid grid-cols-2 gap-4">
                  <div className="p-4 bg-gray-800 rounded-lg">
                    <p className="text-sm text-gray-400 mb-1">Total Pool Share</p>
                    <p className="text-2xl font-bold text-brand-green">${result.total_pool.toLocaleString()}</p>
                  </div>
                  <div className="p-4 bg-gray-800 rounded-lg">
                    <p className="text-sm text-gray-400 mb-1">Status</p>
                    <p className="text-2xl font-bold text-gray-100">{result.status}</p>
                  </div>
                </div>

                <div className="border border-gray-700 rounded-lg overflow-hidden">
                  <table className="w-full text-left">
                    <thead className="bg-gray-800/50">
                      <tr>
                        <th className="px-4 py-3 text-xs font-bold text-gray-400 uppercase tracking-wider">Metric</th>
                        <th className="px-4 py-3 text-xs font-bold text-gray-400 uppercase tracking-wider">Raw Count</th>
                        <th className="px-4 py-3 text-xs font-bold text-gray-400 uppercase tracking-wider text-right">Weighted</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-800">
                      <tr>
                        <td className="px-4 py-3 text-gray-300">Total Plays</td>
                        <td className="px-4 py-3 text-gray-100">12,450</td>
                        <td className="px-4 py-3 text-right text-brand-green font-mono">12,450.00</td>
                      </tr>
                      <tr>
                        <td className="px-4 py-3 text-gray-300">Social Shares</td>
                        <td className="px-4 py-3 text-gray-100">840</td>
                        <td className="px-4 py-3 text-right text-brand-green font-mono">2,100.00</td>
                      </tr>
                      <tr>
                        <td className="px-4 py-3 text-gray-300">Comments</td>
                        <td className="px-4 py-3 text-gray-100">320</td>
                        <td className="px-4 py-3 text-right text-brand-green font-mono">480.00</td>
                      </tr>
                    </tbody>
                    <tfoot className="bg-gray-800/30">
                      <tr>
                        <td className="px-4 py-3 font-bold text-gray-100" colSpan={2}>Total EQS Score</td>
                        <td className="px-4 py-3 text-right text-brand-green font-bold font-mono">15,030.00</td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
