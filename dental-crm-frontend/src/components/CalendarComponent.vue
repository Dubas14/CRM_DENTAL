<script setup>
// ⚠️ ВАЖЛИВО: для FullCalendar v5 з бандлерами — core/vdom та core мають бути першими
import '@fullcalendar/core/vdom';
import '@fullcalendar/core';

import { computed, reactive, watch } from 'vue';
import FullCalendar from '@fullcalendar/vue3';

import timeGridPlugin from '@fullcalendar/timegrid';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';

import resourceTimeGridPlugin from '@fullcalendar/resource-timegrid';
import resourceDayGridPlugin from '@fullcalendar/resource-daygrid';

import ukLocale from '@fullcalendar/core/locales/uk';

import { useAuth } from '../composables/useAuth';
import { useCalendar } from '../composables/useCalendar';
import BookingModal from './BookingModal.vue';

// (можна лишити, але useAuth має бути "чистим" — без onMounted всередині composable)
useAuth();

const {
  calendarRef,
  events,
  availabilityBgEvents,
  calendarBlocks,

  viewMode,
  selectedDoctorId,
  selectedDoctorIds,
  selectedProcedureId,
  selectedEquipmentId,
  selectedRoomId,
  selectedRoomIds,
  selectedAssistantId,
  selectedClinicId,
  selectedSpecializations,
  resourceViewType,
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
  specializations,
  isResourceView,

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

const selectedDoctorResources = computed(() =>
    doctors.value.filter((doctor) => selectedDoctorIds.value.includes(String(doctor.id))),
);

const selectedRoomResources = computed(() =>
    rooms.value.filter((room) => selectedRoomIds.value.includes(String(room.id))),
);

const calendarResources = computed(() => {
  if (!isResourceView.value) return [];

  if (resourceViewType.value === 'doctor') {
    return selectedDoctorResources.value.map((doctor) => ({
      id: String(doctor.id),
      title: doctor.full_name || doctor.name || `#${doctor.id}`,
    }));
  }

  return selectedRoomResources.value.map((room) => ({
    id: String(room.id),
    title: room.name || `#${room.id}`,
  }));
});

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
    assistant.full_name ||
    assistant.name ||
    `${assistant.first_name || ''} ${assistant.last_name || ''}`.trim() ||
    `#${assistant.id}`;

watch([requiresAssistant, assistants], () => {
  if (requiresAssistant.value && assistants.value.length === 1 && !selectedAssistantId.value) {
    selectedAssistantId.value = assistants.value[0].id;
  }
});

// ✅ options як reactive — ок, але без "лівих" плагінів
const calendarOptions = reactive({
  // ❌ НІЯКОГО resourceCommonPlugin — це не плагін
  plugins: [
    timeGridPlugin,
    dayGridPlugin,
    interactionPlugin,
    resourceTimeGridPlugin,
    resourceDayGridPlugin,
  ],

  initialView: 'timeGridWeek',

  headerToolbar: {
    left: 'prev,next today',
    center: 'title',
    right: 'timeGridDay,timeGridWeek,dayGridMonth,resourceTimeGridDay,resourceTimeGridWeek',
  },

  timeZone: 'local',

  locales: [ukLocale],
  locale: 'uk',

  slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
  eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },

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

  eventSources: [],
  resources: [],
  resourceAreaWidth: '18%',
  resourceAreaHeaderContent: 'Лікарі',

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
    handleDatesSet(info);
  },
});

watch(calendarEventSources, (val) => {
  calendarOptions.eventSources = val;
}, { immediate: true });

watch(calendarResources, (val) => {
  calendarOptions.resources = val;
}, { immediate: true });

watch(resourceViewType, (val) => {
  calendarOptions.resourceAreaHeaderContent = val === 'room' ? 'Кабінети' : 'Лікарі';
}, { immediate: true });

const onBookingSubmit = (payload) => {
  createAppointment(payload);
};
</script>

<template>
  <div class="p-6 space-y-4">
    <div class="flex items-start justify-between gap-4 flex-wrap">
      <p class="text-slate-400 text-sm max-w-2xl">
        Виділяйте час — створюйте запис. Перетягуйте — переносьте. Вільні слоти підсвічуються.
      </p>

      <div class="grid w-full gap-3 sm:grid-cols-2 xl:grid-cols-6">
        <label
            v-if="showClinicSelector"
            class="flex flex-col gap-1 text-xs text-slate-400 min-w-[180px]"
        >
          <span>Клініка</span>
          <select
              v-model="selectedClinicId"
              class="bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white text-sm"
              aria-label="Оберіть клініку"
              :disabled="loading"
          >
            <option disabled value="">Оберіть клініку</option>
            <option v-for="clinic in clinics" :key="clinic.id" :value="clinic.id">
              {{ clinic.name }}
            </option>
          </select>
        </label>

        <fieldset class="flex flex-col gap-1 text-xs text-slate-400 min-w-[220px]">
          <legend class="text-xs text-slate-400">Спеціалізації</legend>
          <div
              class="bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white text-sm max-h-28 overflow-y-auto space-y-1"
              role="group"
              aria-label="Фільтр спеціалізацій"
              :aria-disabled="loading || !specializations.length"
          >
            <label
                v-for="spec in specializations"
                :key="spec"
                class="flex items-center gap-2 text-slate-200"
            >
              <input
                  v-model="selectedSpecializations"
                  type="checkbox"
                  :value="spec"
                  class="accent-emerald-500"
                  :disabled="loading || !specializations.length"
              />
              <span>{{ spec }}</span>
            </label>
            <p v-if="!specializations.length" class="text-xs text-slate-500">
              Немає доступних спеціалізацій
            </p>
          </div>
        </fieldset>

        <label class="flex flex-col gap-1 text-xs text-slate-400 min-w-[180px]">
          <span>Лікар</span>
          <select
              v-model="selectedDoctorId"
              class="bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white text-sm"
              aria-label="Оберіть лікаря"
              :disabled="loading"
          >
            <option disabled value="">Оберіть лікаря</option>
            <option v-for="doc in filteredDoctors" :key="doc.id" :value="doc.id">
              {{ doc.full_name || doc.name }}
            </option>
          </select>
        </label>

        <label
            v-if="isResourceView"
            class="flex flex-col gap-1 text-xs text-slate-400 min-w-[180px]"
        >
          <span>Група ресурсів</span>
          <select
              v-model="resourceViewType"
              class="bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white text-sm"
              aria-label="Ресурсний режим"
          >
            <option value="doctor">Лікарі</option>
            <option value="room">Кабінети</option>
          </select>
        </label>

        <label
            v-if="isResourceView && resourceViewType === 'doctor'"
            class="flex flex-col gap-1 text-xs text-slate-400 min-w-[200px]"
        >
          <span>Лікарі у групі</span>
          <select
              v-model="selectedDoctorIds"
              multiple
              class="bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white text-sm"
              aria-label="Оберіть лікарів"
          >
            <option v-for="doc in filteredDoctors" :key="doc.id" :value="String(doc.id)">
              {{ doc.full_name || doc.name }}
            </option>
          </select>
        </label>

        <label
            v-if="isResourceView && resourceViewType === 'room'"
            class="flex flex-col gap-1 text-xs text-slate-400 min-w-[200px]"
        >
          <span>Кабінети у групі</span>
          <select
              v-model="selectedRoomIds"
              multiple
              class="bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white text-sm"
              aria-label="Оберіть кабінети"
          >
            <option v-for="room in rooms" :key="room.id" :value="String(room.id)">
              {{ room.name }}
            </option>
          </select>
        </label>

        <label class="flex flex-col gap-1 text-xs text-slate-400 min-w-[180px]">
          <span>Вид</span>
          <select
              v-model="viewMode"
              class="bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white text-sm"
              aria-label="Виберіть вид"
          >
            <option value="timeGridDay">День</option>
            <option value="timeGridWeek">Тиждень</option>
            <option value="dayGridMonth">Місяць</option>
            <option value="resourceTimeGridDay">Multi (день)</option>
            <option value="resourceTimeGridWeek">Multi (тиждень)</option>
          </select>
        </label>

        <div class="flex flex-col justify-end min-w-[140px]">
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
    </div>

    <div v-if="loading" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
      <div class="bg-slate-900 border border-slate-700 rounded-lg p-6 flex flex-col items-center gap-3">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-500"></div>
        <p class="text-white">Завантаження календаря...</p>
      </div>
    </div>

    <div class="grid lg:grid-cols-4 gap-4">
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
