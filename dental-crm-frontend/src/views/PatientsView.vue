<script setup>
import { ref, onMounted } from 'vue';
import apiClient from '../services/apiClient';

const patients = ref([]);
const clinics = ref([]);
const loading = ref(true);
const error = ref(null);

// форма
const showForm = ref(false);
const creating = ref(false);
const formError = ref(null);

const form = ref({
  clinic_id: '',
  full_name: '',
  phone: '',
  email: '',
  birth_date: '',
  notes: '',
});

const search = ref('');

const loadClinics = async () => {
  const { data } = await apiClient.get('/clinics');
  clinics.value = data;
};

const loadPatients = async () => {
  loading.value = true;
  error.value = null;

  try {
    const { data } = await apiClient.get('/patients', {
      params: {
        search: search.value || undefined,
      },
    });

    // бо ми повертаємо paginate – data.data
    patients.value = data.data || data;
  } catch (e) {
    console.error(e);
    error.value =
        e.response?.data?.message || e.message || 'Помилка завантаження пацієнтів';
  } finally {
    loading.value = false;
  }
};

const createPatient = async () => {
  formError.value = null;
  creating.value = true;

  try {
    const { data } = await apiClient.post('/patients', form.value);

    patients.value.unshift(data);

    form.value = {
      clinic_id: '',
      full_name: '',
      phone: '',
      email: '',
      birth_date: '',
      notes: '',
    };

    showForm.value = false;
  } catch (e) {
    console.error(e);
    if (e.response?.data?.errors) {
      const first = Object.values(e.response.data.errors)[0];
      formError.value = Array.isArray(first) ? first[0] : String(first);
    } else {
      formError.value =
          e.response?.data?.message || e.message || 'Не вдалося створити пацієнта';
    }
  } finally {
    creating.value = false;
  }
};

onMounted(async () => {
  await Promise.all([loadClinics(), loadPatients()]);
});
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold">Пацієнти</h1>
        <p class="text-sm text-text/70">
          Картотека пацієнтів клінік.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <input
            v-model="search"
            type="text"
            placeholder="Пошук по імені / телефону / email"
            class="rounded-lg bg-card border border-border/80 px-3 py-2 text-sm"
            @keyup.enter="loadPatients"
        />
        <button
            type="button"
            class="px-3 py-2 rounded-lg border border-border/80 text-sm hover:bg-card/80"
            @click="loadPatients"
        >
          Знайти
        </button>

        <button
            type="button"
            class="px-3 py-2 rounded-lg bg-emerald-500 text-sm font-semibold text-text hover:bg-emerald-400"
            @click="showForm = !showForm"
        >
          {{ showForm ? 'Приховати форму' : 'Новий пацієнт' }}
        </button>
      </div>
    </div>

    <!-- форма створення -->
    <div
        v-if="showForm"
        class="rounded-xl border border-border bg-card/60 p-4 space-y-4"
    >
      <h2 class="text-lg font-semibold">Новий пацієнт</h2>

      <div v-if="formError" class="text-sm text-red-400">
        ❌ {{ formError }}
      </div>

      <form class="grid gap-4 md:grid-cols-2" @submit.prevent="createPatient">
        <div>
          <label class="block text-xs uppercase tracking-wide text-text/70 mb-1">
            Клініка *
          </label>
          <select
              v-model="form.clinic_id"
              required
              class="w-full rounded-lg bg-card border border-border/80 px-3 py-2 text-sm"
          >
            <option value="" disabled>Оберіть клініку</option>
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
          <label class="block text-xs uppercase tracking-wide text-text/70 mb-1">
            ПІБ *
          </label>
          <input
              v-model="form.full_name"
              type="text"
              required
              class="w-full rounded-lg bg-card border border-border/80 px-3 py-2 text-sm"
              placeholder="Іваненко Іван Іванович"
          />
        </div>

        <div>
          <label class="block text-xs uppercase tracking-wide text-text/70 mb-1">
            Телефон
          </label>
          <input
              v-model="form.phone"
              type="text"
              class="w-full rounded-lg bg-card border border-border/80 px-3 py-2 text-sm"
              placeholder="+380..."
          />
        </div>

        <div>
          <label class="block text-xs uppercase tracking-wide text-text/70 mb-1">
            Email
          </label>
          <input
              v-model="form.email"
              type="email"
              class="w-full rounded-lg bg-card border border-border/80 px-3 py-2 text-sm"
              placeholder="patient@example.com"
          />
        </div>

        <div>
          <label class="block text-xs uppercase tracking-wide text-text/70 mb-1">
            Дата народження
          </label>
          <input
              v-model="form.birth_date"
              type="date"
              class="w-full rounded-lg bg-card border border-border/80 px-3 py-2 text-sm"
          />
        </div>

        <div class="md:col-span-2">
          <label class="block text-xs uppercase tracking-wide text-text/70 mb-1">
            Нотатки
          </label>
          <textarea
              v-model="form.notes"
              rows="2"
              class="w-full rounded-lg bg-card border border-border/80 px-3 py-2 text-sm"
              placeholder="Алергії, особливості, важливі деталі..."
          ></textarea>
        </div>

        <div class="md:col-span-2 flex justify-end gap-2">
          <button
              type="button"
              class="px-3 py-2 rounded-lg border border-border/80 text-sm text-text/80 hover:bg-card/80"
              @click="showForm = false"
          >
            Скасувати
          </button>
          <button
              type="submit"
              :disabled="creating"
              class="px-4 py-2 rounded-lg bg-emerald-500 text-sm font-semibold text-text hover:bg-emerald-400 disabled:opacity-60"
          >
            {{ creating ? 'Збереження...' : 'Зберегти' }}
          </button>
        </div>
      </form>
    </div>

    <!-- список пацієнтів -->
    <div v-if="loading" class="text-text/80">
      Завантаження пацієнтів...
    </div>

    <div v-else-if="error" class="text-red-400">
      ❌ {{ error }}
    </div>

    <div v-else>
      <div v-if="patients.length === 0" class="text-text/70 text-sm">
        Пацієнтів поки немає. Додай першого через форму вище 🙂
      </div>

      <div
          v-else
          class="overflow-hidden rounded-xl border border-border bg-card/40"
      >
        <table class="min-w-full text-sm">
          <thead class="bg-card/80 text-text/80">
          <tr>
            <th class="px-4 py-2 text-left">ПІБ</th>
            <th class="px-4 py-2 text-left">Клініка</th>
            <th class="px-4 py-2 text-left">Телефон</th>
            <th class="px-4 py-2 text-left">Email</th>
          </tr>
          </thead>
          <tbody>
          <tr
              v-for="p in patients"
              :key="p.id"
              class="border-t border-border hover:bg-card/80/40"
          >
            <td class="px-4 py-2 font-medium">
              {{ p.full_name }}
            </td>
            <td class="px-4 py-2">
              {{ p.clinic?.name || '—' }}
            </td>
            <td class="px-4 py-2">
              {{ p.phone || '—' }}
            </td>
            <td class="px-4 py-2">
              {{ p.email || '—' }}
            </td>
          </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
