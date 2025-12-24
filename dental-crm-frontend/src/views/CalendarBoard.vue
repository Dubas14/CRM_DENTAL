<!-- src/views/CalendarBoard.vue - ПОКРАЩЕНА ВЕРСІЯ -->
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

    <!-- Основний контент -->
    <div class="px-6 pb-6">
      <CalendarComponent />
    </div>

    <!-- Toast повідомлення -->
    <ToastContainer />
  </div>
</template>

<script setup>
import CalendarComponent from '../components/CalendarComponent.vue';
import ToastContainer from '../components/ToastContainer.vue';
import '../assets/css/calendar.css';
</script>
