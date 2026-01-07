import apiClient from './apiClient'

export default {
  // Список пацієнтів
  list(params?: { search?: string; clinic_id?: number; per_page?: number }) {
    return apiClient.get('/patients', { params })
  },

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
