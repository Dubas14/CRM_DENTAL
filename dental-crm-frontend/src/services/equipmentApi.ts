import apiClient from './apiClient'

const equipmentApi = {
  list(params) {
    return apiClient.get('/equipments', { params })
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
