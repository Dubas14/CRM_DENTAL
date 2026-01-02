import apiClient from './apiClient'
import { buildKey, clearRequestCache, withCacheAndDedupe } from './requestCache'

function pick(obj, keys) {
  const out = {}
  keys.forEach((k) => {
    // Виключаємо undefined та null значення (окрім false та 0)
    if (obj[k] !== undefined && obj[k] !== null) {
      out[k] = obj[k]
    }
  })
  return out
}

const calendarApi = {
  // Availability (slots)
  getDoctorSlots(doctorId, params) {
    const key = buildKey(`/doctors/${doctorId}/slots`, params)
    return withCacheAndDedupe(key, () => apiClient.get(`/doctors/${doctorId}/slots`, { params }))
  },
  getRecommendedSlots(doctorId, params) {
    const key = buildKey(`/doctors/${doctorId}/recommended-slots`, params)
    return withCacheAndDedupe(key, () =>
      apiClient.get(`/doctors/${doctorId}/recommended-slots`, { params })
    )
  },
  getBookingSuggestions(params) {
    const key = buildKey('/booking-suggestions', params)
    return withCacheAndDedupe(key, () => apiClient.get('/booking-suggestions', { params }))
  },

  // Appointments
  getDoctorAppointments(doctorId, params) {
    const key = buildKey(`/doctors/${doctorId}/appointments`, params)
    return withCacheAndDedupe(key, () =>
      apiClient.get(`/doctors/${doctorId}/appointments`, { params })
    )
  },
  getAppointments(params = {}) {
    const key = buildKey('/appointments', params)
    return withCacheAndDedupe(key, () => apiClient.get('/appointments', { params }))
  },

  // Calendar blocks
  getCalendarBlocks(params = {}) {
    const normalized = { ...params }
    if (normalized.from_date && !normalized.from) {
      normalized.from = normalized.from_date
    }
    if (normalized.to_date && !normalized.to) {
      normalized.to = normalized.to_date
    }
    delete normalized.from_date
    delete normalized.to_date
    const key = buildKey('/calendar-blocks', normalized)
    return withCacheAndDedupe(key, () => apiClient.get('/calendar-blocks', { params: normalized }))
  },
  createCalendarBlock(payload) {
    return apiClient.post('/calendar-blocks', payload)
  },
  updateCalendarBlock(blockId, payload) {
    return apiClient.put(`/calendar-blocks/${blockId}`, payload)
  },
  deleteCalendarBlock(blockId) {
    return apiClient.delete(`/calendar-blocks/${blockId}`)
  },

  // Create appointment
  createAppointment(payload) {
    // Ensure UI does not show stale slots/appointments right after a write
    clearRequestCache()
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
          'clinic_id' // якщо буде потрібно
        ]),
        start_at: payload.start_at,
        end_at: payload.end_at
      })
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
        'clinic_id' // якщо буде потрібно
      ])
    })
  },

  createAppointmentSeries(payload) {
    clearRequestCache()
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
        'clinic_id' // якщо буде потрібно
      ])
    })
  },

  updateAppointment(appointmentId, payload) {
    clearRequestCache()
    if (payload.start_at && payload.end_at) {
      const requestPayload = {
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
          'clinic_id'
        ]),
        start_at: payload.start_at,
        end_at: payload.end_at
      }
      
      console.log('Sending PUT request to:', `/appointments/${appointmentId}`, 'Payload:', JSON.stringify(requestPayload, null, 2))
      return apiClient.put(`/appointments/${appointmentId}`, requestPayload)
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
        'clinic_id' // якщо буде потрібно
      ])
    })
  },

  cancelAppointment(appointmentId, payload) {
    clearRequestCache()
    return apiClient.post(`/appointments/${appointmentId}/cancel`, payload)
  },

  finishAppointment(appointmentId, payload = {}) {
    clearRequestCache()
    return apiClient.post(`/appointments/${appointmentId}/finish`, payload)
  },

  // Waitlist
  fetchWaitlist(params) {
    return apiClient.get('/waitlist', { params })
  },
  createWaitlistEntry(payload) {
    return apiClient.post('/waitlist', payload)
  },
  getWaitlistCandidates(params) {
    const key = buildKey('/waitlist/candidates', params)
    return withCacheAndDedupe(key, () => apiClient.get('/waitlist/candidates', { params }))
  },
  markWaitlistBooked(entryId) {
    return apiClient.post(`/waitlist/${entryId}/book`)
  },
  cancelWaitlistEntry(entryId) {
    return apiClient.post(`/waitlist/${entryId}/cancel`)
  }
}

export default calendarApi
