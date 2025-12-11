<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import CalendarSlotPicker from '../components/CalendarSlotPicker.vue';
import WaitlistCandidatesPanel from '../components/WaitlistCandidatesPanel.vue';
import WaitlistRequestForm from '../components/WaitlistRequestForm.vue';
import AppointmentCancellationCard from '../components/AppointmentCancellationCard.vue';
import calendarApi from '../services/calendarApi';
import apiClient from '../services/apiClient';
import { useAuth } from '../composables/useAuth';

const { user } = useAuth();

const doctors = ref([]);
const procedures = ref([]);
const appointments = ref([]);

const selectedDoctorId = ref('');
const selectedProcedureId = ref('');
const selectedDate = ref(new Date().toISOString().slice(0, 10));
const selectedSlot = ref(null);

const patientId = ref('');
const comment = ref('');
const bookingMessage = ref('');
const bookingError = ref(null);
const bookingLoading = ref(false);

const activeAppointment = ref(null);

const clinicId = computed(() =>
  user.value?.clinic_id ||
  user.value?.doctor?.clinic_id ||
  user.value?.doctor?.clinic?.id ||
  user.value?.clinics?.[0]?.clinic_id ||
  null,
);

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

const loadAppointments = async () => {
  if (!selectedDoctorId.value) return;
  const { data } = await calendarApi.getDoctorAppointments(selectedDoctorId.value, { date: selectedDate.value });
  appointments.value = data || [];
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
});

watch(() => [selectedDoctorId.value, selectedDate.value], () => {
  loadAppointments();
});
</script>

<template>
  <div class="p-6 space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white">Календар: швидкі дії</h1>
        <p class="text-slate-400 text-sm">Готові Vue-компоненти з інтеграцією API для слотів, скасувань і waitlist.</p>
      </div>
      <div class="flex gap-3 flex-wrap">
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
      </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-4">
        <CalendarSlotPicker
          v-if="selectedDoctorId"
          :doctor-id="selectedDoctorId"
          :procedure-id="selectedProcedureId"
          :date="selectedDate"
          @select-slot="onSlotSelected"
        />

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
            <p class="text-lg font-semibold text-white">Записи на день</p>
            <button class="text-sm text-emerald-400" @click="loadAppointments">Оновити</button>
          </div>
          <div v-if="appointments.length" class="space-y-2">
            <div v-for="appt in appointments" :key="appt.id" class="border border-slate-800 rounded-lg p-3 flex items-center justify-between">
              <div>
                <p class="text-white font-semibold">{{ appt.patient?.full_name || 'Пацієнт' }}</p>
                <p class="text-xs text-slate-400">
                  {{ appt.start_at?.slice(11,16) }}–{{ appt.end_at?.slice(11,16) }}
                  • {{ appt.procedure?.name || 'без процедури' }}
                </p>
              </div>
              <div class="flex gap-2">
                <span class="text-xs bg-slate-800 px-2 py-1 rounded text-slate-300">{{ appt.status }}</span>
                <button class="text-sm text-red-400 hover:text-red-300" @click="openCancellation(appt)">Скасувати</button>
              </div>
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
