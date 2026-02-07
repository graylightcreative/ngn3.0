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
  updated_at?: string
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
  created_at: string
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

export interface Certificate {
  certificate_id: string
  artist: string
  artist_verified: boolean
  isrc: string
  status: string
  verified_at?: string
  contributors: RightsSplit[]
  generated_at: string
}
