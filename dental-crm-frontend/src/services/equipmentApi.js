import apiClient from './apiClient';

const equipmentApi = {
  list(params) {
    return apiClient.get('/equipments', { params });
  },
  create(payload) {
    return apiClient.post('/equipments', payload);
  },
};

export default equipmentApi;
