<!-- src/views/CalendarBoard.vue -->
<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-950 to-slate-900">
    <!-- Заголовок сторінки -->
    <div class="p-6 pb-2">
      <h1 class="text-2xl font-bold text-white mb-2">Календар записів</h1>
      <p class="text-slate-400 text-sm">
        Управління розкладом лікарів, бронювання та перегляд записів
      </p>

      <div class="fc mt-3 flex flex-wrap gap-4 text-xs text-slate-300">
        <div class="flex items-center gap-2">
          <span class="fc-event inline-block h-3 w-3 rounded-sm"></span>
          <span>Запис</span>
        </div>
        <div class="flex items-center gap-2">
          <span class="fc-bg-event free-slot inline-block h-3 w-3 rounded-sm"></span>
          <span>Вільний слот</span>
        </div>
      </div>
    </div>

    <!-- 🟢 КАЛЕНДАР -->
    <div class="px-6 pb-6 h-[calc(100vh-160px)] overflow-hidden">
      <ToastCalendar :events="events" />
    </div>

    <!-- Toast повідомлення -->
    <ToastContainer />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import ToastContainer from '../components/ToastContainer.vue'
import ToastCalendar from '../components/ToastCalendar.vue'
import '../assets/css/calendar.css'

// 🧪 ТИМЧАСОВІ тестові події
const events = ref([
  {
    id: '1',
    calendarId: 'main',
    title: 'ТЕСТ: операція',
    category: 'time',
    start: '2025-12-25T09:30:00',
    end: '2025-12-25T10:00:00',

    backgroundColor: '#2563eb',   // синій
    borderColor: '#60a5fa',
    color: '#ffffff',

    dragBackgroundColor: '#2563eb',
  },
])
</script>
