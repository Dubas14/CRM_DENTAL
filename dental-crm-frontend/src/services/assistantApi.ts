import apiClient from './apiClient'

const assistantApi = {
  list(params) {
    return apiClient.get('/assistants', { params })
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
