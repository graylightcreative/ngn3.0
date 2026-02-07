import api from './api'

export interface RightsRegistration {
  id: number
  artist_id: number
  artist_name: string
  track_id?: number
  isrc?: string
  owner_id: number
  status: 'pending' | 'verified' | 'disputed' | 'rejected'
  verified_at?: string
  created_at: string
}

export interface RightsSplit {
  id: number
  right_id: number
  contributor_id: number
  percentage: number
  role?: string
  verified: boolean
  email?: string
  name?: string
}

export interface RightsDispute {
  id: number
  right_id: number
  reason: string
  resolution?: string
  status: 'open' | 'resolved' | 'rejected'
  created_at: string
  resolved_at?: string
  artist_name?: string
}

export interface RightsSummary {
  pending: number
  verified: number
  disputed: number
  rejected: number
}

export interface RegistryResponse {
  success: boolean
  data: {
    registry: RightsRegistration[]
    summary: RightsSummary
  }
}

// Get rights registry
export async function getRegistry(
  status?: string,
  limit: number = 50,
  offset: number = 0
): Promise<RegistryResponse> {
  const params = new URLSearchParams()
  if (status) params.append('status', status)
  params.append('limit', limit.toString())
  params.append('offset', offset.toString())

  const response = await api.get(`/admin/rights-ledger?${params}`)
  return response.data
}

// Get disputes
export async function getDisputes(
  status?: string,
  limit: number = 50
): Promise<RightsDispute[]> {
  const params = new URLSearchParams()
  if (status) params.append('status', status)
  params.append('limit', limit.toString())

  const response = await api.get(`/admin/rights-ledger/disputes?${params}`)
  return response.data.data || []
}

// Get single right with splits
export async function getRight(rightId: number) {
  const response = await api.get(`/admin/rights-ledger/${rightId}`)
  return response.data.data
}

// Update right status
export async function updateStatus(rightId: number, status: string): Promise<void> {
  await api.put(`/admin/rights-ledger/${rightId}/status`, { status })
}

// Resolve dispute
export async function resolveDispute(
  rightId: number,
  resolution: string,
  finalStatus: string
): Promise<void> {
  await api.post(`/admin/rights-ledger/${rightId}/resolve-dispute`, {
    resolution,
    final_status: finalStatus
  })
}

// Add ownership split
export async function addSplit(
  rightId: number,
  contributorId: number,
  percentage: number,
  role?: string
): Promise<number> {
  const response = await api.post(`/admin/rights-ledger/${rightId}/splits`, {
    contributor_id: contributorId,
    percentage,
    role
  })
  return response.data.data.split_id
}

// Get splits
export async function getSplits(rightId: number): Promise<RightsSplit[]> {
  const { data } = await getRight(rightId)
  return data.splits || []
}

// Generate Digital Safety Seal certificate
export async function generateCertificate(rightId: number) {
  const response = await api.get(`/admin/rights-ledger/${rightId}/certificate`)
  return response.data.data
}
