import api from './api'

export type EntityType = 'artists' | 'users' | 'labels' | 'stations'

export async function getEntities(type: EntityType, limit: number = 50, offset: number = 0, search: string = '') {
    let url = `/admin/entities/${type}?limit=${limit}&offset=${offset}`
    if (search) {
        url += `&search=${encodeURIComponent(search)}`
    }
    const response = await api.get(url)
    return response.data.data
}

export async function getEntity(type: EntityType, id: number) {
    const response = await api.get(`/admin/entities/${type}/${id}`)
    return response.data.data
}

export async function updateEntity(type: EntityType, id: number, data: any) {
    const response = await api.put(`/admin/entities/${type}/${id}`, data)
    return response.data
}
