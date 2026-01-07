<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { debounce } from 'lodash-es'
import equipmentApi from '../services/equipmentApi'
import clinicApi from '../services/clinicApi'
import { useAuth } from '../composables/useAuth'
import SearchField from '../components/SearchField.vue'

const { user } = useAuth()

const clinics = ref([])
const selectedClinicId = ref('')
const equipments = ref([])
const loading = ref(false)
const error = ref(null)

const showForm = ref(false)
const creating = ref(false)
const createError = ref(null)
const editError = ref(null)
const editingEquipmentId = ref(null)
const editForm = ref({
  name: '',
  description: '',
  is_active: true
})
const savingEdit = ref(false)

const form = ref({
  clinic_id: '',
  name: '',
  description: '',
  is_active: true
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

  if (!selectedClinicId.value && clinics.value.length) {
    selectedClinicId.value = clinics.value[0].id
  }
}

const fetchEquipments = async () => {
  if (!selectedClinicId.value) return
  const currentSeq = ++requestSeq
  loading.value = true
  error.value = null
  try {
    const params: Record<string, any> = { clinic_id: selectedClinicId.value }
    if (search.value.trim()) params.search = search.value.trim()

    const { data } = await equipmentApi.list(params)

    // Ignore stale responses
    if (currentSeq !== requestSeq) return

    equipments.value = data.data ?? data
  } catch (err) {
    // Ignore stale responses
    if (currentSeq !== requestSeq) return

    console.error(err)
    error.value = 'Не вдалося завантажити обладнання'
  } finally {
    // Only update loading if this is still the latest request
    if (currentSeq === requestSeq) {
      loading.value = false
    }
  }
}

const debouncedFetchEquipments = debounce(fetchEquipments, 300)

const resetForm = () => {
  form.value = {
    clinic_id: selectedClinicId.value || clinics.value[0]?.id || '',
    name: '',
    description: '',
    is_active: true
  }
}

const toggleForm = () => {
  showForm.value = !showForm.value
  if (showForm.value) {
    resetForm()
  }
}

const createEquipment = async () => {
  creating.value = true
  createError.value = null
  try {
    await equipmentApi.create({
      ...form.value,
      clinic_id: form.value.clinic_id || selectedClinicId.value
    })
    showForm.value = false
    resetForm()
    await fetchEquipments()
  } catch (err) {
    console.error(err)
    createError.value = err.response?.data?.message || 'Помилка створення обладнання'
  } finally {
    creating.value = false
  }
}

const startEdit = (equipment) => {
  editingEquipmentId.value = equipment.id
  editError.value = null
  editForm.value = {
    name: equipment.name || '',
    description: equipment.description || '',
    is_active: !!equipment.is_active
  }
}

const cancelEdit = () => {
  editingEquipmentId.value = null
  editError.value = null
}

const updateEquipment = async (equipment) => {
  savingEdit.value = true
  editError.value = null
  try {
    await equipmentApi.update(equipment.id, {
      name: editForm.value.name,
      description: editForm.value.description || null,
      is_active: editForm.value.is_active
    })
    await fetchEquipments()
    editingEquipmentId.value = null
  } catch (err) {
    console.error(err)
    editError.value = err.response?.data?.message || 'Не вдалося оновити обладнання'
  } finally {
    savingEdit.value = false
  }
}

const deleteEquipment = async (equipment) => {
  if (!window.confirm(`Видалити обладнання "${equipment.name}"?`)) return
  editError.value = null
  try {
    await equipmentApi.delete(equipment.id)
    await fetchEquipments()
  } catch (err) {
    console.error(err)
    editError.value = err.response?.data?.message || 'Не вдалося видалити обладнання'
  }
}

watch(selectedClinicId, () => {
  debouncedFetchEquipments()
})

// Live search: trigger search on search change
watch(search, () => {
  debouncedFetchEquipments()
})

onMounted(async () => {
  await loadClinics()
  // fetchEquipments will be called by watch when selectedClinicId is set in loadClinics
  // Only call directly if selectedClinicId was not set
  if (!selectedClinicId.value && clinics.value.length) {
    await fetchEquipments()
  }
})
</script>

<template>
  <div class="space-y-6">
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-2xl font-semibold">Обладнання</h1>
        <p class="text-sm text-text/70">Створення та перегляд обладнання для клініки.</p>
      </div>
      <button
        class="px-4 py-2 rounded-lg bg-emerald-500 text-text text-sm font-semibold hover:bg-emerald-400"
        @click="toggleForm"
      >
        {{ showForm ? 'Приховати форму' : 'Нове обладнання' }}
      </button>
    </header>

    <div class="flex flex-wrap items-center gap-3">
      <SearchField v-model="search" id="equipments-search" placeholder="Пошук (назва / опис)" />
      <label for="equipments-clinic-filter" class="text-xs uppercase text-text/70">Клініка</label>
      <select
        v-model="selectedClinicId"
        id="equipments-clinic-filter"
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
      <h2 class="text-sm font-semibold text-text/90">Додати нове обладнання</h2>

      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label for="equipment-create-clinic" class="block text-xs uppercase text-text/70 mb-1"
            >Клініка</label
          >
          <select
            v-model="form.clinic_id"
            id="equipment-create-clinic"
            name="clinic_id"
            class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
          >
            <option v-for="clinic in clinics" :key="clinic.id" :value="clinic.id">
              {{ clinic.name }}
            </option>
          </select>
        </div>

        <div>
          <label for="equipment-create-name" class="block text-xs uppercase text-text/70 mb-1"
            >Назва</label
          >
          <input
            v-model="form.name"
            id="equipment-create-name"
            name="name"
            type="text"
            class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
          />
        </div>
      </div>

      <div>
        <label for="equipment-create-description" class="block text-xs uppercase text-text/70 mb-1">
          Опис
        </label>
        <textarea
          v-model="form.description"
          id="equipment-create-description"
          name="description"
          rows="2"
          class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
        />
      </div>

      <label class="flex items-center gap-2 text-sm text-text/80">
        <input
          v-model="form.is_active"
          id="equipment-create-is-active"
          name="is_active"
          type="checkbox"
          class="rounded border-border/80 bg-bg"
        />
        Активне обладнання
      </label>

      <div class="flex items-center justify-between gap-3">
        <span v-if="createError" class="text-sm text-red-400">❌ {{ createError }}</span>
        <button
          class="ml-auto px-4 py-2 rounded-lg bg-emerald-500 text-text text-sm font-semibold hover:bg-emerald-400 disabled:opacity-60"
          :disabled="creating"
          @click="createEquipment"
        >
          {{ creating ? 'Створення...' : 'Створити' }}
        </button>
      </div>
    </section>

    <section class="rounded-xl bg-card/40 shadow-sm shadow-black/10 dark:shadow-black/40 p-4">
      <div v-if="loading" class="text-sm text-text/70">Завантаження...</div>
      <div v-else-if="error" class="text-sm text-red-400">{{ error }}</div>
      <div v-else-if="editError" class="text-sm text-red-400">{{ editError }}</div>
      <div v-else-if="!equipments.length" class="text-sm text-text/70">Немає обладнання.</div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="text-text/70 text-xs uppercase">
            <tr>
              <th class="text-left py-2 px-3">Назва</th>
              <th class="text-left py-2 px-3">Опис</th>
              <th class="text-left py-2 px-3">Статус</th>
              <th class="text-right py-2 px-3">Дії</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="equipment in equipments" :key="equipment.id" class="border-t border-border">
              <td class="py-2 px-3">
                <label
                  v-if="editingEquipmentId === equipment.id"
                  :for="`equipment-edit-name-${equipment.id}`"
                  class="sr-only"
                >
                  Назва
                </label>
                <input
                  v-if="editingEquipmentId === equipment.id"
                  v-model="editForm.name"
                  :id="`equipment-edit-name-${equipment.id}`"
                  name="name"
                  type="text"
                  class="w-full rounded-md bg-bg border border-border/80 px-2 py-1 text-sm text-text"
                />
                <span v-else class="text-text/90">{{ equipment.name }}</span>
              </td>
              <td class="py-2 px-3">
                <label
                  v-if="editingEquipmentId === equipment.id"
                  :for="`equipment-edit-description-${equipment.id}`"
                  class="sr-only"
                >
                  Опис
                </label>
                <input
                  v-if="editingEquipmentId === equipment.id"
                  v-model="editForm.description"
                  :id="`equipment-edit-description-${equipment.id}`"
                  name="description"
                  type="text"
                  class="w-full rounded-md bg-bg border border-border/80 px-2 py-1 text-sm text-text"
                />
                <span v-else class="text-text/70">{{ equipment.description || '—' }}</span>
              </td>
              <td class="py-2 px-3">
                <label
                  v-if="editingEquipmentId === equipment.id"
                  class="inline-flex items-center gap-2 text-xs text-text/80"
                >
                  <input
                    v-model="editForm.is_active"
                    :id="`equipment-edit-is-active-${equipment.id}`"
                    name="is_active"
                    type="checkbox"
                    class="accent-emerald-500"
                  />
                  {{ editForm.is_active ? 'Активне' : 'Неактивне' }}
                </label>
                <span
                  v-else
                  class="px-2 py-1 rounded-full text-xs"
                  :class="
                    equipment.is_active
                      ? 'bg-emerald-500/20 text-emerald-300'
                      : 'bg-card/80 text-text/70'
                  "
                >
                  {{ equipment.is_active ? 'Активне' : 'Неактивне' }}
                </span>
              </td>
              <td class="py-2 px-3 text-right text-xs">
                <div v-if="editingEquipmentId === equipment.id" class="flex justify-end gap-3">
                  <button
                    class="text-emerald-300 hover:text-emerald-200 disabled:opacity-60"
                    :disabled="savingEdit"
                    @click="updateEquipment(equipment)"
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
                    @click="startEdit(equipment)"
                  >
                    Редагувати
                  </button>
                  <button
                    class="text-red-400 hover:text-red-300"
                    @click="deleteEquipment(equipment)"
                  >
                    Видалити
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
