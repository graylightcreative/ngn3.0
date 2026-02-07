import api from './api'

export async function getAnalyticsSummary() {
    const response = await api.get('/admin/analytics/summary')
    return response.data.data
}

export async function getAnalyticsTrends(days: number = 30) {
    const response = await api.get(`/admin/analytics/trends?days=${days}`)
    return response.data.data
}
