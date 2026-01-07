import apiClient from './apiClient'
import { buildKey, withCacheAndDedupe } from './requestCache'

const doctorApi = {
  list(params: Record<string, any> = {}) {
    const key = buildKey('/doctors', params)
    return withCacheAndDedupe(key, () => apiClient.get('/doctors', { params }))
  },
  get(id: number | string) {
    return apiClient.get(`/doctors/${id}`)
  }
}

export default doctorApi
