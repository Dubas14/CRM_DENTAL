<script setup>
import { computed, reactive, watch } from 'vue';
import FullCalendar from '@fullcalendar/vue3';
import timeGridPlugin from '@fullcalendar/timegrid';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import ukLocale from '@fullcalendar/core/locales/uk';

import { useAuth } from '../composables/useAuth';
import { useCalendar } from '../composables/useCalendar';
import BookingModal from './BookingModal.vue';

useAuth();

const {
  calendarRef,
  events,
  availabilityBgEvents,
  calendarBlocks,

  viewMode,
  selectedDoctorId,
  selectedProcedureId,
  selectedEquipmentId,
  selectedRoomId,
  selectedAssistantId,
  selectedClinicId,
  isFollowUp,
  allowSoftConflicts,

  doctors,
  filteredDoctors,
  procedures,
  rooms,
  equipments,
  assistants,
  clinics,
  showClinicSelector,

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
  refreshCalendar,
} = useCalendar();

const calendarEventSources = computed(() => [
  availabilityBgEvents.value,
  calendarBlocks.value,
  events.value,
]);

const selectedProcedure = computed(() =>
  procedures.value.find((p) => p.id === Number(selectedProcedureId.value)),
);

const requiresAssistant = computed(() => !!selectedProcedure.value?.requires_assistant);

const assistantLabel = (assistant) =>
  assistant.full_name || assistant.name || `${assistant.first_name || ''} ${assistant.last_name || ''}`.trim() || `#${assistant.id}`;

watch([requiresAssistant, assistants], () => {
  if (requiresAssistant.value && assistants.value.length === 1 && !selectedAssistantId.value) {
    selectedAssistantId.value = assistants.value[0].id;
  }
});

// ✅ Стабільний options-об'єкт (не пересоздається на кожен рух)
const calendarOptions = reactive({
  plugins: [timeGridPlugin, dayGridPlugin, interactionPlugin],

  // initialView не робимо реактивним — view міняє composable через api.changeView
  initialView: 'timeGridWeek',

  headerToolbar: {
    left: 'prev,next today',
    center: 'title',
    right: 'timeGridDay,timeGridWeek,dayGridMonth',
  },

  timeZone: 'local',

  // ✅ Українська локаль
  locales: [ukLocale],
  locale: 'uk',

  // ✅ 24h формат часу
  slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
  eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },

  // ✅ 24 години, але зі скролом (щоб було “красиво як раніше”)
  slotMinTime: '00:00:00',
  slotMaxTime: '24:00:00',
  scrollTime: '07:00:00',
  scrollTimeReset: false,

  height: '100%',
  nowIndicator: true,
  selectable: true,
  editable: true,
  eventResizableFromStart: false,

  slotDuration: '00:30:00',
  allDaySlot: false,
  weekends: true,

  eventSources: [], // поставимо нижче через watch

  selectAllow: (info) => selectAllow(info),
  select: (info) => handleSelect(info),
  eventClick: (info) => handleEventClick(info),

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

  datesSet: (info) => {
    // ✅ важливо: передаємо info в composable,
    // щоб він зберіг range і не ганявся по колу
    handleDatesSet(info);
  },
});

// ✅ оновлюємо тільки events масив, а не весь options-об’єкт
watch(calendarEventSources, (val) => {
  calendarOptions.eventSources = val;
}, { immediate: true });

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
            v-if="showClinicSelector"
            v-model="selectedClinicId"
            class="bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white"
            aria-label="Оберіть клініку"
            :disabled="loading"
        >
          <option disabled value="">Оберіть клініку</option>
          <option v-for="clinic in clinics" :key="clinic.id" :value="clinic.id">
            {{ clinic.name }}
          </option>
        </select>

        <select
            v-model="selectedDoctorId"
            class="bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white"
            aria-label="Оберіть лікаря"
            :disabled="loading"
        >
          <option disabled value="">Оберіть лікаря</option>
          <option v-for="doc in filteredDoctors" :key="doc.id" :value="doc.id">
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
      <!-- ✅ ВАЖЛИВО: фіксована висота + overflow, щоб 24h було зі скролом -->
      <div class="lg:col-span-3 bg-slate-900/60 border border-slate-800 rounded-xl p-3 relative h-[75vh] overflow-hidden">
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
          <span class="text-xs text-slate-400">
            Асистент<span v-if="requiresAssistant" class="text-rose-400"> *</span>
          </span>
          <select
              v-model="selectedAssistantId"
              class="w-full bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white"
              :disabled="assistants.length === 0"
              aria-label="Виберіть асистента"
          >
            <option value="">Без асистента</option>
            <option v-for="assistant in assistants" :key="assistant.id" :value="assistant.id">
              {{ assistantLabel(assistant) }}
            </option>
          </select>
          <p v-if="requiresAssistant && !assistants.length" class="text-[10px] text-rose-400">
            Додайте асистента в налаштуваннях клініки.
          </p>
        </label>

        <div class="space-y-2 text-sm text-slate-300">
          <label class="flex items-center gap-2">
            <input v-model="isFollowUp" type="checkbox" class="accent-emerald-500" />
            <span>Повторний візит</span>
          </label>

          <label class="flex items-center gap-2">
            <input v-model="allowSoftConflicts" type="checkbox" class="accent-emerald-500" />
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
