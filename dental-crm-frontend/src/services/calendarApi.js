import apiClient from './apiClient';

function pick(obj, keys) {
  const out = {};
  keys.forEach((k) => {
    if (obj[k] !== undefined) out[k] = obj[k];
  });
  return out;
}

const calendarApi = {
  // Availability (slots)
  getDoctorSlots(doctorId, params) {
    return apiClient.get(`/doctors/${doctorId}/slots`, { params });
  },
  getRecommendedSlots(doctorId, params) {
    return apiClient.get(`/doctors/${doctorId}/recommended-slots`, { params });
  },

  // Appointments
  getDoctorAppointments(doctorId, params) {
    return apiClient.get(`/doctors/${doctorId}/appointments`, { params });
  },
  getAppointments(params = {}) {
    return apiClient.get('/appointments', { params });
  },

  // Calendar blocks
  getCalendarBlocks(params = {}) {
    const normalized = { ...params };
    if (normalized.from_date && !normalized.from) {
      normalized.from = normalized.from_date;
    }
    if (normalized.to_date && !normalized.to) {
      normalized.to = normalized.to_date;
    }
    delete normalized.from_date;
    delete normalized.to_date;
    return apiClient.get('/calendar-blocks', { params: normalized });
  },
  createCalendarBlock(payload) {
    return apiClient.post('/calendar-blocks', payload);
  },
  updateCalendarBlock(blockId, payload) {
    return apiClient.put(`/calendar-blocks/${blockId}`, payload);
  },
  deleteCalendarBlock(blockId) {
    return apiClient.delete(`/calendar-blocks/${blockId}`);
  },

  // Create appointment
  createAppointment(payload) {
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
          'clinic_id', // якщо буде потрібно
        ]),
        start_at: payload.start_at,
        end_at: payload.end_at,
      });
    }

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
        'clinic_id', // якщо буде потрібно
      ]),
    });
  },

  createAppointmentSeries(payload) {
    return apiClient.post('/appointments/series', {
      ...pick(payload, [
        'doctor_id',
        'patient_id',
        'procedure_id',
        'room_id',
        'equipment_id',
        'assistant_id',
        'is_follow_up',
        'allow_soft_conflicts',
        'comment',
        'source',
        'steps',
        'clinic_id', // якщо буде потрібно
      ]),
    });
  },

  updateAppointment(appointmentId, payload) {
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
          'clinic_id', // якщо буде потрібно
        ]),
        start_at: payload.start_at,
        end_at: payload.end_at,
      });
    }

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
        'clinic_id', // якщо буде потрібно
      ]),
    });
  },

  cancelAppointment(appointmentId, payload) {
    return apiClient.post(`/appointments/${appointmentId}/cancel`, payload);
  },

  // Waitlist
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
