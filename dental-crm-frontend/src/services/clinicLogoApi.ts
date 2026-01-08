import apiClient from './apiClient'

const clinicLogoApi = {
  upload(clinicId: number | string, file: File) {
    const formData = new FormData()
    formData.append('logo', file)
    return apiClient.post(`/clinics/${clinicId}/logo`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
  },

  delete(clinicId: number | string) {
    return apiClient.delete(`/clinics/${clinicId}/logo`)
  }
}

export default clinicLogoApi
