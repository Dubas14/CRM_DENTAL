<script setup>
import { ref, onMounted, watch } from 'vue';
import assistantApi from '../services/assistantApi';
import clinicApi from '../services/clinicApi';
import { useAuth } from '../composables/useAuth';

const { user } = useAuth();

const clinics = ref([]);
const selectedClinicId = ref('');
const assistants = ref([]);
const loading = ref(false);
const error = ref(null);

const showForm = ref(false);
const creating = ref(false);
const createError = ref(null);

const form = ref({
  clinic_id: '',
  first_name: '',
  last_name: '',
  email: '',
  password: '',
});

const loadClinics = async () => {
  if (user.value?.global_role === 'super_admin') {
    const { data } = await clinicApi.list();
    clinics.value = data.data ?? data;
  } else {
    const { data } = await clinicApi.listMine();
    clinics.value = (data.clinics ?? []).map((clinic) => ({
      id: clinic.clinic_id,
      name: clinic.clinic_name,
    }));
  }

  if (!selectedClinicId.value && clinics.value.length) {
    selectedClinicId.value = clinics.value[0].id;
  }
};

const fetchAssistants = async () => {
  if (!selectedClinicId.value) return;
  loading.value = true;
  error.value = null;
  try {
    const { data } = await assistantApi.list({ clinic_id: selectedClinicId.value });
    assistants.value = data.data ?? data;
  } catch (err) {
    console.error(err);
    error.value = 'Не вдалося завантажити асистентів';
  } finally {
    loading.value = false;
  }
};

const resetForm = () => {
  form.value = {
    clinic_id: selectedClinicId.value || clinics.value[0]?.id || '',
    first_name: '',
    last_name: '',
    email: '',
    password: '',
  };
};

const toggleForm = () => {
  showForm.value = !showForm.value;
  if (showForm.value) {
    resetForm();
  }
};

const createAssistant = async () => {
  creating.value = true;
  createError.value = null;
  try {
    await assistantApi.create({
      ...form.value,
      clinic_id: form.value.clinic_id || selectedClinicId.value,
    });
    showForm.value = false;
    resetForm();
    await fetchAssistants();
  } catch (err) {
    console.error(err);
    createError.value = err.response?.data?.message || 'Помилка створення асистента';
  } finally {
    creating.value = false;
  }
};

const assistantName = (assistant) => {
  return assistant.full_name || assistant.name || `${assistant.first_name || ''} ${assistant.last_name || ''}`.trim();
};

const assistantClinic = (assistant) => {
  return assistant.clinics?.[0]?.name || '—';
};

watch(selectedClinicId, async () => {
  await fetchAssistants();
});

onMounted(async () => {
  await loadClinics();
  await fetchAssistants();
});
</script>

<template>
  <div class="space-y-6">
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-2xl font-semibold">Асистенти</h1>
        <p class="text-sm text-slate-400">Створення та перегляд асистентів клініки.</p>
      </div>
      <button
        class="px-4 py-2 rounded-lg bg-emerald-500 text-slate-900 text-sm font-semibold hover:bg-emerald-400"
        @click="toggleForm"
      >
        {{ showForm ? 'Приховати форму' : 'Новий асистент' }}
      </button>
    </header>

    <div class="flex flex-wrap items-center gap-3">
      <label class="text-xs uppercase text-slate-400">Клініка</label>
      <select
        v-model="selectedClinicId"
        class="rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
      >
        <option v-for="clinic in clinics" :key="clinic.id" :value="clinic.id">
          {{ clinic.name }}
        </option>
      </select>
    </div>

    <section
      v-if="showForm"
      class="rounded-xl border border-slate-800 bg-slate-900/60 p-4 space-y-4"
    >
      <h2 class="text-sm font-semibold text-slate-200">Додати асистента</h2>

      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs uppercase text-slate-400 mb-1">Клініка</label>
          <select
            v-model="form.clinic_id"
            class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
          >
            <option v-for="clinic in clinics" :key="clinic.id" :value="clinic.id">
              {{ clinic.name }}
            </option>
          </select>
        </div>

        <div>
          <label class="block text-xs uppercase text-slate-400 mb-1">Ім'я</label>
          <input
            v-model="form.first_name"
            type="text"
            class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
          />
        </div>

        <div>
          <label class="block text-xs uppercase text-slate-400 mb-1">Прізвище</label>
          <input
            v-model="form.last_name"
            type="text"
            class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
          />
        </div>

        <div>
          <label class="block text-xs uppercase text-slate-400 mb-1">Email</label>
          <input
            v-model="form.email"
            type="email"
            class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
          />
        </div>

        <div>
          <label class="block text-xs uppercase text-slate-400 mb-1">Пароль</label>
          <input
            v-model="form.password"
            type="password"
            class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
          />
        </div>
      </div>

      <div class="flex items-center justify-between gap-3">
        <span v-if="createError" class="text-sm text-red-400">❌ {{ createError }}</span>
        <button
          class="ml-auto px-4 py-2 rounded-lg bg-emerald-500 text-slate-900 text-sm font-semibold hover:bg-emerald-400 disabled:opacity-60"
          :disabled="creating"
          @click="createAssistant"
        >
          {{ creating ? 'Створення...' : 'Створити' }}
        </button>
      </div>
    </section>

    <section class="rounded-xl border border-slate-800 bg-slate-900/40 p-4">
      <div v-if="loading" class="text-sm text-slate-400">Завантаження...</div>
      <div v-else-if="error" class="text-sm text-red-400">{{ error }}</div>
      <div v-else-if="!assistants.length" class="text-sm text-slate-400">Немає асистентів.</div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="text-slate-400 text-xs uppercase">
            <tr>
              <th class="text-left py-2 px-3">Ім'я</th>
              <th class="text-left py-2 px-3">Email</th>
              <th class="text-left py-2 px-3">Клініка</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="assistant in assistants" :key="assistant.id" class="border-t border-slate-800">
              <td class="py-2 px-3 text-slate-200">{{ assistantName(assistant) }}</td>
              <td class="py-2 px-3 text-slate-400">{{ assistant.email }}</td>
              <td class="py-2 px-3 text-slate-400">{{ assistantClinic(assistant) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
