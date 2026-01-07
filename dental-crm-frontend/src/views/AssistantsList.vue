<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue'
import { debounce } from 'lodash-es'
import roleApi from '../services/roleApi'
import assistantApi from '../services/assistantApi'
import clinicApi from '../services/clinicApi'
import { useAuth } from '../composables/useAuth'
import SearchField from '../components/SearchField.vue'
import { UIButton, UIDropdown, UIDrawer } from '../ui'
import { useRouter } from 'vue-router'
import AssistantQuickView from '../components/AssistantQuickView.vue'

const { user } = useAuth()
const router = useRouter()

const clinics = ref([])
const selectedClinicId = ref('')
const assistants = ref([])
const loading = ref(false)
const error = ref(null)

const showForm = ref(false)
const creating = ref(false)
const createError = ref(null)
const editError = ref(null)
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

const totalItems = computed(() => pagination.value.total || assistants.value.length)
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

const pagedAssistants = computed(() => {
  if (isServerPaginated.value) {
    return assistants.value
  }
  const start = (safeCurrentPage.value - 1) * perPage
  return assistants.value.slice(start, start + perPage)
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

const form = ref({
  clinic_id: '',
  first_name: '',
  last_name: '',
  email: '',
  password: ''
})

const search = ref('')
let requestSeq = 0

const loadClinics = async () => {
  if (user.value?.global_role === 'super_admin') {
    const { data } = await clinicApi.list()
    clinics.value = data.data ?? data
  } else {
    const { data } = await clinicApi.listMine()
    clinics.value = (data.clinics ?? []).map((clinic) => ({
      id: clinic.clinic_id,
      name: clinic.clinic_name
    }))
  }

  // за замовчуванням показуємо всіх доступних асистентів по всіх клініках
  if (!selectedClinicId.value) {
    selectedClinicId.value = ''
  }
}

const fetchAssistants = async () => {
  const currentSeq = ++requestSeq
  loading.value = true
  error.value = null
  try {
    const params: Record<string, any> = {
      page: currentPage.value,
      per_page: perPage,
      role: 'assistant' // Filter by assistant role
    }
    if (selectedClinicId.value) {
      params.clinic_id = selectedClinicId.value
    }
    if (search.value.trim()) params.search = search.value.trim()

    const { data } = await roleApi.listUsers(params)

    // Ignore stale responses
    if (currentSeq !== requestSeq) return

    const items = data.data ?? data
    // Transform users to assistant format
    assistants.value = items.map((user: any) => ({
      id: user.id,
      name: user.name,
      first_name: user.first_name,
      last_name: user.last_name,
      email: user.email,
      full_name:
        user.first_name && user.last_name
          ? `${user.first_name} ${user.last_name}`.trim()
          : user.name || user.email,
      clinics: user.clinics || []
    }))
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
  } catch (err) {
    // Ignore stale responses
    if (currentSeq !== requestSeq) return

    console.error(err)
    error.value = 'Не вдалося завантажити асистентів'
  } finally {
    // Only update loading if this is still the latest request
    if (currentSeq === requestSeq) {
      loading.value = false
    }
  }
}

const debouncedFetchAssistants = debounce(fetchAssistants, 300)

const selectedAssistantId = ref<number | null>(null)
const drawerOpen = ref(false)
const quickActions = [
  { id: 'note', label: 'Додати нотатку', icon: '📝' },
  { id: 'message', label: 'Надіслати повідомлення', icon: '💬' }
]

const activeAssistant = computed(
  () => pagedAssistants.value.find((a: any) => a.id === selectedAssistantId.value) || null
)

const resetForm = () => {
  form.value = {
    clinic_id: selectedClinicId.value || clinics.value[0]?.id || '',
    first_name: '',
    last_name: '',
    email: '',
    password: ''
  }
}

const toggleForm = () => {
  showForm.value = !showForm.value
  if (showForm.value) {
    resetForm()
  }
}

const createAssistant = async () => {
  creating.value = true
  createError.value = null
  try {
    await assistantApi.create({
      ...form.value,
      clinic_id: form.value.clinic_id || selectedClinicId.value
    })
    showForm.value = false
    resetForm()
    await fetchAssistants()
  } catch (err: any) {
    console.error(err)
    createError.value = err.response?.data?.message || 'Помилка створення асистента'
  } finally {
    creating.value = false
  }
}

const deleteAssistant = async (assistant: any) => {
  if (!window.confirm(`Видалити асистента "${assistantName(assistant)}"?`)) return
  editError.value = null
  try {
    await assistantApi.delete(assistant.id)
    await fetchAssistants()
  } catch (err: any) {
    console.error(err)
    editError.value = err.response?.data?.message || 'Не вдалося видалити асистента'
  }
}

const assistantClinic = (assistant) => {
  return assistant.clinics?.[0]?.name || '—'
}

const assistantName = (assistant) => {
  return (
    assistant.full_name ||
    assistant.name ||
    `${assistant.first_name || ''} ${assistant.last_name || ''}`.trim() ||
    assistant.email
  )
}

const openAssistant = (assistant: any) => {
  selectedAssistantId.value = assistant.id
  drawerOpen.value = true
}

const handleManageClick = (assistant: any, event: Event) => {
  event.stopPropagation()
  openAssistant(assistant)
}

const goToDetails = (id: number | string) => {
  router.push({ name: 'assistant-details', params: { id } })
}

const handleAction = (assistant: any, actionId: string) => {
  console.log(`Assistant action`, { assistantId: assistant.id, actionId })
}

const handleDelete = (assistant: any) => {
  deleteAssistant(assistant)
}

watch(selectedClinicId, () => {
  currentPage.value = 1
  debouncedFetchAssistants()
})

// Live search: reset page and trigger search on search change
watch(search, () => {
  currentPage.value = 1
  debouncedFetchAssistants()
})

onMounted(async () => {
  await loadClinics()
  // fetchAssistants will be called by watch when selectedClinicId is set in loadClinics
  // Only call directly if selectedClinicId was not set
  if (!selectedClinicId.value && clinics.value.length) {
    await fetchAssistants()
  }
})

const goToPage = async (page) => {
  const nextPage = Math.min(Math.max(page, 1), pageCount.value)
  if (nextPage === currentPage.value) return
  currentPage.value = nextPage
  await fetchAssistants()
}
</script>

<template>
  <div class="space-y-6">
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-text">Асистенти</h1>
        <p class="text-sm text-text/70">Створення та перегляд асистентів клініки.</p>
      </div>
      <UIButton variant="secondary" size="sm" @click="toggleForm">
        {{ showForm ? 'Приховати форму' : 'Новий асистент' }}
      </UIButton>
    </header>

    <div class="flex flex-wrap items-center gap-3">
      <SearchField
        v-model="search"
        id="assistants-search"
        placeholder="Пошук (імʼя / прізвище / email)"
      />
      <label for="assistants-clinic-filter" class="text-xs uppercase text-text/70">Клініка</label>
      <select
        v-model="selectedClinicId"
        id="assistants-clinic-filter"
        name="clinic_id"
        class="rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
      >
        <option value="">Всі клініки</option>
        <option v-for="clinic in clinics" :key="clinic.id" :value="clinic.id">
          {{ clinic.name }}
        </option>
      </select>
    </div>

    <section
      v-if="showForm"
      class="rounded-xl bg-card/60 shadow-sm shadow-black/10 dark:shadow-black/40 p-4 space-y-4"
    >
      <h2 class="text-sm font-semibold text-text/90">Додати асистента</h2>

      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label for="assistant-create-clinic" class="block text-xs uppercase text-text/70 mb-1"
            >Клініка</label
          >
          <select
            v-model="form.clinic_id"
            id="assistant-create-clinic"
            name="clinic_id"
            class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
          >
            <option v-for="clinic in clinics" :key="clinic.id" :value="clinic.id">
              {{ clinic.name }}
            </option>
          </select>
        </div>

        <div>
          <label for="assistant-create-first-name" class="block text-xs uppercase text-text/70 mb-1"
            >Ім'я</label
          >
          <input
            v-model="form.first_name"
            id="assistant-create-first-name"
            name="first_name"
            type="text"
            class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
          />
        </div>

        <div>
          <label for="assistant-create-last-name" class="block text-xs uppercase text-text/70 mb-1"
            >Прізвище</label
          >
          <input
            v-model="form.last_name"
            id="assistant-create-last-name"
            name="last_name"
            type="text"
            class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
          />
        </div>

        <div>
          <label for="assistant-create-email" class="block text-xs uppercase text-text/70 mb-1"
            >Email</label
          >
          <input
            v-model="form.email"
            id="assistant-create-email"
            name="email"
            type="email"
            class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
          />
        </div>

        <div>
          <label for="assistant-create-password" class="block text-xs uppercase text-text/70 mb-1"
            >Пароль</label
          >
          <input
            v-model="form.password"
            id="assistant-create-password"
            name="password"
            type="password"
            class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
          />
        </div>
      </div>

      <div class="flex items-center justify-between gap-3">
        <span v-if="createError" class="text-sm text-red-400">❌ {{ createError }}</span>
        <button
          class="ml-auto px-4 py-2 rounded-lg bg-emerald-500 text-text text-sm font-semibold hover:bg-emerald-400 disabled:opacity-60"
          :disabled="creating"
          @click="createAssistant"
        >
          {{ creating ? 'Створення...' : 'Створити' }}
        </button>
      </div>
    </section>

    <section class="rounded-xl bg-card/40 shadow-sm shadow-black/10 dark:shadow-black/40 p-4">
      <div v-if="loading" class="text-sm text-text/70">Завантаження...</div>
      <div v-else-if="error" class="text-sm text-red-400">{{ error }}</div>
      <div v-else-if="editError" class="text-sm text-red-400">{{ editError }}</div>
      <div v-else-if="!assistants.length" class="text-sm text-text/70">Немає асистентів.</div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-card/80 border-b border-border">
            <tr class="text-left text-text/70">
              <th class="px-4 py-2">Ім'я</th>
              <th class="px-4 py-2">Клініка</th>
              <th class="px-4 py-2">Email</th>
              <th class="px-4 py-2">Статус</th>
              <th class="px-4 py-2 text-right">Дії</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="assistant in pagedAssistants"
              :key="assistant.id"
              class="border-t border-border/60 hover:bg-card/80 cursor-pointer transition-colors"
              :class="assistant.id === selectedAssistantId ? 'bg-emerald-500/5' : ''"
              @click="openAssistant(assistant)"
            >
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  <div
                    class="w-10 h-10 rounded-full bg-gradient-to-br from-emerald-500 to-blue-500 flex items-center justify-center text-text font-bold shadow-md overflow-hidden"
                  >
                    <span>{{ assistantName(assistant)?.[0] || '?' }}</span>
                  </div>
                  <div>
                    <p class="font-semibold text-text">{{ assistantName(assistant) }}</p>
                    <p class="text-xs text-text/60">{{ assistant.email }}</p>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3 text-text/80">
                {{ assistantClinic(assistant) }}
              </td>
              <td class="px-4 py-3 text-text/70">{{ assistant.email }}</td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-emerald-500/20 text-emerald-300"
                >
                  Активний
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-2" @click.stop>
                  <UIDropdown
                    :items="quickActions"
                    placement="bottom-end"
                    @select="(id) => handleAction(assistant, id)"
                  >
                    <template #trigger="{ toggle }">
                      <UIButton variant="secondary" size="sm" @click="toggle">Дії ▾</UIButton>
                    </template>
                  </UIDropdown>
                  <UIButton
                    variant="primary"
                    size="sm"
                    @click="handleManageClick(assistant, $event)"
                  >
                    Керувати
                  </UIButton>
                  <UIButton variant="ghost" size="sm" @click="goToDetails(assistant.id)">
                    Деталі
                  </UIButton>
                </div>
              </td>
            </tr>
            <tr v-if="!pagedAssistants.length">
              <td colspan="5" class="px-4 py-4 text-sm text-text/70">Нічого не знайдено.</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div
        v-if="pageCount > 1"
        class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm text-text/70"
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

    <UIDrawer
      v-model="drawerOpen"
      title="Асистент"
      width="520px"
      @close="selectedAssistantId = null"
    >
      <AssistantQuickView
        :assistant="activeAssistant"
        @close="drawerOpen = false"
        @details="goToDetails"
      />
    </UIDrawer>
  </div>
</template>
