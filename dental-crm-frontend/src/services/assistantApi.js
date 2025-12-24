import apiClient from './apiClient';

const assistantApi = {
  list(params) {
    return apiClient.get('/assistants', { params });
  },
  create(payload) {
    return apiClient.post('/assistants', payload);
  },
};

export default assistantApi;
