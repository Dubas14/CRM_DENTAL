import apiClient from './apiClient'
import { buildKey, withCacheAndDedupe } from './requestCache'

const assistantApi = {
  list(params) {
    const key = buildKey('/assistants', params || {})
    return withCacheAndDedupe(key, () => apiClient.get('/assistants', { params }))
  },
  create(payload) {
    return apiClient.post('/assistants', payload)
  },
  update(id, payload) {
    return apiClient.put(`/assistants/${id}`, payload)
  },
  delete(id) {
    return apiClient.delete(`/assistants/${id}`)
  }
}

export default assistantApi
