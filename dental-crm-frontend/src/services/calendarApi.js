import apiClient from './apiClient';

const calendarApi = {
  getDoctorSlots(doctorId, params) {
    return apiClient.get(`/doctors/${doctorId}/slots`, { params });
  },
  getRecommendedSlots(doctorId, params) {
    return apiClient.get(`/doctors/${doctorId}/recommended-slots`, { params });
  },
  getDoctorAppointments(doctorId, params) {
    return apiClient.get(`/doctors/${doctorId}/appointments`, { params });
  },
  getAppointments(params = {}) {
    // Підтримка мульти-лікар та діапазону дат
    return apiClient.get('/appointments', { params });
  },
  getSlots(params = {}) {
    // Підтримка кількох лікарів або тижневого діапазону
    return apiClient.get('/slots', { params });
  },
  createAppointment(payload) {
    return apiClient.post('/appointments', payload);
  },
  updateAppointment(appointmentId, payload) {
    return apiClient.put(`/appointments/${appointmentId}`, payload);
  },
  cancelAppointment(appointmentId, payload) {
    return apiClient.post(`/appointments/${appointmentId}/cancel`, payload);
  },
  fetchWaitlist(params) {
    return apiClient.get('/waitlist', { params });
  },
  createWaitlistEntry(payload) {
    return apiClient.post('/waitlist', payload);
  },
  getWaitlistCandidates(params) {
    return apiClient.get('/waitlist/candidates', { params });
  },
  markWaitlistBooked(entryId) {
    return apiClient.post(`/waitlist/${entryId}/book`);
  },
  cancelWaitlistEntry(entryId) {
    return apiClient.post(`/waitlist/${entryId}/cancel`);
  },
};

export default calendarApi;
