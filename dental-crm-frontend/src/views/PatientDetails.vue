<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import apiClient from '../services/apiClient';
// Імпортуємо наш новий компонент
import DentalMap from '../components/DentalMap.vue';

const route = useRoute();
const router = useRouter();

// --- СТАН ---
const patientId = computed(() => Number(route.params.id));
const activeTab = ref('info'); // 'info' | 'dental_map' | 'history'

const loading = ref(true);
const error = ref('');
const saving = ref(false);
const saveError = ref('');
const savedMessage = ref('');

const patient = ref(null);
const form = ref({
  clinic_id: '',
  full_name: '',
  birth_date: '',
  phone: '',
  email: '',
  address: '',
  note: '',
});

// --- ОБЧИСЛЮВАНІ ВЛАСТИВОСТІ ---
const visitHistory = computed(() => patient.value?.appointments || []);
const treatmentHistory = computed(() =>
    (patient.value?.appointments || [])
        .map((appointment) => ({
          ...appointment,
          historyDate: appointment.updated_at || appointment.start_at,
          visitDate: appointment.start_at,
          updatedDate: appointment.updated_at,
        }))
        .sort((a, b) => new Date(b.historyDate) - new Date(a.historyDate))
);

// --- МЕТОДИ ---
const goToSchedule = () => {
  router.push({ name: 'schedule', query: { patient_id: patientId.value } });
};

const formatDateTime = (value) => {
  if (!value) return '—';
  return new Date(value).toLocaleString('uk-UA', { dateStyle: 'medium', timeStyle: 'short' });
};

const statusLabel = (status) => {
  const labels = { planned: 'Заплановано', done: 'Завершено', cancelled: 'Скасовано', no_show: 'Не з’явився' };
  return labels[status] || status;
};

const statusClass = (status) => {
  const classes = {
    planned: 'bg-amber-500/15 text-amber-300 border border-amber-500/30',
    done: 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30',
    cancelled: 'bg-red-500/15 text-red-300 border border-red-500/30',
    no_show: 'bg-slate-500/15 text-slate-300 border border-slate-500/30',
  };
  return classes[status] || 'bg-slate-800/50 text-slate-200 border border-slate-700';
};

const fillForm = (data) => {
  form.value = {
    clinic_id: data.clinic_id || '',
    full_name: data.full_name || '',
    birth_date: data.birth_date || '',
    phone: data.phone || '',
    email: data.email || '',
    address: data.address || '',
    note: data.note || '',
  };
};

const loadPatient = async () => {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await apiClient.get(`/patients/${patientId.value}`);
    patient.value = data;
    fillForm(data);
  } catch (e) {
    console.error(e);
    error.value = e.response?.data?.message || 'Не вдалося завантажити пацієнта';
  } finally {
    loading.value = false;
  }
};

const savePatient = async () => {
  saving.value = true;
  saveError.value = '';
  savedMessage.value = '';
  try {
    const { data } = await apiClient.put(`/patients/${patientId.value}`, { ...form.value });
    patient.value = { ...patient.value, ...data };
    savedMessage.value = 'Дані оновлено';
  } catch (e) {
    saveError.value = e.response?.data?.message || 'Помилка збереження';
  } finally {
    saving.value = false;
  }
};

const resetForm = () => {
  if (!patient.value) return;
  fillForm(patient.value);
  saveError.value = '';
  savedMessage.value = '';
};

onMounted(loadPatient);
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <button type="button" class="text-xs text-slate-400 hover:text-slate-200" @click="router.back()">
        ← Назад до списку
      </button>
      <div class="text-xs text-slate-500" v-if="patient">ID: {{ patient.id }}</div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 bg-slate-900/50 p-4 rounded-xl border border-slate-800">
      <div>
        <h1 class="text-2xl font-bold text-slate-100">{{ patient?.full_name || 'Завантаження...' }}</h1>
        <div class="flex gap-3 text-sm text-slate-400 mt-1">
          <span v-if="patient?.phone">📞 {{ patient.phone }}</span>
          <span v-if="patient?.birth_date">🎂 {{ patient.birth_date }}</span>
        </div>
      </div>
      <button
          type="button"
          class="px-4 py-2 rounded-lg bg-emerald-500 text-sm font-bold text-slate-900 hover:bg-emerald-400 shadow-lg shadow-emerald-500/20"
          @click="goToSchedule"
      >
        + Записати на прийом
      </button>
    </div>

    <div v-if="loading" class="text-slate-300 text-center py-10">Завантаження даних...</div>
    <div v-else-if="error" class="text-red-400 text-center py-10">❌ {{ error }}</div>

    <div v-else class="space-y-6">

      <div class="border-b border-slate-800">
        <nav class="-mb-px flex space-x-6 overflow-x-auto">
          <button
              @click="activeTab = 'info'"
              :class="[activeTab === 'info' ? 'border-emerald-500 text-emerald-400' : 'border-transparent text-slate-400 hover:text-slate-200 hover:border-slate-600', 'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors']">
            Інформація
          </button>
          <button
              @click="activeTab = 'dental_map'"
              :class="[activeTab === 'dental_map' ? 'border-emerald-500 text-emerald-400' : 'border-transparent text-slate-400 hover:text-slate-200 hover:border-slate-600', 'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors']">
            🦷 Зубна формула
          </button>
          <button
              @click="activeTab = 'history'"
              :class="[activeTab === 'history' ? 'border-emerald-500 text-emerald-400' : 'border-transparent text-slate-400 hover:text-slate-200 hover:border-slate-600', 'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors']">
            Історія візитів
          </button>
        </nav>
      </div>

      <div v-show="activeTab === 'info'" class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-slate-800 bg-slate-900/60 p-5 space-y-4">
          <h2 class="text-lg font-semibold text-slate-200 mb-4">Редагування профілю</h2>

          <form class="space-y-4" @submit.prevent="savePatient">
            <div class="grid md:grid-cols-2 gap-4">
              <div>
                <label class="text-xs uppercase text-slate-500 block mb-1">ПІБ</label>
                <input v-model="form.full_name" type="text" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-slate-200" required />
              </div>
              <div>
                <label class="text-xs uppercase text-slate-500 block mb-1">Дата народження</label>
                <input v-model="form.birth_date" type="date" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-slate-200" />
              </div>
            </div>

            <div class="grid md:grid-cols-2 gap-4">
              <div>
                <label class="text-xs uppercase text-slate-500 block mb-1">Телефон</label>
                <input v-model="form.phone" type="text" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-slate-200" />
              </div>
              <div>
                <label class="text-xs uppercase text-slate-500 block mb-1">Email</label>
                <input v-model="form.email" type="email" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-slate-200" />
              </div>
            </div>

            <div>
              <label class="text-xs uppercase text-slate-500 block mb-1">Адреса</label>
              <input v-model="form.address" type="text" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-slate-200" />
            </div>

            <div>
              <label class="text-xs uppercase text-slate-500 block mb-1">Примітка</label>
              <textarea v-model="form.note" rows="3" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-slate-200"></textarea>
            </div>

            <div class="flex justify-end gap-3 pt-2">
              <button type="button" @click="resetForm" class="text-slate-400 hover:text-white text-sm">Скасувати</button>
              <button type="submit" :disabled="saving" class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-500 disabled:opacity-50">
                {{ saving ? 'Збереження...' : 'Зберегти зміни' }}
              </button>
            </div>
            <div v-if="savedMessage" class="text-emerald-400 text-sm text-center mt-2">{{ savedMessage }}</div>
          </form>
        </section>

        <section class="space-y-6">
          <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-5">
            <h3 class="text-sm uppercase text-slate-500 mb-3">Останні записи лікаря</h3>
            <div v-if="treatmentHistory.length === 0" class="text-slate-500 text-sm">Немає записів</div>
            <div v-else class="space-y-3">
              <div v-for="t in treatmentHistory.slice(0, 3)" :key="t.id" class="text-sm border-l-2 border-slate-700 pl-3 py-1">
                <div class="text-slate-300 font-medium">{{ formatDateTime(t.historyDate) }}</div>
                <div class="text-slate-400">{{ t.doctor?.full_name }}</div>
                <div class="text-slate-500 italic truncate">{{ t.comment || 'Без коментаря' }}</div>
              </div>
            </div>
          </div>
        </section>
      </div>

      <div v-if="activeTab === 'dental_map'">
        <DentalMap :patient-id="patientId" />
      </div>

      <div v-if="activeTab === 'history'">
        <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
          <div v-if="visitHistory.length === 0" class="text-center py-8 text-slate-500">Історія порожня</div>
          <div v-else class="space-y-3">
            <div v-for="visit in visitHistory" :key="visit.id" class="bg-slate-950 p-4 rounded-lg border border-slate-800 flex justify-between items-start">
              <div>
                <div class="font-bold text-slate-200">{{ formatDateTime(visit.start_at) }}</div>
                <div class="text-sm text-slate-400">Лікар: {{ visit.doctor?.full_name }}</div>
                <div class="text-sm text-slate-500 mt-1">{{ visit.comment }}</div>
              </div>
              <span class="text-xs px-2 py-1 rounded border" :class="statusClass(visit.status)">{{ statusLabel(visit.status) }}</span>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>