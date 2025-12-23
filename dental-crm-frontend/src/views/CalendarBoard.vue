<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import FullCalendar from '@fullcalendar/vue3';
import timeGridPlugin from '@fullcalendar/timegrid';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';

import apiClient from '../services/apiClient';
import calendarApi from '../services/calendarApi';
import equipmentApi from '../services/equipmentApi';
import { useAuth } from '../composables/useAuth';

const { user } = useAuth();

/**
 * UI state
 */
const viewMode = ref('timeGridWeek'); // timeGridDay | timeGridWeek | dayGridMonth
const selectedDoctorId = ref('');
const selectedProcedureId = ref('');
const selectedEquipmentId = ref('');
const selectedRoomId = ref('');
const selectedAssistantId = ref('');
const isFollowUp = ref(false);
const allowSoftConflicts = ref(false);

const doctors = ref([]);
const procedures = ref([]);
const rooms = ref([]);
const equipments = ref([]);

const loading = ref(false);
const error = ref(null);

/**
 * Booking modal
 */
const isBookingOpen = ref(false);
const bookingLoading = ref(false);
const bookingError = ref(null);
const bookingSuccess = ref(null);

const booking = ref({
  start: null, // Date
  end: null,   // Date
  patient_id: '',
  comment: '',
  waitlist_entry_id: '',
});

/**
 * Calendar data
 */
const events = ref([]);
const calendarRef = ref(null);

// Беремо clinic_id так само, як ти робив у DoctorSchedule / CalendarModule
const clinicId = computed(() =>
    user.value?.clinic_id ||
    user.value?.doctor?.clinic_id ||
    user.value?.doctor?.clinic?.id ||
    user.value?.clinics?.[0]?.clinic_id ||
    null
);

const mapCollection = (data) => {
  if (Array.isArray(data)) return data;
  if (data?.data && Array.isArray(data.data)) return data.data;
  return [];
};

const formatDateYMD = (date) => {
  // date: Date
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

const calendarOptions = computed(() => ({
  plugins: [timeGridPlugin, dayGridPlugin, interactionPlugin],
  initialView: viewMode.value,
  headerToolbar: {
    left: 'prev,next today',
    center: 'title',
    right: 'timeGridDay,timeGridWeek,dayGridMonth',
  },
  height: 'auto',
  nowIndicator: true,
  selectable: true,
  editable: true, // drag & resize
  eventResizableFromStart: false,
  slotMinTime: '07:00:00',
  slotMaxTime: '22:00:00',
  slotDuration: '00:30:00',
  allDaySlot: false,
  weekends: true,

  events: events.value,

  select: (info) => {
    // юзер виділив час (як в Google Calendar)
    booking.value.start = info.start;
    booking.value.end = info.end;
    booking.value.patient_id = '';
    booking.value.comment = '';
    booking.value.waitlist_entry_id = '';
    bookingError.value = null;
    bookingSuccess.value = null;
    isBookingOpen.value = true;
  },

  eventClick: (info) => {
    // клік по запису — покажемо details простим alert-ом (пізніше замінимо на красивий modal)
    const appt = info.event.extendedProps?.appointment;
    const patient = appt?.patient?.full_name || 'Пацієнт';
    const proc = appt?.procedure?.name || 'без процедури';
    const room = appt?.room?.name ? `, кабінет: ${appt.room.name}` : '';
    const eq = appt?.equipment?.name ? `, обладнання: ${appt.equipment.name}` : '';
    const asst = appt?.assistant?.full_name ? `, асистент: ${appt.assistant.full_name}` : '';
    window.alert(`${patient}\n${proc}${room}${eq}${asst}\nСтатус: ${appt?.status || info.event.extendedProps?.status}`);
  },

  eventDrop: async (info) => {
    // перетягнули запис (зміна часу)
    await handleEventMoveResize(info, 'drop');
  },

  eventResize: async (info) => {
    // розтягнули запис (зміна тривалості) — якщо бек не підтримує duration напряму, ми просто змінимо start і time (а end бек перерахує)
    await handleEventMoveResize(info, 'resize');
  },
}));

/**
 * Load dictionaries
 */
const fetchDoctors = async () => {
  const { data } = await apiClient.get('/doctors');
  doctors.value = mapCollection(data);
  if (!selectedDoctorId.value && doctors.value.length) {
    selectedDoctorId.value = doctors.value[0].id;
  }
};

const fetchProcedures = async () => {
  const { data } = await apiClient.get('/procedures');
  procedures.value = mapCollection(data);
};

const fetchRooms = async () => {
  if (!clinicId.value) return;
  const { data } = await apiClient.get('/rooms', { params: { clinic_id: clinicId.value } });
  rooms.value = mapCollection(data);
};

const fetchEquipments = async () => {
  if (!clinicId.value) return;
  const { data } = await equipmentApi.list({ clinic_id: clinicId.value });
  equipments.value = mapCollection(data);
};

/**
 * Load appointments -> events
 * Працює так:
 * - якщо є /appointments?from_date&to_date&doctor_id — беремо звідти
 * - якщо нема (404) — fallback: тягнемо по днях через /doctors/{id}/appointments?date=...
 */
const loadAppointmentsRange = async (doctorId, fromDate, toDate) => {
  // 1) пробуємо “нормальний” endpoint
  try {
    const { data } = await calendarApi.getAppointments({
      doctor_id: doctorId,
      from_date: fromDate,
      to_date: toDate,
    });
    return Array.isArray(data) ? data : (data?.data || []);
  } catch (e) {
    // якщо немає — fallback
    if (e?.response?.status !== 404) {
      throw e;
    }
  }

  // 2) fallback: тягнемо по днях
  const from = new Date(fromDate);
  const to = new Date(toDate);
  const days = [];
  const cursor = new Date(from);

  while (cursor <= to) {
    days.push(formatDateYMD(cursor));
    cursor.setDate(cursor.getDate() + 1);
  }

  const results = await Promise.all(
      days.map((d) => calendarApi.getDoctorAppointments(doctorId, { date: d }).then(r => r.data).catch(() => []))
  );

  return results.flat();
};

const buildEventTitle = (appt) => {
  const patient = appt?.patient?.full_name || 'Пацієнт';
  const proc = appt?.procedure?.name || '';
  return proc ? `${patient} • ${proc}` : patient;
};

const mapAppointmentsToEvents = (appts) => {
  return appts.map((appt) => ({
    id: String(appt.id),
    title: buildEventTitle(appt),
    start: appt.start_at, // ISO/string
    end: appt.end_at,
    extendedProps: {
      appointment: appt,
      status: appt.status,
    },
  }));
};

const loadEvents = async () => {
  if (!selectedDoctorId.value) return;

  loading.value = true;
  error.value = null;

  try {
    const api = calendarRef.value?.getApi?.();
    const view = api?.view;

    // Якщо календар ще не ініціалізувався — беремо “сьогодні ± 7”
    const start = view?.activeStart ? new Date(view.activeStart) : new Date();
    const end = view?.activeEnd ? new Date(view.activeEnd) : new Date(Date.now() + 7 * 86400000);

    const fromDate = formatDateYMD(start);
    const toDate = formatDateYMD(new Date(end.getTime() - 86400000)); // activeEnd зазвичай exclusive

    const appts = await loadAppointmentsRange(selectedDoctorId.value, fromDate, toDate);
    events.value = mapAppointmentsToEvents(appts);
  } catch (e) {
    error.value = e.response?.data?.message || e.message;
  } finally {
    loading.value = false;
  }
};

const handleEventMoveResize = async (info, kind) => {
  const id = info.event.id;
  const appt = info.event.extendedProps?.appointment;

  try {
    const start = info.event.start;
    if (!start) throw new Error('Не вдалося визначити час початку');

    const payload = {
      doctor_id: appt?.doctor_id || selectedDoctorId.value,
      date: formatDateYMD(start),
      time: formatTimeHM(start),

      // щоб бек не “забув” контекст
      patient_id: appt?.patient_id ?? null,
      procedure_id: appt?.procedure_id ?? null,
      room_id: appt?.room_id ?? null,
      equipment_id: appt?.equipment_id ?? null,
      assistant_id: appt?.assistant_id ?? null,
      is_follow_up: !!appt?.is_follow_up,

      // drag краще пропускати soft-конфлікти (інакше буде дратувати)
      allow_soft_conflicts: true,
    };

    await calendarApi.updateAppointment(id, payload);
    await loadEvents();
  } catch (e) {
    // повертаємо назад
    info.revert();
    const msg = e.response?.data?.message || e.message || `Помилка при ${kind}`;
    window.alert(msg);
  }
};

/**
 * Create appointment from selection (booking modal)
 */
const createAppointment = async () => {
  if (!selectedDoctorId.value || !booking.value.start) return;

  bookingLoading.value = true;
  bookingError.value = null;
  bookingSuccess.value = null;

  try {
    const start = booking.value.start;

    const payload = {
      doctor_id: selectedDoctorId.value,
      date: formatDateYMD(start),
      time: formatTimeHM(start),

      patient_id: booking.value.patient_id ? Number(booking.value.patient_id) : null,
      procedure_id: selectedProcedureId.value ? Number(selectedProcedureId.value) : null,
      room_id: selectedRoomId.value ? Number(selectedRoomId.value) : null,
      equipment_id: selectedEquipmentId.value ? Number(selectedEquipmentId.value) : null,
      assistant_id: selectedAssistantId.value ? Number(selectedAssistantId.value) : null,

      is_follow_up: !!isFollowUp.value,
      allow_soft_conflicts: !!allowSoftConflicts.value,
      waitlist_entry_id: booking.value.waitlist_entry_id ? Number(booking.value.waitlist_entry_id) : null,

      comment: booking.value.comment || null,
      source: 'crm',
    };

    const { data } = await calendarApi.createAppointment(payload);
    bookingSuccess.value = `Запис створено (#${data?.id || 'OK'})`;
    isBookingOpen.value = false;

    await loadEvents();
  } catch (e) {
    bookingError.value = e.response?.data?.message || e.message;
  } finally {
    bookingLoading.value = false;
  }
};

const closeBooking = () => {
  isBookingOpen.value = false;
};

onMounted(async () => {
  await Promise.all([fetchDoctors(), fetchProcedures()]);
  await Promise.all([fetchRooms(), fetchEquipments()]);
  await loadEvents();
});

watch([selectedDoctorId, viewMode], async () => {
  // перемикач виду або лікаря
  // важливо: спочатку змінюємо view, потім тягнемо дані
  const api = calendarRef.value?.getApi?.();
  if (api) api.changeView(viewMode.value);

  await loadEvents();
});

watch(clinicId, async () => {
  await Promise.all([fetchRooms(), fetchEquipments()]);
});

watch([selectedProcedureId], () => {
  // автопідстановка обладнання з процедури (якщо в процедурі є equipment_id)
  const p = procedures.value.find((x) => x.id === Number(selectedProcedureId.value));
  if (p?.equipment_id) selectedEquipmentId.value = p.equipment_id;
});
</script>

<template>
  <div class="p-6 space-y-4">
    <div class="flex items-start justify-between gap-4 flex-wrap">
      <div>
        <h1 class="text-2xl font-bold text-white">Календар записів (як Google Calendar)</h1>
        <p class="text-slate-400 text-sm">
          Виділяй час — створюєш запис. Перетягнув — переніс.
        </p>
      </div>

      <div class="flex gap-2 flex-wrap items-center">
        <select v-model="selectedDoctorId" class="bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white">
          <option disabled value="">Оберіть лікаря</option>
          <option v-for="doc in doctors" :key="doc.id" :value="doc.id">
            {{ doc.full_name || doc.name }}
          </option>
        </select>

        <select v-model="viewMode" class="bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white">
          <option value="timeGridDay">День</option>
          <option value="timeGridWeek">Тиждень</option>
          <option value="dayGridMonth">Місяць</option>
        </select>

        <button
            class="px-3 py-2 rounded border border-slate-700 text-slate-200 hover:text-white"
            :disabled="loading"
            @click="loadEvents"
        >
          {{ loading ? 'Оновлення...' : 'Оновити' }}
        </button>
      </div>
    </div>

    <div class="grid lg:grid-cols-4 gap-4">
      <!-- Left: Calendar -->
      <div class="lg:col-span-3 bg-slate-900/60 border border-slate-800 rounded-xl p-3">
        <div v-if="error" class="text-sm text-red-400 bg-red-900/20 border border-red-700/40 rounded-lg p-3 mb-3">
          {{ error }}
        </div>

        <FullCalendar ref="calendarRef" :options="calendarOptions" />
      </div>

      <!-- Right: Filters / Context -->
      <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-4 space-y-3">
        <p class="text-white font-semibold">Контекст бронювання</p>

        <label class="space-y-1 block">
          <span class="text-xs text-slate-400">Процедура</span>
          <select v-model="selectedProcedureId" class="w-full bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white">
            <option value="">Без процедури</option>
            <option v-for="p in procedures" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </label>

        <label class="space-y-1 block">
          <span class="text-xs text-slate-400">Кабінет</span>
          <select v-model="selectedRoomId" class="w-full bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white">
            <option value="">Будь-який</option>
            <option v-for="r in rooms" :key="r.id" :value="r.id">{{ r.name }}</option>
          </select>
        </label>

        <label class="space-y-1 block">
          <span class="text-xs text-slate-400">Обладнання</span>
          <select v-model="selectedEquipmentId" class="w-full bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white">
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
          />
        </label>

        <div class="space-y-2 text-sm text-slate-300">
          <label class="flex items-center gap-2">
            <input v-model="isFollowUp" type="checkbox" class="accent-emerald-500" />
            <span>Повторний візит</span>
          </label>

          <label class="flex items-center gap-2">
            <input v-model="allowSoftConflicts" type="checkbox" class="accent-emerald-500" />
            <span>Дозволити soft</span>
          </label>
        </div>

        <div class="text-xs text-slate-500 pt-2 border-t border-slate-800">
          Підказка: виділи час на календарі — зʼявиться модалка створення запису.
        </div>
      </div>
    </div>

    <!-- Booking modal -->
    <div v-if="isBookingOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
      <div class="w-full max-w-lg bg-slate-900 border border-slate-800 rounded-xl shadow-2xl overflow-hidden">
        <div class="p-4 bg-slate-950 border-b border-slate-800 flex items-center justify-between">
          <div>
            <p class="text-white font-semibold">Створити запис</p>
            <p class="text-xs text-slate-400">
              {{ booking.start ? booking.start.toLocaleString() : '' }}
            </p>
          </div>
          <button class="text-slate-400 hover:text-white text-xl" @click="closeBooking">×</button>
        </div>

        <div class="p-4 space-y-3">
          <div v-if="bookingError" class="text-sm text-red-400 bg-red-900/20 border border-red-700/40 rounded-lg p-3">
            {{ bookingError }}
          </div>
          <div v-if="bookingSuccess" class="text-sm text-emerald-300 bg-emerald-900/20 border border-emerald-700/40 rounded-lg p-3">
            {{ bookingSuccess }}
          </div>

          <label class="space-y-1 block">
            <span class="text-xs text-slate-400">ID пацієнта (можна пусто — гість)</span>
            <input v-model="booking.patient_id" type="number" class="w-full bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white" placeholder="Напр. 42" />
          </label>

          <label class="space-y-1 block">
            <span class="text-xs text-slate-400">Waitlist entry ID (опційно)</span>
            <input v-model="booking.waitlist_entry_id" type="number" class="w-full bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white" placeholder="Напр. 12" />
          </label>

          <label class="space-y-1 block">
            <span class="text-xs text-slate-400">Коментар</span>
            <textarea v-model="booking.comment" rows="3" class="w-full bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white" placeholder="Скарги, побажання..."></textarea>
          </label>
        </div>

        <div class="p-4 border-t border-slate-800 flex justify-end gap-2">
          <button class="px-4 py-2 rounded border border-slate-700 text-slate-200 hover:text-white" @click="closeBooking">
            Скасувати
          </button>
          <button
              class="px-4 py-2 rounded bg-emerald-600 hover:bg-emerald-500 text-white disabled:opacity-60"
              :disabled="bookingLoading"
              @click="createAppointment"
          >
            {{ bookingLoading ? 'Створення...' : 'Створити' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style>
/* FullCalendar базові “косметичні” — без фанатизму */
.fc {
  color: white;
}
.fc .fc-toolbar-title {
  font-size: 1.1rem;
}
.fc .fc-button {
  border-radius: 0.5rem;
}
.fc .fc-timegrid-slot-label,
.fc .fc-col-header-cell-cushion,
.fc .fc-daygrid-day-number {
  color: rgba(226, 232, 240, 0.9); /* slate-ish */
}
</style>
