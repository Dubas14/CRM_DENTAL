import apiClient from './apiClient'
import { buildKey, withCacheAndDedupe } from './requestCache'

const roomApi = {
  list(params: Record<string, any> = {}) {
    const key = buildKey('/rooms', params)
    return withCacheAndDedupe(key, () => apiClient.get('/rooms', { params }))
  }
}

export default roomApi

