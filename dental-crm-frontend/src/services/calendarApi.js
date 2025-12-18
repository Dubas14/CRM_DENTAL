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
    return apiClient.get('/appointments', { params });
  },

  getSlots(params = {}) {
    return apiClient.get('/slots', { params });
  },

  createAppointment(payload) {
    const {
      doctor_id,
      date,
      time,
      patient_id,
      procedure_id,
      room_id,
      equipment_id,
      assistant_id,
      is_follow_up,
      waitlist_entry_id,
      allow_soft_conflicts,
      comment,
      source,
    } = payload;

    return apiClient.post('/appointments', {
      doctor_id,
      date,
      time,
      patient_id,
      procedure_id,
      room_id,
      equipment_id,
      assistant_id,
      is_follow_up,
      waitlist_entry_id,
      allow_soft_conflicts,
      comment,
      source,
    });
  },

  updateAppointment(appointmentId, payload) {
    const {
      doctor_id,
      date,
      time,
      patient_id,
      procedure_id,
      room_id,
      equipment_id,
      assistant_id,
      is_follow_up,
      waitlist_entry_id,
      allow_soft_conflicts,
      status,
      comment,
      source,
    } = payload;

    return apiClient.put(`/appointments/${appointmentId}`, {
      doctor_id,
      date,
      time,
      patient_id,
      procedure_id,
      room_id,
      equipment_id,
      assistant_id,
      is_follow_up,
      waitlist_entry_id,
      allow_soft_conflicts,
      status,
      comment,
      source,
    });
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
