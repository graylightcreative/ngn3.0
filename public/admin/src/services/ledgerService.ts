import api from './api'

export async function getLedgerEntries(limit: number = 50, offset: number = 0, ownerId?: number, source?: string) {
    let url = `/admin/content-ledger?limit=${limit}&offset=${offset}`
    if (ownerId) url += `&owner_id=${ownerId}`
    if (source) url += `&source=${source}`
    const response = await api.get(url)
    return response.data.data
}

export async function getLedgerEntry(id: number) {
    const response = await api.get(`/admin/content-ledger/${id}`)
    return response.data.data
}

export async function anchorPendingEntries() {
    const response = await api.post('/admin/content-ledger/anchor')
    return response.data
}
