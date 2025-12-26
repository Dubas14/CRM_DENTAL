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
      <ToastCalendar ref="calendarRef" :events="events" />
    </div>

  </div>
</template>

<script setup>
import { onMounted, nextTick, ref } from 'vue'
import CalendarHeader from '../components/CalendarHeader.vue'
import ToastCalendar from '../components/ToastCalendar.vue'


const calendarRef = ref(null)
const view = ref('week')
const currentDate = ref(new Date())

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

onMounted(async () => {
  await nextTick()
  updateCurrentDate()
})
</script>
