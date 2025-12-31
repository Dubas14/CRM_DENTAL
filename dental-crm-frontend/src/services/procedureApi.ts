import apiClient from './apiClient'

const procedureApi = {
  list(params) {
    return apiClient.get('/procedures', { params })
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
