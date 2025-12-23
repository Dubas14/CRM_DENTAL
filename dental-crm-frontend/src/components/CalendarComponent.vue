<script setup>
import { computed } from 'vue';
import FullCalendar from '@fullcalendar/vue3';
import timeGridPlugin from '@fullcalendar/timegrid';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import { useAuth } from '../composables/useAuth';
import { useCalendar } from '../composables/useCalendar';
import BookingModal from './BookingModal.vue';

useAuth();

const {
  calendarRef,
  events,
  availabilityBgEvents,
  viewMode,
  selectedDoctorId,
  selectedProcedureId,
  selectedEquipmentId,
  selectedRoomId,
  selectedAssistantId,
  isFollowUp,
  allowSoftConflicts,
  doctors,
  procedures,
  rooms,
  equipments,
  loading,
  loadingSlots,
  error,
  booking,
  isBookingOpen,
  bookingLoading,
  bookingError,
  closeBooking,
  createAppointment,
  handleSelect,
  handleEventClick,
  handleEventMoveResize,
  showDragAvailability,
  hideDragAvailability,
  selectAllow,
  handleDatesSet,
} = useCalendar();

const calendarOptions = computed(() => ({
  plugins: [timeGridPlugin, dayGridPlugin, interactionPlugin],
  initialView: viewMode.value,
  headerToolbar: {
    left: 'prev,next today',
    center: 'title',
    right: 'timeGridDay,timeGridWeek,dayGridMonth',
  },
  timeZone: 'local',
  height: 'auto',
  nowIndicator: true,
  selectable: true,
  editable: true,
  eventResizableFromStart: false,
  slotMinTime: '07:00:00',
  slotMaxTime: '22:00:00',
  slotDuration: '00:30:00',
  allDaySlot: false,
  weekends: true,
  events: [...availabilityBgEvents.value, ...events.value],
  selectAllow,
  select: handleSelect,
  eventClick: handleEventClick,
  eventDragStart: async (info) => {
    await showDragAvailability(info.event);
  },
  eventDragStop: async () => {
    await hideDragAvailability();
  },
  eventDrop: async (info) => {
    await handleEventMoveResize(info, 'drop');
  },
  eventResize: async (info) => {
    await handleEventMoveResize(info, 'resize');
  },
  datesSet: handleDatesSet,
}));

const onBookingSubmit = (payload) => {
  createAppointment(payload);
};
</script>

<template>
  <div class="p-6 space-y-4">
    <div class="flex items-start justify-between gap-4 flex-wrap">
      <div>
        <h1 class="text-2xl font-bold text-white">Календар записів</h1>
        <p class="text-slate-400 text-sm">
          Виділяйте час — створюйте запис. Перетягуйте — переносьте. Вільні слоти підсвічуються.
        </p>
      </div>

      <div class="flex gap-2 flex-wrap items-center">
        <select
            v-model="selectedDoctorId"
            class="bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white"
            aria-label="Оберіть лікаря"
            :disabled="loading"
        >
          <option disabled value="">Оберіть лікаря</option>
          <option v-for="doc in doctors" :key="doc.id" :value="doc.id">
            {{ doc.full_name || doc.name }}
          </option>
        </select>

        <select
            v-model="viewMode"
            class="bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white"
            aria-label="Виберіть вид"
        >
          <option value="timeGridDay">День</option>
          <option value="timeGridWeek">Тиждень</option>
          <option value="dayGridMonth">Місяць</option>
        </select>

        <button
            class="px-3 py-2 rounded border border-slate-700 text-slate-200 hover:text-white disabled:opacity-50"
            :disabled="loading"
            @click="refreshCalendar"
            aria-label="Оновити календар"
        >
          <span v-if="loading">Оновлення...</span>
          <span v-else>Оновити</span>
        </button>
      </div>
    </div>

    <div v-if="loading" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
      <div class="bg-slate-900 border border-slate-700 rounded-lg p-6 flex flex-col items-center gap-3">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-500"></div>
        <p class="text-white">Завантаження календаря...</p>
      </div>
    </div>

    <div class="grid lg:grid-cols-4 gap-4">
      <div class="lg:col-span-3 bg-slate-900/60 border border-slate-800 rounded-xl p-3 relative">
        <div v-if="error" class="text-sm text-red-400 bg-red-900/20 border border-red-700/40 rounded-lg p-3 mb-3">
          {{ error }}
        </div>

        <div v-if="loadingSlots" class="absolute top-3 right-3 z-10 flex items-center gap-2">
          <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-emerald-500"></div>
          <span class="text-xs text-slate-400">Оновлення слотів...</span>
        </div>

        <FullCalendar ref="calendarRef" :options="calendarOptions" />
      </div>

      <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-4 space-y-3">
        <p class="text-white font-semibold">Контекст бронювання</p>

        <label class="space-y-1 block">
          <span class="text-xs text-slate-400">Процедура</span>
          <select
              v-model="selectedProcedureId"
              class="w-full bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white"
              aria-label="Виберіть процедуру"
          >
            <option value="">Без процедури</option>
            <option v-for="p in procedures" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </label>

        <label class="space-y-1 block">
          <span class="text-xs text-slate-400">Кабінет</span>
          <select
              v-model="selectedRoomId"
              class="w-full bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white"
              aria-label="Виберіть кабінет"
          >
            <option value="">Будь-який</option>
            <option v-for="r in rooms" :key="r.id" :value="r.id">{{ r.name }}</option>
          </select>
        </label>

        <label class="space-y-1 block">
          <span class="text-xs text-slate-400">Обладнання</span>
          <select
              v-model="selectedEquipmentId"
              class="w-full bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white"
              aria-label="Виберіть обладнання"
          >
            <option value="">Будь-яке</option>
            <option v-for="e in equipments" :key="e.id" :value="e.id">{{ e.name }}</option>
          </select>
        </label>

        <label class="space-y-1 block">
          <span class="text-xs text-slate-400">Асистент ID</span>
          <input
              v-model="selectedAssistantId"
              type="number"
              class="w-full bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white"
              placeholder="Напр. 123"
              aria-label="ID асистента"
          />
        </label>

        <div class="space-y-2 text-sm text-slate-300">
          <label class="flex items-center gap-2">
            <input
                v-model="isFollowUp"
                type="checkbox"
                class="accent-emerald-500"
                aria-label="Повторний візит"
            />
            <span>Повторний візит</span>
          </label>

          <label class="flex items-center gap-2">
            <input
                v-model="allowSoftConflicts"
                type="checkbox"
                class="accent-emerald-500"
                aria-label="Дозволити soft конфлікти"
            />
            <span>Дозволити soft конфлікти</span>
          </label>
        </div>

        <div class="text-xs text-slate-500 pt-2 border-t border-slate-800">
          <p class="mb-1">Підказка:</p>
          <ul class="space-y-1 list-disc list-inside">
            <li>Вільні слоти підсвічуються зеленим</li>
            <li>Переносьте запис тільки на підсвічений час</li>
            <li>Під час перетягування підсвітка автоматично підлаштовується</li>
          </ul>
        </div>
      </div>
    </div>

    <BookingModal
        :is-open="isBookingOpen"
        :booking="booking"
        :booking-loading="bookingLoading"
        :booking-error="bookingError"
        @close="closeBooking"
        @submit="onBookingSubmit"
    />
  </div>
</template>