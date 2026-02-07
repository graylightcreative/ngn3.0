export interface SMRIngestion {
  id: number
  filename: string
  file_hash: string
  file_size: number
  status: 'pending_review' | 'pending_finalize' | 'finalized' | 'error'
  uploaded_by: number
  created_at: string
  updated_at?: string
}

export interface SMRRecord {
  id: number
  ingestion_id: number
  artist_name: string
  track_title: string
  spin_count: number
  add_count: number
  isrc?: string
  station_id?: number
  cdm_artist_id?: number
  status: 'pending_mapping' | 'mapped' | 'imported'
  created_at: string
}

export interface UnmatchedArtist {
  artist_name: string
  record_count: number
}

export interface ReviewRecord extends SMRRecord {
  artist_name_verified?: string
}

export interface UploadResult {
  ingestion_id: number
  filename: string
  records_parsed: number
}

export interface FinalizeResult {
  success: boolean
  ingestion_id: number
  records_imported: number
}
