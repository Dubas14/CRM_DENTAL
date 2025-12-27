<template>
  <div class="min-h-screen bg-bg">
    <div class="p-6 pb-2">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-text mb-2">Календар записів</h1>
          <p class="text-text/70 text-sm">
            Управління розкладом лікарів, бронювання та перегляд записів
          </p>
        </div>

        <div v-if="clinics.length > 1 || !user?.clinic_id" class="w-64">
          <label class="text-xs text-text/60 uppercase font-bold block mb-1">Оберіть клініку</label>
          <select
              v-model="selectedClinicId"
              @change="handleClinicChange"
              class="w-full bg-card border border-border/80 rounded px-3 py-2 text-text text-sm"
          >
            <option :value="null" disabled>-- Оберіть клініку --</option>
            <option v-for="clinic in clinics" :key="clinic.id" :value="clinic.id">
              {{ clinic.name }}
            </option>
          </select>
        </div>
      </div>
    </div>

    <div class="px-6 flex flex-wrap items-center justify-between gap-4 mb-4">
      <CalendarHeader
          :current-date="currentDate"
          @prev="prev"
          @next="next"
          @today="today"
          @select-date="selectMonth"
      />

      <div class="flex items-center gap-2">
        <button
            @click="fetchEvents"
            class="text-sm px-3 py-1 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded hover:bg-emerald-500/20"
        >
          🔄 Оновити
        </button>
        <select
            v-model="view"
            @change="changeView"
            class="bg-card border border-border/80 text-text/90 rounded-md px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
        >
          <option value="day">День</option>
          <option value="week">Тиждень</option>
          <option value="month">Місяць</option>
        </select>
      </div>
    </div>

    <div class="px-6 pb-6 h-[calc(100vh-160px)] overflow-hidden bg-card/30 rounded-xl mx-6 border border-border/40">
      <div v-if="!currentClinicId" class="flex h-full items-center justify-center text-text/60">
        ⬅ Будь ласка, оберіть клініку зі списку зверху
      </div>
      <ToastCalendar
          v-else
          ref="calendarRef"
          @select-date-time="handleSelectDateTime"
          @click-event="handleClickEvent"
          @before-update-event="handleBeforeUpdateEvent"
          @event-drag-start="handleEventDragStart"
          @event-drag-end="handleEventDragEnd"
      />
    </div>

    <EventModal
        :open="isEventModalOpen"
        :event="activeEvent"
        @save="handleSaveEvent"
        @close="handleCloseModal"
    />

    <AppointmentModal
        :is-open="isAppointmentModalOpen"
        :appointment="selectedAppointment"
        :clinic-id="currentClinicId"
        @close="handleCloseAppointmentModal"
        @saved="handleAppointmentSaved"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, nextTick, ref, watch } from 'vue'
import CalendarHeader from '../components/CalendarHeader.vue'
import ToastCalendar from '../components/ToastCalendar.vue'
import EventModal from '../components/EventModal.vue'
import AppointmentModal from '../components/AppointmentModal.vue'
import calendarApi from '../services/calendarApi'
import clinicApi from '../services/clinicApi'
import { useToast } from '../composables/useToast'
import { useAuth } from '../composables/useAuth'

// --- STATE ---
const calendarRef = ref(null)
const view = ref('week')
const currentDate = ref(new Date())
const isEventModalOpen = ref(false)
const activeEvent = ref({})
const isAppointmentModalOpen = ref(false)
const selectedAppointment = ref(null)

const events = ref([])
const availabilityEvents = ref([])
const clinics = ref([])
const selectedClinicId = ref(null)

const { error: toastError, success: toastSuccess } = useToast()
const { user, initAuth } = useAuth()

const SNAP_MINUTES = 15
const DRAG_VALID_COLOR = '#16a34a'
const DRAG_INVALID_COLOR = '#ef4444'
const AVAILABILITY_BG_COLOR = 'rgba(16, 185, 129, 0.18)'
const AVAILABILITY_BORDER_COLOR = 'rgba(16, 185, 129, 0.45)'
let availabilityRequestId = 0

// --- COMPUTED ---
const currentClinicId = computed(() => {
  if (selectedClinicId.value) return selectedClinicId.value
  return user.value?.clinic_id || user.value?.doctor?.clinic_id || null
})

const defaultDoctorId = computed(() => user.value?.doctor_id || user.value?.doctor?.id || null)

// --- HELPERS ---
const toDate = (value) => {
  if (!value) return null
  if (value.toDate) return value.toDate()
  const date = value instanceof Date ? value : new Date(value)
  return Number.isNaN(date.getTime()) ? null : date
}

const formatDateOnly = (date) => {
  const normalized = toDate(date)
  if (!normalized) return ''
  const year = normalized.getFullYear()
  const month = `${normalized.getMonth() + 1}`.padStart(2, '0')
  const day = `${normalized.getDate()}`.padStart(2, '0')
  return `${year}-${month}-${day}`
}

const formatDateTime = (date) => {
  const normalized = toDate(date)
  if (!normalized) return ''
  const year = normalized.getFullYear()
  const month = `${normalized.getMonth() + 1}`.padStart(2, '0')
  const day = `${normalized.getDate()}`.padStart(2, '0')
  const hour = `${normalized.getHours()}`.padStart(2, '0')
  const minute = `${normalized.getMinutes()}`.padStart(2, '0')
  return `${year}-${month}-${day} ${hour}:${minute}:00`
}

const formatTimeHM = (date) => {
  const normalized = toDate(date)
  if (!normalized) return ''
  const hour = `${normalized.getHours()}`.padStart(2, '0')
  const minute = `${normalized.getMinutes()}`.padStart(2, '0')
  return `${hour}:${minute}`
}

const snapToMinutes = (date, stepMinutes = SNAP_MINUTES) => {
  const normalized = toDate(date)
  if (!normalized) return null
  const stepMs = stepMinutes * 60000
  return new Date(Math.round(normalized.getTime() / stepMs) * stepMs)
}

const addMinutesToTime = (time, minutes) => {
  if (!time || typeof time !== 'string') return null
  const [hourStr, minuteStr] = time.split(':')
  const hour = Number.parseInt(hourStr, 10)
  const minute = Number.parseInt(minuteStr, 10)
  if (Number.isNaN(hour) || Number.isNaN(minute)) return null
  const totalMinutes = hour * 60 + minute + minutes
  const nextHour = Math.floor((totalMinutes % (24 * 60)) / 60)
  const nextMinute = totalMinutes % 60
  return `${String(nextHour).padStart(2, '0')}:${String(nextMinute).padStart(2, '0')}`
}

const getAppointmentDurationMinutes = (appt) => {
  const start = toDate(appt?.start_at)
  const end = toDate(appt?.end_at)
  if (!start || !end) return 30
  const diff = Math.round((end.getTime() - start.getTime()) / 60000)
  return diff > 0 ? diff : 30
}

const mergeSlotsToIntervals = (slots, durationMinutes = 30) => {
  if (!slots?.length) return []
  const normalized = slots
    .map((slot) => {
      const start = slot.start
      const end = slot.end || addMinutesToTime(slot.start, durationMinutes)
      if (!start || !end) return null
      return { start, end }
    })
    .filter(Boolean)
  if (!normalized.length) return []

  const sorted = [...normalized].sort((a, b) => a.start.localeCompare(b.start))
  const merged = []

  for (const slot of sorted) {
    const last = merged[merged.length - 1]
    if (!last) {
      merged.push({ ...slot })
      continue
    }
    if (last.end === slot.start) {
      last.end = slot.end
    } else {
      merged.push({ ...slot })
    }
  }

  return merged
}

const mapBlockToEvent = (block) => {
  const s = toDate(block.start_at)
  const e = toDate(block.end_at)
  if (!s || !e) return null
  return {
    id: String(block.id),
    calendarId: 'main',
    title: block.note || 'Блок',
    category: 'time',
    start: s,
    end: e,
    isReadOnly: false,
    backgroundColor: '#6b7280',
    dragBackgroundColor: '#9ca3af',
    color: '#fff',
    raw: block
  }
}

const mapAppointmentToEvent = (appt) => {
  const s = toDate(appt.start_at)
  const e = toDate(appt.end_at)
  if (!s || !e) return null
  const isDone = appt.status === 'done'
  return {
    id: String(appt.id),
    calendarId: 'main',
    title: appt.patient?.full_name || 'Запис',
    category: 'time',
    start: s,
    end: e,
    isReadOnly: isDone,
    backgroundColor: isDone ? '#dbeafe' : '#10b981',
    borderColor: isDone ? '#93c5fd' : '#059669',
    dragBackgroundColor: isDone ? '#93c5fd' : DRAG_VALID_COLOR,
    color: isDone ? '#1e3a8a' : '#fff',
    raw: appt
  }
}

const getAppointmentDoctorId = (appt) => appt?.doctor_id || appt?.doctor?.id || null

const isDropAllowed = async (appt, start, end) => {
  const doctorId = getAppointmentDoctorId(appt)
  if (!doctorId) {
    toastError('Неможливо визначити лікаря для запису')
    return false
  }

  try {
    const date = formatDateOnly(start)
    const startTime = formatTimeHM(start)
    const { data } = await calendarApi.getDoctorSlots(doctorId, {
      date,
      procedure_id: appt.procedure_id || appt.procedure?.id || undefined,
      room_id: appt.room_id || appt.room?.id || undefined,
      equipment_id: appt.equipment_id || appt.equipment?.id || undefined,
      assistant_id: appt.assistant_id || appt.assistant?.id || undefined,
    })
    const slots = Array.isArray(data?.slots) ? data.slots : []
    const allowed = new Set(slots.map((slot) => slot.start))
    return allowed.has(startTime)
  } catch (error) {
    console.error(error)
    toastError('Не вдалося перевірити доступний час лікаря')
    return false
  }
}

const replaceAppointmentEvent = (updatedAppointment, fallbackStart, fallbackEnd) => {
  const mapped = mapAppointmentToEvent(updatedAppointment) ?? {
    id: String(updatedAppointment.id),
    calendarId: 'main',
    start: fallbackStart,
    end: fallbackEnd,
    raw: updatedAppointment
  }

  events.value = events.value.map((event) => (
    event.id === String(updatedAppointment.id)
      ? { ...event, ...mapped }
      : event
  ))

  calendarRef.value?.updateEvent?.(String(updatedAppointment.id), mapped.calendarId, {
    title: mapped.title,
    start: mapped.start,
    end: mapped.end,
    isReadOnly: mapped.isReadOnly,
    backgroundColor: mapped.backgroundColor,
    borderColor: mapped.borderColor,
    dragBackgroundColor: mapped.dragBackgroundColor,
    color: mapped.color,
    raw: mapped.raw
  })
}

const flashInvalidDrop = (eventId, calendarId) => {
  const existing = events.value.find((entry) => entry.id === String(eventId))
  if (!existing) return

  calendarRef.value?.updateEvent?.(String(eventId), calendarId, {
    backgroundColor: DRAG_INVALID_COLOR,
    borderColor: DRAG_INVALID_COLOR,
    dragBackgroundColor: DRAG_INVALID_COLOR
  })

  setTimeout(() => {
    calendarRef.value?.updateEvent?.(String(eventId), calendarId, {
      backgroundColor: existing.backgroundColor,
      borderColor: existing.borderColor,
      dragBackgroundColor: existing.dragBackgroundColor
    })
  }, 180)
}

const removeAvailabilityEvents = () => {
  availabilityEvents.value.forEach((event) => {
    calendarRef.value?.deleteEvent?.(event.id, event.calendarId)
  })
  availabilityEvents.value = []
}

const applyAvailabilityEvents = (nextEvents) => {
  removeAvailabilityEvents()
  if (!nextEvents?.length) return
  availabilityEvents.value = nextEvents
  calendarRef.value?.createEvents?.(availabilityEvents.value)
}

const loadAvailabilitySlots = async (appointment) => {
  if (!appointment || view.value === 'month') {
    removeAvailabilityEvents()
    return
  }

  const doctorId = getAppointmentDoctorId(appointment)
  if (!doctorId) {
    removeAvailabilityEvents()
    return
  }

  const rangeStart = calendarRef.value?.getDateRangeStart?.()
  const rangeEnd = calendarRef.value?.getDateRangeEnd?.()
  if (!rangeStart || !rangeEnd) {
    removeAvailabilityEvents()
    return
  }

  const startDate = new Date(rangeStart)
  const endDate = new Date(rangeEnd)
  startDate.setHours(0, 0, 0, 0)
  endDate.setHours(0, 0, 0, 0)
  const endExclusive = new Date(endDate)
  endExclusive.setDate(endExclusive.getDate() + 1)

  const durationMinutes = getAppointmentDurationMinutes(appointment)
  const requestId = ++availabilityRequestId
  const eventsBuffer = []

  const cursor = new Date(startDate)
  while (cursor < endExclusive) {
    const date = formatDateOnly(cursor)
    try {
      const { data } = await calendarApi.getDoctorSlots(doctorId, {
        date,
        procedure_id: appointment.procedure_id || appointment.procedure?.id || undefined,
        room_id: appointment.room_id || appointment.room?.id || undefined,
        equipment_id: appointment.equipment_id || appointment.equipment?.id || undefined,
        assistant_id: appointment.assistant_id || appointment.assistant?.id || undefined,
        duration_minutes: durationMinutes,
      })
      const slots = Array.isArray(data?.slots) ? data.slots : []
      const intervals = mergeSlotsToIntervals(slots, durationMinutes)
      intervals.forEach((interval) => {
        const start = new Date(`${date}T${interval.start}:00`)
        const end = new Date(`${date}T${interval.end}:00`)
        if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return
        eventsBuffer.push({
          id: `availability-${doctorId}-${date}-${interval.start}`,
          calendarId: 'main',
          title: '',
          category: 'time',
          start,
          end,
          isReadOnly: true,
          backgroundColor: AVAILABILITY_BG_COLOR,
          borderColor: AVAILABILITY_BORDER_COLOR,
          dragBackgroundColor: AVAILABILITY_BG_COLOR,
          color: 'transparent',
          classNames: ['calendar-availability-slot'],
        })
      })
    } catch (error) {
      console.error(`Помилка завантаження слотів на ${date}`, error)
    }
    cursor.setDate(cursor.getDate() + 1)
  }

  if (requestId !== availabilityRequestId) return
  applyAvailabilityEvents(eventsBuffer)
}

// --- API ACTIONS ---

// 1. Завантаження клінік
const loadClinics = async () => {
  try {
    if (user.value?.global_role === 'super_admin') {
      const { data } = await clinicApi.list();
      clinics.value = Array.isArray(data?.data) ? data.data : (Array.isArray(data) ? data : []);
    } else {
      const { data } = await clinicApi.listMine();
      clinics.value = (data.clinics || []).map(c => ({ id: c.clinic_id, name: c.clinic_name }));
    }

    if (!currentClinicId.value && clinics.value.length > 0) {
      selectedClinicId.value = clinics.value[0].id
    }
  } catch (e) {
    console.error('Failed to load clinics', e)
  }
}

// 2. Завантаження подій
const fetchEvents = async () => {
  if (!currentClinicId.value) return;

  const start = calendarRef.value?.getDateRangeStart?.()
  const end = calendarRef.value?.getDateRangeEnd?.()

  if (!start || !end) return;

  const from = formatDateOnly(start)
  const to = formatDateOnly(end)

  try {
    const [blocksResponse, appointmentsResponse] = await Promise.all([
      calendarApi.getCalendarBlocks({ clinic_id: currentClinicId.value, from, to }),
      calendarApi.getAppointments({ clinic_id: currentClinicId.value, from_date: from, to_date: to })
    ]);

    // Обробка Блоків
    const blocksData = blocksResponse.data?.data || blocksResponse.data || [];
    const mappedBlocks = blocksData.map(mapBlockToEvent).filter(Boolean)

    // Обробка Записів
    const appointmentsData = appointmentsResponse.data?.data || appointmentsResponse.data || [];
    const mappedAppointments = appointmentsData.map(mapAppointmentToEvent).filter(Boolean)

    events.value = [...mappedBlocks, ...mappedAppointments];

    calendarRef.value?.clear?.();
    if (events.value.length) {
      calendarRef.value?.createEvents?.(events.value);
    }
    if (availabilityEvents.value.length) {
      calendarRef.value?.createEvents?.(availabilityEvents.value);
    }

  } catch (error) {
    console.error(error);
    toastError('Помилка завантаження даних');
  }
}

// 3. Збереження події
const saveEvent = async (payload) => {
  if (!currentClinicId.value) {
    toastError('Оберіть клініку!');
    return;
  }

  const payloadToSave = { ...payload };
  if (!payloadToSave.doctor_id && defaultDoctorId.value) {
    payloadToSave.doctor_id = defaultDoctorId.value;
  }

  if (!payloadToSave.doctor_id && !payloadToSave.room_id && !payloadToSave.equipment_id) {
    toastError('Помилка: Потрібно обрати лікаря або кабінет у формі.');
    return;
  }

  const isEdit = payload.id && !String(payload.id).startsWith('new-');

  const apiPayload = {
    clinic_id: currentClinicId.value,
    type: payload.type || 'break',
    start_at: formatDateTime(payload.start),
    end_at: formatDateTime(payload.end),
    note: payload.note ?? payload.title ?? '',
    doctor_id: payloadToSave.doctor_id
  };

  try {
    if (isEdit) {
      await calendarApi.updateCalendarBlock(payload.id, apiPayload)
      toastSuccess('Блок оновлено')
    } else {
      await calendarApi.createCalendarBlock(apiPayload)
      toastSuccess('Блок створено')
    }
    fetchEvents();
    handleCloseModal();
  } catch (e) {
    console.error(e);
    toastError('Не вдалося зберегти: ' + (e.response?.data?.message || 'Помилка'));
  }
}

// --- EVENT HANDLERS ---
const handleSaveEvent = (payload) => {
  saveEvent(payload)
}

const handleClinicChange = () => {
  removeAvailabilityEvents()
  nextTick(() => fetchEvents())
}

const updateCurrentDate = () => {
  const date = calendarRef.value?.getDate?.()
  if (date) currentDate.value = new Date(date)
}

const next = () => {
  calendarRef.value?.next()
  updateCurrentDate()
  fetchEvents()
  removeAvailabilityEvents()
}
const prev = () => {
  calendarRef.value?.prev()
  updateCurrentDate()
  fetchEvents()
  removeAvailabilityEvents()
}
const today = () => {
  calendarRef.value?.today()
  updateCurrentDate()
  fetchEvents()
  removeAvailabilityEvents()
}

const changeView = () => {
  calendarRef.value?.changeView(view.value)
  updateCurrentDate()
  fetchEvents()
  removeAvailabilityEvents()
}

const selectMonth = (date) => {
  if (!date) return
  calendarRef.value?.setDate?.(date)
  updateCurrentDate()
  fetchEvents()
  removeAvailabilityEvents()
}

const createDefaultEvent = ({ start, end, event }) => {
  const s = toDate(start) ?? new Date()
  const e = toDate(end) ?? new Date(s.getTime() + 30 * 60000)
  return {
    id: event?.id || `new-${Date.now()}`,
    calendarId: 'main',
    title: event?.title || '',
    start: s,
    end: e,
    doctor_id: event?.doctor_id || defaultDoctorId.value,
    type: event?.type || 'break',
    note: event?.note || '',
  }
}

const openEventModal = (data) => {
  activeEvent.value = data
  isEventModalOpen.value = true
}

const handleCloseModal = () => {
  isEventModalOpen.value = false
  activeEvent.value = {}
}

const handleCloseAppointmentModal = () => {
  isAppointmentModalOpen.value = false
  selectedAppointment.value = null
}

const handleAppointmentSaved = () => {
  fetchEvents()
}

const handleSelectDateTime = (info) => {
  const start = toDate(info?.start)
  const end = toDate(info?.end)
  removeAvailabilityEvents()
  openEventModal(createDefaultEvent({ start, end }))
}

const handleClickEvent = (info) => {
  const event = info?.event
  if (!event) return

  if (event.raw && Object.prototype.hasOwnProperty.call(event.raw, 'patient_id')) {
    removeAvailabilityEvents()
    selectedAppointment.value = event.raw
    isAppointmentModalOpen.value = true
    return;
  }
  removeAvailabilityEvents()
  openEventModal(createDefaultEvent({ event, start: event.start, end: event.end }))
}

const handleEventDragStart = ({ event }) => {
  if (!event?.raw || !Object.prototype.hasOwnProperty.call(event.raw, 'patient_id')) return
  loadAvailabilitySlots(event.raw)
}

const handleEventDragEnd = () => {
  removeAvailabilityEvents()
}

const handleBeforeUpdateEvent = async (info) => {
  const event = info?.event
  if (!event) return

  const originalStart = toDate(event.start)
  const originalEnd = toDate(event.end)
  if (!originalStart || !originalEnd) {
    toastError('Не вдалося оновити час запису');
    return
  }

  const nextStartRaw = toDate(info?.changes?.start ?? event.start)
  const nextEndRaw = toDate(info?.changes?.end ?? event.end)

  if (!nextStartRaw || !nextEndRaw) {
    toastError('Не вдалося оновити час запису');
    return
  }

  const snappedStart = snapToMinutes(nextStartRaw)
  if (!snappedStart) {
    toastError('Не вдалося обчислити новий час запису')
    return
  }

  const durationMs = nextEndRaw.getTime() - nextStartRaw.getTime()
  if (durationMs <= 0) {
    toastError('Некоректна тривалість запису')
    return
  }

  const snappedEnd = new Date(snappedStart.getTime() + durationMs)

  if (event.raw && Object.prototype.hasOwnProperty.call(event.raw, 'patient_id')) {
    if (event.raw.status === 'done') {
      toastError('Завершений запис не можна переносити');
      return
    }

    try {
      const slotAllowed = await isDropAllowed(event.raw, snappedStart, snappedEnd)
      if (!slotAllowed) {
        toastError('Лікар недоступний у вибраний час')
        flashInvalidDrop(event.id, event.calendarId)
        calendarRef.value?.updateEvent?.(event.id, event.calendarId, {
          start: originalStart,
          end: originalEnd,
        })
        removeAvailabilityEvents()
        return
      }

      const { data } = await calendarApi.updateAppointment(event.id, {
        doctor_id: getAppointmentDoctorId(event.raw),
        start_at: formatDateTime(snappedStart),
        end_at: formatDateTime(snappedEnd),
      })

      const updatedAppointment = data?.data || data?.appointment || data
      if (updatedAppointment) {
        replaceAppointmentEvent(updatedAppointment, snappedStart, snappedEnd)
      } else {
        calendarRef.value?.updateEvent?.(event.id, event.calendarId, {
          start: snappedStart,
          end: snappedEnd,
        })
      }

      toastSuccess('Запис перенесено')
      removeAvailabilityEvents()
    } catch (error) {
      console.error(error)
      toastError('Не вдалося перенести запис')
      flashInvalidDrop(event.id, event.calendarId)
      calendarRef.value?.updateEvent?.(event.id, event.calendarId, {
        start: originalStart,
        end: originalEnd,
      })
      removeAvailabilityEvents()
    }
    return
  }

  removeAvailabilityEvents()
  openEventModal(createDefaultEvent({ event, start: snappedStart, end: snappedEnd }))
}

// --- LIFECYCLE ---
onMounted(async () => {
  await initAuth()
  await loadClinics()

  await nextTick()
  setTimeout(() => {
    updateCurrentDate()
    fetchEvents()
  }, 100)
})

watch(view, () => {
  handleCloseModal()
  handleCloseAppointmentModal()
})
watch(currentClinicId, () => {
  fetchEvents()
})
</script>
