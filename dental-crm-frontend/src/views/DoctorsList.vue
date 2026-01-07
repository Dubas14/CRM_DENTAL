<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { debounce } from 'lodash-es'
import apiClient from '../services/apiClient'
import clinicApi from '../services/clinicApi'
import doctorApi from '../services/doctorApi'
import { useAuth } from '../composables/useAuth'
import { RouterLink } from 'vue-router'
import SearchField from '../components/SearchField.vue'

const doctors = ref([])
const loading = ref(false)
const error = ref(null)

const clinics = ref([])
const loadingClinics = ref(false)

const form = ref({
  clinic_id: '',
  full_name: '',
  specialization: '',
  bio: '',
  color: '#22c55e',
  email: '',
  password: ''
})

const creating = ref(false)
const createError = ref(null)

// 👇 додали
const showForm = ref(false)
const perPage = 12
const currentPage = ref(1)
const pagination = ref({
  currentPage: 1,
  lastPage: 1,
  total: 0,
  perPage,
  from: 0,
  to: 0
})
const isServerPaginated = ref(false)

const totalItems = computed(() => pagination.value.total || doctors.value.length)
const pageCount = computed(() => {
  if (isServerPaginated.value) {
    return pagination.value.lastPage || 1
  }
  return Math.max(1, Math.ceil(totalItems.value / perPage))
})
const safeCurrentPage = computed(() => Math.min(Math.max(currentPage.value, 1), pageCount.value))

const pagesToShow = computed(() => {
  const visible = 5
  const half = Math.floor(visible / 2)
  let start = Math.max(1, safeCurrentPage.value - half)
  const end = Math.min(pageCount.value, start + visible - 1)

  if (end - start + 1 < visible) {
    start = Math.max(1, end - visible + 1)
  }

  return Array.from({ length: end - start + 1 }, (_, idx) => start + idx)
})

const pagedDoctors = computed(() => {
  if (isServerPaginated.value) {
    return doctors.value
  }
  const start = (safeCurrentPage.value - 1) * perPage
  return doctors.value.slice(start, start + perPage)
})

const displayFrom = computed(() => {
  if (!totalItems.value) return 0
  if (isServerPaginated.value) return pagination.value.from ?? 0
  return (safeCurrentPage.value - 1) * perPage + 1
})

const displayTo = computed(() => {
  if (!totalItems.value) return 0
  if (isServerPaginated.value) return pagination.value.to ?? 0
  return Math.min(safeCurrentPage.value * perPage, totalItems.value)
})

const { user } = useAuth()

const canCreateDoctor = computed(() => user.value?.global_role === 'super_admin')

const search = ref('')
let requestSeq = 0

const fetchClinics = async () => {
  loadingClinics.value = true
  try {
    const { data } = await clinicApi.list()
    clinics.value = data.data ?? data
  } finally {
    loadingClinics.value = false
  }
}

const fetchDoctors = async () => {
  const currentSeq = ++requestSeq
  loading.value = true
  error.value = null
  try {
    const params: Record<string, any> = { page: currentPage.value, per_page: perPage }
    if (search.value.trim()) params.search = search.value.trim()

    const { data } = await doctorApi.list(params)

    // Ignore stale responses
    if (currentSeq !== requestSeq) return

    const items = data.data ?? data
    doctors.value = items
    const hasPagination =
      data?.current_page !== undefined || data?.last_page !== undefined || data?.total !== undefined
    isServerPaginated.value = hasPagination
    const fallbackTotal = data?.total ?? items.length
    const fallbackLastPage = Math.max(1, Math.ceil(fallbackTotal / perPage))
    pagination.value = {
      currentPage: data?.current_page ?? currentPage.value,
      lastPage: data?.last_page ?? fallbackLastPage,
      total: fallbackTotal,
      perPage: data?.per_page ?? perPage,
      from: data?.from ?? (items.length ? (currentPage.value - 1) * perPage + 1 : 0),
      to: data?.to ?? (items.length ? Math.min(currentPage.value * perPage, fallbackTotal) : 0)
    }

    if (!hasPagination && currentPage.value > pagination.value.lastPage) {
      currentPage.value = pagination.value.lastPage
    } else {
      currentPage.value = pagination.value.currentPage
    }
  } catch (e) {
    // Ignore stale responses
    if (currentSeq !== requestSeq) return

    console.error(e)
    error.value = 'Не вдалося завантажити лікарів'
  } finally {
    // Only update loading if this is still the latest request
    if (currentSeq === requestSeq) {
      loading.value = false
    }
  }
}

const debouncedFetchDoctors = debounce(fetchDoctors, 300)

const resetForm = () => {
  form.value = {
    clinic_id: clinics.value[0]?.id || '',
    full_name: '',
    specialization: '',
    bio: '',
    color: '#22c55e',
    email: '',
    password: ''
  }
}

// при відкритті форми одразу чистимо її
const toggleForm = () => {
  showForm.value = !showForm.value
  if (showForm.value) {
    resetForm()
  }
}

const cancelCreate = () => {
  resetForm()
  showForm.value = false
}

const createDoctor = async () => {
  creating.value = true
  createError.value = null
  try {
    await apiClient.post('/doctors', form.value)
    resetForm()
    showForm.value = false
    await fetchDoctors()
  } catch (e) {
    console.error(e)
    if (e.response?.data?.errors) {
      const firstKey = Object.keys(e.response.data.errors)[0]
      createError.value = e.response.data.errors[firstKey][0]
    } else {
      createError.value = e.response?.data?.message || 'Помилка створення лікаря'
    }
  } finally {
    creating.value = false
  }
}

onMounted(async () => {
  await fetchClinics()
  if (!form.value.clinic_id && clinics.value.length) {
    form.value.clinic_id = clinics.value[0].id
  }
  await fetchDoctors()
})

// Live search: reset page and trigger search on search change
watch(search, () => {
  currentPage.value = 1
  debouncedFetchDoctors()
})

const goToPage = async (page) => {
  const nextPage = Math.min(Math.max(page, 1), pageCount.value)
  if (nextPage === currentPage.value) return
  currentPage.value = nextPage
  await fetchDoctors()
}
</script>

<template>
  <div class="space-y-6">
    <header class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold">Лікарі</h1>
        <p class="text-sm text-text/70">Керування лікарями клінік.</p>
      </div>
      <button
        v-if="canCreateDoctor"
        class="px-4 py-2 rounded-lg bg-emerald-500 text-text text-sm font-semibold hover:bg-emerald-400"
        @click="toggleForm"
      >
        {{ showForm ? 'Приховати форму' : 'Новий лікар' }}
      </button>
    </header>

    <!-- фільтри -->
    <div class="flex flex-wrap items-center gap-3">
      <SearchField v-model="search" id="doctors-search" placeholder="Пошук (ПІБ / спеціалізація)" />
    </div>

    <!-- Форма створення лікаря (тільки для супер адміна) -->
    <section
      v-if="canCreateDoctor && showForm"
      class="rounded-xl bg-card/60 shadow-sm shadow-black/10 dark:shadow-black/40 p-4 space-y-4"
    >
      <h2 class="text-sm font-semibold text-text/90">Створення нового лікаря</h2>

      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label for="doctor-create-clinic" class="block text-xs uppercase text-text/70 mb-1">
            Клініка
          </label>
          <select
            v-model="form.clinic_id"
            id="doctor-create-clinic"
            name="clinic_id"
            class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
          >
            <option v-for="clinic in clinics" :key="clinic.id" :value="clinic.id">
              {{ clinic.name }}
            </option>
          </select>
        </div>

        <div>
          <label for="doctor-create-full-name" class="block text-xs uppercase text-text/70 mb-1">
            ПІБ лікаря
          </label>
          <input
            v-model="form.full_name"
            id="doctor-create-full-name"
            name="full_name"
            type="text"
            class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
          />
        </div>

        <div>
          <label
            for="doctor-create-specialization"
            class="block text-xs uppercase text-text/70 mb-1"
          >
            Спеціалізація
          </label>
          <input
            v-model="form.specialization"
            id="doctor-create-specialization"
            name="specialization"
            type="text"
            class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
          />
        </div>

        <div>
          <label for="doctor-create-email" class="block text-xs uppercase text-text/70 mb-1">
            Email (логін)
          </label>
          <input
            v-model="form.email"
            id="doctor-create-email"
            name="email"
            type="email"
            class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
          />
        </div>

        <div>
          <label for="doctor-create-password" class="block text-xs uppercase text-text/70 mb-1">
            Пароль
          </label>
          <input
            v-model="form.password"
            id="doctor-create-password"
            name="password"
            type="password"
            class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
          />
        </div>

        <div>
          <label for="doctor-create-color" class="block text-xs uppercase text-text/70 mb-1">
            Колір картки
          </label>
          <input
            v-model="form.color"
            id="doctor-create-color"
            name="color"
            type="color"
            class="h-10 w-20 rounded-lg bg-bg border border-border/80 px-2 py-1"
          />
        </div>
      </div>

      <div>
        <label for="doctor-create-bio" class="block text-xs uppercase text-text/70 mb-1">
          Коротке біо
        </label>
        <textarea
          v-model="form.bio"
          id="doctor-create-bio"
          name="bio"
          rows="2"
          class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
        />
      </div>

      <div class="flex items-center justify-between gap-3">
        <div class="text-sm text-red-400" v-if="createError">❌ {{ createError }}</div>
        <div class="flex gap-2 ml-auto">
          <button
            type="button"
            class="px-3 py-2 rounded-lg border border-border/80 text-sm text-text/80 hover:bg-card/80"
            @click="cancelCreate"
          >
            Скасувати
          </button>
          <button
            type="button"
            :disabled="creating"
            class="px-4 py-2 rounded-lg bg-emerald-500 text-text text-sm font-semibold hover:bg-emerald-400 disabled:opacity-60"
            @click="createDoctor"
          >
            {{ creating ? 'Створюємо...' : 'Створити лікаря' }}
          </button>
        </div>
      </div>
    </section>

    <!-- Таблиця лікарів -->
    <section class="rounded-xl bg-card/60 shadow-sm shadow-black/10 dark:shadow-black/40">
      <div class="p-4 border-b border-border flex items-center justify-between">
        <h2 class="text-sm font-semibold text-text/90">Список лікарів</h2>
        <button type="button" class="text-xs text-text/70 hover:text-text/90" @click="fetchDoctors">
          Оновити
        </button>
      </div>

      <div v-if="loading" class="p-4 text-sm text-text/70">Завантаження...</div>
      <div v-else-if="error" class="p-4 text-sm text-red-400">❌ {{ error }}</div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-card/80 border-b border-border">
            <tr class="text-left text-text/70">
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
              v-for="doctor in pagedDoctors"
              :key="doctor.id"
              class="border-t border-border/60 hover:bg-card/80"
            >
              <td class="px-4 py-2 text-text/70">#{{ doctor.id }}</td>
              <td class="px-4 py-2 font-medium">
                {{ doctor.full_name }}
              </td>
              <td class="px-4 py-2 text-text/80">
                {{ doctor.clinic?.name ?? '—' }}
              </td>
              <td class="px-4 py-2 text-text/80">
                {{ doctor.specialization || '—' }}
              </td>
              <td class="px-4 py-2">
                <span
                  class="inline-flex items-center rounded-full px-2 py-0.5 text-xs"
                  :class="
                    doctor.is_active
                      ? 'bg-emerald-500/20 text-emerald-300'
                      : 'bg-card/70 text-text/80'
                  "
                >
                  {{ doctor.is_active ? 'Активний' : 'Не активний' }}
                </span>
              </td>
              <td class="px-4 py-2 text-right">
                <RouterLink
                  :to="{ name: 'doctor-details', params: { id: doctor.id } }"
                  class="inline-flex items-center px-3 py-1 rounded-lg border border-border/80 text-xs text-text/90 hover:bg-card/80"
                >
                  Керувати
                </RouterLink>
              </td>
            </tr>
            <tr v-if="!doctors.length">
              <td colspan="5" class="px-4 py-4 text-sm text-text/70">Лікарів поки немає.</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div
        v-if="pageCount > 1"
        class="mt-4 flex flex-wrap items-center justify-between gap-3 px-4 pb-4 text-sm text-text/70"
      >
        <p>Показано {{ displayFrom }}–{{ displayTo }} з {{ totalItems }}</p>
        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-1.5 text-sm text-text transition hover:bg-card/70 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="safeCurrentPage === 1"
            @click="goToPage(safeCurrentPage - 1)"
          >
            Попередня
          </button>

          <button
            v-for="page in pagesToShow"
            :key="page"
            type="button"
            class="inline-flex min-w-[40px] items-center justify-center rounded-lg border px-3 py-1.5 text-sm transition"
            :class="
              page === safeCurrentPage
                ? 'border-accent bg-accent text-card'
                : 'border-border bg-card text-text hover:bg-card/70'
            "
            @click="goToPage(page)"
          >
            {{ page }}
          </button>

          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-1.5 text-sm text-text transition hover:bg-card/70 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="safeCurrentPage === pageCount"
            @click="goToPage(safeCurrentPage + 1)"
          >
            Наступна
          </button>
        </div>
      </div>
    </section>
  </div>
</template>
