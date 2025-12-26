<template>
  <div class="min-h-screen bg-bg">
    <!-- Заголовок -->
    <div class="p-6 pb-2">
      <h1 class="text-2xl font-bold text-text mb-2">Календар записів</h1>
      <p class="text-text/70 text-sm">
        Управління розкладом лікарів, бронювання та перегляд записів
      </p>
    </div>

    <!-- Навігація -->
    <div class="px-6 flex flex-wrap items-center justify-between gap-4 mb-4">
      <CalendarHeader
        :current-date="currentDate"
        @prev="prev"
        @next="next"
        @today="today"
        @select-date="selectMonth"
      />

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

    <!-- Календар -->
    <div class="px-6 pb-6 h-[calc(100vh-160px)] overflow-hidden">
      <ToastCalendar
        ref="calendarRef"
        @select-date-time="handleSelectDateTime"
        @click-event="handleClickEvent"
        @before-update-event="handleBeforeUpdateEvent"
      />
    </div>

    <EventModal
      :open="isEventModalOpen"
      :event="activeEvent"
      @save="handleSaveEvent"
      @close="handleCloseModal"
    />
  </div>
</template>

<script setup>
import { onMounted, nextTick, ref, watch } from 'vue'
import CalendarHeader from '../components/CalendarHeader.vue'
import ToastCalendar from '../components/ToastCalendar.vue'
import EventModal from '../components/EventModal.vue'
import apiClient from '../services/apiClient'
import { useToast } from '../composables/useToast'


const calendarRef = ref(null)
const view = ref('week')
const currentDate = ref(new Date())
const isEventModalOpen = ref(false)
const activeEvent = ref({})
const pendingUpdateInfo = ref(null)
const { error: toastError, success: toastSuccess } = useToast()

const events = ref([])

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
  return `${year}-${month}-${day}T${hour}:${minute}:00`
}

const mapApiEventToCalendar = (event) => ({
  id: event.id,
  calendarId: 'main',
  title: event.title,
  category: 'time',
  start: event.start,
  end: event.end,
  doctor_id: event.doctor_id,
  patient_id: event.patient_id,
  status: event.status,
  type: event.type,
})

const getRangeParams = () => {
  const start = calendarRef.value?.getDateRangeStart?.()
  const end = calendarRef.value?.getDateRangeEnd?.()
  return {
    start: formatDateOnly(start),
    end: formatDateOnly(end),
  }
}

const fetchEvents = async () => {
  const { start, end } = getRangeParams()
  if (!start || !end) return

  try {
    const { data } = await apiClient.get('/calendar/events', {
      params: { start, end },
    })
    events.value = Array.isArray(data) ? data.map(mapApiEventToCalendar) : []
    calendarRef.value?.clear?.()
    if (events.value.length) {
      calendarRef.value?.createEvents?.(events.value)
    }
  } catch (error) {
    console.error('Не вдалося отримати події календаря', error)
  }
}

const updateCurrentDate = () => {
  const date = calendarRef.value?.getDate?.()
  if (!date) return
  currentDate.value = new Date(date)
}

const next = () => {
  calendarRef.value?.next()
  updateCurrentDate()
  fetchEvents()
}
const prev = () => {
  calendarRef.value?.prev()
  updateCurrentDate()
  fetchEvents()
}
const today = () => {
  calendarRef.value?.today()
  updateCurrentDate()
  fetchEvents()
}

const changeView = () => {
  calendarRef.value?.changeView(view.value)
  updateCurrentDate()
  fetchEvents()
}

const selectMonth = (date) => {
  if (!date) return
  calendarRef.value?.setDate?.(date)
  updateCurrentDate()
  fetchEvents()
}

const createDefaultEvent = ({ start, end, event }) => {
  const startDate = toDate(start) ?? new Date()
  const endDate = toDate(end) ?? new Date(startDate.getTime() + 30 * 60000)

  return {
    id: event?.id,
    calendarId: event?.calendarId || 'main',
    title: event?.title || '',
    category: event?.category || 'time',
    start: startDate,
    end: endDate,
    doctor_id: event?.doctor_id,
    patient_id: event?.patient_id,
    status: event?.status,
    type: event?.type,
  }
}

const openEventModal = (eventPayload) => {
  activeEvent.value = eventPayload
  isEventModalOpen.value = true
}

const handleCloseModal = () => {
  isEventModalOpen.value = false
  activeEvent.value = {}
  pendingUpdateInfo.value = null
}

const handleSelectDateTime = (info) => {
  const startDate = toDate(info?.start) ?? new Date()
  const endDate = new Date(startDate.getTime() + 30 * 60000)

  openEventModal(createDefaultEvent({ start: startDate, end: endDate }))
}

const handleClickEvent = (info) => {
  const event = info?.event
  if (!event) return

  openEventModal(
    createDefaultEvent({
      event,
      start: event.start,
      end: event.end,
    })
  )
}

const handleBeforeUpdateEvent = (info) => {
  const event = info?.event
  if (!event) return

  pendingUpdateInfo.value = info
  const nextStart = info?.changes?.start ?? event.start
  const nextEnd = info?.changes?.end ?? event.end

  openEventModal(
    createDefaultEvent({
      event,
      start: nextStart,
      end: nextEnd,
    })
  )
}

const generateEventId = () => {
  if (typeof crypto !== 'undefined' && crypto.randomUUID) {
    return crypto.randomUUID()
  }

  return `event-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

const handleSaveEvent = (payload) => {
  saveEvent(payload)
}

onMounted(async () => {
  await nextTick()
  updateCurrentDate()
  fetchEvents()
})

watch(view, () => {
  handleCloseModal()
})

const buildApiPayload = (payload) => {
  const apiPayload = {
    title: payload.title,
    start: formatDateTime(payload.start),
    end: formatDateTime(payload.end),
  }

  if (payload.doctor_id) {
    apiPayload.doctor_id = payload.doctor_id
  }
  if (payload.patient_id) {
    apiPayload.patient_id = payload.patient_id
  }
  if (payload.status) {
    apiPayload.status = payload.status
  }
  if (payload.type) {
    apiPayload.type = payload.type
  }

  return apiPayload
}

const saveEvent = async (payload) => {
  const isEdit = Boolean(payload.id)
  const apiPayload = buildApiPayload(payload)

  try {
    if (isEdit) {
      const { data } = await apiClient.put(`/calendar/events/${payload.id}`, apiPayload)
      const updatedEvent = mapApiEventToCalendar(data ?? { ...payload, ...apiPayload })
      const existingIndex = events.value.findIndex((event) => event.id === payload.id)
      if (existingIndex >= 0) {
        const nextEvents = [...events.value]
        nextEvents[existingIndex] = updatedEvent
        events.value = nextEvents
      }
      calendarRef.value?.updateEvent?.(updatedEvent.id, updatedEvent.calendarId, updatedEvent)
      toastSuccess('Запис оновлено')
    } else {
      const { data } = await apiClient.post('/calendar/events', apiPayload)
      const createdEvent = mapApiEventToCalendar(data ?? { ...payload, ...apiPayload, id: generateEventId() })
      events.value = [...events.value, createdEvent]
      calendarRef.value?.createEvents?.([createdEvent])
      toastSuccess('Запис створено')
    }
    pendingUpdateInfo.value = null
    handleCloseModal()
  } catch (error) {
    if (error?.response?.status === 409) {
      toastError('Обраний час вже зайнятий. Оберіть інший слот.')
    } else {
      toastError('Не вдалося зберегти подію. Спробуйте ще раз.')
    }
    console.error('Не вдалося зберегти подію', error)
  }
}
</script>
