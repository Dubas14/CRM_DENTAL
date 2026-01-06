import apiClient from './apiClient'

const roleApi = {
  listRoles() {
    return apiClient.get('/roles')
  },
  listUsers(params = {}) {
    return apiClient.get('/roles/users', { params })
  },
  updateUserRoles(userId, roles, clinicId = null) {
    const payload = { roles }
    if (clinicId) {
      payload.clinic_id = clinicId
    }
    return apiClient.put(`/roles/users/${userId}`, payload)
  }
}

export default roleApi
