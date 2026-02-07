import api from './api'

export async function getPendingPayouts() {
    const response = await api.get('/admin/royalties/pending-payouts')
    return response.data.data
}

export async function processPayoutRequest(payoutId: number) {
    const response = await api.post(`/admin/royalties/process-payout/${payoutId}`)
    return response.data.data
}

export async function getBalance(userId: number) {
    const response = await api.get(`/admin/royalties/balance/${userId}`)
    return response.data.data
}

export async function getTransactions(userId: number, limit: number = 50, offset: number = 0) {
    const response = await api.get(`/admin/royalties/transactions?user_id=${userId}&limit=${limit}&offset=${offset}`)
    return response.data.data
}

export async function createPayout(userId: number, amount: number) {
    const response = await api.post('/admin/royalties/create-payout', {
        user_id: userId,
        amount
    })
    return response.data.data
}

export async function calculateEQS(startDate?: string, endDate?: string) {
    let url = '/admin/royalties/eqs-calculate'
    if (startDate && endDate) {
        url += `?start_date=${startDate}&end_date=${endDate}`
    }
    const response = await api.get(url)
    return response.data.data
}
