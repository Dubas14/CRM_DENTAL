<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import CalendarSlotPicker from '../components/CalendarSlotPicker.vue';
import WaitlistCandidatesPanel from '../components/WaitlistCandidatesPanel.vue';
import WaitlistRequestForm from '../components/WaitlistRequestForm.vue';
import AppointmentCancellationCard from '../components/AppointmentCancellationCard.vue';
import calendarApi from '../services/calendarApi';
import apiClient from '../services/apiClient';
import equipmentApi from '../services/equipmentApi';
import { useAuth } from '../composables/useAuth';

const { user } = useAuth();

const doctors = ref([]);
const procedures = ref([]);
const equipments = ref([]);
const rooms = ref([]);
const appointments = ref([]);

const selectedDoctorId = ref('');
const viewMode = ref('day');
const selectedProcedureId = ref('');
const selectedEquipmentId = ref('');
const selectedRoomId = ref('');
const selectedAssistantId = ref('');
const isFollowUp = ref(false);
const waitlistEntryId = ref('');
const allowSoftConflicts = ref(false);

const selectedProcedure = computed(() => procedures.value.find((p) => p.id === Number(selectedProcedureId.value)));
const selectedDate = ref(new Date().toISOString().slice(0, 10));
const selectedSlot = ref(null);

const patientId = ref('');
const comment = ref('');
const bookingMessage = ref('');
const bookingError = ref(null);
const bookingLoading = ref(false);

const activeAppointment = ref(null);
const viewModeOptions = [
  { value: 'day', label: 'День' },
  { value: 'week', label: 'Тиждень' },
  { value: 'doctors', label: 'Лікарі' },
  { value: 'rooms', label: 'Кабінети' },
];


const clinicId = computed(() =>
  user.value?.clinic_id ||
  user.value?.doctor?.clinic_id ||
  user.value?.doctor?.clinic?.id ||
  user.value?.clinics?.[0]?.clinic_id ||
  null,
);

const activeDoctorIds = computed(() => {
  if (viewMode.value === 'doctors' && doctors.value.length) {
    // Під майбутній мульти-лікар режим: використовуватимемо вибір кількох лікарів
    return doctors.value.map((doc) => doc.id);
  }
  return selectedDoctorId.value ? [selectedDoctorId.value] : [];
});

const dateRange = computed(() => {
  if (viewMode.value === 'week') {
    // Точний діапазон тижня буде розрахований пізніше
    return { from: selectedDate.value, to: selectedDate.value };
  }

  return { from: selectedDate.value, to: selectedDate.value };
});

const groupedAppointments = computed(() => {
  if (viewMode.value === 'doctors') {
    return activeDoctorIds.value.map((doctorId) => ({
      groupKey: doctorId,
      title: `Лікар #${doctorId}`,
      items: appointments.value.filter((appt) => appt.doctor_id === Number(doctorId) || appt.doctor?.id === Number(doctorId)),
    }));
  }

  if (viewMode.value === 'rooms') {
    // Підготовка до групування за кабінетами
    return [{
      groupKey: 'room-default',
      title: 'Кабінет: не визначено',
      items: appointments.value,
    }];
  }

  return [
    {
      groupKey: 'day',
      title: viewMode.value === 'week' ? 'Діапазон дат' : 'Обраний день',
      items: appointments.value,
    },
  ];
});

const mapCollection = (data) => {
  if (Array.isArray(data)) return data;
  if (data?.data && Array.isArray(data.data)) return data.data;
  return [];
};

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

const fetchEquipments = async () => {
  if (!clinicId.value) return;
  const { data } = await equipmentApi.list({ clinic_id: clinicId.value });
  equipments.value = mapCollection(data);
};

const fetchRooms = async () => {
  if (!clinicId.value) return;
  const { data } = await apiClient.get('/rooms', { params: { clinic_id: clinicId.value } });
  rooms.value = mapCollection(data);
};

const mapAppointments = (data) => {
  if (!data) return [];
  if (Array.isArray(data)) return data;
  return data.data || [];
};

const loadAppointments = async () => {
  if (!activeDoctorIds.value.length && !clinicId.value) return;

  const params = {
    date: viewMode.value === 'day' ? selectedDate.value : undefined,
    from_date: dateRange.value.from,
    to_date: dateRange.value.to,
    doctor_ids: activeDoctorIds.value.length > 1 ? activeDoctorIds.value : undefined,
    doctor_id: activeDoctorIds.value.length === 1 ? activeDoctorIds.value[0] : undefined,
  };

  if (viewMode.value === 'day' && activeDoctorIds.value.length === 1) {
    const { data } = await calendarApi.getDoctorAppointments(activeDoctorIds.value[0], { date: selectedDate.value });
    appointments.value = data || [];
    return;
  }

  const { data } = await calendarApi.getAppointments(params);
  appointments.value = mapAppointments(data);
};

const onSlotSelected = (slot) => {
  selectedSlot.value = slot;
  bookingMessage.value = '';
};

const bookAppointment = async () => {
  if (!selectedDoctorId.value || !selectedDate.value || !selectedSlot.value) {
    bookingError.value = 'Оберіть лікаря, дату та слот.';
    return;
  }
  bookingLoading.value = true;
  bookingError.value = null;
  bookingMessage.value = '';
  try {
    const payload = {
      doctor_id: selectedDoctorId.value,
      date: selectedSlot.value.date || selectedDate.value,
      time: selectedSlot.value.start,
      patient_id: patientId.value || null,
      procedure_id: selectedProcedureId.value || null,
      equipment_id: selectedEquipmentId.value || null,
      room_id: selectedRoomId.value || null,
      assistant_id: selectedAssistantId.value || null,
      is_follow_up: !!isFollowUp.value,
      waitlist_entry_id: waitlistEntryId.value || null,
      allow_soft_conflicts: !!allowSoftConflicts.value,
      comment: comment.value || null,
    };
    const { data } = await calendarApi.createAppointment(payload);
    bookingMessage.value = `Запис створено (#${data.id})`;
    selectedSlot.value = null;
    comment.value = '';
    await loadAppointments();
  } catch (e) {
    bookingError.value = e.response?.data?.message || e.message;
  } finally {
    bookingLoading.value = false;
  }
};

const openCancellation = (appointment) => {
  activeAppointment.value = appointment;
};

onMounted(async () => {
  await Promise.all([fetchDoctors(), fetchProcedures()]);
  await Promise.all([fetchEquipments(), fetchRooms()]);
});

watch(() => [selectedDoctorId.value, selectedDate.value, viewMode.value], () => {
  loadAppointments();
});

watch(activeDoctorIds, () => {
  if (viewMode.value === 'doctors') {
    loadAppointments();
  }
});

watch(clinicId, () => {
  fetchEquipments();
  fetchRooms();
});

watch(selectedProcedure, (proc) => {
  if (proc?.equipment_id) {
    selectedEquipmentId.value = proc.equipment_id;
  }
});
</script>

<template>
  <div class="p-6 space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white">Календар: швидкі дії</h1>
        <p class="text-slate-400 text-sm">Готові Vue-компоненти з інтеграцією API для слотів, скасувань і waitlist.</p>
      </div>
      <div class="flex gap-3 flex-wrap items-center">
        <div class="flex rounded-lg overflow-hidden border border-slate-800 bg-slate-900">
          <button
              v-for="mode in viewModeOptions"
              :key="mode.value"
              class="px-3 py-2 text-sm"
              :class="viewMode === mode.value ? 'bg-emerald-700 text-white' : 'text-slate-300 hover:text-white'"
              @click="viewMode = mode.value"
          >
            {{ mode.label }}
          </button>
        </div>
        <label class="text-sm text-slate-300 space-x-2">
          <span>Дата</span>
          <input v-model="selectedDate" type="date" class="bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white" />
        </label>
        <select v-model="selectedDoctorId" class="bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white">
          <option disabled value="">Оберіть лікаря</option>
          <option v-for="doc in doctors" :key="doc.id" :value="doc.id">{{ doc.full_name || doc.name }}</option>
        </select>
        <select v-model="selectedProcedureId" class="bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white">
          <option value="">Без процедури</option>
          <option v-for="proc in procedures" :key="proc.id" :value="proc.id">{{ proc.name }}</option>
        </select>
        <select v-model="selectedEquipmentId" class="bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white">
          <option value="">Без обладнання</option>
          <option v-for="item in equipments" :key="item.id" :value="item.id">{{ item.name }}</option>
        </select>
        <select v-model="selectedRoomId" class="bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white">
          <option value="">Без кабінету</option>
          <option v-for="room in rooms" :key="room.id" :value="room.id">{{ room.name }}</option>
        </select>
        <label class="text-sm text-slate-300 space-x-2">
          <span>Асистент</span>
          <input
            v-model="selectedAssistantId"
            type="number"
            class="bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white w-28"
            placeholder="ID"
          />
        </label>
      </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-4">
        <CalendarSlotPicker
            v-if="selectedDoctorId && viewMode === 'day'"
          :doctor-id="selectedDoctorId"
          :procedure-id="selectedProcedureId"
          :equipment-id="selectedEquipmentId"
          :room-id="selectedRoomId"
          :assistant-id="selectedAssistantId"
          :date="selectedDate"
          @select-slot="onSlotSelected"
        />

        <div
            v-else
            class="bg-slate-900/60 border border-slate-800 rounded-xl p-4 text-sm text-slate-300"
        >
          Нові режими календаря ({{ viewMode }}) вимагатимуть окремих списків слотів по лікарях/кабінетах або за тижневим
          діапазоном.
        </div>

        <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-4 space-y-3">
          <div class="flex items-center justify-between">
            <p class="text-lg font-semibold text-white">Швидке бронювання</p>
            <span v-if="selectedSlot" class="text-xs text-emerald-300 bg-emerald-900/40 px-3 py-1 rounded">
              {{ selectedSlot.date || selectedDate }} • {{ selectedSlot.start }}–{{ selectedSlot.end }}
            </span>
          </div>

          <div class="grid md:grid-cols-2 gap-3">
            <label class="space-y-1">
              <span class="text-sm text-slate-300">ID пацієнта</span>
              <input v-model="patientId" type="number" class="w-full bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white" placeholder="Напр. 15" />
            </label>
            <label class="space-y-1">
              <span class="text-sm text-slate-300">Коментар</span>
              <input v-model="comment" type="text" class="w-full bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white" placeholder="Побажання пацієнта" />
            </label>
            <label class="space-y-1">
              <span class="text-sm text-slate-300">Waitlist entry ID</span>
              <input v-model="waitlistEntryId" type="number" class="w-full bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white" placeholder="Опційно" />
            </label>
            <div class="space-y-2 text-sm text-slate-300">
              <label class="flex items-center gap-2">
                <input v-model="isFollowUp" type="checkbox" class="accent-emerald-500" />
                <span>Повторний візит</span>
              </label>
              <label class="flex items-center gap-2">
                <input v-model="allowSoftConflicts" type="checkbox" class="accent-emerald-500" />
                <span>Дозволити м'які конфлікти</span>
              </label>
            </div>
          </div>

          <div class="flex items-center gap-3 text-sm">
            <button
              class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg disabled:opacity-60"
              :disabled="bookingLoading"
              @click="bookAppointment"
            >
              {{ bookingLoading ? 'Створення...' : 'Створити запис' }}
            </button>
            <span v-if="bookingMessage" class="text-emerald-300">{{ bookingMessage }}</span>
            <span v-if="bookingError" class="text-red-400">{{ bookingError }}</span>
          </div>
        </div>

        <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-4 space-y-3">
          <div class="flex items-center justify-between">
            <p class="text-lg font-semibold text-white">Записи: {{ viewMode }}</p>
            <button class="text-sm text-emerald-400" @click="loadAppointments">Оновити</button>
          </div>
          <div v-if="groupedAppointments.length" class="space-y-4">
            <div
                v-for="group in groupedAppointments"
                :key="group.groupKey"
                class="space-y-2"
            >
              <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-white">{{ group.title }}</p>
                <!-- TODO: drag-and-drop для перенесення записів між групами -->
              </div>
              <div v-if="group.items.length" class="space-y-2">
                <div v-for="appt in group.items" :key="appt.id" class="border border-slate-800 rounded-lg p-3 flex items-center justify-between">
                  <div>
                    <p class="text-white font-semibold">{{ appt.patient?.full_name || 'Пацієнт' }}</p>
                    <p class="text-xs text-slate-400">
                      {{ appt.start_at?.slice(11,16) }}–{{ appt.end_at?.slice(11,16) }}
                      • {{ appt.procedure?.name || 'без процедури' }}
                      <!-- TODO: додати іконки типів процедур -->
                      <span v-if="appt.room" class="text-sky-300">• {{ appt.room.name }}</span>
                      <span v-if="appt.assistant" class="text-indigo-300">• Асистент: {{ appt.assistant.full_name || appt.assistant.name || appt.assistant.id }}</span>
                      <span v-if="appt.equipment" class="text-amber-300">• {{ appt.equipment.name }}</span>
                    </p>
                  </div>
                  <div class="flex gap-2">
                    <span v-if="appt.is_follow_up" class="text-xs bg-emerald-900/60 px-2 py-1 rounded text-emerald-300">Повторний</span>
                    <span class="text-xs bg-slate-800 px-2 py-1 rounded text-slate-300">{{ appt.status }}</span>
                    <!-- TODO: кольорове кодування статусів записів -->
                    <button class="text-sm text-red-400 hover:text-red-300" @click="openCancellation(appt)">Скасувати</button>
                  </div>
                </div>
              </div>
              <p v-else class="text-sm text-slate-500">Немає записів у цій групі</p>
            </div>
          </div>
          <p v-else class="text-sm text-slate-500">Немає записів на цю дату</p>
        </div>

        <AppointmentCancellationCard
          v-if="activeAppointment"
          :appointment="activeAppointment"
          @cancelled="loadAppointments"
          @close="activeAppointment = null"
        />
      </div>

      <div class="space-y-4">
        <WaitlistCandidatesPanel
          v-if="clinicId"
          :clinic-id="clinicId"
          :doctor-id="selectedDoctorId"
          :procedure-id="selectedProcedureId"
          :preferred-date="selectedDate"
          @booked="loadAppointments"
        />
        <WaitlistRequestForm
          v-if="clinicId"
          :clinic-id="clinicId"
          :default-doctor-id="selectedDoctorId"
          :default-procedure-id="selectedProcedureId"
          @created="loadAppointments"
        />
      </div>
    </div>
  </div>
</template>
