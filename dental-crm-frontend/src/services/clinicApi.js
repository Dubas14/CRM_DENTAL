import apiClient from './apiClient';

const clinicApi = {
  list() {
    return apiClient.get('/clinics');
  },
  listMine() {
    return apiClient.get('/me/clinics');
  },
};

export default clinicApi;
