<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { QCalendarScheduler } from '@quasar/quasar-ui-qcalendar';
import '@quasar/quasar-ui-qcalendar/dist/index.css';

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
  selectAllow,

  handleDatesSet,
  refreshCalendar,
} = useCalendar();

const formatDateYMD = (date) => {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
};

const formatTimeHM = (date) => {
  const h = String(date.getHours()).padStart(2, '0');
  const m = String(date.getMinutes()).padStart(2, '0');
  return `${h}:${m}`;
};

const toQCalendarDateTime = (value) => {
  if (!value) return value;
  const date = new Date(value);
  if (!Number.isNaN(date.getTime())) {
    return `${formatDateYMD(date)} ${formatTimeHM(date)}`;
  }
  if (typeof value === 'string') {
    return value.replace('T', ' ').replace('Z', '');
  }
  return value;
};

const qcalendarRef = ref(null);
const selectedDate = ref(formatDateYMD(new Date()));
const activeRange = ref({ start: null, end: null });

const calendarView = computed(() => {
  if (viewMode.value === 'dayGridMonth') return 'month';
  if (viewMode.value === 'timeGridDay' || viewMode.value === 'resourceTimeGridDay') return 'day';
  return 'week';
});

const selectedDoctorResources = computed(() =>
  doctors.value.filter((doctor) => selectedDoctorIds.value.includes(String(doctor.id))),
);

const selectedRoomResources = computed(() =>
  rooms.value.filter((room) => selectedRoomIds.value.includes(String(room.id))),
);

const fallbackResource = computed(() => {
  if (isResourceView.value) return null;
  const doctor = doctors.value.find((doc) => String(doc.id) === String(selectedDoctorId.value));
  return {
    id: selectedDoctorId.value ? String(selectedDoctorId.value) : 'schedule',
    title: doctor?.full_name || doctor?.name || 'Розклад',
  };
});

const calendarResources = computed(() => {
  if (!isResourceView.value) {
    return fallbackResource.value ? [fallbackResource.value] : [];
  }

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

const calendarEvents = computed(() => {
  const sources = [...availabilityBgEvents.value, ...calendarBlocks.value, ...events.value];
  return sources.map((event) => ({
    id: String(event.id),
    title: event.title || '',
    start: toQCalendarDateTime(event.start),
    end: toQCalendarDateTime(event.end),
    resourceId: event.resourceId
      ? String(event.resourceId)
      : (!isResourceView.value && fallbackResource.value ? fallbackResource.value.id : undefined),
    bgcolor: event.display === 'background' ? (event.backgroundColor || 'rgba(148, 163, 184, 0.22)') : undefined,
    color: event.display === 'background' ? 'transparent' : undefined,
    class: Array.isArray(event.classNames) ? event.classNames.join(' ') : undefined,
    extendedProps: event.extendedProps,
  }));
});

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

const startOfWeek = (date) => {
  const d = new Date(date);
  const day = d.getDay();
  const diff = (day === 0 ? -6 : 1) - day; // Monday start
  d.setDate(d.getDate() + diff);
  d.setHours(0, 0, 0, 0);
  return d;
};

const startOfMonth = (date) => {
  const d = new Date(date);
  d.setDate(1);
  d.setHours(0, 0, 0, 0);
  return d;
};

const addDays = (date, amount) => {
  const d = new Date(date);
  d.setDate(d.getDate() + amount);
  return d;
};

const addMonths = (date, amount) => {
  const d = new Date(date);
  d.setMonth(d.getMonth() + amount);
  return d;
};

const updateRange = () => {
  let base = selectedDate.value ? new Date(`${selectedDate.value}T00:00:00`) : new Date();
  if (Number.isNaN(base.getTime())) {
    base = new Date();
    selectedDate.value = formatDateYMD(base);
  }
  let start = base;
  let end = base;

  if (calendarView.value === 'day') {
    start = new Date(base);
    start.setHours(0, 0, 0, 0);
    end = addDays(start, 1);
  } else if (calendarView.value === 'month') {
    start = startOfMonth(base);
    end = addMonths(start, 1);
  } else {
    start = startOfWeek(base);
    end = addDays(start, 7);
  }

  activeRange.value = { start, end };

  calendarRef.value = {
    getApi: () => ({
      view: {
        type: viewMode.value,
        activeStart: start,
        activeEnd: end,
      },
    }),
  };

  handleDatesSet({
    start,
    end,
    view: { type: viewMode.value },
  });
};

const onIntervalClick = async (payload) => {
  const scope = payload?.scope || payload;
  const date = scope?.timestamp?.date || scope?.date;
  const time = scope?.timestamp?.time || scope?.time;
  if (!date || !time) return;

  const start = new Date(`${date}T${time}`);
  const end = new Date(start.getTime() + 30 * 60000);
  const resourceId = scope?.resource?.id || scope?.resourceId;

  const info = {
    start,
    end,
    resource: resourceId ? { id: String(resourceId) } : undefined,
  };

  const allowed = await selectAllow(info);
  if (!allowed) return;

  handleSelect(info);
};

const onEventClick = (payload) => {
  const event = payload?.event || payload;
  if (!event || event.display === 'background') return;
  handleEventClick({ event });
};

const calendarTitle = computed(() => {
  if (!activeRange.value.start || !activeRange.value.end) return '';
  const start = activeRange.value.start;
  const end = addDays(activeRange.value.end, -1);
  if (calendarView.value === 'day') return formatDateYMD(start);
  if (calendarView.value === 'month') return start.toLocaleDateString('uk-UA', { month: 'long', year: 'numeric' });
  return `${formatDateYMD(start)} — ${formatDateYMD(end)}`;
});

const goPrev = () => {
  const base = selectedDate.value ? new Date(`${selectedDate.value}T00:00:00`) : new Date();
  let next = base;
  if (calendarView.value === 'day') next = addDays(base, -1);
  else if (calendarView.value === 'month') next = addMonths(base, -1);
  else next = addDays(base, -7);
  selectedDate.value = formatDateYMD(next);
};

const goNext = () => {
  const base = selectedDate.value ? new Date(`${selectedDate.value}T00:00:00`) : new Date();
  let next = base;
  if (calendarView.value === 'day') next = addDays(base, 1);
  else if (calendarView.value === 'month') next = addMonths(base, 1);
  else next = addDays(base, 7);
  selectedDate.value = formatDateYMD(next);
};

const goToday = () => {
  selectedDate.value = formatDateYMD(new Date());
};

const onBookingSubmit = (payload) => {
  createAppointment(payload);
};

watch([selectedDate, viewMode], () => {
  updateRange();
}, { immediate: true });

onMounted(() => {
  if (!selectedDate.value) {
    selectedDate.value = formatDateYMD(new Date());
  }
});
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
          <div class="flex flex-wrap gap-2">
            <label
              v-for="spec in specializations"
              :key="spec"
              class="flex items-center gap-2 text-xs text-slate-300"
            >
              <input
                v-model="selectedSpecializations"
                type="checkbox"
                :value="spec"
                class="accent-emerald-500"
              />
              <span>{{ spec }}</span>
            </label>
          </div>
        </fieldset>

        <label class="flex flex-col gap-1 text-xs text-slate-400 min-w-[180px]">
          <span>Лікар</span>
          <select
            v-model="selectedDoctorId"
            class="bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white text-sm"
            aria-label="Оберіть лікаря"
          >
            <option value="">Оберіть лікаря</option>
            <option v-for="doc in filteredDoctors" :key="doc.id" :value="doc.id">
              {{ doc.full_name || doc.name }}
            </option>
          </select>
        </label>

        <label class="flex flex-col gap-1 text-xs text-slate-400 min-w-[180px]">
          <span>Multi view</span>
          <select
            v-model="resourceViewType"
            class="bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white text-sm"
            aria-label="Оберіть тип ресурсу"
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

        <QCalendarScheduler
          ref="qcalendarRef"
          v-model="selectedDate"
          :view="calendarView"
          :resources="calendarResources"
          :events="calendarEvents"
          :interval-minutes="30"
          :hour24-format="true"
          :resource-key="'id'"
          :resource-label="'title'"
          animated
          bordered
          no-active-date
          @click-interval="onIntervalClick"
          @click-event="onEventClick"
        />
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
