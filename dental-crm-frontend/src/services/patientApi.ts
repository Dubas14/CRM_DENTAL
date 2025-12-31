import apiClient from './apiClient'

export default {
  // Отримати дані пацієнта
  getPatient(id) {
    return apiClient.get(`/patients/${id}`)
  },

  // Отримати історію хвороби
  getMedicalRecords(patientId) {
    return apiClient.get(`/patients/${patientId}/records`)
  },

  // Додати запис в картку
  createMedicalRecord(patientId, data) {
    return apiClient.post(`/patients/${patientId}/records`, data)
  }
}
