export interface Payout {
    id: number
    user_id: number
    display_name?: string
    email?: string
    amount: number
    status: 'pending' | 'processing' | 'completed' | 'failed'
    stripe_transfer_id?: string
    requested_at: string
    processed_at?: string
}

export interface RoyaltyTransaction {
    id: number
    user_id: number
    ingestion_id?: number
    amount: number
    eqs_pool_share?: number
    calculation_type: 'eqs' | 'flat' | 'adjusted'
    period_start?: string
    period_end?: string
    created_at: string
}

export interface Balance {
    user_id: number
    current_balance: number
    pending_payout: number
    available_balance: number
}

export interface EQSResult {
    period_start: string
    period_end: string
    total_pool: number
    status: string
}
