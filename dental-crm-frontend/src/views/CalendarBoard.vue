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
    <div class="px-6 flex items-center gap-2 mb-4">
      <button @click="prev">‹</button>
      <button @click="today">Today</button>
      <button @click="next">›</button>

      <select
          v-model="view"
          @change="changeView"
          class="bg-card border border-border/80 text-text/90
         rounded-md px-3 py-1 text-sm
         focus:outline-none focus:ring-2 focus:ring-emerald-500"
      >
        <option value="day">Day</option>
        <option value="week">Week</option>
        <option value="month">Month</option>
      </select>
    </div>
    <button
        class="px-2 py-1 rounded-md bg-card/80 hover:bg-card/70 text-text/90"
        @click="prev"
    >‹</button>

    <button
        class="px-3 py-1 rounded-md bg-emerald-600 hover:bg-emerald-500 text-text text-sm font-medium"
        @click="today"
    >Today</button>

    <button
        class="px-2 py-1 rounded-md bg-card/80 hover:bg-card/70 text-text/90"
        @click="next"
    >›</button>

    <!-- Календар -->
    <div class="px-6 pb-6 h-[calc(100vh-160px)] overflow-hidden">
      <ToastCalendar ref="calendarRef" :events="events" />
    </div>

  </div>
</template>

<script setup>
import { ref } from 'vue'
import ToastCalendar from '../components/ToastCalendar.vue'
import '../assets/css/calendar-theme.css'

const calendarRef = ref(null)
const view = ref('week')

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

const next = () => calendarRef.value?.next()
const prev = () => calendarRef.value?.prev()
const today = () => calendarRef.value?.today()

const changeView = () => {
  calendarRef.value?.changeView(view.value)
}
</script>
