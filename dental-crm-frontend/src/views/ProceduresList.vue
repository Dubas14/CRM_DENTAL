<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue'
import { debounce } from 'lodash-es'
import procedureApi from '../services/procedureApi'
import equipmentApi from '../services/equipmentApi'
import clinicApi from '../services/clinicApi'
import { useAuth } from '../composables/useAuth'

const { user } = useAuth()

const clinics = ref([])
const selectedClinicId = ref('')
const procedures = ref([])
const equipments = ref([])
const loading = ref(false)
const error = ref(null)

const showForm = ref(false)
const creating = ref(false)
const createError = ref(null)
const editError = ref(null)
const editingProcedureId = ref(null)
const editForm = ref({
  name: '',
  category: '',
  duration_minutes: 30,
  requires_room: false,
  requires_assistant: false,
  equipment_id: '',
  steps: []
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

const totalItems = computed(() => pagination.value.total || procedures.value.length)
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

const pagedProcedures = computed(() => {
  if (isServerPaginated.value) {
    return procedures.value
  }
  const start = (safeCurrentPage.value - 1) * perPage
  return procedures.value.slice(start, start + perPage)
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
  name: '',
  category: '',
  duration_minutes: 30,
  requires_room: false,
  requires_assistant: false,
  equipment_id: '',
  steps: []
})

const createStep = (overrides = {}) => ({
  name: '',
  duration_minutes: 30,
  order: 1,
  ...overrides
})

const normalizeSteps = (steps) =>
  (steps || []).map((step, index) => ({
    name: step.name,
    duration_minutes: step.duration_minutes ?? 30,
    order: index + 1
  }))

const addFormStep = () => {
  form.value.steps.push(createStep({ order: form.value.steps.length + 1 }))
}

const removeFormStep = (index) => {
  form.value.steps.splice(index, 1)
}

const addEditStep = () => {
  editForm.value.steps.push(createStep({ order: editForm.value.steps.length + 1 }))
}

const removeEditStep = (index) => {
  editForm.value.steps.splice(index, 1)
}

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

const isFetchingEquipments = ref(false)
const isFetchingProcedures = ref(false)

const fetchEquipments = async () => {
  if (!selectedClinicId.value || isFetchingEquipments.value) return
  isFetchingEquipments.value = true
  try {
    const { data } = await equipmentApi.list({ clinic_id: selectedClinicId.value })
    equipments.value = data.data ?? data
  } catch (err) {
    console.error(err)
  } finally {
    isFetchingEquipments.value = false
  }
}

const fetchProcedures = async () => {
  if (!selectedClinicId.value || isFetchingProcedures.value) return
  isFetchingProcedures.value = true
  loading.value = true
  error.value = null
  try {
    const { data } = await procedureApi.list({
      clinic_id: selectedClinicId.value,
      page: currentPage.value,
      per_page: perPage
    })
    const items = data.data ?? data
    procedures.value = items
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
    error.value = 'Не вдалося завантажити процедури'
  } finally {
    loading.value = false
    isFetchingProcedures.value = false
  }
}

const debouncedFetchProcedures = debounce(fetchProcedures, 300)
const debouncedFetchEquipments = debounce(fetchEquipments, 300)

const resetForm = () => {
  form.value = {
    clinic_id: selectedClinicId.value || clinics.value[0]?.id || '',
    name: '',
    category: '',
    duration_minutes: 30,
    requires_room: false,
    requires_assistant: false,
    equipment_id: '',
    steps: []
  }
}

const toggleForm = () => {
  showForm.value = !showForm.value
  if (showForm.value) {
    resetForm()
  }
}

const createProcedure = async () => {
  creating.value = true
  createError.value = null
  try {
    await procedureApi.create({
      ...form.value,
      clinic_id: form.value.clinic_id || selectedClinicId.value,
      equipment_id: form.value.equipment_id || null,
      steps: normalizeSteps(form.value.steps)
    })
    showForm.value = false
    resetForm()
    await fetchProcedures()
  } catch (err) {
    console.error(err)
    createError.value = err.response?.data?.message || 'Помилка створення процедури'
  } finally {
    creating.value = false
  }
}

const startEdit = (procedure) => {
  editingProcedureId.value = procedure.id
  editError.value = null
  editForm.value = {
    name: procedure.name || '',
    category: procedure.category || '',
    duration_minutes: procedure.duration_minutes ?? 30,
    requires_room: !!procedure.requires_room,
    requires_assistant: !!procedure.requires_assistant,
    equipment_id: procedure.equipment_id || '',
    steps: (procedure.steps || []).map((step) => createStep(step))
  }
}

const cancelEdit = () => {
  editingProcedureId.value = null
  editError.value = null
}

const updateProcedure = async (procedure) => {
  savingEdit.value = true
  editError.value = null
  try {
    await procedureApi.update(procedure.id, {
      name: editForm.value.name,
      category: editForm.value.category || null,
      duration_minutes: editForm.value.duration_minutes,
      requires_room: editForm.value.requires_room,
      requires_assistant: editForm.value.requires_assistant,
      equipment_id: editForm.value.equipment_id || null,
      steps: normalizeSteps(editForm.value.steps)
    })
    await fetchProcedures()
    editingProcedureId.value = null
  } catch (err) {
    console.error(err)
    editError.value = err.response?.data?.message || 'Не вдалося оновити процедуру'
  } finally {
    savingEdit.value = false
  }
}

const deleteProcedure = async (procedure) => {
  if (!window.confirm(`Видалити процедуру "${procedure.name}"?`)) return
  editError.value = null
  try {
    await procedureApi.delete(procedure.id)
    await fetchProcedures()
  } catch (err) {
    console.error(err)
    editError.value = err.response?.data?.message || 'Не вдалося видалити процедуру'
  }
}

watch(selectedClinicId, () => {
  currentPage.value = 1
  debouncedFetchProcedures()
  debouncedFetchEquipments()
})

onMounted(async () => {
  await loadClinics()
  // fetch functions will be called by watch when selectedClinicId is set in loadClinics
  // Only call directly if selectedClinicId was not set
  if (!selectedClinicId.value && clinics.value.length) {
    await Promise.all([fetchProcedures(), fetchEquipments()])
  }
})

const goToPage = async (page) => {
  const nextPage = Math.min(Math.max(page, 1), pageCount.value)
  if (nextPage === currentPage.value) return
  currentPage.value = nextPage
  await fetchProcedures()
}
</script>

<template>
  <div class="space-y-6">
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-2xl font-semibold">Процедури</h1>
        <p class="text-sm text-text/70">Налаштування процедур для клінік.</p>
      </div>
      <button
        class="px-4 py-2 rounded-lg bg-emerald-500 text-text text-sm font-semibold hover:bg-emerald-400"
        @click="toggleForm"
      >
        {{ showForm ? 'Приховати форму' : 'Нова процедура' }}
      </button>
    </header>

    <div class="flex flex-wrap items-center gap-3">
      <label for="procedures-clinic-filter" class="text-xs uppercase text-text/70">Клініка</label>
      <select
        v-model="selectedClinicId"
        id="procedures-clinic-filter"
        name="clinic_id"
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
      <h2 class="text-sm font-semibold text-text/90">Додати процедуру</h2>

      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label for="procedure-create-clinic" class="block text-xs uppercase text-text/70 mb-1"
            >Клініка</label
          >
          <select
            v-model="form.clinic_id"
            id="procedure-create-clinic"
            name="clinic_id"
            class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
          >
            <option v-for="clinic in clinics" :key="clinic.id" :value="clinic.id">
              {{ clinic.name }}
            </option>
          </select>
        </div>

        <div>
          <label for="procedure-create-name" class="block text-xs uppercase text-text/70 mb-1"
            >Назва</label
          >
          <input
            v-model="form.name"
            id="procedure-create-name"
            name="name"
            type="text"
            class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
          />
        </div>

        <div>
          <label for="procedure-create-category" class="block text-xs uppercase text-text/70 mb-1"
            >Категорія</label
          >
          <input
            v-model="form.category"
            id="procedure-create-category"
            name="category"
            type="text"
            class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
          />
        </div>

        <div>
          <label
            for="procedure-create-duration"
            class="block text-xs uppercase text-text/70 mb-1"
          >
            Тривалість (хв)
          </label>
          <input
            v-model.number="form.duration_minutes"
            id="procedure-create-duration"
            name="duration_minutes"
            type="number"
            min="5"
            class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
          />
        </div>

        <div>
          <label
            for="procedure-create-equipment"
            class="block text-xs uppercase text-text/70 mb-1"
          >
            Обладнання
          </label>
          <select
            v-model="form.equipment_id"
            id="procedure-create-equipment"
            name="equipment_id"
            class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
          >
            <option value="">Без обладнання</option>
            <option v-for="equipment in equipments" :key="equipment.id" :value="equipment.id">
              {{ equipment.name }}
            </option>
          </select>
        </div>
      </div>

      <div class="flex flex-wrap gap-4">
        <label class="flex items-center gap-2 text-sm text-text/80">
          <input
            v-model="form.requires_room"
            id="procedure-create-requires-room"
            name="requires_room"
            type="checkbox"
            class="rounded border-border/80 bg-bg"
          />
          Потрібна кімната
        </label>
        <label class="flex items-center gap-2 text-sm text-text/80">
          <input
            v-model="form.requires_assistant"
            id="procedure-create-requires-assistant"
            name="requires_assistant"
            type="checkbox"
            class="rounded border-border/80 bg-bg"
          />
          Потрібен асистент
        </label>
      </div>

      <div class="space-y-2">
        <div class="flex items-center justify-between">
          <label class="text-xs uppercase text-text/70">Етапи процедури</label>
          <button
            type="button"
            class="text-xs text-emerald-300 hover:text-emerald-200"
            @click="addFormStep"
          >
            + Додати етап
          </button>
        </div>
        <div v-if="!form.steps.length" class="text-xs text-text/60">Етапи не додані.</div>
        <div
          v-for="(step, index) in form.steps"
          :key="`new-step-${index}`"
          class="flex flex-wrap items-center gap-2"
        >
          <label :for="`procedure-create-step-name-${index}`" class="sr-only">Назва етапу</label>
          <input
            v-model="step.name"
            :id="`procedure-create-step-name-${index}`"
            :name="`steps[${index}][name]`"
            type="text"
            placeholder="Назва етапу"
            class="flex-1 min-w-[180px] rounded-lg bg-bg border border-border/80 px-2 py-1 text-sm text-text"
          />
          <label :for="`procedure-create-step-duration-${index}`" class="sr-only">
            Тривалість етапу (хв)
          </label>
          <input
            v-model.number="step.duration_minutes"
            :id="`procedure-create-step-duration-${index}`"
            :name="`steps[${index}][duration_minutes]`"
            type="number"
            min="5"
            class="w-28 rounded-lg bg-bg border border-border/80 px-2 py-1 text-sm text-text"
          />
          <span class="text-xs text-text/70">хв</span>
          <button
            type="button"
            class="text-xs text-red-400 hover:text-red-300"
            @click="removeFormStep(index)"
          >
            Видалити
          </button>
        </div>
      </div>

      <div class="flex items-center justify-between gap-3">
        <span v-if="createError" class="text-sm text-red-400">❌ {{ createError }}</span>
        <button
          class="ml-auto px-4 py-2 rounded-lg bg-emerald-500 text-text text-sm font-semibold hover:bg-emerald-400 disabled:opacity-60"
          :disabled="creating"
          @click="createProcedure"
        >
          {{ creating ? 'Створення...' : 'Створити' }}
        </button>
      </div>
    </section>

    <section class="rounded-xl bg-card/40 shadow-sm shadow-black/10 dark:shadow-black/40 p-4">
      <div v-if="loading" class="text-sm text-text/70">Завантаження...</div>
      <div v-else-if="error" class="text-sm text-red-400">{{ error }}</div>
      <div v-else-if="editError" class="text-sm text-red-400">{{ editError }}</div>
      <div v-else-if="!procedures.length" class="text-sm text-text/70">Немає процедур.</div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="text-text/70 text-xs uppercase">
            <tr>
              <th class="text-left py-2 px-3">Назва</th>
              <th class="text-left py-2 px-3">Категорія</th>
              <th class="text-left py-2 px-3">Тривалість</th>
              <th class="text-left py-2 px-3">Етапи</th>
              <th class="text-left py-2 px-3">Обладнання</th>
              <th class="text-left py-2 px-3">Вимоги</th>
              <th class="text-right py-2 px-3">Дії</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="procedure in pagedProcedures"
              :key="procedure.id"
              class="border-t border-border"
            >
              <td class="py-2 px-3">
                <label
                  v-if="editingProcedureId === procedure.id"
                  :for="`procedure-edit-name-${procedure.id}`"
                  class="sr-only"
                >
                  Назва
                </label>
                <input
                  v-if="editingProcedureId === procedure.id"
                  v-model="editForm.name"
                  :id="`procedure-edit-name-${procedure.id}`"
                  name="name"
                  type="text"
                  class="w-full rounded-md bg-bg border border-border/80 px-2 py-1 text-sm text-text"
                />
                <span v-else class="text-text/90">{{ procedure.name }}</span>
              </td>
              <td class="py-2 px-3">
                <label
                  v-if="editingProcedureId === procedure.id"
                  :for="`procedure-edit-category-${procedure.id}`"
                  class="sr-only"
                >
                  Категорія
                </label>
                <input
                  v-if="editingProcedureId === procedure.id"
                  v-model="editForm.category"
                  :id="`procedure-edit-category-${procedure.id}`"
                  name="category"
                  type="text"
                  class="w-full rounded-md bg-bg border border-border/80 px-2 py-1 text-sm text-text"
                />
                <span v-else class="text-text/70">{{ procedure.category || '—' }}</span>
              </td>
              <td class="py-2 px-3">
                <label
                  v-if="editingProcedureId === procedure.id"
                  :for="`procedure-edit-duration-${procedure.id}`"
                  class="sr-only"
                >
                  Тривалість (хв)
                </label>
                <input
                  v-if="editingProcedureId === procedure.id"
                  v-model.number="editForm.duration_minutes"
                  :id="`procedure-edit-duration-${procedure.id}`"
                  name="duration_minutes"
                  type="number"
                  min="5"
                  class="w-full rounded-md bg-bg border border-border/80 px-2 py-1 text-sm text-text"
                />
                <span v-else class="text-text/70">{{ procedure.duration_minutes }} хв</span>
              </td>
              <td class="py-2 px-3">
                <div v-if="editingProcedureId === procedure.id" class="space-y-2">
                  <div v-if="!editForm.steps.length" class="text-xs text-text/60">
                    Етапи не додані.
                  </div>
                  <div
                    v-for="(step, index) in editForm.steps"
                    :key="`edit-step-${index}`"
                    class="flex flex-wrap items-center gap-2"
                  >
                    <label
                      :for="`procedure-edit-step-name-${procedure.id}-${index}`"
                      class="sr-only"
                    >
                      Назва етапу
                    </label>
                    <input
                      v-model="step.name"
                      :id="`procedure-edit-step-name-${procedure.id}-${index}`"
                      :name="`steps[${index}][name]`"
                      type="text"
                      placeholder="Назва етапу"
                      class="flex-1 min-w-[160px] rounded-md bg-bg border border-border/80 px-2 py-1 text-xs text-text"
                    />
                    <label
                      :for="`procedure-edit-step-duration-${procedure.id}-${index}`"
                      class="sr-only"
                    >
                      Тривалість етапу (хв)
                    </label>
                    <input
                      v-model.number="step.duration_minutes"
                      :id="`procedure-edit-step-duration-${procedure.id}-${index}`"
                      :name="`steps[${index}][duration_minutes]`"
                      type="number"
                      min="5"
                      class="w-24 rounded-md bg-bg border border-border/80 px-2 py-1 text-xs text-text"
                    />
                    <span class="text-[10px] text-text/70">хв</span>
                    <button
                      type="button"
                      class="text-[10px] text-red-400 hover:text-red-300"
                      @click="removeEditStep(index)"
                    >
                      ✕
                    </button>
                  </div>
                  <button
                    type="button"
                    class="text-xs text-emerald-300 hover:text-emerald-200"
                    @click="addEditStep"
                  >
                    + Додати етап
                  </button>
                </div>
                <div v-else class="text-xs text-text/70">
                  <span v-if="procedure.steps?.length">
                    {{ procedure.steps.map((step) => step.name).join(', ') }}
                  </span>
                  <span v-else>—</span>
                </div>
              </td>
              <td class="py-2 px-3">
                <label
                  v-if="editingProcedureId === procedure.id"
                  :for="`procedure-edit-equipment-${procedure.id}`"
                  class="sr-only"
                >
                  Обладнання
                </label>
                <select
                  v-if="editingProcedureId === procedure.id"
                  v-model="editForm.equipment_id"
                  :id="`procedure-edit-equipment-${procedure.id}`"
                  name="equipment_id"
                  class="w-full rounded-md bg-bg border border-border/80 px-2 py-1 text-sm text-text"
                >
                  <option value="">Без обладнання</option>
                  <option v-for="equipment in equipments" :key="equipment.id" :value="equipment.id">
                    {{ equipment.name }}
                  </option>
                </select>
                <span v-else class="text-text/70">
                  {{ equipments.find((e) => e.id === procedure.equipment_id)?.name || '—' }}
                </span>
              </td>
              <td class="py-2 px-3">
                <div
                  v-if="editingProcedureId === procedure.id"
                  class="flex flex-col gap-1 text-xs text-text/80"
                >
                  <label class="inline-flex items-center gap-2">
                    <input
                      v-model="editForm.requires_room"
                      :id="`procedure-edit-requires-room-${procedure.id}`"
                      name="requires_room"
                      type="checkbox"
                      class="accent-emerald-500"
                    />
                    Кімната
                  </label>
                  <label class="inline-flex items-center gap-2">
                    <input
                      v-model="editForm.requires_assistant"
                      :id="`procedure-edit-requires-assistant-${procedure.id}`"
                      name="requires_assistant"
                      type="checkbox"
                      class="accent-emerald-500"
                    />
                    Асистент
                  </label>
                </div>
                <span v-else class="text-text/70">
                  <span v-if="procedure.requires_room">Кімната</span>
                  <span v-if="procedure.requires_room && procedure.requires_assistant"> · </span>
                  <span v-if="procedure.requires_assistant">Асистент</span>
                  <span v-if="!procedure.requires_room && !procedure.requires_assistant">—</span>
                </span>
              </td>
              <td class="py-2 px-3 text-right text-xs">
                <div v-if="editingProcedureId === procedure.id" class="flex justify-end gap-3">
                  <button
                    class="text-emerald-300 hover:text-emerald-200 disabled:opacity-60"
                    :disabled="savingEdit"
                    @click="updateProcedure(procedure)"
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
                    @click="startEdit(procedure)"
                  >
                    Редагувати
                  </button>
                  <button
                    class="text-red-400 hover:text-red-300"
                    @click="deleteProcedure(procedure)"
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
