import apiClient from './apiClient'
import { buildKey, withCacheAndDedupe } from './requestCache'

const procedureApi = {
  list(params) {
    const key = buildKey('/procedures', params || {})
    return withCacheAndDedupe(key, () => apiClient.get('/procedures', { params }))
  },
  create(payload) {
    return apiClient.post('/procedures', payload)
  },
  update(id, payload) {
    return apiClient.put(`/procedures/${id}`, payload)
  },
  delete(id) {
    return apiClient.delete(`/procedures/${id}`)
  }
}

export default procedureApi
