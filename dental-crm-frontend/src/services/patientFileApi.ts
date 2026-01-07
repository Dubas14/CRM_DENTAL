import apiClient from './apiClient'

const patientFileApi = {
  list(patientId: number | string) {
    return apiClient.get(`/patients/${patientId}/files`)
  },
  upload(
    patientId: number | string,
    file: File,
    fileType: 'xray' | 'photo' | 'contract' | 'anamnesis'
  ) {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('file_type', fileType)
    return apiClient.post(`/patients/${patientId}/files`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
  },
  delete(patientId: number | string, fileId: number | string) {
    return apiClient.delete(`/patients/${patientId}/files/${fileId}`)
  }
}

export default patientFileApi
