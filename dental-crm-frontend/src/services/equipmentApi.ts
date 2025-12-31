import apiClient from './apiClient'
import { buildKey, withCacheAndDedupe } from './requestCache'

const equipmentApi = {
  list(params) {
    const key = buildKey('/equipments', params || {})
    return withCacheAndDedupe(key, () => apiClient.get('/equipments', { params }))
  },
  create(payload) {
    return apiClient.post('/equipments', payload)
  },
  update(id, payload) {
    return apiClient.put(`/equipments/${id}`, payload)
  },
  delete(id) {
    return apiClient.delete(`/equipments/${id}`)
  }
}

export default equipmentApi
