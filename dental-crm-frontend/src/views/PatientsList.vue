<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useRouter } from 'vue-router';
import apiClient from '../services/apiClient';
import { useAuth } from '../composables/useAuth';
import { usePermissions } from '../composables/usePermissions';
import BaseGrid from '../components/BaseGrid.vue';
import BasePagination from '../components/BasePagination.vue';

const patients = ref([]);
const clinics = ref([]);
const loading = ref(true);
const error = ref(null);

const search = ref('');
const selectedClinicFilter = ref('');

// форма
const showForm = ref(false);
const creating = ref(false);
const formError = ref(null);

const { user } = useAuth();
const { isDoctor } = usePermissions();
const router = useRouter();

const doctorProfile = computed(() => user.value?.doctor || null);
const doctorClinicId = computed(() => doctorProfile.value?.clinic_id || '');
const doctorClinic = computed(() => doctorProfile.value?.clinic || null);

const initialFormState = () => ({
  clinic_id: doctorClinicId.value || '',
  full_name: '',
  birth_date: '',
  phone: '',
  email: '',
  address: '',
  note: '',
});
const form = ref(initialFormState());
const pageSize = 10;
const currentPage = ref(1);

const gridData = computed(() =>
  patients.value.map((patient) => ({
    ...patient,
    clinicName: patient.clinic?.name || '—',
  }))
);

const totalItems = computed(() => gridData.value.length);
const pageCount = computed(() => Math.max(1, Math.ceil(totalItems.value / pageSize)));
const pagedPatients = computed(() => {
  const start = (currentPage.value - 1) * pageSize;
  return gridData.value.slice(start, start + pageSize);
});

const gridColumns = computed(() => [
  {
    header: 'ПІБ',
    name: 'full_name',
    sortable: true,
    filter: 'text',
    formatter: ({ row }) => {
      const href = router.resolve({ name: 'patient-details', params: { id: row.id } }).href;
      return `<a href="${href}" class="text-emerald-300 hover:text-emerald-200">${row.full_name}</a>`;
    },
  },
  {
    header: 'Клініка',
    name: 'clinicName',
    sortable: true,
    filter: 'text',
  },
  {
    header: 'Телефон',
    name: 'phone',
    sortable: true,
    filter: 'text',
    formatter: ({ value }) => value || '—',
  },
  {
    header: 'Email',
    name: 'email',
    sortable: true,
    filter: 'text',
    formatter: ({ value }) => value || '—',
  },
]);

const loadClinics = async () => {
  if (isDoctor.value) {
    clinics.value = doctorClinic.value ? [doctorClinic.value] : [];
    form.value.clinic_id = doctorClinicId.value || '';
    selectedClinicFilter.value = doctorClinicId.value
        ? String(doctorClinicId.value)
        : '';
    return;
  }
  const { data } = await apiClient.get('/clinics');
  clinics.value = data;
};

const loadPatients = async () => {
  loading.value = true;
  error.value = null;

  try {
    const params = {};
    if (search.value) params.search = search.value;
    if (isDoctor.value && doctorClinicId.value) {
      params.clinic_id = doctorClinicId.value;
    } else if (selectedClinicFilter.value) {
      params.clinic_id = selectedClinicFilter.value;
    }

    const { data } = await apiClient.get('/patients', { params });
    // бо ми повернули paginate, беремо data.data
    patients.value = data.data ?? data;
    currentPage.value = 1;
  } catch (e) {
    console.error(e);
    error.value =
        e.response?.data?.message ||
        e.message ||
        'Помилка завантаження пацієнтів';
  } finally {
    loading.value = false;
  }
};

const createPatient = async () => {
  formError.value = null;
  creating.value = true;

  try {
    const payload = { ...form.value };

    if (isDoctor.value && doctorClinicId.value) {
      payload.clinic_id = doctorClinicId.value;
    }

    const { data } = await apiClient.post('/patients', payload);
    patients.value.unshift(data);

    form.value = initialFormState();
    showForm.value = false;
  } catch (e) {
    console.error(e);
    if (e.response?.data?.errors) {
      const first = Object.values(e.response.data.errors)[0];
      formError.value = Array.isArray(first) ? first[0] : String(first);
    } else {
      formError.value =
          e.response?.data?.message ||
          e.message ||
          'Не вдалося створити пацієнта';
    }
  } finally {
    creating.value = false;
  }
};
const validatePhone = (event) => {
  // Замінюємо все, що не є цифрою, плюсом, дужками або дефісом на пустоту
  let val = event.target.value.replace(/[^0-9+\-() ]/g, '');
  form.value.phone = val;
  // Синхронізуємо значення в полі (інколи v-model не встигає)
  event.target.value = val;
};

onMounted(async () => {
  await loadClinics();
  await loadPatients();
});

watch(
  () => totalItems.value,
  () => {
    if (currentPage.value > pageCount.value) {
      currentPage.value = pageCount.value;
    }
  }
);
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold">Пацієнти</h1>
        <p class="text-sm text-text/70">
          Реєстрація пацієнтів та швидкий пошук по ПІБ / телефону / email.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
            type="button"
            class="px-3 py-2 rounded-lg border border-emerald-500/50 text-sm text-emerald-300 hover:bg-emerald-500/10"
            @click="showForm = !showForm"
        >
          {{ showForm ? 'Приховати форму' : 'Новий пацієнт' }}
        </button>
        <button
            type="button"
            class="px-3 py-2 rounded-lg border border-border/80 text-sm hover:bg-card/80"
            @click="loadPatients"
        >
          Оновити
        </button>
      </div>
    </div>

    <!-- фільтри -->
    <div class="flex flex-wrap items-center gap-3">
      <div class="flex items-center gap-2">
        <input
            v-model="search"
            type="text"
            placeholder="Пошук (ПІБ / телефон / email)"
            class="w-64 max-w-full rounded-lg bg-card border border-border/80 px-3 py-2 text-sm"
            @keyup.enter="loadPatients"
        />
        <button
            type="button"
            class="px-3 py-2 rounded-lg border border-border/80 text-sm hover:bg-card/80"
            @click="loadPatients"
        >
          Знайти
        </button>
      </div>

      <label v-if="!isDoctor" class="text-sm text-text/80">
        Клініка:
        <select
            v-model="selectedClinicFilter"
            @change="loadPatients"
            class="ml-2 rounded-lg bg-card border border-border/80 px-2 py-1 text-sm"
        >
          <option value="">Усі</option>
          <option v-for="clinic in clinics" :key="clinic.id" :value="clinic.id">
            {{ clinic.name }} ({{ clinic.city || '—' }})
          </option>
        </select>
      </label>
      <div v-else class="text-sm text-text/80">
        Клініка: <span class="font-semibold">{{ doctorClinic?.name || '—' }}</span>
      </div>
    </div>

    <!-- форма створення -->
    <div
        v-if="showForm"
        class="rounded-xl bg-card/60 shadow-sm shadow-black/10 dark:shadow-black/40 p-4 space-y-4"
    >
      <h2 class="text-lg font-semibold">Новий пацієнт</h2>

      <div v-if="formError" class="text-sm text-red-400">
        ❌ {{ formError }}
      </div>

      <form class="grid gap-4 md:grid-cols-2" @submit.prevent="createPatient">
        <div v-if="!isDoctor">
          <label class="block text-xs uppercase tracking-wide text-text/70 mb-1">
            Клініка *
          </label>
          <select
              v-model="form.clinic_id"
              required
              class="w-full rounded-lg bg-card border border-border/80 px-3 py-2 text-sm"
          >
            <option value="" disabled>Оберіть клініку</option>
            <option v-for="clinic in clinics" :key="clinic.id" :value="clinic.id">
              {{ clinic.name }} ({{ clinic.city || '—' }})
            </option>
          </select>
        </div>
        <div v-else>
          <label class="block text-xs uppercase tracking-wide text-text/70 mb-1">
            Клініка
          </label>
          <div class="w-full rounded-lg bg-card shadow-sm shadow-black/10 dark:shadow-black/40 px-3 py-2 text-sm text-text/90">
            {{ doctorClinic?.name || '—' }}
          </div>
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
              placeholder="Петренко Олег Олегович"
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

        <div>
          <label class="block text-xs uppercase tracking-wide text-text/70 mb-1">
            Телефон
          </label>
          <input
              v-model="form.phone"
              @input="validatePhone"
              type="tel"
              class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-text/90"
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

        <div class="md:col-span-2">
          <label class="block text-xs uppercase tracking-wide text-text/70 mb-1">
            Адреса
          </label>
          <input
              v-model="form.address"
              type="text"
              class="w-full rounded-lg bg-card border border-border/80 px-3 py-2 text-sm"
              placeholder="місто, вулиця, будинок"
          />
        </div>

        <div class="md:col-span-2">
          <label class="block text-xs uppercase tracking-wide text-text/70 mb-1">
            Примітка
          </label>
          <textarea
              v-model="form.note"
              rows="2"
              class="w-full rounded-lg bg-card border border-border/80 px-3 py-2 text-sm"
              placeholder="Коментар адміністратора, особливості пацієнта..."
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

    <!-- список -->
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
          class="overflow-hidden rounded-xl bg-card/40 shadow-sm shadow-black/10 dark:shadow-black/40"
      >
        <BaseGrid :columns="gridColumns" :data="pagedPatients" />
      </div>

      <BasePagination
        v-show="totalItems > pageSize"
        v-model:currentPage="currentPage"
        :total-items="totalItems"
        :items-per-page="pageSize"
        class="mt-4"
      />
    </div>
  </div>
</template>
