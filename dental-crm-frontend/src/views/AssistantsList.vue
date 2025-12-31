<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue'
import assistantApi from '../services/assistantApi'
import clinicApi from '../services/clinicApi'
import { useAuth } from '../composables/useAuth'

const { user } = useAuth()

const clinics = ref([])
const selectedClinicId = ref('')
const assistants = ref([])
const loading = ref(false)
const error = ref(null)

const showForm = ref(false)
const creating = ref(false)
const createError = ref(null)
const editError = ref(null)
const editingAssistantId = ref(null)
const editForm = ref({
  first_name: '',
  last_name: '',
  email: '',
  password: ''
})
const savingEdit = ref(false)
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

  if (!selectedClinicId.value && clinics.value.length) {
    selectedClinicId.value = clinics.value[0].id
  }
}

const fetchAssistants = async () => {
  if (!selectedClinicId.value) return
  loading.value = true
  error.value = null
  try {
    const { data } = await assistantApi.list({
      clinic_id: selectedClinicId.value,
      page: currentPage.value,
      per_page: perPage
    })
    const items = data.data ?? data
    assistants.value = items
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
    console.error(err)
    error.value = 'Не вдалося завантажити асистентів'
  } finally {
    loading.value = false
  }
}

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
  } catch (err) {
    console.error(err)
    createError.value = err.response?.data?.message || 'Помилка створення асистента'
  } finally {
    creating.value = false
  }
}

const startEdit = (assistant) => {
  editingAssistantId.value = assistant.id
  editError.value = null
  editForm.value = {
    first_name: assistant.first_name || '',
    last_name: assistant.last_name || '',
    email: assistant.email || '',
    password: ''
  }
}

const cancelEdit = () => {
  editingAssistantId.value = null
  editError.value = null
}

const updateAssistant = async (assistant) => {
  savingEdit.value = true
  editError.value = null
  try {
    const payload = {
      first_name: editForm.value.first_name,
      last_name: editForm.value.last_name,
      email: editForm.value.email
    }
    if (editForm.value.password) {
      payload.password = editForm.value.password
    }
    await assistantApi.update(assistant.id, payload)
    await fetchAssistants()
    editingAssistantId.value = null
  } catch (err) {
    console.error(err)
    editError.value = err.response?.data?.message || 'Не вдалося оновити асистента'
  } finally {
    savingEdit.value = false
  }
}

const deleteAssistant = async (assistant) => {
  if (!window.confirm(`Видалити асистента "${assistantName(assistant)}"?`)) return
  editError.value = null
  try {
    await assistantApi.delete(assistant.id)
    await fetchAssistants()
  } catch (err) {
    console.error(err)
    editError.value = err.response?.data?.message || 'Не вдалося видалити асистента'
  }
}

const assistantName = (assistant) => {
  return (
    assistant.full_name ||
    assistant.name ||
    `${assistant.first_name || ''} ${assistant.last_name || ''}`.trim()
  )
}

const assistantClinic = (assistant) => {
  return assistant.clinics?.[0]?.name || '—'
}

watch(selectedClinicId, async () => {
  currentPage.value = 1
  await fetchAssistants()
})

onMounted(async () => {
  await loadClinics()
  await fetchAssistants()
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
        <h1 class="text-2xl font-semibold">Асистенти</h1>
        <p class="text-sm text-text/70">Створення та перегляд асистентів клініки.</p>
      </div>
      <button
        class="px-4 py-2 rounded-lg bg-emerald-500 text-text text-sm font-semibold hover:bg-emerald-400"
        @click="toggleForm"
      >
        {{ showForm ? 'Приховати форму' : 'Новий асистент' }}
      </button>
    </header>

    <div class="flex flex-wrap items-center gap-3">
      <label class="text-xs uppercase text-text/70">Клініка</label>
      <select
        v-model="selectedClinicId"
        class="rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
      >
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
          <label class="block text-xs uppercase text-text/70 mb-1">Клініка</label>
          <select
            v-model="form.clinic_id"
            class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
          >
            <option v-for="clinic in clinics" :key="clinic.id" :value="clinic.id">
              {{ clinic.name }}
            </option>
          </select>
        </div>

        <div>
          <label class="block text-xs uppercase text-text/70 mb-1">Ім'я</label>
          <input
            v-model="form.first_name"
            type="text"
            class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
          />
        </div>

        <div>
          <label class="block text-xs uppercase text-text/70 mb-1">Прізвище</label>
          <input
            v-model="form.last_name"
            type="text"
            class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
          />
        </div>

        <div>
          <label class="block text-xs uppercase text-text/70 mb-1">Email</label>
          <input
            v-model="form.email"
            type="email"
            class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
          />
        </div>

        <div>
          <label class="block text-xs uppercase text-text/70 mb-1">Пароль</label>
          <input
            v-model="form.password"
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
          <thead class="text-text/70 text-xs uppercase">
            <tr>
              <th class="text-left py-2 px-3">Ім'я</th>
              <th class="text-left py-2 px-3">Email</th>
              <th class="text-left py-2 px-3">Клініка</th>
              <th class="text-right py-2 px-3">Дії</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="assistant in pagedAssistants"
              :key="assistant.id"
              class="border-t border-border"
            >
              <td class="py-2 px-3">
                <div v-if="editingAssistantId === assistant.id" class="grid gap-2">
                  <input
                    v-model="editForm.first_name"
                    type="text"
                    placeholder="Ім'я"
                    class="w-full rounded-md bg-bg border border-border/80 px-2 py-1 text-sm text-text"
                  />
                  <input
                    v-model="editForm.last_name"
                    type="text"
                    placeholder="Прізвище"
                    class="w-full rounded-md bg-bg border border-border/80 px-2 py-1 text-sm text-text"
                  />
                </div>
                <span v-else class="text-text/90">{{ assistantName(assistant) }}</span>
              </td>
              <td class="py-2 px-3">
                <div v-if="editingAssistantId === assistant.id" class="grid gap-2">
                  <input
                    v-model="editForm.email"
                    type="email"
                    placeholder="Email"
                    class="w-full rounded-md bg-bg border border-border/80 px-2 py-1 text-sm text-text"
                  />
                  <input
                    v-model="editForm.password"
                    type="password"
                    placeholder="Новий пароль (опційно)"
                    class="w-full rounded-md bg-bg border border-border/80 px-2 py-1 text-sm text-text"
                  />
                </div>
                <span v-else class="text-text/70">{{ assistant.email }}</span>
              </td>
              <td class="py-2 px-3 text-text/70">{{ assistantClinic(assistant) }}</td>
              <td class="py-2 px-3 text-right text-xs">
                <div v-if="editingAssistantId === assistant.id" class="flex justify-end gap-3">
                  <button
                    class="text-emerald-300 hover:text-emerald-200 disabled:opacity-60"
                    :disabled="savingEdit"
                    @click="updateAssistant(assistant)"
                  >
                    {{ savingEdit ? 'Збереження...' : 'Зберегти' }}
                  </button>
                  <button class="text-text/70 hover:text-text/90" @click="cancelEdit">
                    Скасувати
                  </button>
                </div>
                <div v-else class="flex justify-end gap-3">
                  <button
                    class="text-emerald-300 hover:text-emerald-200"
                    @click="startEdit(assistant)"
                  >
                    Редагувати
                  </button>
                  <button
                    class="text-red-400 hover:text-red-300"
                    @click="deleteAssistant(assistant)"
                  >
                    Видалити
                  </button>
                </div>
              </td>
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
  </div>
</template>
