import { useEffect, useState } from 'react'
import { User, Search, Loader, Filter, Edit, Shield, Mail } from 'lucide-react'
import { getEntities, updateEntity } from '../../services/entityService'

interface User {
  id: number
  email: string
  display_name: string
  username: string
  role_id: number
  status: string
  created_at: string
}

export default function UsersPage() {
  const [users, setUsers] = useState<User[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [total, setTotal] = useState(0)
  
  const limit = 20

  useEffect(() => {
    loadData()
  }, [page, search])

  async function loadData() {
    setIsLoading(true)
    try {
      const data = await getEntities('users', limit, (page - 1) * limit, search)
      setUsers(data.items)
      setTotal(data.total)
    } catch (err) {
      console.error(err)
    } finally {
      setIsLoading(false)
    }
  }

  const getRoleName = (roleId: number) => {
    switch (roleId) {
      case 1: return 'Admin'
      case 2: return 'Staff'
      case 3: return 'Artist'
      case 4: return 'Label'
      default: return 'User'
    }
  }

  return (
    <div className="max-w-6xl">
      <div className="card mb-6">
        <div className="flex items-center gap-3 mb-4">
          <User className="text-purple-500" size={28} />
          <h1 className="text-3xl font-bold text-gray-100">User Management</h1>
        </div>
        <p className="text-gray-400">
          Manage platform accounts, permissions, and roles.
        </p>
      </div>

      <div className="card">
        <div className="flex justify-between items-center mb-6">
          <div className="relative w-64">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" size={18} />
            <input 
              type="text"
              placeholder="Search by email..."
              className="w-full bg-gray-800 border border-gray-700 rounded-lg pl-10 pr-4 py-2 text-sm text-gray-100 focus:outline-none focus:ring-2 focus:ring-purple-500"
              value={search}
              onChange={(e) => { setSearch(e.target.value); setPage(1); }}
            />
          </div>
          <button className="btn-secondary flex items-center gap-2">
            <Filter size={16} /> Filters
          </button>
        </div>

        {isLoading ? (
          <div className="flex justify-center py-12">
            <Loader className="animate-spin text-purple-500" size={32} />
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left">
              <thead>
                <tr className="border-b border-gray-700 text-gray-400 text-sm">
                  <th className="pb-3 pl-4">User</th>
                  <th className="pb-3">Role</th>
                  <th className="pb-3">Status</th>
                  <th className="pb-3">Joined</th>
                  <th className="pb-3 text-right pr-4">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-800">
                {users.map((user) => (
                  <tr key={user.id} className="group hover:bg-gray-800/30">
                    <td className="py-3 pl-4">
                      <div>
                        <p className="font-semibold text-gray-100">{user.display_name || user.username}</p>
                        <p className="text-xs text-gray-500 flex items-center gap-1">
                          <Mail size={10} /> {user.email}
                        </p>
                      </div>
                    </td>
                    <td className="py-3">
                      <div className="flex items-center gap-1.5 text-sm text-gray-300">
                        <Shield size={14} className={user.role_id === 1 ? 'text-red-400' : 'text-gray-500'} />
                        {getRoleName(user.role_id)}
                      </div>
                    </td>
                    <td className="py-3">
                      <span className={`px-2 py-1 rounded text-xs font-bold ${
                        user.status === 'active' 
                          ? 'bg-green-500/20 text-green-400' 
                          : 'bg-red-500/20 text-red-400'
                      }`}>
                        {user.status.toUpperCase()}
                      </span>
                    </td>
                    <td className="py-3 text-sm text-gray-400">
                      {new Date(user.created_at).toLocaleDateString()}
                    </td>
                    <td className="py-3 pr-4 text-right">
                      <button className="p-1 hover:bg-gray-700 rounded text-gray-400 hover:text-white">
                        <Edit size={16} />
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

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
