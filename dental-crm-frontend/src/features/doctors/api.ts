import apiClient from '../../services/apiClient'
import { buildKey, withCacheAndDedupe } from '../../services/requestCache'
import type { Doctor, DoctorProcedure } from './types'

export const doctorsApi = {
  list(params: Record<string, any> = {}) {
    const key = buildKey('/doctors', params)
    return withCacheAndDedupe(key, () => apiClient.get<Doctor[]>('/doctors', { params }))
  },
  get(id: number | string) {
    return apiClient.get<Doctor>(`/doctors/${id}`)
  },
  procedures(id: number | string) {
    return apiClient.get<DoctorProcedure[]>(`/doctors/${id}/procedures`)
  },
  update(id: number | string, payload: Partial<Doctor>) {
    return apiClient.put<Doctor>(`/doctors/${id}`, payload)
  },
  saveProcedures(id: number | string, payload: { procedures: any[] }) {
    return apiClient.put(`/doctors/${id}/procedures`, payload)
  },
  uploadAvatar(id: number | string, file: File) {
    const form = new FormData()
    form.append('avatar', file)
    return apiClient.post(`/doctors/${id}/avatar`, form, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
  },
  // Optional hooks for appointments/patients; fallback handled in UI when endpoint missing.
  history(id: number | string) {
    return apiClient.get(`/appointments`, { params: { doctor_id: id } })
  }
}
