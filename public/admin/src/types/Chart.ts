export interface QAGate {
    id: string
    name: string
    value: number
    target: number
    status: 'pass' | 'warn' | 'fail'
}

export interface QAStatus {
    gates: QAGate[]
    overall_status: 'pass' | 'review_required'
}

export interface ScoreCorrection {
    id: number
    ingestion_id?: number
    artist_id: number
    artist_name?: string
    original_score: number
    corrected_score: number
    reason: string
    corrected_by: number
    admin_name?: string
    approved: boolean
    created_at: string
}

export interface ScoreDispute {
    id: number
    ingestion_id?: number
    artist_id: number
    artist_name?: string
    reported_by: number
    reporter_name?: string
    reason: string
    resolution?: string
    status: 'open' | 'resolved' | 'rejected'
    created_at: string
    resolved_at?: string
}
