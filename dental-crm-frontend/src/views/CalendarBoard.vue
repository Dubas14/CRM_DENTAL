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
import { computed, onMounted, nextTick, ref, watch } from 'vue'
import CalendarHeader from '../components/CalendarHeader.vue'
import ToastCalendar from '../components/ToastCalendar.vue'
import EventModal from '../components/EventModal.vue'
import calendarApi from '../services/calendarApi'
import { useToast } from '../composables/useToast'
import { useAuth } from '../composables/useAuth'


const calendarRef = ref(null)
const view = ref('week')
const currentDate = ref(new Date())
const isEventModalOpen = ref(false)
const activeEvent = ref({})
const pendingUpdateInfo = ref(null)
const { error: toastError, success: toastSuccess } = useToast()
const { user, initAuth } = useAuth()

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
  return `${year}-${month}-${day} ${hour}:${minute}:00`
}

const clinicId = computed(
  () =>
    user.value?.clinic_id ||
    user.value?.doctor?.clinic_id ||
    user.value?.doctor?.clinic?.id ||
    null
)

const defaultDoctorId = computed(() => user.value?.doctor_id || user.value?.doctor?.id || null)

const mapApiEventToCalendar = (event) => ({
  id: event.id,
  calendarId: 'main',
  title: String(event.note || event.type || 'Запис'),
  category: 'time',
  start: event.start_at,
  end: event.end_at,
  doctor_id: event.doctor_id,
  room_id: event.room_id,
  equipment_id: event.equipment_id,
  assistant_id: event.assistant_id,
  type: event.type,
  note: event.note,
})

const getRangeParams = () => {
  const start = calendarRef.value?.getDateRangeStart?.()
  const end = calendarRef.value?.getDateRangeEnd?.()
  return {
    from: formatDateOnly(start),
    to: formatDateOnly(end),
  }
}

const fetchEvents = async () => {
  if (!clinicId.value) return
  const { from, to } = getRangeParams()
  if (!from || !to) return

  try {
    const { data } = await calendarApi.getCalendarBlocks({
      clinic_id: clinicId.value,
      from,
      to,
    })
    const blocks = Array.isArray(data?.data) ? data.data : (Array.isArray(data) ? data : [])
    events.value = blocks.map(mapApiEventToCalendar)
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
    room_id: event?.room_id,
    equipment_id: event?.equipment_id,
    assistant_id: event?.assistant_id,
    type: event?.type,
    note: event?.note,
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
  await initAuth()
  await nextTick()
  updateCurrentDate()
  setTimeout(fetchEvents, 0)
})

watch(view, () => {
  handleCloseModal()
})

watch(
  clinicId,
  async (nextClinicId) => {
    if (!nextClinicId) return
    await nextTick()
    fetchEvents()
  },
  { immediate: true }
)

const buildApiPayload = (payload) => {
  const apiPayload = {
    clinic_id: clinicId.value,
    type: 'personal_block',
    start_at: formatDateTime(payload.start),
    end_at: formatDateTime(payload.end),
    note: payload.note ?? payload.title ?? '',
  }

  if (payload.doctor_id) {
    apiPayload.doctor_id = payload.doctor_id
  }
  if (payload.room_id) {
    apiPayload.room_id = payload.room_id
  }
  if (payload.equipment_id) {
    apiPayload.equipment_id = payload.equipment_id
  }
  if (payload.assistant_id) {
    apiPayload.assistant_id = payload.assistant_id
  }
  if (payload.patient_id) {
    apiPayload.patient_id = payload.patient_id
  }
  if (payload.status) {
    apiPayload.status = payload.status
  }
  if (!apiPayload.doctor_id && defaultDoctorId.value) {
    apiPayload.doctor_id = defaultDoctorId.value
  }

  return apiPayload
}

const saveEvent = async (payload) => {
  if (!clinicId.value) return
  const isEdit = Boolean(payload.id)
  const apiPayload = buildApiPayload(payload)
  if (!apiPayload.doctor_id && !apiPayload.room_id && !apiPayload.equipment_id && !apiPayload.assistant_id) {
    toastError('Заповніть лікаря або ресурс')
    return
  }

  try {
    if (isEdit) {
      const { data } = await calendarApi.updateCalendarBlock(payload.id, apiPayload)
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
      const { data } = await calendarApi.createCalendarBlock(apiPayload)
      const createdEvent = mapApiEventToCalendar(data ?? { ...payload, ...apiPayload, id: generateEventId() })
      events.value = [...events.value, createdEvent]
      calendarRef.value?.createEvents?.([createdEvent])
      toastSuccess('Запис створено')
    }
    await nextTick()
    fetchEvents()
    pendingUpdateInfo.value = null
    handleCloseModal()
  } catch (error) {
    if (error?.response?.status === 409) {
      toastError('Обраний час вже зайнятий. Оберіть інший слот.')
    } else if (error?.response?.status === 422) {
      toastError('Заповніть лікаря або ресурс')
    } else {
      toastError('Не вдалося зберегти подію. Спробуйте ще раз.')
    }
    console.error('Не вдалося зберегти подію', error)
  }
}
</script>
