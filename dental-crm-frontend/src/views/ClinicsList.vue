<script setup>
import { ref, onMounted, computed } from 'vue';
import apiClient from '../services/apiClient';
import { useAuth } from '../composables/useAuth';

const { user } = useAuth();
const canManageClinics = computed(() => user.value?.global_role === 'super_admin');

const clinics = ref([]);
const loading = ref(true);
const error = ref(null);

// --- стан форми створення ---
const showForm = ref(false);
const creating = ref(false);
const formError = ref(null);

const form = ref({
  name: '',
  legal_name: '',
  city: '',
  address: '',
  phone: '',
  email: '',
  website: '',
});

// завантаження списку клінік
const loadClinics = async () => {
  loading.value = true;
  error.value = null;

  try {
    const { data } = await apiClient.get('/clinics');
    clinics.value = data;
  } catch (e) {
    console.error(e);
    error.value =
        e.response?.data?.message ||
        e.message ||
        'Помилка завантаження клінік';
  } finally {
    loading.value = false;
  }
};

// створення нової клініки
const createClinic = async () => {
  formError.value = null;
  creating.value = true;

  try {
    const { data } = await apiClient.post('/clinics', form.value);

    // додаємо в список без додаткового запиту
    clinics.value.push(data);

    // чистимо форму
    form.value = {
      name: '',
      legal_name: '',
      city: '',
      address: '',
      phone: '',
      email: '',
      website: '',
    };

    showForm.value = false;
  } catch (e) {
    console.error(e);
    if (e.response?.data?.errors) {
      // беремо першу помилку валідації
      const first = Object.values(e.response.data.errors)[0];
      formError.value = Array.isArray(first) ? first[0] : String(first);
    } else {
      formError.value =
          e.response?.data?.message || e.message || 'Не вдалося створити клініку';
    }
  } finally {
    creating.value = false;
  }
};

onMounted(loadClinics);
</script>

<template>
  <div class="space-y-6">
    <!-- шапка сторінки -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold">Клініки</h1>
        <p class="text-sm text-text/70">
          Дані тягнемо з Laravel API (<code>/api/clinics</code>).
        </p>
      </div>

      <div class="flex items-center gap-2">
        <!-- ✅ цю кнопку бачить тільки super_admin -->
        <button
            v-if="canManageClinics"
            type="button"
            class="px-3 py-2 rounded-lg border border-emerald-500/50 text-sm text-emerald-300 hover:bg-emerald-500/10"
            @click="showForm = !showForm"
        >
          {{ showForm ? 'Приховати форму' : 'Нова клініка' }}
        </button>

        <button
            type="button"
            class="px-3 py-2 rounded-lg border border-border/80 text-sm hover:bg-card/80"
            @click="loadClinics"
        >
          Оновити
        </button>
      </div>
    </div>

    <!-- форма створення -->
    <div
        v-if="showForm"
        class="rounded-xl bg-card/60 shadow-sm shadow-black/10 dark:shadow-black/40 p-4 space-y-4"
    >
      <h2 class="text-lg font-semibold">Нова клініка</h2>

      <div v-if="formError" class="text-sm text-red-400">
        ❌ {{ formError }}
      </div>

      <form class="grid gap-4 md:grid-cols-2" @submit.prevent="createClinic">
        <div class="md:col-span-2">
          <label class="block text-xs uppercase tracking-wide text-text/70 mb-1">
            Назва *
          </label>
          <input
              v-model="form.name"
              type="text"
              required
              class="w-full rounded-lg bg-card border border-border/80 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
              placeholder="Dental Plus"
          />
        </div>

        <div class="md:col-span-2">
          <label class="block text-xs uppercase tracking-wide text-text/70 mb-1">
            Юридична назва
          </label>
          <input
              v-model="form.legal_name"
              type="text"
              class="w-full rounded-lg bg-card border border-border/80 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
              placeholder="ТОВ «Дентал Плюс»"
          />
        </div>

        <div>
          <label class="block text-xs uppercase tracking-wide text-text/70 mb-1">
            Місто
          </label>
          <input
              v-model="form.city"
              type="text"
              class="w-full rounded-lg bg-card border border-border/80 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
              placeholder="Черкаси"
          />
        </div>

        <div>
          <label class="block text-xs uppercase tracking-wide text-text/70 mb-1">
            Адреса
          </label>
          <input
              v-model="form.address"
              type="text"
              class="w-full rounded-lg bg-card border border-border/80 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
              placeholder="вул. Прикладна, 10"
          />
        </div>

        <div>
          <label class="block text-xs uppercase tracking-wide text-text/70 mb-1">
            Телефон
          </label>
          <input
              v-model="form.phone"
              type="text"
              class="w-full rounded-lg bg-card border border-border/80 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
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
              class="w-full rounded-lg bg-card border border-border/80 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
              placeholder="clinic@example.com"
          />
        </div>

        <div class="md:col-span-2">
          <label class="block text-xs uppercase tracking-wide text-text/70 mb-1">
            Сайт
          </label>
          <input
              v-model="form.website"
              type="text"
              class="w-full rounded-lg bg-card border border-border/80 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
              placeholder="https://..."
          />
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
              class="px-4 py-2 rounded-lg bg-emerald-500 text-sm font-semibold text-text hover:bg-emerald-400 disabled:opacity-60 disabled:cursor-not-allowed"
          >
            {{ creating ? 'Збереження...' : 'Зберегти' }}
          </button>
        </div>
      </form>
    </div>

    <!-- список клінік -->
    <div v-if="loading" class="text-text/80">
      Завантаження клінік...
    </div>

    <div v-else-if="error" class="text-red-400">
      ❌ {{ error }}
    </div>

    <div v-else>
      <div v-if="clinics.length === 0" class="text-text/70 text-sm">
        Клінік поки немає. Додай першу через форму вище 🦷
      </div>

      <div
          v-else
          class="overflow-hidden rounded-xl bg-card/40 shadow-sm shadow-black/10 dark:shadow-black/40"
      >
        <table class="min-w-full text-sm">
          <thead class="bg-card/80 text-text/80">
          <tr>
            <th class="px-4 py-2 text-left">ID</th>
            <th class="px-4 py-2 text-left">Назва</th>
            <th class="px-4 py-2 text-left">Місто</th>
            <th class="px-4 py-2 text-left">Адреса</th>
            <th class="px-4 py-2 text-left">Телефон</th>
          </tr>
          </thead>
          <tbody>
          <tr
              v-for="clinic in clinics"
              :key="clinic.id"
              class="border-t border-border hover:bg-card/80/40"
          >
            <td class="px-4 py-2 text-text/70">#{{ clinic.id }}</td>
            <td class="px-4 py-2 font-medium">
              {{ clinic.name }}
            </td>
            <td class="px-4 py-2">
              {{ clinic.city || '—' }}
            </td>
            <td class="px-4 py-2 text-text/80">
              {{ clinic.address || '—' }}
            </td>
            <td class="px-4 py-2 text-text/80">
              {{ clinic.phone || '—' }}
            </td>
          </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
