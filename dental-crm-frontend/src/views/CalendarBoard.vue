<template>
  <div class="min-h-screen bg-bg">
    <div class="p-6 pb-2">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-text mb-2">Календар записів</h1>
          <p class="text-text/70 text-sm">
            Управління розкладом лікарів, бронювання та перегляд записів
          </p>
        </div>

        <div v-if="clinics.length > 1 || !user?.clinic_id" class="w-64">
          <label class="text-xs text-text/60 uppercase font-bold block mb-1">Оберіть клініку</label>
          <select
              v-model="selectedClinicId"
              @change="handleClinicChange"
              class="w-full bg-card border border-border/80 rounded px-3 py-2 text-text text-sm"
          >
            <option :value="null" disabled>-- Оберіть клініку --</option>
            <option v-for="clinic in clinics" :key="clinic.id" :value="clinic.id">
              {{ clinic.name }}
            </option>
          </select>
        </div>
      </div>
    </div>

    <div class="px-6 flex flex-wrap items-center justify-between gap-4 mb-4">
      <CalendarHeader
          :current-date="currentDate"
          @prev="prev"
          @next="next"
          @today="today"
          @select-date="selectMonth"
      />

      <div class="flex items-center gap-2">
        <button
            @click="fetchEvents"
            class="text-sm px-3 py-1 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded hover:bg-emerald-500/20"
        >
          🔄 Оновити
        </button>
        <select
            v-model="view"
            @change="changeView"
            class="bg-card border border-border/80 text-text/90 rounded-md px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
        >
          <option value="day">День</option>
          <option value="week">Тиждень</option>
          <option value="month">Місяць</option>
        </select>
      </div>
    </div>

    <div class="px-6 pb-6 h-[calc(100vh-160px)] overflow-hidden bg-card/30 rounded-xl mx-6 border border-border/40">
      <div v-if="!currentClinicId" class="flex h-full items-center justify-center text-text/60">
        ⬅ Будь ласка, оберіть клініку зі списку зверху
      </div>
      <ToastCalendar
          v-else
          ref="calendarRef"
          @select-date-time="handleSelectDateTime"
          @click-event="handleClickEvent"
          @before-update-event="handleBeforeUpdateEvent"
      />
    </div>

    <EventModal
        :open="isEventModalOpen"
        :event="activeEvent"
        @save="handleSaveEvent"
        @close="handleCloseModal"
    />

    <AppointmentModal
        :is-open="isAppointmentModalOpen"
        :appointment="selectedAppointment"
        :clinic-id="currentClinicId"
        @close="handleCloseAppointmentModal"
        @saved="handleAppointmentSaved"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, nextTick, ref, watch } from 'vue'
import CalendarHeader from '../components/CalendarHeader.vue'
import ToastCalendar from '../components/ToastCalendar.vue'
import EventModal from '../components/EventModal.vue'
import AppointmentModal from '../components/AppointmentModal.vue'
import calendarApi from '../services/calendarApi'
import clinicApi from '../services/clinicApi'
import { useToast } from '../composables/useToast'
import { useAuth } from '../composables/useAuth'

// --- STATE ---
const calendarRef = ref(null)
const view = ref('week')
const currentDate = ref(new Date())
const isEventModalOpen = ref(false)
const activeEvent = ref({})
const isAppointmentModalOpen = ref(false)
const selectedAppointment = ref(null)

const events = ref([])
const clinics = ref([])
const selectedClinicId = ref(null)

const { error: toastError, success: toastSuccess } = useToast()
const { user, initAuth } = useAuth()

// --- COMPUTED ---
const currentClinicId = computed(() => {
  if (selectedClinicId.value) return selectedClinicId.value
  return user.value?.clinic_id || user.value?.doctor?.clinic_id || null
})

const defaultDoctorId = computed(() => user.value?.doctor_id || user.value?.doctor?.id || null)

// --- HELPERS ---
const toDate = (value) => {
  if (!value) return null
  if (value.toDate) return value.toDate()
  const date = value instanceof Date ? value : new Date(value)
  return Number.isNaN(date.getTime()) ? null : date
}

const formatDateOnly = (date) => {
  const normalized = toDate(date)
  if (!normalized) return ''
  const year = normalized.getFullYear()
  const month = `${normalized.getMonth() + 1}`.padStart(2, '0')
  const day = `${normalized.getDate()}`.padStart(2, '0')
  return `${year}-${month}-${day}`
}

const formatDateTime = (date) => {
  const normalized = toDate(date)
  if (!normalized) return ''
  const year = normalized.getFullYear()
  const month = `${normalized.getMonth() + 1}`.padStart(2, '0')
  const day = `${normalized.getDate()}`.padStart(2, '0')
  const hour = `${normalized.getHours()}`.padStart(2, '0')
  const minute = `${normalized.getMinutes()}`.padStart(2, '0')
  return `${year}-${month}-${day} ${hour}:${minute}:00`
}

// --- API ACTIONS ---

// 1. Завантаження клінік
const loadClinics = async () => {
  try {
    if (user.value?.global_role === 'super_admin') {
      const { data } = await clinicApi.list();
      clinics.value = Array.isArray(data?.data) ? data.data : (Array.isArray(data) ? data : []);
    } else {
      const { data } = await clinicApi.listMine();
      clinics.value = (data.clinics || []).map(c => ({ id: c.clinic_id, name: c.clinic_name }));
    }

    if (!currentClinicId.value && clinics.value.length > 0) {
      selectedClinicId.value = clinics.value[0].id
    }
  } catch (e) {
    console.error('Failed to load clinics', e)
  }
}

// 2. Завантаження подій
const fetchEvents = async () => {
  if (!currentClinicId.value) return;

  const start = calendarRef.value?.getDateRangeStart?.()
  const end = calendarRef.value?.getDateRangeEnd?.()

  if (!start || !end) return;

  const from = formatDateOnly(start)
  const to = formatDateOnly(end)

  try {
    const [blocksResponse, appointmentsResponse] = await Promise.all([
      calendarApi.getCalendarBlocks({ clinic_id: currentClinicId.value, from, to }),
      calendarApi.getAppointments({ clinic_id: currentClinicId.value, from_date: from, to_date: to })
    ]);

    // Обробка Блоків
    const blocksData = blocksResponse.data?.data || blocksResponse.data || [];
    const mappedBlocks = blocksData.map(event => {
      const s = toDate(event.start_at);
      const e = toDate(event.end_at);
      if (!s || !e) return null;
      return {
        id: String(event.id),
        calendarId: 'main',
        title: event.note || 'Блок',
        category: 'time',
        start: s,
        end: e,
        isReadOnly: false,
        backgroundColor: '#6b7280',
        color: '#fff',
        raw: event
      }
    }).filter(Boolean);

    // Обробка Записів
    const appointmentsData = appointmentsResponse.data?.data || appointmentsResponse.data || [];
    const mappedAppointments = appointmentsData.map(appt => {
      const s = toDate(appt.start_at);
      const e = toDate(appt.end_at);
      if (!s || !e) return null;
      const isDone = appt.status === 'done';
      return {
        id: String(appt.id),
        calendarId: 'main',
        title: appt.patient?.full_name || 'Запис',
        category: 'time',
        start: s,
        end: e,
        isReadOnly: isDone,
        backgroundColor: isDone ? '#dbeafe' : '#10b981',
        borderColor: isDone ? '#93c5fd' : '#059669',
        color: isDone ? '#1e3a8a' : '#fff',
        raw: appt
      }
    }).filter(Boolean);

    events.value = [...mappedBlocks, ...mappedAppointments];

    calendarRef.value?.clear?.();
    if (events.value.length) {
      calendarRef.value?.createEvents?.(events.value);
    }

  } catch (error) {
    console.error(error);
    toastError('Помилка завантаження даних');
  }
}

// 3. Збереження події
const saveEvent = async (payload) => {
  if (!currentClinicId.value) {
    toastError('Оберіть клініку!');
    return;
  }

  const payloadToSave = { ...payload };
  if (!payloadToSave.doctor_id && defaultDoctorId.value) {
    payloadToSave.doctor_id = defaultDoctorId.value;
  }

  if (!payloadToSave.doctor_id && !payloadToSave.room_id && !payloadToSave.equipment_id) {
    toastError('Помилка: Потрібно обрати лікаря або кабінет у формі.');
    return;
  }

  const isEdit = payload.id && !String(payload.id).startsWith('new-');

  const apiPayload = {
    clinic_id: currentClinicId.value,
    type: payload.type || 'break',
    start_at: formatDateTime(payload.start),
    end_at: formatDateTime(payload.end),
    note: payload.note ?? payload.title ?? '',
    doctor_id: payloadToSave.doctor_id
  };

  try {
    if (isEdit) {
      await calendarApi.updateCalendarBlock(payload.id, apiPayload)
      toastSuccess('Блок оновлено')
    } else {
      await calendarApi.createCalendarBlock(apiPayload)
      toastSuccess('Блок створено')
    }
    fetchEvents();
    handleCloseModal();
  } catch (e) {
    console.error(e);
    toastError('Не вдалося зберегти: ' + (e.response?.data?.message || 'Помилка'));
  }
}

// --- EVENT HANDLERS ---
const handleSaveEvent = (payload) => {
  saveEvent(payload)
}

const handleClinicChange = () => {
  nextTick(() => fetchEvents())
}

const updateCurrentDate = () => {
  const date = calendarRef.value?.getDate?.()
  if (date) currentDate.value = new Date(date)
}

const next = () => { calendarRef.value?.next(); updateCurrentDate(); fetchEvents(); }
const prev = () => { calendarRef.value?.prev(); updateCurrentDate(); fetchEvents(); }
const today = () => { calendarRef.value?.today(); updateCurrentDate(); fetchEvents(); }

const changeView = () => {
  calendarRef.value?.changeView(view.value)
  updateCurrentDate()
  fetchEvents()
}

const selectMonth = (date) => {
  if (!date) return
  calendarRef.value?.setDate?.(date)
  updateCurrentDate()
  fetchEvents()
}

const createDefaultEvent = ({ start, end, event }) => {
  const s = toDate(start) ?? new Date()
  const e = toDate(end) ?? new Date(s.getTime() + 30 * 60000)
  return {
    id: event?.id || `new-${Date.now()}`,
    calendarId: 'main',
    title: event?.title || '',
    start: s,
    end: e,
    doctor_id: event?.doctor_id || defaultDoctorId.value,
    type: event?.type || 'break',
    note: event?.note || '',
  }
}

const openEventModal = (data) => {
  activeEvent.value = data
  isEventModalOpen.value = true
}

const handleCloseModal = () => {
  isEventModalOpen.value = false
  activeEvent.value = {}
}

const handleCloseAppointmentModal = () => {
  isAppointmentModalOpen.value = false
  selectedAppointment.value = null
}

const handleAppointmentSaved = () => {
  fetchEvents()
}

const handleSelectDateTime = (info) => {
  const start = toDate(info?.start)
  const end = toDate(info?.end)
  openEventModal(createDefaultEvent({ start, end }))
}

const handleClickEvent = (info) => {
  const event = info?.event
  if (!event) return

  if (event.raw && Object.prototype.hasOwnProperty.call(event.raw, 'patient_id')) {
    selectedAppointment.value = event.raw
    isAppointmentModalOpen.value = true
    return;
  }
  openEventModal(createDefaultEvent({ event, start: event.start, end: event.end }))
}

const handleBeforeUpdateEvent = async (info) => {
  const event = info?.event
  if (!event) return

  const nextStart = toDate(info?.changes?.start ?? event.start)
  const nextEnd = toDate(info?.changes?.end ?? event.end)

  if (!nextStart || !nextEnd) {
    toastError('Не вдалося оновити час запису');
    return
  }

  if (event.raw && event.raw.patient_id) {
    if (event.raw.status === 'done') {
      toastError('Завершений запис не можна переносити');
      return
    }

    try {
      await calendarApi.updateAppointment(event.id, {
        start_at: formatDateTime(nextStart),
        end_at: formatDateTime(nextEnd),
      })
      toastSuccess('Запис перенесено')
      fetchEvents()
    } catch (error) {
      console.error(error)
      toastError('Не вдалося перенести запис')
    }
    return
  }

  openEventModal(createDefaultEvent({ event, start: nextStart, end: nextEnd }))
}

// --- LIFECYCLE ---
onMounted(async () => {
  await initAuth()
  await loadClinics()

  await nextTick()
  setTimeout(() => {
    updateCurrentDate()
    fetchEvents()
  }, 100)
})

watch(view, () => {
  handleCloseModal()
  handleCloseAppointmentModal()
})
watch(currentClinicId, () => {
  fetchEvents()
})
</script>
