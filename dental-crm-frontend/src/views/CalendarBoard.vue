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
        :events="events"
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


const calendarRef = ref(null)
const view = ref('week')
const currentDate = ref(new Date())
const isEventModalOpen = ref(false)
const activeEvent = ref({})
const pendingUpdateInfo = ref(null)

const events = ref([
  {
    id: '1',
    calendarId: 'main',
    title: 'ТЕСТ: операція',
    category: 'time',
    start: '2025-12-25T09:30:00',
    end: '2025-12-25T10:00:00',
  },
])

const updateCurrentDate = () => {
  const date = calendarRef.value?.getDate?.()
  if (!date) return
  currentDate.value = new Date(date)
}

const next = () => {
  calendarRef.value?.next()
  updateCurrentDate()
}
const prev = () => {
  calendarRef.value?.prev()
  updateCurrentDate()
}
const today = () => {
  calendarRef.value?.today()
  updateCurrentDate()
}

const changeView = () => {
  calendarRef.value?.changeView(view.value)
  updateCurrentDate()
}

const selectMonth = (date) => {
  if (!date) return
  calendarRef.value?.setDate?.(date)
  updateCurrentDate()
}

const toDate = (value) => {
  if (!value) return null
  if (value.toDate) return value.toDate()
  const date = value instanceof Date ? value : new Date(value)
  return Number.isNaN(date.getTime()) ? null : date
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
  pendingUpdateInfo.value = null
  const eventId = payload.id || generateEventId()
  const updatedEvent = {
    ...payload,
    id: eventId,
    calendarId: payload.calendarId || 'main',
    category: payload.category || 'time',
  }

  const existingIndex = events.value.findIndex((event) => event.id === eventId)

  if (existingIndex >= 0) {
    const nextEvents = [...events.value]
    nextEvents[existingIndex] = updatedEvent
    events.value = nextEvents
    calendarRef.value?.updateEvent?.(eventId, updatedEvent.calendarId, updatedEvent)
  } else {
    events.value = [...events.value, updatedEvent]
    calendarRef.value?.createEvents?.([updatedEvent])
  }

  handleCloseModal()
}

onMounted(async () => {
  await nextTick()
  updateCurrentDate()
})

watch(view, () => {
  handleCloseModal()
})
</script>
