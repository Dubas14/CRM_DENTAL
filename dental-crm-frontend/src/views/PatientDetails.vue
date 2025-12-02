<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import apiClient from '../services/apiClient';
import DentalMap from '../components/DentalMap.vue';

const route = useRoute();
const router = useRouter();

// --- СТАН ---
const patientId = computed(() => Number(route.params.id));
const activeTab = ref('info');

const loading = ref(true);
const error = ref('');
const saving = ref(false);
const saveError = ref('');
const savedMessage = ref('');

const patient = ref(null);
// Додаємо окремий стан для історії лікування
const medicalRecords = ref([]);

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

// 1. Історія візитів (Календар)
const appointmentsHistory = computed(() => patient.value?.appointments || []);

// 2. Об'єднана історія (Календар + Медичні записи)
const unifiedHistory = computed(() => {
  const apps = (patient.value?.appointments || []).map(a => ({
    type: 'appointment',
    id: a.id,
    date: a.start_at,
    doctor_name: a.doctor?.user?.last_name || a.doctor?.full_name || 'Невідомий',
    title: 'Візит (Календар)',
    description: a.comment,
    status: a.status
  }));

  const records = medicalRecords.value.map(r => ({
    type: 'record',
    id: r.id,
    date: r.created_at,
    doctor_name: r.doctor?.user?.last_name || r.doctor?.full_name || 'Невідомий',
    title: r.diagnosis || 'Запис лікування',
    description: `Скарги: ${r.complaints || '-'} \n Лікування: ${r.treatment || '-'}`,
    tooth: r.tooth_number
  }));

  // Об'єднуємо і сортуємо: найновіші зверху
  return [...apps, ...records].sort((a, b) => new Date(b.date) - new Date(a.date));
});

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
// 1. Додайте змінні
const notes = ref([]);
const newNoteText = ref('');

// 2. Додайте метод завантаження нотаток
const loadNotes = async () => {
  try {
    const { data } = await apiClient.get(`/patients/${patientId.value}/notes`);
    notes.value = data;
  } catch (e) {
    console.error("Notes error:", e);
  }
};

// 3. Додайте метод відправки
const sendNote = async () => {
  if (!newNoteText.value.trim()) return;

  try {
    const { data } = await apiClient.post(`/patients/${patientId.value}/notes`, {
      content: newNoteText.value
    });
    notes.value.unshift(data); // Додаємо нову зверху
    newNoteText.value = '';
  } catch (e) {
    alert('Помилка додавання нотатки');
  }
};

// 4. Додайте виклик loadNotes() у loadData або onMounted
onMounted(() => {
  loadData();
  loadNotes(); // <-- ВАЖЛИВО ДОДАТИ ЦЕ
});

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

const loadData = async () => {
  loading.value = true;
  error.value = '';
  try {
    // Паралельний запит: дані пацієнта + медична карта
    const [patientRes, recordsRes] = await Promise.all([
      apiClient.get(`/patients/${patientId.value}`),
      apiClient.get(`/patients/${patientId.value}/records`)
    ]);

    patient.value = patientRes.data;
    medicalRecords.value = recordsRes.data; // Зберігаємо записи лікування
    fillForm(patientRes.data);
  } catch (e) {
    console.error(e);
    error.value = e.response?.data?.message || 'Не вдалося завантажити дані';
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
const validatePhone = (event) => {
  // Замінюємо все, що не є цифрою, плюсом, дужками або дефісом на пустоту
  let val = event.target.value.replace(/[^0-9+\-() ]/g, '');
  form.value.phone = val;
  // Синхронізуємо значення в полі (інколи v-model не встигає)
  event.target.value = val;
};

onMounted(loadData);
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
            Історія лікування
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
                <input
                    v-model="form.phone"
                    @input="validatePhone"
                    type="tel"
                    class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-slate-200"
                    placeholder="+380..."
                />
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
              <div class="mt-6 border-t border-slate-800 pt-4">
                <label class="text-xs uppercase text-slate-500 block mb-3">Історія нотаток</label>

                <div class="space-y-3 mb-4 max-h-60 overflow-y-auto pr-2 custom-scrollbar">
                  <div v-if="notes.length === 0" class="text-sm text-slate-500 italic text-center py-2">
                    Ще немає нотаток. Додайте першу!
                  </div>

                  <div v-for="note in notes" :key="note.id" class="bg-slate-950 p-3 rounded-lg border border-slate-800 text-sm">
                    <div class="flex justify-between items-center mb-1">
                        <span class="font-bold text-xs text-emerald-400">
                          {{ note.user?.last_name || note.user?.first_name || 'Система' }}
                        </span>
                      <span class="text-[10px] text-slate-500">
                          {{ new Date(note.created_at).toLocaleString('uk-UA') }}
                        </span>
                    </div>
                    <div class="text-slate-200 whitespace-pre-wrap">{{ note.content }}</div>
                  </div>
                </div>

                <div class="flex gap-2 items-start">
                    <textarea
                        v-model="newNoteText"
                        rows="1"
                        placeholder="Напишіть нотатку..."
                        class="flex-1 rounded-lg bg-slate-900 border border-slate-700 px-3 py-2 text-slate-200 text-sm focus:ring-1 focus:ring-emerald-500 resize-none"
                    ></textarea>
                  <button
                      type="button"
                      @click="sendNote"
                      :disabled="!newNoteText"
                      class="bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 disabled:cursor-not-allowed text-white px-4 py-2 rounded-lg text-sm transition-colors"
                  >
                    ➤
                  </button>
                </div>
              </div>
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
      </div>

      <div v-if="activeTab === 'dental_map'">
        <DentalMap :patient-id="patientId" />
      </div>

      <div v-if="activeTab === 'history'">
        <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
          <div v-if="unifiedHistory.length === 0" class="text-center py-8 text-slate-500">Історія порожня</div>
          <div v-else class="space-y-4">

            <div v-for="item in unifiedHistory" :key="item.type + item.id"
                 class="bg-slate-950 p-4 rounded-lg border flex flex-col gap-2"
                 :class="item.type === 'record' ? 'border-emerald-500/30 border-l-4 border-l-emerald-500' : 'border-slate-800 border-l-4 border-l-amber-500'">

              <div class="flex justify-between items-start">
                <div>
                  <div class="font-bold text-slate-200 text-lg flex items-center gap-2">
                    {{ item.title }}
                    <span v-if="item.tooth" class="bg-slate-800 text-slate-300 text-xs px-2 py-0.5 rounded">Зуб {{ item.tooth }}</span>
                  </div>
                  <div class="text-xs text-slate-400 mt-1">
                    {{ formatDateTime(item.date) }} • Лікар: {{ item.doctor_name }}
                  </div>
                </div>
                <span v-if="item.status" class="text-xs px-2 py-1 rounded border" :class="statusClass(item.status)">
                         {{ statusLabel(item.status) }}
                      </span>
                <span v-else class="text-xs px-2 py-1 rounded bg-emerald-900/20 text-emerald-400 border border-emerald-500/20">
                         Медичний запис
                      </span>
              </div>

              <div class="text-sm text-slate-300 whitespace-pre-line mt-2 pl-2 border-l border-slate-800">
                {{ item.description || 'Без опису' }}
              </div>

            </div>

          </div>
        </div>
      </div>

    </div>
  </div>
</template>