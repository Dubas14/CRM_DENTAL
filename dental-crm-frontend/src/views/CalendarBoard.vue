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
const availabilityBgEvents = ref([]); // background подсвітка “вільно”
const calendarRef = ref(null);

/**
 * Slots cache (щоб не лупити API 100 разів)
 * key: doctor|date|proc|room|equip|duration
 */
const slotsCache = new Map();

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

const fetchDoctorSlots = async ({ doctorId, date, procedureId, roomId, equipmentId, durationMinutes }) => {
  const key = buildSlotsKey({ doctorId, date, procedureId, roomId, equipmentId, durationMinutes });
  if (slotsCache.has(key)) return slotsCache.get(key);

  const params = { date };
  if (procedureId) params.procedure_id = procedureId;
  if (roomId) params.room_id = roomId;
  if (equipmentId) params.equipment_id = equipmentId;

  const { data } = await calendarApi.getDoctorSlots(doctorId, params);
  // бек повертає: { date, slots: [{start,end}], reason, duration_minutes }
  const slots = Array.isArray(data?.slots) ? data.slots : [];
  const set = new Set(slots.map((s) => s.start)); // HH:MM
  const result = { slots, set, raw: data };

  slotsCache.set(key, result);
  return result;
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
    start: appt.start_at,
    end: appt.end_at,
    extendedProps: {
      appointment: appt,
      status: appt.status,
    },
  }));
};

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
 * Load appointments range
 */
const loadAppointmentsRange = async (doctorId, fromDate, toDate) => {
  // пробуємо /appointments?doctor_id&from_date&to_date
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
    const toDate = formatDateYMD(new Date(end.getTime() - 86400000)); // activeEnd exclusive

    const appts = await loadAppointmentsRange(selectedDoctorId.value, fromDate, toDate);
    events.value = mapAppointmentsToEvents(appts);
  } catch (e) {
    error.value = e.response?.data?.message || e.message;
  } finally {
    loading.value = false;
  }
};

/**
 * Availability background (показати “вільні” комірки)
 * Для спрощення: показуємо доступність за поточним контекстом праворуч:
 * procedure/room/equipment (для створення запису).
 */
const refreshAvailabilityBackground = async () => {
  const api = calendarRef.value?.getApi?.();
  const view = api?.view;
  if (!view) return;

  // У month view не малюємо (забагато)
  if (viewMode.value === 'dayGridMonth') {
    availabilityBgEvents.value = [];
    return;
  }

  if (!selectedDoctorId.value) return;

  const start = new Date(view.activeStart);
  const end = new Date(view.activeEnd); // exclusive
  const cursor = new Date(start);

  // беремо “тривалість” по процедурі (якщо є) — інакше 30
  const duration = selectedProcedureId.value
      ? (procedures.value.find(p => p.id === Number(selectedProcedureId.value))?.duration_minutes || 30)
      : 30;

  const bg = [];
  while (cursor < end) {
    const date = formatDateYMD(cursor);

    try {
      const { slots } = await fetchDoctorSlots({
        doctorId: selectedDoctorId.value,
        date,
        procedureId: selectedProcedureId.value ? Number(selectedProcedureId.value) : null,
        roomId: selectedRoomId.value ? Number(selectedRoomId.value) : null,
        equipmentId: selectedEquipmentId.value ? Number(selectedEquipmentId.value) : null,
        durationMinutes: duration,
      });

      // кожен слот -> background event
      for (const s of slots) {
        bg.push({
          id: `free-${date}-${s.start}`,
          start: `${date}T${s.start}:00`,
          end: `${date}T${s.end}:00`,
          display: 'background',
          classNames: ['fc-free-slot'],
        });
      }
    } catch {
      // мовчки
    }

    cursor.setDate(cursor.getDate() + 1);
  }

  availabilityBgEvents.value = bg;
};

/**
 * Create appointment from selection
 */
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
  } catch (e) {
    bookingError.value = e.response?.data?.message || e.message;
  } finally {
    bookingLoading.value = false;
  }
};

const closeBooking = () => {
  isBookingOpen.value = false;
};

/**
 * Перетягування/resize з превалідацією “вільно/не вільно” через /slots
 */
const handleEventMoveResize = async (info, kind) => {
  const id = info.event.id;
  const appt = info.event.extendedProps?.appointment;

  try {
    const start = info.event.start;
    if (!start) throw new Error('Не вдалося визначити час початку');

    const date = formatDateYMD(start);
    const time = formatTimeHM(start);

    // контекст беремо з самого запису (НЕ з правої панелі)
    const procedureId = appt?.procedure_id ?? null;
    const roomId = appt?.room_id ?? null;
    const equipmentId = appt?.equipment_id ?? null;

    // duration для превалідації
    const startOld = appt?.start_at ? new Date(appt.start_at) : null;
    const endOld = appt?.end_at ? new Date(appt.end_at) : null;
    const duration = (startOld && endOld) ? minutesDiff(startOld, endOld) : 30;

    // 1) превалідація — чи старт взагалі “доступний” для цього контексту
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
      window.alert('Цей час недоступний (зайнято / поза графіком / конфлікт по кабінету чи обладнанню). Обери підсвічений вільний слот.');
      return;
    }

    // 2) оновлення
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

      allow_soft_conflicts: true,
    };

    await calendarApi.updateAppointment(id, payload);
    await loadEvents();
  } catch (e) {
    info.revert();
    const msg = e.response?.data?.message || e.message || `Помилка при ${kind}`;
    window.alert(msg);
  }
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
  editable: true,
  eventResizableFromStart: false,
  slotMinTime: '07:00:00',
  slotMaxTime: '22:00:00',
  slotDuration: '00:30:00',
  allDaySlot: false,
  weekends: true,

  // показуємо і записи, і “вільні” зони
  events: [...availabilityBgEvents.value, ...events.value],

  selectAllow: async (selectInfo) => {
    // для створення — дозволяємо тільки якщо старт є у доступних слотах (по правому контексту)
    try {
      if (!selectedDoctorId.value) return false;
      if (viewMode.value === 'dayGridMonth') return true; // в місяці простіше не блокувати

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
    window.alert(`${patient}\n${proc}${room}${eq}${asst}\nСтатус: ${appt?.status || info.event.extendedProps?.status}`);
  },

  eventDrop: async (info) => {
    await handleEventMoveResize(info, 'drop');
  },

  eventResize: async (info) => {
    await handleEventMoveResize(info, 'resize');
  },

  datesSet: async () => {
    // коли юзер перелистує тиждень/день — оновлюємо івенти + підсвітку
    await loadEvents();
    await refreshAvailabilityBackground();
  },
}));

onMounted(async () => {
  await Promise.all([fetchDoctors(), fetchProcedures()]);
  await Promise.all([fetchRooms(), fetchEquipments()]);
  await loadEvents();
  await refreshAvailabilityBackground();
});

watch([selectedDoctorId, viewMode], async () => {
  const api = calendarRef.value?.getApi?.();
  if (api) api.changeView(viewMode.value);

  await loadEvents();
  await refreshAvailabilityBackground();
});

watch(clinicId, async () => {
  await Promise.all([fetchRooms(), fetchEquipments()]);
  await refreshAvailabilityBackground();
});

watch([selectedProcedureId, selectedRoomId, selectedEquipmentId], async () => {
  // змінився контекст — перемалюємо “вільні” слоти
  await refreshAvailabilityBackground();
});

watch([selectedProcedureId], () => {
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
          Виділяй час — створюєш запис. Перетягнув — переніс. Вільні слоти підсвічуються.
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
            @click="() => { loadEvents(); refreshAvailabilityBackground(); }"
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
          Підказка: “вільні” слоти підсвічуються. Перенось запис тільки на підсвічений час.
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
/* ===== FullCalendar dark theme fixes ===== */
.fc {
  color: rgba(226, 232, 240, 0.95);
}

/* grid background */
.fc-theme-standard .fc-scrollgrid,
.fc-theme-standard td,
.fc-theme-standard th {
  border-color: rgba(148, 163, 184, 0.18);
}

.fc .fc-scrollgrid-section-header > *,
.fc .fc-col-header-cell {
  background: rgba(15, 23, 42, 0.9);
}

.fc .fc-timegrid-slot-label,
.fc .fc-col-header-cell-cushion,
.fc .fc-daygrid-day-number,
.fc .fc-toolbar-title {
  color: rgba(226, 232, 240, 0.92) !important;
}

/* title */
.fc .fc-toolbar-title {
  font-size: 1.1rem;
  font-weight: 700;
}

/* buttons */
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

/* today highlight */
.fc .fc-day-today {
  background: rgba(16, 185, 129, 0.08) !important;
}

/* timegrid background */
.fc .fc-timegrid-body,
.fc .fc-timegrid-col-frame {
  background: rgba(2, 6, 23, 0.35);
}

/* “free slots” background */
.fc-free-slot {
  background: rgba(16, 185, 129, 0.12) !important;
}

/* event chip */
.fc .fc-event {
  border-color: rgba(56, 189, 248, 0.35);
}
</style>
