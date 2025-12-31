<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import apiClient from '../services/apiClient'
import clinicApi from '../services/clinicApi'
import { useAuth } from '../composables/useAuth'

const { user } = useAuth()
const canManageClinics = computed(() => user.value?.global_role === 'super_admin')

const clinics = ref([])
const loading = ref(true)
const error = ref(null)
const pageSize = 10
const currentPage = ref(1)

const gridData = computed(() => clinics.value)
const totalItems = computed(() => gridData.value.length)
const pageCount = computed(() => Math.max(1, Math.ceil(totalItems.value / pageSize)))
const safeCurrentPage = computed(() => Math.min(Math.max(currentPage.value, 1), pageCount.value))
const pagedClinics = computed(() => {
  const start = (currentPage.value - 1) * pageSize
  return gridData.value.slice(start, start + pageSize)
})

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

// --- стан форми створення ---
const showForm = ref(false)
const creating = ref(false)
const formError = ref(null)

const form = ref({
  name: '',
  legal_name: '',
  city: '',
  address: '',
  phone: '',
  email: '',
  website: ''
})

// завантаження списку клінік
const loadClinics = async () => {
  loading.value = true
  error.value = null

  try {
    const { data } = await clinicApi.list()
    // Handle paginated response
    clinics.value = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
    currentPage.value = 1
  } catch (e) {
    console.error(e)
    error.value = e.response?.data?.message || e.message || 'Помилка завантаження клінік'
    clinics.value = []
  } finally {
    loading.value = false
  }
}

// створення нової клініки
const createClinic = async () => {
  formError.value = null
  creating.value = true

  try {
    const { data } = await apiClient.post('/clinics', form.value)

    // Очищаємо кеш, щоб наступний запит отримав актуальні дані
    clinicApi.clearCache()

    // додаємо в список без додаткового запиту
    clinics.value.push(data)

    // чистимо форму
    form.value = {
      name: '',
      legal_name: '',
      city: '',
      address: '',
      phone: '',
      email: '',
      website: ''
    }

    showForm.value = false
  } catch (e) {
    console.error(e)
    if (e.response?.data?.errors) {
      // беремо першу помилку валідації
      const first = Object.values(e.response.data.errors)[0]
      formError.value = Array.isArray(first) ? first[0] : String(first)
    } else {
      formError.value = e.response?.data?.message || e.message || 'Не вдалося створити клініку'
    }
  } finally {
    creating.value = false
  }
}

onMounted(loadClinics)

const goToPage = (page) => {
  const nextPage = Math.min(Math.max(page, 1), pageCount.value)
  if (nextPage === currentPage.value) return
  currentPage.value = nextPage
}

watch(
  () => totalItems.value,
  () => {
    if (currentPage.value > pageCount.value) {
      currentPage.value = pageCount.value
    }
  }
)
</script>

<template>
  <div class="space-y-6">
    <!-- шапка сторінки -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold">Клініки</h1>
        <p class="text-sm text-text/70">Дані тягнемо з Laravel API (<code>/api/clinics</code>).</p>
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

      <div v-if="formError" class="text-sm text-red-400">❌ {{ formError }}</div>

      <form class="grid gap-4 md:grid-cols-2" @submit.prevent="createClinic">
        <div class="md:col-span-2">
          <label for="clinic-create-name" class="block text-xs uppercase tracking-wide text-text/70 mb-1">
            Назва *
          </label>
          <input
            v-model="form.name"
            id="clinic-create-name"
            name="name"
            type="text"
            required
            class="w-full rounded-lg bg-card border border-border/80 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
            placeholder="Dental Plus"
          />
        </div>

        <div class="md:col-span-2">
          <label
            for="clinic-create-legal-name"
            class="block text-xs uppercase tracking-wide text-text/70 mb-1"
          >
            Юридична назва
          </label>
          <input
            v-model="form.legal_name"
            id="clinic-create-legal-name"
            name="legal_name"
            type="text"
            class="w-full rounded-lg bg-card border border-border/80 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
            placeholder="ТОВ «Дентал Плюс»"
          />
        </div>

        <div>
          <label for="clinic-create-city" class="block text-xs uppercase tracking-wide text-text/70 mb-1">
            Місто
          </label>
          <input
            v-model="form.city"
            id="clinic-create-city"
            name="city"
            type="text"
            class="w-full rounded-lg bg-card border border-border/80 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
            placeholder="Черкаси"
          />
        </div>

        <div>
          <label for="clinic-create-address" class="block text-xs uppercase tracking-wide text-text/70 mb-1">
            Адреса
          </label>
          <input
            v-model="form.address"
            id="clinic-create-address"
            name="address"
            type="text"
            class="w-full rounded-lg bg-card border border-border/80 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
            placeholder="вул. Прикладна, 10"
          />
        </div>

        <div>
          <label for="clinic-create-phone" class="block text-xs uppercase tracking-wide text-text/70 mb-1">
            Телефон
          </label>
          <input
            v-model="form.phone"
            id="clinic-create-phone"
            name="phone"
            type="text"
            class="w-full rounded-lg bg-card border border-border/80 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
            placeholder="+380..."
          />
        </div>

        <div>
          <label for="clinic-create-email" class="block text-xs uppercase tracking-wide text-text/70 mb-1">
            Email
          </label>
          <input
            v-model="form.email"
            id="clinic-create-email"
            name="email"
            type="email"
            class="w-full rounded-lg bg-card border border-border/80 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
            placeholder="clinic@example.com"
          />
        </div>

        <div class="md:col-span-2">
          <label for="clinic-create-website" class="block text-xs uppercase tracking-wide text-text/70 mb-1">
            Сайт
          </label>
          <input
            v-model="form.website"
            id="clinic-create-website"
            name="website"
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
    <div v-if="loading" class="text-text/80">Завантаження клінік...</div>

    <div v-else-if="error" class="text-red-400">❌ {{ error }}</div>

    <div v-else>
      <div v-if="clinics.length === 0" class="text-text/70 text-sm">
        Клінік поки немає. Додай першу через форму вище 🦷
      </div>

      <div
        v-else
        class="overflow-hidden rounded-xl bg-card/40 shadow-sm shadow-black/10 dark:shadow-black/40"
      >
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-border/60 text-sm">
            <thead class="bg-card/60 text-left text-xs uppercase tracking-wide text-text/60">
              <tr>
                <th class="px-4 py-3">ID</th>
                <th class="px-4 py-3">Назва</th>
                <th class="px-4 py-3">Місто</th>
                <th class="px-4 py-3">Адреса</th>
                <th class="px-4 py-3">Телефон</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border/40">
              <tr
                v-for="clinic in pagedClinics"
                :key="clinic.id"
                class="transition hover:bg-card/70"
              >
                <td class="px-4 py-3 text-text/80">
                  {{ clinic.id }}
                </td>
                <td class="px-4 py-3 font-medium text-text">
                  {{ clinic.name }}
                </td>
                <td class="px-4 py-3 text-text/80">
                  {{ clinic.city || '—' }}
                </td>
                <td class="px-4 py-3 text-text/80">
                  {{ clinic.address || '—' }}
                </td>
                <td class="px-4 py-3 text-text/80">
                  {{ clinic.phone || '—' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div
        v-if="pageCount > 1"
        class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm text-text/70"
      >
        <p>
          Показано {{ (safeCurrentPage - 1) * pageSize + 1 }}–{{
            Math.min(safeCurrentPage * pageSize, totalItems)
          }}
          з {{ totalItems }}
        </p>
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
    </div>
  </div>
</template>
