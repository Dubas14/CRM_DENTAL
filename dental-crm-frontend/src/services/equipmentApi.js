import apiClient from './apiClient';

const equipmentApi = {
  list(params) {
    return apiClient.get('/equipments', { params });
  },
};

export default equipmentApi;
