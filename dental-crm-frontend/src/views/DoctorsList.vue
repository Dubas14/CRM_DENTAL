<script setup>
import { ref, onMounted, computed } from 'vue';
import apiClient from '../services/apiClient';
import { useAuth } from '../composables/useAuth';
import { RouterLink } from "vue-router";

const doctors = ref([]);
const loading = ref(false);
const error = ref(null);

const clinics = ref([]);
const loadingClinics = ref(false);

const form = ref({
  clinic_id: '',
  full_name: '',
  specialization: '',
  bio: '',
  color: '#22c55e',
  email: '',
  password: '',
});

const creating = ref(false);
const createError = ref(null);

// 👇 додали
const showForm = ref(false);

const { user } = useAuth();

const canCreateDoctor = computed(
    () => user.value?.global_role === 'super_admin'
);

const fetchClinics = async () => {
  loadingClinics.value = true;
  try {
    const { data } = await apiClient.get('/clinics');
    clinics.value = data.data ?? data;
  } finally {
    loadingClinics.value = false;
  }
};

const fetchDoctors = async () => {
  loading.value = true;
  error.value = null;
  try {
    const { data } = await apiClient.get('/doctors');
    doctors.value = data.data ?? data;
  } catch (e) {
    console.error(e);
    error.value = 'Не вдалося завантажити лікарів';
  } finally {
    loading.value = false;
  }
};

const resetForm = () => {
  form.value = {
    clinic_id: clinics.value[0]?.id || '',
    full_name: '',
    specialization: '',
    bio: '',
    color: '#22c55e',
    email: '',
    password: '',
  };
};

// при відкритті форми одразу чистимо її
const toggleForm = () => {
  showForm.value = !showForm.value;
  if (showForm.value) {
    resetForm();
  }
};

const cancelCreate = () => {
  resetForm();
  showForm.value = false;
};

const createDoctor = async () => {
  creating.value = true;
  createError.value = null;
  try {
    await apiClient.post('/doctors', form.value);
    resetForm();
    showForm.value = false;
    await fetchDoctors();
  } catch (e) {
    console.error(e);
    if (e.response?.data?.errors) {
      const firstKey = Object.keys(e.response.data.errors)[0];
      createError.value = e.response.data.errors[firstKey][0];
    } else {
      createError.value =
          e.response?.data?.message || 'Помилка створення лікаря';
    }
  } finally {
    creating.value = false;
  }
};

onMounted(async () => {
  await fetchClinics();
  if (!form.value.clinic_id && clinics.value.length) {
    form.value.clinic_id = clinics.value[0].id;
  }
  await fetchDoctors();
});
</script>

<template>
  <div class="space-y-6">
    <header class="flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold">Лікарі</h1>
        <p class="text-sm text-slate-400">
          Керування лікарями клінік.
        </p>
      </div>
      <button
          v-if="canCreateDoctor"
          class="px-4 py-2 rounded-lg bg-emerald-500 text-slate-900 text-sm font-semibold hover:bg-emerald-400"
          @click="toggleForm"
      >
        {{ showForm ? 'Приховати форму' : 'Новий лікар' }}
      </button>
    </header>

    <!-- Форма створення лікаря (тільки для супер адміна) -->
    <section
        v-if="canCreateDoctor && showForm"
        class="rounded-xl border border-slate-800 bg-slate-900/60 p-4 space-y-4"
    >
      <h2 class="text-sm font-semibold text-slate-200">
        Створення нового лікаря
      </h2>

      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs uppercase text-slate-400 mb-1">
            Клініка
          </label>
          <select
              v-model="form.clinic_id"
              class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
          >
            <option
                v-for="clinic in clinics"
                :key="clinic.id"
                :value="clinic.id"
            >
              {{ clinic.name }}
            </option>
          </select>
        </div>

        <div>
          <label class="block text-xs uppercase text-slate-400 mb-1">
            ПІБ лікаря
          </label>
          <input
              v-model="form.full_name"
              type="text"
              class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
          />
        </div>

        <div>
          <label class="block text-xs uppercase text-slate-400 mb-1">
            Спеціалізація
          </label>
          <input
              v-model="form.specialization"
              type="text"
              class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
          />
        </div>

        <div>
          <label class="block text-xs uppercase text-slate-400 mb-1">
            Email (логін)
          </label>
          <input
              v-model="form.email"
              type="email"
              class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
          />
        </div>

        <div>
          <label class="block text-xs uppercase text-slate-400 mb-1">
            Пароль
          </label>
          <input
              v-model="form.password"
              type="password"
              class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
          />
        </div>

        <div>
          <label class="block text-xs uppercase text-slate-400 mb-1">
            Колір картки
          </label>
          <input
              v-model="form.color"
              type="color"
              class="h-10 w-20 rounded-lg bg-slate-950 border border-slate-700 px-2 py-1"
          />
        </div>
      </div>

      <div>
        <label class="block text-xs uppercase text-slate-400 mb-1">
          Коротке біо
        </label>
        <textarea
            v-model="form.bio"
            rows="2"
            class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
        />
      </div>

      <div class="flex items-center justify-between gap-3">
        <div class="text-sm text-red-400" v-if="createError">
          ❌ {{ createError }}
        </div>
        <div class="flex gap-2 ml-auto">
          <button
              type="button"
              class="px-3 py-2 rounded-lg border border-slate-700 text-sm text-slate-300 hover:bg-slate-800"
              @click="cancelCreate"
          >
            Скасувати
          </button>
          <button
              type="button"
              :disabled="creating"
              class="px-4 py-2 rounded-lg bg-emerald-500 text-slate-900 text-sm font-semibold hover:bg-emerald-400 disabled:opacity-60"
              @click="createDoctor"
          >
            {{ creating ? 'Створюємо...' : 'Створити лікаря' }}
          </button>
        </div>
      </div>
    </section>

    <!-- Таблиця лікарів -->
    <section class="rounded-xl border border-slate-800 bg-slate-900/60">
      <div class="p-4 border-b border-slate-800 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-200">Список лікарів</h2>
        <button
            type="button"
            class="text-xs text-slate-400 hover:text-slate-200"
            @click="fetchDoctors"
        >
          Оновити
        </button>
      </div>

      <div v-if="loading" class="p-4 text-sm text-slate-400">
        Завантаження...
      </div>
      <div v-else-if="error" class="p-4 text-sm text-red-400">
        ❌ {{ error }}
      </div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-900/80 border-b border-slate-800">
          <tr class="text-left text-slate-400">
            <th class="px-4 py-2">ID</th>
            <th class="px-4 py-2">ПІБ</th>
            <th class="px-4 py-2">Клініка</th>
            <th class="px-4 py-2">Спеціалізація</th>
            <th class="px-4 py-2">Активний</th>
            <th class="px-4 py-2 text-left">Дії</th>
          </tr>
          </thead>
          <tbody>
          <tr
              v-for="doctor in doctors"
              :key="doctor.id"
              class="border-t border-slate-800/60 hover:bg-slate-900/80"
          >
            <td class="px-4 py-2 text-slate-400">#{{ doctor.id }}</td>
            <td class="px-4 py-2 font-medium">
              {{ doctor.full_name }}
            </td>
            <td class="px-4 py-2 text-slate-300">
              {{ doctor.clinic?.name ?? '—' }}
            </td>
            <td class="px-4 py-2 text-slate-300">
              {{ doctor.specialization || '—' }}
            </td>
            <td class="px-4 py-2">
                <span
                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs"
                    :class="doctor.is_active ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-700 text-slate-300'"
                >
                  {{ doctor.is_active ? 'Активний' : 'Не активний' }}
                </span>
            </td>
            <td class="px-4 py-2 text-right">
              <RouterLink
                  :to="{ name: 'doctor-details', params: { id: doctor.id } }"
                  class="inline-flex items-center px-3 py-1 rounded-lg border border-slate-700 text-xs text-slate-200 hover:bg-slate-800"
              >
                Керувати
              </RouterLink>
            </td>
          </tr>
          <tr v-if="!doctors.length">
            <td colspan="5" class="px-4 py-4 text-sm text-slate-400">
              Лікарів поки немає.
            </td>
          </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
