import api from './api'

export async function getQAStatus(ingestionId?: number) {
    const url = ingestionId ? `/admin/charts/qa-status?ingestion_id=${ingestionId}` : '/admin/charts/qa-status'
    const response = await api.get(url)
    return response.data.data
}

export async function getCorrections(limit: number = 50) {
    const response = await api.get(`/admin/charts/corrections?limit=${limit}`)
    return response.data.data
}

export async function applyCorrection(data: {
    artist_id: number
    original_score: number
    corrected_score: number
    reason: string
    ingestion_id?: number
}) {
    const response = await api.post('/admin/charts/corrections', data)
    return response.data.data
}

export async function getScoreDisputes(status?: string) {
    const url = status ? `/admin/charts/disputes?status=${status}` : '/admin/charts/disputes'
    const response = await api.get(url)
    return response.data.data
}

export async function resolveScoreDispute(disputeId: number, resolution: string, status: string = 'resolved') {
    const response = await api.post(`/admin/charts/disputes/${disputeId}/resolve`, {
        resolution,
        status
    })
    return response.data.data
}
