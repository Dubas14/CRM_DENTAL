<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import apiClient from '../services/apiClient';

const route = useRoute();
const router = useRouter();
const goToSchedule = () => {
  router.push({
    // Візьми name маршруту з router/index.js для DoctorSchedule.vue.
    // Якщо там name: 'schedule' — лишаємо 'schedule'.
    name: 'schedule',
    query: {
      patient_id: patientId.value,
    },
  });
};

const patientId = computed(() => Number(route.params.id));

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

const formatDateTime = (value) => {
  if (!value) return '—';
  return new Date(value).toLocaleString('uk-UA', {
    dateStyle: 'medium',
    timeStyle: 'short',
  });
};

const statusLabel = (status) => {
  const labels = {
    planned: 'Заплановано',
    done: 'Завершено',
    cancelled: 'Скасовано',
    no_show: 'Не з\u2019явився',
  };

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
    const payload = { ...form.value };
    const { data } = await apiClient.put(`/patients/${patientId.value}`, payload);
    patient.value = { ...patient.value, ...data };
    savedMessage.value = 'Дані пацієнта оновлено';
    router.push({ name: 'patients' });
  } catch (e) {
    console.error(e);
    if (e.response?.data?.errors) {
      const firstKey = Object.keys(e.response.data.errors)[0];
      saveError.value = e.response.data.errors[firstKey][0];
    } else {
      saveError.value = e.response?.data?.message || 'Не вдалося зберегти зміни';
    }
  } finally {
    saving.value = false;
  }
};

const resetForm = () => {
  if (!patient.value) return;
  fillForm(patient.value);
  saveError.value = '';
  savedMessage.value = '';
  router.push({ name: 'patients' });
};

onMounted(loadPatient);
</script>

<template>
  <div class="space-y-6">
    <button
        type="button"
        class="text-xs text-slate-400 hover:text-slate-200"
        @click="router.back()"
    >
      ← Назад до пацієнтів
    </button>

    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-semibold">
          {{ patient?.full_name || 'Пацієнт' }}
        </h1>
        <p class="text-sm text-slate-400">
          Картка пацієнта з історією візитів та примітками лікування.
        </p>
      </div>

      <button
          type="button"
          class="px-3 py-2 rounded-lg bg-emerald-500 text-sm font-semibold text-slate-900 hover:bg-emerald-400"
          @click="goToSchedule"
      >
        Новий запис у розкладі
      </button>
    </div>

    <div class="flex flex-wrap items-center gap-2 text-sm text-slate-300">
      <span class="px-3 py-1 rounded-lg bg-slate-800 border border-slate-700">
        {{ patient?.clinic?.name || 'Клініка не вказана' }}
      </span>
      <span
          v-if="patient?.birth_date"
          class="px-3 py-1 rounded-lg bg-slate-800 border border-slate-700"
      >
        {{ patient.birth_date }}
      </span>
      <span
          v-if="patient?.phone"
          class="px-3 py-1 rounded-lg bg-slate-800 border border-slate-700"
      >
        {{ patient.phone }}
      </span>
    </div>

    <div v-if="loading" class="text-slate-300">Завантаження даних...</div>
    <div v-else-if="error" class="text-red-400">❌ {{ error }}</div>

    <div v-else class="space-y-6">
      <div class="grid gap-6 lg:grid-cols-[1.7fr,1fr]">
        <!-- Ліва колонка -->
        <section class="rounded-xl border border-slate-800 bg-slate-900/60 p-4 space-y-4">
          <h2 class="text-sm font-semibold text-slate-200">Загальна інформація</h2>

          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-1">
              <p class="text-xs uppercase text-slate-400">Клініка</p>
              <p class="text-sm text-slate-100">{{ patient.clinic?.name || '—' }}</p>
              <p class="text-xs text-slate-500">{{ patient.clinic?.city || '' }}</p>
            </div>

            <div class="space-y-1">
              <p class="text-xs uppercase text-slate-400">Дата народження</p>
              <p class="text-sm text-slate-100">{{ patient.birth_date || '—' }}</p>
            </div>

            <div class="space-y-1">
              <p class="text-xs uppercase text-slate-400">Телефон</p>
              <p class="text-sm text-slate-100">{{ patient.phone || '—' }}</p>
            </div>

            <div class="space-y-1">
              <p class="text-xs uppercase text-slate-400">Email</p>
              <p class="text-sm text-slate-100">{{ patient.email || '—' }}</p>
            </div>

            <div class="md:col-span-2 space-y-1">
              <p class="text-xs uppercase text-slate-400">Адреса</p>
              <p class="text-sm text-slate-100">{{ patient.address || '—' }}</p>
            </div>
          </div>

          <div class="space-y-2">
            <p class="text-xs uppercase text-slate-400">Загальна примітка</p>
            <div
                class="rounded-lg border border-slate-800 bg-slate-950 px-3 py-3 text-sm text-slate-200"
            >
              {{ patient.note || 'Немає записів' }}
            </div>
          </div>

          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <p class="text-xs uppercase text-slate-400">Історія лікування</p>
              <span class="text-[11px] text-slate-400">
                {{ treatmentHistory.length }} запис(ів)
              </span>
            </div>

            <div v-if="treatmentHistory.length === 0" class="text-sm text-slate-400">
              Немає записів про лікування від лікарів.
            </div>

            <div v-else class="space-y-2">
              <div
                  v-for="treatment in treatmentHistory"
                  :key="treatment.id + '-' + treatment.historyDate"
                  class="rounded-lg border border-slate-800 bg-slate-950 px-3 py-3 text-sm text-slate-200"
              >
                <div
                    class="flex flex-wrap items-center justify-between gap-2 mb-1"
                >
                  <p class="font-semibold text-slate-100">
                    {{ treatment.doctor?.full_name || 'Лікар не вказаний' }}
                  </p>
                  <span class="text-xs text-slate-400">
                    {{ formatDateTime(treatment.historyDate) }}
                  </span>
                </div>
                <div
                    class="flex flex-wrap items-center gap-3 text-[11px] text-slate-400 mb-1"
                >
                  <span v-if="treatment.visitDate">
                    Візит: {{ formatDateTime(treatment.visitDate) }}
                  </span>
                  <span v-if="treatment.updatedDate">
                    Оновлено: {{ formatDateTime(treatment.updatedDate) }}
                  </span>
                </div>
                <p class="text-[13px] text-slate-300 whitespace-pre-line">
                  {{ treatment.comment || 'Коментар від лікаря не додано' }}
                </p>
              </div>
            </div>
          </div>
        </section>

        <!-- Права колонка -->
        <section class="rounded-xl border border-slate-800 bg-slate-900/60 p-4 space-y-4">
          <h2 class="text-sm font-semibold text-slate-200">Редагування даних</h2>

          <div v-if="saveError" class="text-sm text-red-400">❌ {{ saveError }}</div>
          <div v-if="savedMessage" class="text-sm text-emerald-400">
            ✅ {{ savedMessage }}
          </div>

          <form class="space-y-3" @submit.prevent="savePatient">
            <div class="space-y-1">
              <label class="text-xs uppercase text-slate-400">ПІБ</label>
              <input
                  v-model="form.full_name"
                  type="text"
                  required
                  class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm"
              />
            </div>

            <div class="space-y-1">
              <label class="text-xs uppercase text-slate-400">Дата народження</label>
              <input
                  v-model="form.birth_date"
                  type="date"
                  class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm"
              />
            </div>

            <div class="grid gap-3 md:grid-cols-2">
              <div class="space-y-1">
                <label class="text-xs uppercase text-slate-400">Телефон</label>
                <input
                    v-model="form.phone"
                    type="text"
                    class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm"
                />
              </div>

              <div class="space-y-1">
                <label class="text-xs uppercase text-slate-400">Email</label>
                <input
                    v-model="form.email"
                    type="email"
                    class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm"
                />
              </div>
            </div>

            <div class="space-y-1">
              <label class="text-xs uppercase text-slate-400">Адреса</label>
              <input
                  v-model="form.address"
                  type="text"
                  class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm"
              />
            </div>

            <div class="space-y-1">
              <label class="text-xs uppercase text-slate-400">
                Примітка / історія лікування
              </label>
              <textarea
                  v-model="form.note"
                  rows="3"
                  class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm"
                  placeholder="Особливості лікування, реакції, попередні консультації"
              ></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-2">
              <button
                  type="button"
                  class="px-3 py-2 rounded-lg border border-slate-700 text-sm text-slate-300 hover:bg-slate-800"
                  @click="resetForm"
              >
                Скинути
              </button>
              <button
                  type="submit"
                  :disabled="saving"
                  class="px-4 py-2 rounded-lg bg-emerald-500 text-sm font-semibold text-slate-900 hover:bg-emerald-400 disabled:opacity-60"
              >
                {{ saving ? 'Збереження...' : 'Зберегти зміни' }}
              </button>
            </div>
          </form>
        </section>
      </div>

      <section class="rounded-xl border border-slate-800 bg-slate-900/60 p-4 space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-semibold text-slate-200">Історія візитів</h2>
          <span class="text-xs text-slate-400">
            {{ visitHistory.length }} запис(ів)
          </span>
        </div>

        <div v-if="visitHistory.length === 0" class="text-sm text-slate-400">
          Поки що немає записів про візити цього пацієнта.
        </div>

        <div v-else class="space-y-3">
          <div
              v-for="visit in visitHistory"
              :key="visit.id"
              class="rounded-lg border border-slate-800 bg-slate-950/70 p-3"
          >
            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
              <div class="text-sm font-semibold text-slate-100">
                {{ formatDateTime(visit.start_at) }}
              </div>
              <span
                  class="text-xs px-2 py-1 rounded-full"
                  :class="statusClass(visit.status)"
              >
                {{ statusLabel(visit.status) }}
              </span>
            </div>

            <div class="text-sm text-slate-200">
              Лікар:
              <span class="font-medium">
                {{ visit.doctor?.full_name || '—' }}
              </span>
            </div>
            <div class="text-xs text-slate-400">
              {{ visit.doctor?.specialization || 'Спеціалізація не вказана' }}
            </div>

            <div class="mt-2 text-xs text-slate-400">
              Клініка: {{ visit.clinic?.name || '—' }}
            </div>

            <div v-if="visit.comment" class="mt-3 text-sm text-slate-100">
              <p class="text-xs uppercase text-slate-500 mb-1">
                Коментар / перебіг прийому
              </p>
              <p class="whitespace-pre-line">{{ visit.comment }}</p>
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>