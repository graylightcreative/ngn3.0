import api from './api'

export interface SMRIngestion {
  id: number
  filename: string
  file_hash: string
  file_size: number
  status: 'pending_review' | 'pending_finalize' | 'finalized'
  uploaded_by: number
  created_at: string
}

export interface SMRRecord {
  id: number
  ingestion_id: number
  artist_name: string
  track_title: string
  spin_count: number
  add_count: number
  isrc: string
  cdm_artist_id?: number
  status: string
}

export interface UnmatchedArtist {
  artist_name: string
  record_count: number
}

export interface UploadResponse {
  success: boolean
  data: {
    ingestion_id: number
    filename: string
    records_parsed: number
  }
}

// Get pending SMR ingestions
export async function getPendingIngestions(): Promise<SMRIngestion[]> {
  const response = await api.get('/admin/smr/pending')
  return response.data.data || []
}

// Upload SMR file
export async function uploadSMRFile(file: File): Promise<UploadResponse> {
  const formData = new FormData()
  formData.append('file', file)

  const response = await api.post('/admin/smr/upload', formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  })

  return response.data
}

// Get unmatched artists for an ingestion
export async function getUnmatchedArtists(ingestionId: number): Promise<UnmatchedArtist[]> {
  const response = await api.get(`/admin/smr/${ingestionId}/unmatched`)
  return response.data.data || []
}

// Map artist identity
export async function mapArtistIdentity(
  ingestionId: number,
  unmatched: string,
  cdmArtistId: number
): Promise<void> {
  await api.post('/admin/smr/map-identity', {
    ingestion_id: ingestionId,
    unmatched,
    cdm_artist_id: cdmArtistId
  })
}

// Get records for review
export async function getReviewRecords(ingestionId: number): Promise<SMRRecord[]> {
  const response = await api.get(`/admin/smr/${ingestionId}/review`)
  return response.data.data || []
}

// Finalize ingestion
export async function finalizeIngestion(ingestionId: number) {
  const response = await api.post(`/admin/smr/${ingestionId}/finalize`)
  return response.data.data
}
