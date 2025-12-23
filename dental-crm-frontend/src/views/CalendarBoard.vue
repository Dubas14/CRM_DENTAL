<!-- CRM_DENTAL/dental-crm-frontend/src/views/CalendarBoard.vue -->
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

    <!-- Loading overlay -->
    <div v-if="loading" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
      <div class="bg-slate-900 border border-slate-700 rounded-lg p-6 flex flex-col items-center gap-3">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-500"></div>
        <p class="text-white">Завантаження календаря...</p>
      </div>
    </div>

    <div class="grid lg:grid-cols-4 gap-4">
      <!-- Left: Calendar -->
      <div class="lg:col-span-3 bg-slate-900/60 border border-slate-800 rounded-xl p-3 relative">
        <div v-if="error" class="text-sm text-red-400 bg-red-900/20 border border-red-700/40 rounded-lg p-3 mb-3">
          {{ error }}
        </div>

        <!-- Slots loading indicator -->
        <div v-if="loadingSlots" class="absolute top-3 right-3 z-10 flex items-center gap-2">
          <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-emerald-500"></div>
          <span class="text-xs text-slate-400">Оновлення слотів...</span>
        </div>

        <FullCalendar ref="calendarRef" :options="calendarOptions" />
      </div>

      <!-- Right: Filters / Context -->
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

    <!-- Booking Modal -->
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

          <div class="grid grid-cols-2 gap-3">
            <label class="space-y-1 block">
              <span class="text-xs text-slate-400">ID пацієнта</span>
              <input
                  v-model="booking.patient_id"
                  type="number"
                  class="w-full bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white"
                  placeholder="Напр. 42"
              />
              <p class="text-xs text-slate-500">Залиште порожнім для гостя</p>
            </label>

            <label class="space-y-1 block">
              <span class="text-xs text-slate-400">Waitlist entry ID</span>
              <input
                  v-model="booking.waitlist_entry_id"
                  type="number"
                  class="w-full bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white"
                  placeholder="Напр. 12"
              />
              <p class="text-xs text-slate-500">Опційно</p>
            </label>
          </div>

          <label class="space-y-1 block">
            <span class="text-xs text-slate-400">Коментар</span>
            <textarea
                v-model="booking.comment"
                rows="3"
                class="w-full bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white"
                placeholder="Скарги, побажання, особливі вимоги..."
            ></textarea>
          </label>
        </div>

        <div class="p-4 border-t border-slate-800 flex justify-end gap-2">
          <button
              class="px-4 py-2 rounded border border-slate-700 text-slate-200 hover:text-white"
              @click="closeBooking"
          >
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

// UI state
const viewMode = ref('timeGridWeek');
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
const loadingSlots = ref(false);
const error = ref(null);

// Booking modal
const isBookingOpen = ref(false);
const bookingLoading = ref(false);
const bookingError = ref(null);

const booking = ref({
  start: null,
  end: null,
  patient_id: '',
  comment: '',
  waitlist_entry_id: '',
});

// Calendar data
const events = ref([]);
const availabilityBgEvents = ref([]);
const calendarRef = ref(null);

// Drag context
const dragContextActive = ref(false);

// Slots cache with TTL
const slotsCache = new Map();
const CACHE_TTL = 5 * 60 * 1000; // 5 minutes

const clinicId = computed(() =>
    user.value?.clinic_id ||
    user.value?.doctor?.clinic_id ||
    user.value?.doctor?.clinic?.id ||
    user.value?.clinics?.[0]?.clinic_id ||
    null
);

// Utility functions
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

const minutesDiff = (a, b) => Math.max(0, Math.round((b.getTime() - a.getTime()) / 60000));

const normalizeDateTimeForCalendar = (value) => {
  if (!value) return value;

  if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/.test(value)) {
    return value.replace(' ', 'T');
  }

  return value;
};

// Cache management
const buildSlotsKey = ({ doctorId, date, procedureId, roomId, equipmentId, durationMinutes }) => {
  return [
    doctorId || '',
    date || '',
    procedureId || '',
    roomId || '',
    equipmentId || '',
    durationMinutes || '',
  ].join('|');
};

const clearExpiredCache = () => {
  const now = Date.now();
  for (const [key, value] of slotsCache.entries()) {
    if (now - value.timestamp > CACHE_TTL) {
      slotsCache.delete(key);
    }
  }
};

const fetchDoctorSlots = async ({ doctorId, date, procedureId, roomId, equipmentId, durationMinutes }) => {
  clearExpiredCache();

  const key = buildSlotsKey({ doctorId, date, procedureId, roomId, equipmentId, durationMinutes });

  // Check cache
  const cached = slotsCache.get(key);
  if (cached && (Date.now() - cached.timestamp) < CACHE_TTL) {
    return cached.data;
  }

  // Fetch new data
  const params = { date };
  if (procedureId) params.procedure_id = procedureId;
  if (roomId) params.room_id = roomId;
  if (equipmentId) params.equipment_id = equipmentId;

  const { data } = await calendarApi.getDoctorSlots(doctorId, params);
  const slots = Array.isArray(data?.slots) ? data.slots : [];
  const set = new Set(slots.map((s) => s.start));

  const result = { slots, set, raw: data };

  // Store in cache
  slotsCache.set(key, {
    data: result,
    timestamp: Date.now()
  });

  return result;
};

// Event helpers
const buildEventTitle = (appt) => {
  const patient = appt?.patient?.full_name || 'Пацієнт';
  const proc = appt?.procedure?.name || '';
  return proc ? `${patient} • ${proc}` : patient;
};

const mapAppointmentsToEvents = (appts) => {
  return appts.map((appt) => ({
    id: String(appt.id),
    title: buildEventTitle(appt),
    start: normalizeDateTimeForCalendar(appt.start_at),
    end: normalizeDateTimeForCalendar(appt.end_at),
    extendedProps: {
      appointment: appt,
      status: appt.status,
    },
  }));
};

// Data fetching
const fetchDoctors = async () => {
  const { data } = await apiClient.get('/doctors');
  doctors.value = Array.isArray(data) ? data : (data?.data || []);
  if (!selectedDoctorId.value && doctors.value.length) {
    selectedDoctorId.value = doctors.value[0].id;
  }
};

const fetchProcedures = async () => {
  const { data } = await apiClient.get('/procedures');
  procedures.value = Array.isArray(data) ? data : (data?.data || []);
};

const fetchRooms = async () => {
  if (!clinicId.value) return;
  const { data } = await apiClient.get('/rooms', { params: { clinic_id: clinicId.value } });
  rooms.value = Array.isArray(data) ? data : (data?.data || []);
};

const fetchEquipments = async () => {
  if (!clinicId.value) return;
  const { data } = await equipmentApi.list({ clinic_id: clinicId.value });
  equipments.value = Array.isArray(data) ? data : (data?.data || []);
};

const loadAppointmentsRange = async (doctorId, fromDate, toDate) => {
  const { data } = await calendarApi.getAppointments({
    doctor_id: doctorId,
    from_date: fromDate,
    to_date: toDate,
  });

  return Array.isArray(data) ? data : (data?.data || []);
};

const loadEvents = async () => {
  if (!selectedDoctorId.value) return;

  loading.value = true;
  error.value = null;

  try {
    const api = calendarRef.value?.getApi?.();
    const view = api?.view;

    const start = view?.activeStart ? new Date(view.activeStart) : new Date();
    const end = view?.activeEnd ? new Date(view.activeEnd) : new Date(Date.now() + 7 * 86400000);

    const fromDate = formatDateYMD(start);
    const toDate = formatDateYMD(new Date(end.getTime() - 86400000));

    const appts = await loadAppointmentsRange(selectedDoctorId.value, fromDate, toDate);
    events.value = mapAppointmentsToEvents(appts);
  } catch (e) {
    error.value = e.response?.data?.message || e.message;
  } finally {
    loading.value = false;
  }
};

// Availability slots
const mergeSlotsToIntervals = (slots) => {
  const sorted = [...slots].sort((a, b) => a.start.localeCompare(b.start));
  const merged = [];

  for (const s of sorted) {
    const last = merged[merged.length - 1];
    if (!last) {
      merged.push({ start: s.start, end: s.end });
      continue;
    }
    if (last.end === s.start) {
      last.end = s.end;
    } else {
      merged.push({ start: s.start, end: s.end });
    }
  }
  return merged;
};

const getDurationForContext = () => {
  if (selectedProcedureId.value) {
    const p = procedures.value.find((x) => x.id === Number(selectedProcedureId.value));
    return p?.duration_minutes || 30;
  }
  return 30;
};

const buildAvailabilityBgForRange = async ({
                                             doctorId,
                                             startDate,
                                             endDateExclusive,
                                             procedureId,
                                             roomId,
                                             equipmentId,
                                             durationMinutes,
                                           }) => {
  if (viewMode.value === 'dayGridMonth') return [];

  const cursor = new Date(startDate);
  const bg = [];

  while (cursor < endDateExclusive) {
    const date = formatDateYMD(cursor);

    try {
      const { slots } = await fetchDoctorSlots({
        doctorId,
        date,
        procedureId,
        roomId,
        equipmentId,
        durationMinutes,
      });

      const intervals = mergeSlotsToIntervals(slots);

      for (const it of intervals) {
        bg.push({
          id: `free-${doctorId}-${date}-${it.start}`,
          start: `${date}T${it.start}:00`,
          end: `${date}T${it.end}:00`,
          display: 'background',
          overlap: true,
          backgroundColor: 'rgba(16, 185, 129, 0.22)',
          classNames: ['free-slot'],
        });
      }
    } catch {
      // ignore
    }

    cursor.setDate(cursor.getDate() + 1);
  }

  return bg;
};

const refreshAvailabilityBackground = async () => {
  if (!calendarRef.value) return;

  const api = calendarRef.value.getApi();
  const view = api.view;
  if (!view) return;

  if (viewMode.value === 'dayGridMonth') {
    availabilityBgEvents.value = [];
    return;
  }

  if (!selectedDoctorId.value) return;
  if (dragContextActive.value) return;

  loadingSlots.value = true;

  try {
    const start = new Date(view.activeStart);
    const end = new Date(view.activeEnd);
    const duration = getDurationForContext();

    availabilityBgEvents.value = await buildAvailabilityBgForRange({
      doctorId: selectedDoctorId.value,
      startDate: start,
      endDateExclusive: end,
      procedureId: selectedProcedureId.value ? Number(selectedProcedureId.value) : null,
      roomId: selectedRoomId.value ? Number(selectedRoomId.value) : null,
      equipmentId: selectedEquipmentId.value ? Number(selectedEquipmentId.value) : null,
      durationMinutes: duration,
    });
  } finally {
    loadingSlots.value = false;
  }
};

// Booking functionality
const createAppointment = async () => {
  if (!selectedDoctorId.value || !booking.value.start) return;

  bookingLoading.value = true;
  bookingError.value = null;

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

    await calendarApi.createAppointment(payload);
    isBookingOpen.value = false;

    await loadEvents();
    await refreshAvailabilityBackground();
  } catch (e) {
    bookingError.value = e.response?.data?.message || e.message;
  } finally {
    bookingLoading.value = false;
  }
};

const closeBooking = () => {
  isBookingOpen.value = false;
  bookingError.value = null;
};

// Drag & drop
const showDragAvailability = async (event) => {
  const appt = event?.extendedProps?.appointment;
  if (!appt) return;

  const api = calendarRef.value?.getApi?.();
  const view = api?.view;
  if (!view) return;
  if (viewMode.value === 'dayGridMonth') return;

  dragContextActive.value = true;
  loadingSlots.value = true;

  try {
    const start = new Date(view.activeStart);
    const end = new Date(view.activeEnd);

    const procedureId = appt?.procedure_id ?? null;
    const roomId = appt?.room_id ?? null;
    const equipmentId = appt?.equipment_id ?? null;

    const startOld = appt?.start_at ? new Date(normalizeDateTimeForCalendar(appt.start_at)) : null;
    const endOld = appt?.end_at ? new Date(normalizeDateTimeForCalendar(appt.end_at)) : null;
    const duration = (startOld && endOld) ? minutesDiff(startOld, endOld) : 30;

    availabilityBgEvents.value = await buildAvailabilityBgForRange({
      doctorId: appt?.doctor_id || selectedDoctorId.value,
      startDate: start,
      endDateExclusive: end,
      procedureId,
      roomId,
      equipmentId,
      durationMinutes: duration,
    });
  } finally {
    loadingSlots.value = false;
  }
};

const hideDragAvailability = async () => {
  dragContextActive.value = false;
  await refreshAvailabilityBackground();
};

const handleEventMoveResize = async (info, kind) => {
  const id = info.event.id;
  const appt = info.event.extendedProps?.appointment;

  try {
    const start = info.event.start;
    if (!start) throw new Error('Не вдалося визначити час початку');

    const date = formatDateYMD(start);
    const time = formatTimeHM(start);

    const procedureId = appt?.procedure_id ?? null;
    const roomId = appt?.room_id ?? null;
    const equipmentId = appt?.equipment_id ?? null;

    const startOld = appt?.start_at ? new Date(normalizeDateTimeForCalendar(appt.start_at)) : null;
    const endOld = appt?.end_at ? new Date(normalizeDateTimeForCalendar(appt.end_at)) : null;
    const duration = (startOld && endOld) ? minutesDiff(startOld, endOld) : 30;

    const slotRes = await fetchDoctorSlots({
      doctorId: appt?.doctor_id || selectedDoctorId.value,
      date,
      procedureId,
      roomId,
      equipmentId,
      durationMinutes: duration,
    });

    if (!slotRes.set.has(time)) {
      info.revert();
      alert('Цей час недоступний. Переносьте запис тільки на підсвічений час.');
      return;
    }

    const payload = {
      doctor_id: appt?.doctor_id || selectedDoctorId.value,
      date,
      time,
      patient_id: appt?.patient_id ?? null,
      procedure_id: appt?.procedure_id ?? null,
      room_id: appt?.room_id ?? null,
      equipment_id: appt?.equipment_id ?? null,
      assistant_id: appt?.assistant_id ?? null,
      is_follow_up: !!appt?.is_follow_up,
      allow_soft_conflicts: !!allowSoftConflicts.value,
    };

    await calendarApi.updateAppointment(id, payload);

    await loadEvents();
    await refreshAvailabilityBackground();
  } catch (e) {
    info.revert();
    const msg = e.response?.data?.message || e.message || `Помилка при ${kind}`;
    alert(msg);
  } finally {
    await hideDragAvailability();
  }
};

// Calendar options
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

  selectAllow: async (selectInfo) => {
    try {
      if (!selectedDoctorId.value) return false;
      if (viewMode.value === 'dayGridMonth') return true;

      const date = formatDateYMD(selectInfo.start);
      const time = formatTimeHM(selectInfo.start);
      const duration = minutesDiff(selectInfo.start, selectInfo.end) || 30;

      const slotRes = await fetchDoctorSlots({
        doctorId: selectedDoctorId.value,
        date,
        procedureId: selectedProcedureId.value ? Number(selectedProcedureId.value) : null,
        roomId: selectedRoomId.value ? Number(selectedRoomId.value) : null,
        equipmentId: selectedEquipmentId.value ? Number(selectedEquipmentId.value) : null,
        durationMinutes: duration,
      });

      return slotRes.set.has(time);
    } catch {
      return true;
    }
  },

  select: (info) => {
    booking.value.start = info.start;
    booking.value.end = info.end;
    booking.value.patient_id = '';
    booking.value.comment = '';
    booking.value.waitlist_entry_id = '';
    bookingError.value = null;
    isBookingOpen.value = true;
  },

  eventClick: (info) => {
    const appt = info.event.extendedProps?.appointment;
    const patient = appt?.patient?.full_name || 'Пацієнт';
    const proc = appt?.procedure?.name || 'без процедури';
    const room = appt?.room?.name ? `, кабінет: ${appt.room.name}` : '';
    const eq = appt?.equipment?.name ? `, обладнання: ${appt.equipment.name}` : '';
    const asst = appt?.assistant?.full_name ? `, асистент: ${appt.assistant.full_name}` : '';
    alert(`${patient}\n${proc}${room}${eq}${asst}\nСтатус: ${appt?.status || info.event.extendedProps?.status}`);
  },

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

  datesSet: async () => {
    await loadEvents();
    await refreshAvailabilityBackground();
  },
}));

// Initialize
const refreshCalendar = async () => {
  await Promise.all([loadEvents(), refreshAvailabilityBackground()]);
};

const initialize = async () => {
  try {
    loading.value = true;
    await Promise.all([fetchDoctors(), fetchProcedures()]);
    await Promise.all([fetchRooms(), fetchEquipments()]);
    await loadEvents();
    await refreshAvailabilityBackground();
  } catch (error) {
    console.error('Помилка ініціалізації:', error);
  } finally {
    loading.value = false;
  }
};

// Watchers
watch([selectedProcedureId, selectedRoomId, selectedEquipmentId], async () => {
  await refreshAvailabilityBackground();
});

watch([selectedDoctorId, viewMode], async () => {
  const api = calendarRef.value?.getApi?.();
  if (api) api.changeView(viewMode.value);
  await refreshCalendar();
});

watch(clinicId, async () => {
  await Promise.all([fetchRooms(), fetchEquipments()]);
  await refreshAvailabilityBackground();
});

watch(selectedProcedureId, () => {
  const p = procedures.value.find((x) => x.id === Number(selectedProcedureId.value));
  if (p?.equipment_id) selectedEquipmentId.value = p.equipment_id;
});

onMounted(async () => {
  await initialize();
});
</script>

<style>
/* FullCalendar dark theme fixes */
.fc {
  color: rgba(226, 232, 240, 0.95);
}

.fc-theme-standard .fc-scrollgrid,
.fc-theme-standard td,
.fc-theme-standard th {
  border-color: rgba(148, 163, 184, 0.18);
}

.fc .fc-scrollgrid-section-header > *,
.fc .fc-col-header-cell {
  background: rgba(2, 6, 23, 0.9) !important;
}

.fc .fc-timegrid-slot-label,
.fc .fc-timegrid-axis-cushion,
.fc .fc-col-header-cell-cushion,
.fc .fc-daygrid-day-number,
.fc .fc-toolbar-title {
  color: rgba(226, 232, 240, 0.92) !important;
}

.fc .fc-col-header-cell-cushion {
  font-weight: 700;
  letter-spacing: 0.2px;
}

.fc .fc-toolbar-title {
  font-size: 1.1rem;
  font-weight: 800;
}

.fc .fc-button {
  border-radius: 0.5rem;
  border-color: rgba(148, 163, 184, 0.25);
  background: rgba(2, 6, 23, 0.6);
  color: rgba(226, 232, 240, 0.95);
}

.fc .fc-button:hover {
  background: rgba(15, 23, 42, 0.9);
}

.fc .fc-button-primary:not(:disabled).fc-button-active {
  background: rgba(16, 185, 129, 0.25);
  border-color: rgba(16, 185, 129, 0.5);
}

.fc .fc-day-today {
  background: rgba(16, 185, 129, 0.08) !important;
}

.fc .fc-timegrid-body,
.fc .fc-timegrid-col-frame {
  background: rgba(2, 6, 23, 0.35);
}

.fc .fc-bg-event.free-slot {
  background-color: rgba(16, 185, 129, 0.28) !important;
}

.fc .fc-event {
  border-color: rgba(56, 189, 248, 0.35);
  box-shadow: 0 0 0 1px rgba(56, 189, 248, 0.2) inset;
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .fc .fc-toolbar {
    flex-direction: column;
    gap: 0.5rem;
  }

  .fc .fc-toolbar-title {
    font-size: 1rem;
  }

  .fc .fc-button {
    font-size: 0.875rem;
    padding: 0.375rem 0.75rem;
  }
}
</style>