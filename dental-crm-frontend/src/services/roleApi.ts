import apiClient from './apiClient'

const roleApi = {
  listRoles() {
    return apiClient.get('/roles')
  },
  // Get all roles with permissions (for RoleManager constructor)
  listAllRoles() {
    return apiClient.get('/roles/list')
  },
  // Create a new role
  createRole(name: string, permissions: string[] = []) {
    return apiClient.post('/roles', { name, permissions })
  },
  // Update role
  updateRole(roleId: number, name?: string, permissions?: string[]) {
    const payload: any = {}
    if (name !== undefined) payload.name = name
    if (permissions !== undefined) payload.permissions = permissions
    return apiClient.put(`/roles/${roleId}`, payload)
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
  },
  // Assign single role to user (for dropdown-based assignment)
  assignRole(userId: number, roleName: string, clinicId: number | null = null) {
    const payload: any = { role_name: roleName }
    if (clinicId) {
      payload.clinic_id = clinicId
    }
    return apiClient.post(`/users/${userId}/assign-role`, payload)
  }
}

export default roleApi
