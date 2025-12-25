<template>
  <div class="p-6 space-y-4">
    <!-- Заголовок та фільтри -->
    <div class="flex items-start justify-between gap-4 flex-wrap">
      <p class="text-slate-400 text-sm max-w-2xl">
        Виділяйте час — створюйте запис. Перетягуйте — переносьте. Вільні слоти підсвічуються.
      </p>

      <div class="grid w-full gap-3 sm:grid-cols-2 xl:grid-cols-6">
        <!-- Клініка -->
        <label v-if="showClinicSelector" class="flex flex-col gap-1 text-xs text-slate-400 min-w-[180px]">
          <span>Клініка</span>
          <select
              v-model="selectedClinicId"
              class="bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white text-sm"
              :disabled="loading"
          >
            <option disabled value="">Оберіть клініку</option>
            <option v-for="clinic in clinics" :key="clinic.id" :value="clinic.id">
              {{ clinic.name }}
            </option>
          </select>
        </label>

        <!-- Спеціалізації -->
        <fieldset class="flex flex-col gap-1 text-xs text-slate-400 min-w-[220px]">
          <legend class="text-xs text-slate-400">Спеціалізації</legend>
          <div class="flex flex-wrap gap-2">
            <label
                v-for="spec in specializations"
                :key="spec"
                class="flex items-center gap-2 text-xs text-slate-300"
            >
              <input v-model="selectedSpecializations" type="checkbox" :value="spec" class="accent-emerald-500" />
              <span>{{ spec }}</span>
            </label>
          </div>
        </fieldset>

        <!-- Режим (Single/Multi) -->
        <label class="flex flex-col gap-1 text-xs text-slate-400 min-w-[180px]">
          <span>Режим</span>
          <select
              v-model="uiMode"
              class="bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white text-sm"
          >
            <option value="single">Звичайний</option>
            <option value="multi">Multi</option>
          </select>
        </label>

        <!-- Ресурс у Multi -->
        <label v-if="uiMode === 'multi'" class="flex flex-col gap-1 text-xs text-slate-400 min-w-[180px]">
          <span>Ресурс у Multi</span>
          <select
              v-model="resourceViewType"
              class="bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white text-sm"
          >
            <option value="doctor">Лікарі</option>
            <option value="room">Кабінети</option>
          </select>
        </label>

        <!-- Вид (День/Тиждень/Місяць) -->
        <label class="flex flex-col gap-1 text-xs text-slate-400 min-w-[180px]">
          <span>Вид</span>
          <select
              v-model="baseView"
              class="bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white text-sm"
          >
            <option value="day">День</option>
            <option value="week">Тиждень</option>
            <option value="month">Місяць</option>
          </select>
        </label>

        <!-- Single: лікар -->
        <label
            v-if="!(uiMode === 'multi' && resourceViewType === 'doctor')"
            class="flex flex-col gap-1 text-xs text-slate-400 min-w-[180px]"
        >
          <span>Лікар</span>
          <select
              v-model="selectedDoctorId"
              class="bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white text-sm"
          >
            <option value="">Оберіть лікаря</option>
            <option v-for="doc in filteredDoctors" :key="doc.id" :value="doc.id">
              {{ doc.full_name || doc.name }}
            </option>
          </select>
        </label>

        <!-- Multi: лікарі dropdown -->
        <div
            v-if="uiMode === 'multi' && resourceViewType === 'doctor'"
            class="flex flex-col gap-1 text-xs text-slate-400 min-w-[220px] relative"
            data-dd-root
        >
          <span>Лікарі у групі</span>
          <button
              type="button"
              class="bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white text-sm text-left"
              @click="toggleDD('doctors')"
          >
            {{ ddLabel(doctorOptions, selectedDoctorIdsSafe, 'Оберіть лікарів') }}
          </button>

          <div
              v-if="openDD === 'doctors'"
              class="absolute top-full mt-2 z-50 w-full bg-slate-950 border border-slate-700 rounded-lg p-2 max-h-64 overflow-auto"
          >
            <label
                v-for="opt in doctorOptions"
                :key="opt.value"
                class="flex items-center gap-2 px-2 py-1 rounded hover:bg-slate-800 cursor-pointer text-slate-200"
            >
              <input
                  type="checkbox"
                  class="accent-emerald-500"
                  :checked="selectedDoctorIdsSafe.includes(opt.value)"
                  @change="toggleMulti(selectedDoctorIdsSafe, opt.value)"
              />
              <span class="text-sm">{{ opt.label }}</span>
            </label>
          </div>
        </div>

        <!-- Multi: кабінети dropdown -->
        <div
            v-if="uiMode === 'multi' && resourceViewType === 'room'"
            class="flex flex-col gap-1 text-xs text-slate-400 min-w-[220px] relative"
            data-dd-root
        >
          <span>Кабінети у групі</span>
          <button
              type="button"
              class="bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white text-sm text-left"
              @click="toggleDD('rooms')"
          >
            {{ ddLabel(roomOptions, selectedRoomIdsSafe, 'Оберіть кабінети') }}
          </button>

          <div
              v-if="openDD === 'rooms'"
              class="absolute top-full mt-2 z-50 w-full bg-slate-950 border border-slate-700 rounded-lg p-2 max-h-64 overflow-auto"
          >
            <label
                v-for="opt in roomOptions"
                :key="opt.value"
                class="flex items-center gap-2 px-2 py-1 rounded hover:bg-slate-800 cursor-pointer text-slate-200"
            >
              <input
                  type="checkbox"
                  class="accent-emerald-500"
                  :checked="selectedRoomIdsSafe.includes(opt.value)"
                  @change="toggleMulti(selectedRoomIdsSafe, opt.value)"
              />
              <span class="text-sm">{{ opt.label }}</span>
            </label>
          </div>
        </div>

        <!-- Кнопка оновлення -->
        <div class="flex flex-col justify-end min-w-[140px]">
          <button
              class="px-3 py-2 rounded border border-slate-700 text-slate-200 hover:text-white disabled:opacity-50"
              :disabled="loading"
              @click="refreshCalendar"
          >
            <span v-if="loading">Оновлення...</span>
            <span v-else>Оновити</span>
          </button>
        </div>
      </div>

      <div
          v-if="diagnosticsEnabled"
          class="w-full text-[11px] text-slate-400 bg-slate-950/70 border border-slate-800 rounded-lg p-2"
      >
        <p class="text-slate-300 mb-1">Діагностика (тимчасово)</p>
        <div class="grid gap-1 sm:grid-cols-2 xl:grid-cols-3">
          <span>selectedClinicId: {{ diagnosticsSnapshot.selectedClinicId || '—' }}</span>
          <span>clinicId: {{ diagnosticsSnapshot.clinicId || '—' }}</span>
          <span>selectedDoctorId: {{ diagnosticsSnapshot.selectedDoctorId || '—' }}</span>
          <span>selectedDoctorIds: {{ diagnosticsSnapshot.selectedDoctorIds.join(', ') || '—' }}</span>
          <span>filteredDoctors: {{ diagnosticsSnapshot.filteredDoctorsCount }}</span>
          <span>doctors: {{ diagnosticsSnapshot.doctorsCount }}</span>
          <span>eventResourceIds: {{ eventResourceIds.join(', ') || '—' }}</span>
          <span>displayResourceIds: {{ displayResourceIds.join(', ') || '—' }}</span>
          <span>
            missingInDisplay: {{ diagnosticsResourceMismatch.missingInDisplay.join(', ') || '—' }}
          </span>
          <span>
            extraInDisplay: {{ diagnosticsResourceMismatch.extraInDisplay.join(', ') || '—' }}
          </span>
        </div>
      </div>
    </div>

    <!-- Завантаження -->
    <div v-if="loading" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
      <div class="bg-slate-900 border border-slate-700 rounded-lg p-6 flex flex-col items-center gap-3">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-500"></div>
        <p class="text-white">Завантаження календаря...</p>
      </div>
    </div>

    <!-- Основна частина: календар + панель -->
    <div class="grid lg:grid-cols-4 gap-4">
      <!-- Календар -->
      <div class="lg:col-span-3 bg-slate-900/60 border border-slate-800 rounded-xl p-3 relative h-[75vh] overflow-hidden flex flex-col">
        <div v-if="error" class="text-sm text-red-400 bg-red-900/20 border border-red-700/40 rounded-lg p-3 mb-3">
          {{ error }}
        </div>
        <div
            v-if="doctorSelectionMessage"
            class="text-sm text-amber-300 bg-amber-900/20 border border-amber-700/40 rounded-lg p-3 mb-3"
        >
          {{ doctorSelectionMessage }}
        </div>
        <div
            v-if="roomViewMessage"
            class="text-sm text-sky-300 bg-sky-900/20 border border-sky-700/40 rounded-lg p-3 mb-3"
        >
          {{ roomViewMessage }}
        </div>

        <!-- Навігація календаря -->
        <div class="flex items-center justify-between mb-3">
          <div class="flex items-center gap-2">
            <button
                class="px-2 py-1 rounded border border-slate-700 text-slate-200 hover:text-white"
                type="button"
                @click="goPrev"
            >
              ◀
            </button>
            <button
                class="px-2 py-1 rounded border border-slate-700 text-slate-200 hover:text-white"
                type="button"
                @click="goToday"
            >
              Сьогодні
            </button>
            <button
                class="px-2 py-1 rounded border border-slate-700 text-slate-200 hover:text-white"
                type="button"
                @click="goNext"
            >
              ▶
            </button>
          </div>

          <div class="text-sm text-slate-200">
            {{ calendarTitle }}
          </div>
        </div>

        <div v-if="loadingSlots" class="absolute top-3 right-3 z-10 flex items-center gap-2">
          <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-emerald-500"></div>
          <span class="text-xs text-slate-400">Оновлення слотів...</span>
        </div>

        <!-- QCalendarScheduler -->
        <QCalendarScheduler
            ref="qcalendarRef"
            v-model:selected-date="selectedDate"
            :view="calendarView"
            :resources="displayResources"
            :events="calendarEvents"

            dark
            sticky
            bordered
            animated
            no-active-date

            :weekdays="[1,2,3,4,5,6,0]"
            :interval-minutes="intervalMinutes"
            :interval-start="intervalStart"
            :interval-count="intervalCount"
            :hour24-format="true"
            :resource-key="'id'"
            :resource-label="'title'"
            :resource-height="50"

            class="flex-1 min-h-0 w-full q-calendar-custom"
            @click:interval="onIntervalClick"
            @click:event="onEventClick"
            @event-drag-start="onEventDragStart"
            @event-drop="onEventDrop"
            @change="onCalendarChange"
        />
      </div>

      <!-- Панель контексту -->
      <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-4 space-y-3">
        <p class="text-white font-semibold">Контекст бронювання</p>

        <label class="space-y-1 block">
          <span class="text-xs text-slate-400">Процедура</span>
          <select
              v-model="selectedProcedureId"
              class="w-full bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white"
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

    <!-- Модальне вікно бронювання -->
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

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { QCalendarScheduler } from '@quasar/quasar-ui-qcalendar';
import '@quasar/quasar-ui-qcalendar/dist/index.css';

import { useAuth } from '../composables/useAuth';
import { useCalendar } from '../composables/useCalendar';
import BookingModal from './BookingModal.vue';

useAuth();

// ========== ІМПОРТ З useCalendar ==========
const {
  // Calendar data
  events,
  availabilityBgEvents,
  calendarBlocks,
  qcalendarView,
  activeRange,

  // UI state
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
  diagnosticsEnabled,
  doctorSelectionMessage,

  // Data collections
  doctors,
  filteredDoctors,
  procedures,
  rooms,
  equipments,
  assistants,
  clinics,
  specializations,
  diagnosticsSnapshot,
  missingRoomAppointmentsCount,
  NO_ROOM_RESOURCE_ID,

  // Computed properties (ВАЖЛИВО!)
  baseView, // ← ТЕПЕР ДОСТУПНО
  uiMode,   // ← ТЕПЕР ДОСТУПНО
  isResourceView,
  showClinicSelector,

  // Loading states
  loading,
  loadingSlots,
  error,

  // Booking
  booking,
  isBookingOpen,
  bookingLoading,
  bookingError,

  // Functions
  initialize,
  refreshCalendar,
  createAppointment,
  closeBooking,
  openBooking,

  handleSelect,
  handleEventClick,
  handleEventDragStart,
  handleEventDrop,
  selectAllow,

  handleDatesSet,

  // Utility functions
  formatDateYMD,
  formatTimeHM,
  toQCalendarDateTime,
  fromQCalendarDateTime,

  // Additional utilities
  syncSelectedDoctorWithClinic,
  syncSelectedRooms,
  selectedDoctorResources,
  selectedRoomResources,
} = useCalendar();

/* -----------------------------
 * Helpers
 * ----------------------------- */
// Видаляємо дубльовані функції, бо вони вже в useCalendar
// const formatDateYMD = ... // ВЖЕ Є В useCalendar
// const formatTimeHM = ... // ВЖЕ Є В useCalendar
// const toQCalendarDateTime = ... // ВЖЕ Є В useCalendar

const uniqStr = (arr) => Array.from(new Set((arr || []).filter(Boolean).map((v) => String(v))));

// QCalendar view: 'day'|'week'|'month'
const calendarView = computed(() => {
  // Конвертуємо baseView в формат QCalendar
  if (baseView.value === 'month') return 'month';
  if (baseView.value === 'day') return 'day';
  return 'week'; // за замовчуванням
});

/* -----------------------------
 * Dropdown multiselect (лікарі/кабінети)
 * ----------------------------- */
const openDD = ref(null); // 'doctors' | 'rooms' | null

const toggleDD = (name) => {
  openDD.value = openDD.value === name ? null : name;
};
const closeDD = () => {
  openDD.value = null;
};

const onWindowClick = (e) => {
  const el = e.target;
  if (!el?.closest?.('[data-dd-root]')) closeDD();
};

onMounted(() => window.addEventListener('click', onWindowClick));
onBeforeUnmount(() => window.removeEventListener('click', onWindowClick));

const selectedDoctorIdsSafe = computed({
  get: () => uniqStr(selectedDoctorIds.value),
  set: (val) => {
    selectedDoctorIds.value = uniqStr(val);
  },
});

const selectedRoomIdsSafe = computed({
  get: () => uniqStr(selectedRoomIds.value),
  set: (val) => {
    selectedRoomIds.value = uniqStr(val);
  },
});

const doctorOptions = computed(() =>
    filteredDoctors.value.map((d) => ({
      value: String(d.id),
      label: d.full_name || d.name || `#${d.id}`,
    })),
);

const roomOptions = computed(() =>
    rooms.value.map((r) => ({
      value: String(r.id),
      label: r.name || `#${r.id}`,
    })),
);

const ddLabel = (options, values, placeholder = 'Оберіть...') => {
  const map = new Map(options.map((o) => [o.value, o.label]));
  const vals = uniqStr(values);
  if (!vals.length) return placeholder;
  const first = map.get(vals[0]) || vals[0];
  if (vals.length === 1) return first;
  return `${first} +${vals.length - 1}`;
};

const toggleMulti = (model, value) => {
  const set = new Set(uniqStr(model.value));
  if (set.has(value)) set.delete(value);
  else set.add(value);
  model.value = Array.from(set);
};

// авто-вибір ресурсів, щоб multi не був пустим
watch([uiMode, resourceViewType, filteredDoctors, rooms], () => {
  if (uiMode.value !== 'multi') return;

  if (resourceViewType.value === 'doctor') {
    if (!selectedDoctorIdsSafe.value.length && filteredDoctors.value.length) {
      selectedDoctorIdsSafe.value = [String(filteredDoctors.value[0].id)];
    }
  } else {
    if (!selectedRoomIdsSafe.value.length && rooms.value.length) {
      selectedRoomIdsSafe.value = [String(rooms.value[0].id)];
    }
  }
}, { immediate: true });

// авто-вибір лікаря в single (щоб календар не був "без ресурсу")
watch([uiMode, filteredDoctors], () => {
  if (uiMode.value !== 'single') return;
  if (!selectedDoctorId.value && filteredDoctors.value.length) {
    selectedDoctorId.value = filteredDoctors.value[0].id;
  }
}, { immediate: true });

/* -----------------------------
 * Calendar core для QCalendarScheduler
 * ----------------------------- */
const qcalendarRef = ref(null);
const selectedDate = ref(formatDateYMD(new Date()));

// інтервали (щоб не було білого "поля")
const intervalMinutes = ref(30);
const dayStartTime = ref('08:00');
const dayEndTime = ref('22:30');

const toMinutes = (hhmm) => {
  const [h, m] = String(hhmm).split(':').map(Number);
  return (h || 0) * 60 + (m || 0);
};

const intervalStart = computed(() =>
    Math.floor(toMinutes(dayStartTime.value) / Number(intervalMinutes.value)),
);

const intervalCount = computed(() => {
  const start = toMinutes(dayStartTime.value);
  const end = toMinutes(dayEndTime.value);
  const diff = Math.max(0, end - start);
  return Math.ceil(diff / Number(intervalMinutes.value));
});

const requiresAssistant = computed(() => {
  if (!selectedProcedureId.value) return false;
  const p = procedures.value.find((proc) => proc.id === Number(selectedProcedureId.value));
  return !!p?.requires_assistant;
});

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

const displayResourcesRaw = computed(() => {
  if (!isResourceView.value) {
    const doctor = doctors.value.find(doc => String(doc.id) === String(selectedDoctorId.value));
    return [{
      id: selectedDoctorId.value ? String(selectedDoctorId.value) : 'schedule',
      title: doctor?.full_name || doctor?.name || 'Розклад',
      subtitle: doctor?.specialization || '',
    }];
  }

  if (resourceViewType.value === 'doctor') {
    return selectedDoctorResources.value.map(doctor => ({
      id: String(doctor.id),
      title: doctor.full_name || doctor.name || `#${doctor.id}`,
      subtitle: doctor.specialization || '',
    }));
  }

  const roomResources = selectedRoomResources.value.map(room => ({
    id: String(room.id),
    title: room.name || `#${room.id}`,
    subtitle: room.type || 'Кабінет',
  }));

  if (missingRoomAppointmentsCount.value > 0) {
    roomResources.push({
      id: NO_ROOM_RESOURCE_ID,
      title: 'Без кабінету',
      subtitle: 'Записи без room_id',
    });
  }

  return roomResources;
});

const roomViewMessage = computed(() => {
  if (!isResourceView.value || resourceViewType.value !== 'room') return '';
  if (!missingRoomAppointmentsCount.value) return '';
  return `Записи без кабінету (${missingRoomAppointmentsCount.value}) відображені у ресурсі "Без кабінету".`;
});

const displayResources = computed(() => {
  if (displayResourcesRaw.value.length) return displayResourcesRaw.value;
  return [{
    id: 'fallback',
    title: 'Ресурс не знайдено',
    subtitle: 'Оберіть лікаря або кабінет',
  }];
});

const eventResourceIds = computed(() =>
  uniqStr(events.value.map((event) => event?.resourceId)),
);

const displayResourceIds = computed(() =>
  uniqStr(displayResourcesRaw.value.map((resource) => resource?.id)),
);

const diagnosticsResourceMismatch = computed(() => {
  const missingInDisplay = eventResourceIds.value.filter(
    (id) => !displayResourceIds.value.includes(id),
  );
  const extraInDisplay = displayResourceIds.value.filter(
    (id) => !eventResourceIds.value.includes(id),
  );
  return {
    missingInDisplay,
    extraInDisplay,
  };
});

const resourceWarningMessage = computed(() => {
  if (displayResourcesRaw.value.length) return '';
  if (isResourceView.value) {
    return resourceViewType.value === 'doctor'
      ? 'Не вибрано лікарів для відображення.'
      : 'Не вибрано кабінетів для відображення.';
  }
  return 'Не вибрано лікаря для відображення.';
});

const calendarEvents = computed(() => {
  const fallbackResourceId = displayResources.value?.[0]?.id;
  const sources = [...availabilityBgEvents.value, ...calendarBlocks.value, ...events.value];
  return sources.map((event) => ({
    id: String(event.id),
    title: event.title || '',
    start: toQCalendarDateTime(event.start),
    end: toQCalendarDateTime(event.end),
    resourceId: event.resourceId
        ? String(event.resourceId)
        : (fallbackResourceId ? String(fallbackResourceId) : undefined),
    bgcolor: event.display === 'background'
        ? (event.backgroundColor || 'rgba(148, 163, 184, 0.22)')
        : undefined,
    color: event.display === 'background' ? 'transparent' : undefined,
    class: Array.isArray(event.classNames) ? event.classNames.join(' ') : undefined,
    extendedProps: event.extendedProps,
  }));
});

const onIntervalClick = async (payload) => {
  const scope = payload?.scope || payload;
  const date = scope?.timestamp?.date || scope?.date;
  const time = scope?.timestamp?.time || scope?.time;
  if (!date || !time) return;

  const start = new Date(`${date}T${time}:00`);
  const end = new Date(start.getTime() + Number(intervalMinutes.value) * 60000);
  const resourceId = scope?.resource?.id || scope?.resourceId;

  const info = {
    start,
    end,
    resource: resourceId ? { id: String(resourceId) } : undefined,
  };

  const allowed = await selectAllow(info);
  if (!allowed) {
    console.info('Цей інтервал недоступний для бронювання');
    return;
  }

  handleSelect(info);
};

const onEventClick = (payload) => {
  const event = payload?.event || payload;
  if (!event || (event.display === 'background' && event.class?.includes('free-slot'))) return;
  handleEventClick({ event });
};

const onEventDragStart = (payload) => {
  handleEventDragStart(payload);
};

const onEventDrop = (payload) => {
  handleEventDrop(payload);
};

const onCalendarChange = (event) => {
  // Обробка змін в календарі (навігація)
  if (event?.view?.start && event?.view?.end) {
    handleDatesSet({
      start: event.view.start,
      end: event.view.end,
      view: event.view,
    });
  }
};

const calendarTitle = computed(() => {
  if (!activeRange.value.start || !activeRange.value.end) return '';
  const start = new Date(activeRange.value.start);
  const end = new Date(activeRange.value.end);

  if (baseView.value === 'day') {
    return start.toLocaleDateString('uk-UA', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
      year: 'numeric'
    });
  }

  if (baseView.value === 'month') {
    return start.toLocaleDateString('uk-UA', {
      month: 'long',
      year: 'numeric'
    });
  }

  // Тиждень
  const endWeek = new Date(end);
  endWeek.setDate(endWeek.getDate() - 1);

  return `${start.toLocaleDateString('uk-UA', { day: 'numeric', month: 'short' })} - ${endWeek.toLocaleDateString('uk-UA', { day: 'numeric', month: 'short', year: 'numeric' })}`;
});

const goPrev = () => {
  const current = new Date(selectedDate.value);

  if (baseView.value === 'day') {
    current.setDate(current.getDate() - 1);
  } else if (baseView.value === 'week') {
    current.setDate(current.getDate() - 7);
  } else if (baseView.value === 'month') {
    current.setMonth(current.getMonth() - 1);
  }

  selectedDate.value = formatDateYMD(current);
};

const goNext = () => {
  const current = new Date(selectedDate.value);

  if (baseView.value === 'day') {
    current.setDate(current.getDate() + 1);
  } else if (baseView.value === 'week') {
    current.setDate(current.getDate() + 7);
  } else if (baseView.value === 'month') {
    current.setMonth(current.getMonth() + 1);
  }

  selectedDate.value = formatDateYMD(current);
};

const goToday = () => {
  selectedDate.value = formatDateYMD(new Date());
};

const onBookingSubmit = (payload) => {
  createAppointment(payload);
};

watch([selectedDate, baseView], () => {
  // При зміні дати або виду оновлюємо активний діапазон
  const current = new Date(selectedDate.value);
  if (isNaN(current.getTime())) {
    selectedDate.value = formatDateYMD(new Date());
    return;
  }

  // Оновлюємо активний діапазон
  let start, end;

  if (baseView.value === 'day') {
    start = new Date(current);
    start.setHours(0, 0, 0, 0);
    end = new Date(start);
    end.setDate(end.getDate() + 1);
  } else if (baseView.value === 'week') {
    // Початок тижня (понеділок)
    start = new Date(current);
    const day = start.getDay();
    const diff = day === 0 ? -6 : 1 - day;
    start.setDate(start.getDate() + diff);
    start.setHours(0, 0, 0, 0);

    end = new Date(start);
    end.setDate(end.getDate() + 7);
  } else { // month
    start = new Date(current.getFullYear(), current.getMonth(), 1);
    end = new Date(current.getFullYear(), current.getMonth() + 1, 1);
  }

  activeRange.value = { start, end };

  // Завантажуємо дані для нового діапазону
  handleDatesSet({
    start,
    end,
    view: { type: viewMode.value }
  });
}, { immediate: true });

onMounted(() => {
  if (!selectedDate.value) {
    selectedDate.value = formatDateYMD(new Date());
  }

  // Ініціалізація
  initialize();
});
</script>

<style scoped>
/* Додаткові стилі для адаптації */
:deep(.q-calendar-scheduler__day-header) {
  background-color: rgb(30 41 59);
  color: #f8fafc;
}

:deep(.q-calendar-scheduler__resource) {
  background-color: rgb(15 23 42);
  border-bottom: 1px solid #475569;
}

:deep(.q-calendar-scheduler__interval) {
  border-bottom: 1px solid #334155;
}

:deep(.q-calendar-scheduler__interval:hover) {
  background-color: rgba(59, 130, 246, 0.1);
}

:deep(.free-slot) {
  opacity: 0.3;
  pointer-events: none;
}

:deep(.calendar-block) {
  opacity: 0.2;
}

:deep(.status-scheduled) {
  border-left: 3px solid #3b82f6;
}

:deep(.status-confirmed) {
  border-left: 3px solid #10b981;
}

:deep(.status-cancelled) {
  border-left: 3px solid #ef4444;
  opacity: 0.6;
}
</style>
