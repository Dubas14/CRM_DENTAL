import apiClient from './apiClient';

const procedureApi = {
  list(params) {
    return apiClient.get('/procedures', { params });
  },
  create(payload) {
    return apiClient.post('/procedures', payload);
  },
};

export default procedureApi;
