import apiClient from './apiClient'
const specializationApi = {
  list(params: Record<string, any> = {}) {
    // No cache — fetch fresh to show newly seeded/edited items immediately
    return apiClient.get('/specializations', { params })
  },
  create(payload: { name: string }) {
    return apiClient.post('/specializations', payload)
  },
  update(id: number | string, payload: { name?: string; is_active?: boolean }) {
    return apiClient.put(`/specializations/${id}`, payload)
  },
  remove(id: number | string) {
    return apiClient.delete(`/specializations/${id}`)
  }
}

export default specializationApi


