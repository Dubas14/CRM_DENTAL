import apiClient from './apiClient';

function pick(obj, keys) {
  const out = {};
  keys.forEach((k) => {
    if (obj[k] !== undefined) out[k] = obj[k];
  });
  return out;
}

const calendarApi = {
  // -----------------------
  // Availability (slots)
  // -----------------------
  getDoctorSlots(doctorId, params) {
    return apiClient.get(`/doctors/${doctorId}/slots`, { params });
  },

  getRecommendedSlots(doctorId, params) {
    return apiClient.get(`/doctors/${doctorId}/recommended-slots`, { params });
  },

  // -----------------------
  // Appointments
  // -----------------------
  getDoctorAppointments(doctorId, params) {
    return apiClient.get(`/doctors/${doctorId}/appointments`, { params });
  },

  getAppointments(params = {}) {
    return apiClient.get('/appointments', { params });
  },

  getCalendarBlocks(params = {}) {
    return apiClient.get('/calendar-blocks', { params });
  },

  /**
   * Create appointment
   * Supports:
   *  - date + time (existing flow)
   *  - start_at + end_at (calendar flow)
   */
  createAppointment(payload) {
    // якщо прийшло start_at/end_at — шлемо їх напряму
    if (payload.start_at && payload.end_at) {
      return apiClient.post('/appointments', {
        ...pick(payload, [
          'doctor_id',
          'patient_id',
          'procedure_id',
          'room_id',
          'equipment_id',
          'assistant_id',
          'is_follow_up',
          'waitlist_entry_id',
          'allow_soft_conflicts',
          'comment',
          'source',
        ]),
        start_at: payload.start_at,
        end_at: payload.end_at,
      });
    }

    // fallback: старий варіант (date + time)
    return apiClient.post('/appointments', {
      ...pick(payload, [
        'doctor_id',
        'date',
        'time',
        'patient_id',
        'procedure_id',
        'room_id',
        'equipment_id',
        'assistant_id',
        'is_follow_up',
        'waitlist_entry_id',
        'allow_soft_conflicts',
        'comment',
        'source',
      ]),
    });
  },

  /**
   * Update appointment
   * Supports:
   *  - date + time
   *  - start_at + end_at (for drag&drop / resize)
   */
  updateAppointment(appointmentId, payload) {
    // календарний режим: перенос/resize
    if (payload.start_at && payload.end_at) {
      return apiClient.put(`/appointments/${appointmentId}`, {
        ...pick(payload, [
          'doctor_id',
          'patient_id',
          'procedure_id',
          'room_id',
          'equipment_id',
          'assistant_id',
          'is_follow_up',
          'waitlist_entry_id',
          'allow_soft_conflicts',
          'status',
          'comment',
          'source',
        ]),
        start_at: payload.start_at,
        end_at: payload.end_at,
      });
    }

    // старий режим: date/time
    return apiClient.put(`/appointments/${appointmentId}`, {
      ...pick(payload, [
        'doctor_id',
        'date',
        'time',
        'patient_id',
        'procedure_id',
        'room_id',
        'equipment_id',
        'assistant_id',
        'is_follow_up',
        'waitlist_entry_id',
        'allow_soft_conflicts',
        'status',
        'comment',
        'source',
      ]),
    });
  },

  cancelAppointment(appointmentId, payload) {
    return apiClient.post(`/appointments/${appointmentId}/cancel`, payload);
  },

  // -----------------------
  // Waitlist
  // -----------------------
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
