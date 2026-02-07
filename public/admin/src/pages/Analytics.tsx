import { useEffect, useState } from 'react'
import { BarChart3, TrendingUp, Users, DollarSign, Zap, Loader, Calendar } from 'lucide-react'
import { getAnalyticsSummary, getAnalyticsTrends } from '../services/analyticsService'

export default function AnalyticsPage() {
  const [summary, setSummary] = useState<any>(null)
  const [trends, setTrends] = useState<any>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [days, setDays] = useState(30)

  useEffect(() => {
    loadData()
  }, [days])

  async function loadData() {
    setIsLoading(true)
    try {
      const [s, t] = await Promise.all([
        getAnalyticsSummary(),
        getAnalyticsTrends(days)
      ])
      setSummary(s)
      setTrends(t)
    } catch (err) {
      console.error(err)
    } finally {
      setIsLoading(false)
    }
  }

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader className="animate-spin text-brand-green" size={32} />
      </div>
    )
  }

  return (
    <div className="max-w-6xl">
      <div className="flex justify-between items-center mb-6">
        <div className="flex items-center gap-3">
          <BarChart3 className="text-brand-green" size={28} />
          <h1 className="text-3xl font-bold text-gray-100">Platform Analytics</h1>
        </div>
        
        <div className="flex bg-gray-800 rounded-lg p-1 border border-gray-700">
          {[7, 30, 90].map(d => (
            <button
              key={d}
              onClick={() => setDays(d)}
              className={`px-4 py-1.5 rounded-md text-sm font-medium transition ${
                days === d ? 'bg-brand-green text-black' : 'text-gray-400 hover:text-gray-200'
              }`}
            >
              {d}D
            </button>
          ))}
        </div>
      </div>

      {/* Summary Stats */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div className="card border-l-4 border-l-blue-500">
          <p className="text-sm text-gray-400 mb-1">Total Users</p>
          <div className="flex items-end justify-between">
            <h3 className="text-2xl font-bold text-gray-100">{summary.users.toLocaleString()}</h3>
            <Users className="text-blue-500/50" size={24} />
          </div>
        </div>
        
        <div className="card border-l-4 border-l-green-500">
          <p className="text-sm text-gray-400 mb-1">30D Revenue</p>
          <div className="flex items-end justify-between">
            <h3 className="text-2xl font-bold text-gray-100">${summary.revenue_30d.toLocaleString()}</h3>
            <DollarSign className="text-green-500/50" size={24} />
          </div>
        </div>

        <div className="card border-l-4 border-l-pink-500">
          <p className="text-sm text-gray-400 mb-1">Engagements</p>
          <div className="flex items-end justify-between">
            <h3 className="text-2xl font-bold text-gray-100">{summary.engagements_30d.toLocaleString()}</h3>
            <Zap className="text-pink-500/50" size={24} />
          </div>
        </div>

        <div className="card border-l-4 border-l-orange-500">
          <p className="text-sm text-gray-400 mb-1">Active Artists</p>
          <div className="flex items-end justify-between">
            <h3 className="text-2xl font-bold text-gray-100">{summary.artists.toLocaleString()}</h3>
            <TrendingUp className="text-orange-500/50" size={24} />
          </div>
        </div>
      </div>

      {/* Trends Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="card">
          <h2 className="text-lg font-bold text-gray-100 mb-6 flex items-center gap-2">
            <DollarSign size={20} className="text-brand-green" />
            Revenue Growth
          </h2>
          <div className="h-64 flex items-end gap-1 px-2 border-b border-gray-700">
            {trends.revenue.length === 0 ? (
              <div className="w-full h-full flex items-center justify-center text-gray-500 italic">No data for this period</div>
            ) : (
              trends.revenue.map((item: any, i: number) => {
                const height = (item.total / Math.max(...trends.revenue.map((r:any) => r.total))) * 100
                return (
                  <div key={i} className="flex-1 bg-brand-green/40 hover:bg-brand-green transition-all rounded-t-sm" style={{ height: `${height}%` }} title={`${item.date}: $${item.total}`} />
                )
              })
            )}
          </div>
          <div className="flex justify-between mt-2 text-[10px] text-gray-500 font-bold uppercase tracking-widest">
            <span>{days} Days Ago</span>
            <span>Today</span>
          </div>
        </div>

        <div className="card">
          <h2 className="text-lg font-bold text-gray-100 mb-6 flex items-center gap-2">
            <Zap size={20} className="text-pink-500" />
            Engagement Activity
          </h2>
          <div className="h-64 flex items-end gap-1 px-2 border-b border-gray-700">
            {trends.engagement.length === 0 ? (
              <div className="w-full h-full flex items-center justify-center text-gray-500 italic">No data for this period</div>
            ) : (
              trends.engagement.map((item: any, i: number) => {
                const height = (item.count / Math.max(...trends.engagement.map((r:any) => r.count))) * 100
                return (
                  <div key={i} className="flex-1 bg-pink-500/40 hover:bg-pink-500 transition-all rounded-t-sm" style={{ height: `${height}%` }} title={`${item.date}: ${item.count}`} />
                )
              })
            )}
          </div>
          <div className="flex justify-between mt-2 text-[10px] text-gray-500 font-bold uppercase tracking-widest">
            <span>{days} Days Ago</span>
            <span>Today</span>
          </div>
        </div>
      </div>

      {/* Bottom Insights */}
      <div className="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
        <div className="card bg-gray-800/30">
          <h3 className="text-sm font-bold text-gray-400 uppercase tracking-wider mb-4">Top Region</h3>
          <p className="text-xl font-bold text-gray-100">North America</p>
          <p className="text-xs text-brand-green mt-1">+12% from last month</p>
        </div>
        <div className="card bg-gray-800/30">
          <h3 className="text-sm font-bold text-gray-400 uppercase tracking-wider mb-4">New Artists</h3>
          <p className="text-xl font-bold text-gray-100">42</p>
          <p className="text-xs text-brand-green mt-1">+5% from last week</p>
        </div>
        <div className="card bg-gray-800/30">
          <h3 className="text-sm font-bold text-gray-400 uppercase tracking-wider mb-4">Avg. Session</h3>
          <p className="text-xl font-bold text-gray-100">8m 24s</p>
          <p className="text-xs text-brand-green mt-1">+24s from yesterday</p>
        </div>
      </div>
    </div>
  )
}
