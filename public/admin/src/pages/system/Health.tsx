import { useEffect, useState } from 'react'
import { Activity, Server, Database, HardDrive, Cpu, Clock, Loader } from 'lucide-react'
import api from '../../services/api'

export default function SystemHealthPage() {
  const [health, setHealth] = useState<any>(null)
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    loadHealth()
  }, [])

  async function loadHealth() {
    setIsLoading(true)
    try {
      const response = await api.get('/admin/system/health')
      setHealth(response.data.data)
    } catch (err) {
      console.error(err)
    } finally {
      setIsLoading(false)
    }
  }

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader className="animate-spin text-blue-500" size={32} />
      </div>
    )
  }

  return (
    <div className="max-w-6xl">
      <div className="card mb-6">
        <div className="flex items-center gap-3 mb-4">
          <Activity className="text-pink-500" size={28} />
          <h1 className="text-3xl font-bold text-gray-100">System Health</h1>
        </div>
        <p className="text-gray-400">
          Real-time monitoring of server resources and service status.
        </p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {/* Database */}
        <div className="card border-t-4 border-t-blue-500">
          <div className="flex justify-between items-start mb-4">
            <h3 className="font-bold text-gray-100 flex items-center gap-2">
              <Database size={18} /> Database
            </h3>
            <span className={`px-2 py-1 rounded text-xs font-bold uppercase ${health.database.status === 'ok' ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'}`}>
              {health.database.status}
            </span>
          </div>
          <p className="text-gray-400 text-sm">Latency</p>
          <p className="text-2xl font-mono text-gray-100">{health.database.latency_ms}ms</p>
        </div>

        {/* Disk */}
        <div className="card border-t-4 border-t-orange-500">
          <div className="flex justify-between items-start mb-4">
            <h3 className="font-bold text-gray-100 flex items-center gap-2">
              <HardDrive size={18} /> Disk Storage
            </h3>
            <span className={`px-2 py-1 rounded text-xs font-bold uppercase ${health.disk_space.status === 'ok' ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'}`}>
              {health.disk_space.status}
            </span>
          </div>
          <div className="w-full bg-gray-700 h-2 rounded-full overflow-hidden mb-2">
            <div className="bg-orange-500 h-full" style={{ width: `${health.disk_space.percent_used}%` }}></div>
          </div>
          <div className="flex justify-between text-xs text-gray-400">
            <span>{health.disk_space.percent_used}% Used</span>
            <span>{health.disk_space.free_gb}GB Free</span>
          </div>
        </div>

        {/* System */}
        <div className="card border-t-4 border-t-purple-500">
          <div className="flex justify-between items-start mb-4">
            <h3 className="font-bold text-gray-100 flex items-center gap-2">
              <Server size={18} /> Server
            </h3>
            <span className="px-2 py-1 rounded text-xs font-bold bg-gray-700 text-gray-300">
              PHP {health.php_version}
            </span>
          </div>
          <div className="space-y-2">
            <div className="flex justify-between items-center text-sm">
              <span className="text-gray-400 flex items-center gap-1"><Cpu size={14} /> Load</span>
              <span className="font-mono text-gray-100">{health.server_load[0]} / {health.server_load[1]}</span>
            </div>
            <div className="flex justify-between items-center text-sm">
              <span className="text-gray-400 flex items-center gap-1"><Activity size={14} /> Memory</span>
              <span className="font-mono text-gray-100">{health.memory_usage}</span>
            </div>
          </div>
        </div>
      </div>

      <div className="mt-6 text-center text-xs text-gray-500 flex items-center justify-center gap-2">
        <Clock size={12} /> Last updated: {new Date(health.timestamp).toLocaleString()}
        <button onClick={loadHealth} className="text-blue-400 hover:underline ml-2">Refresh</button>
      </div>
    </div>
  )
}
