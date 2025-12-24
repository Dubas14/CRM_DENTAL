import apiClient from './apiClient';

const roleApi = {
  listRoles() {
    return apiClient.get('/roles');
  },
  listUsers(params = {}) {
    return apiClient.get('/roles/users', { params });
  },
  updateUserRoles(userId, roles) {
    return apiClient.put(`/roles/users/${userId}`, { roles });
  },
};

export default roleApi;
