<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useInventoryStore } from '../../stores/useInventoryStore'
import { UIButton, UISelect } from '../../ui'
import { useToast } from '../../composables/useToast'
import { useAuth } from '../../composables/useAuth'
import clinicApi from '../../services/clinicApi'
import inventoryApi from '../../services/inventoryApi'

const props = defineProps<{
  modelValue: boolean
  clinicId?: number | null
  itemId?: number | null
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'saved'): void
  (e: 'close'): void
}>()

const { showToast } = useToast()
const { user } = useAuth()
const inventoryStore = useInventoryStore()

const open = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v)
})

const form = ref({
  name: '',
  code: '',
  unit: 'шт',
  min_stock_level: 0,
  initial_stock: 0
})

const error = ref<string | null>(null)
const loading = ref(false)

// Клініка
const selectedClinicId = ref<number | null>(null)
const clinics = ref<any[]>([])
const loadingClinics = ref(false)

// Перевірка чи супер-адмін
const isSuperAdmin = computed(() => user.value?.global_role === 'super_admin')

// Автоматичне визначення клініки для не-супер-адмінів
const resolvedClinicId = computed(() => {
  if (isSuperAdmin.value) {
    return selectedClinicId.value
  }
  return props.clinicId || user.value?.doctor?.clinic_id || user.value?.clinics?.[0]?.id || null
})

// Опції для вибору клініки
const clinicOptions = computed(() => {
  return clinics.value.map((c) => ({
    value: c.id,
    label: c.name
  }))
})

// Завантажити клініки для супер-адміна
const loadClinics = async () => {
  if (!isSuperAdmin.value) return

  loadingClinics.value = true
  try {
    const { data } = await clinicApi.list()
    clinics.value = Array.isArray(data) ? data : data?.data || []
    // Автовибір першої клініки
    if (clinics.value.length > 0 && !selectedClinicId.value) {
      selectedClinicId.value = clinics.value[0].id
    }
  } catch (err) {
    console.error('Failed to load clinics:', err)
  } finally {
    loadingClinics.value = false
  }
}

const unitOptions = ['шт', 'мл', 'г', 'уп', 'пак', 'л', 'кг']

const isEditing = computed(() => !!props.itemId)

const save = async () => {
  error.value = null

  if (!form.value.name.trim()) {
    error.value = 'Введіть назву матеріалу'
    return
  }

  const clinicId = resolvedClinicId.value
  if (!clinicId && !isEditing.value) {
    error.value = 'Не обрано клініку'
    return
  }

  try {
    loading.value = true
    
    if (isEditing.value && props.itemId) {
      // Редагування існуючого матеріалу
      await inventoryStore.updateItem(props.itemId, {
        name: form.value.name.trim(),
        code: form.value.code.trim() || null,
        unit: form.value.unit,
        min_stock_level: form.value.min_stock_level || 0
      })
      showToast('Матеріал оновлено', 'success')
    } else {
      // Створення нового матеріалу
      await inventoryStore.createItem({
        clinic_id: clinicId!,
        name: form.value.name.trim(),
        code: form.value.code.trim() || null, // Якщо порожній, бекенд згенерує автоматично
        unit: form.value.unit,
        min_stock_level: form.value.min_stock_level || 0,
        initial_stock: form.value.initial_stock || 0
      })
      showToast('Матеріал створено успішно', 'success')
    }
    
    emit('saved')
    open.value = false
    resetForm()
    emit('close')
  } catch (err: any) {
    error.value = err.response?.data?.message || (isEditing.value ? 'Не вдалося оновити матеріал' : 'Не вдалося створити матеріал')
    showToast(error.value, 'error')
  } finally {
    loading.value = false
  }
}

const resetForm = () => {
  form.value = {
    name: '',
    code: '',
    unit: 'шт',
    min_stock_level: 0,
    initial_stock: 0
  }
  error.value = null
}

const loadItem = async () => {
  if (!props.itemId) return
  
  try {
    const foundItem = inventoryStore.items.find((i: any) => i.id === props.itemId)
    
    if (foundItem) {
      form.value = {
        name: foundItem.name || '',
        code: foundItem.code || '',
        unit: foundItem.unit || 'шт',
        min_stock_level: foundItem.min_stock_level || 0,
        initial_stock: 0 // При редагуванні не змінюємо залишок
      }
      
      // Встановити клініку для супер-адміна
      if (isSuperAdmin.value && foundItem.clinic_id) {
        selectedClinicId.value = foundItem.clinic_id
      }
    } else {
      // Якщо матеріал не знайдено в store, завантажити через API
      try {
        const { data } = await inventoryApi.getItem(props.itemId)
        form.value = {
          name: data.name || '',
          code: data.code || '',
          unit: data.unit || 'шт',
          min_stock_level: data.min_stock_level || 0,
          initial_stock: 0
        }
        
        if (isSuperAdmin.value && data.clinic_id) {
          selectedClinicId.value = data.clinic_id
        }
      } catch (err: any) {
        error.value = 'Не вдалося завантажити дані матеріалу'
        showToast(error.value, 'error')
      }
    }
  } catch (err: any) {
    error.value = 'Не вдалося завантажити дані матеріалу'
    showToast(error.value, 'error')
  }
}

const cancel = () => {
  resetForm()
  open.value = false
  emit('close')
}

watch(
  () => props.modelValue,
  async (isOpen) => {
    if (isOpen) {
      if (isSuperAdmin.value) {
        await loadClinics()
      }
      if (props.itemId) {
        await loadItem()
      } else {
        resetForm()
      }
    }
  }
)

watch(
  () => props.itemId,
  async (itemId) => {
    if (props.modelValue && itemId) {
      await loadItem()
    } else if (!itemId) {
      resetForm()
    }
  }
)

onMounted(async () => {
  if (props.modelValue) {
    if (isSuperAdmin.value) {
      await loadClinics()
    }
    if (props.itemId) {
      await loadItem()
    }
  }
})
</script>

<template>
  <Teleport to="body">
    <transition name="fade">
      <div
        v-if="open"
        class="fixed inset-0 z-[2000] flex items-center justify-center bg-black/60 backdrop-blur-sm px-4"
        @click.self="cancel"
      >
        <div class="w-full max-w-md rounded-2xl bg-card text-text shadow-2xl border border-border">
          <div class="p-6 border-b border-border flex items-center justify-between">
            <h2 class="text-xl font-semibold">{{ isEditing ? 'Редагувати матеріал' : 'Новий матеріал' }}</h2>
            <button
              type="button"
              class="text-text/70 hover:text-text transition"
              @click="cancel"
            >
              ✕
            </button>
          </div>

          <div class="p-6 space-y-6">
            <!-- Error -->
            <div v-if="error" class="bg-red-900/30 border border-red-500/30 rounded-lg p-3">
              <p class="text-sm text-red-300">{{ error }}</p>
            </div>

            <!-- Form -->
            <div class="space-y-4">
              <!-- Вибір клініки для супер-адміна -->
              <div v-if="isSuperAdmin" class="space-y-2">
                <label class="block text-xs uppercase text-text/70"
                  >Клініка <span class="text-red-400">*</span></label
                >
                <UISelect
                  v-model="selectedClinicId"
                  :options="clinicOptions"
                  placeholder="Оберіть клініку"
                  :disabled="loadingClinics"
                />
              </div>

              <div>
                <label class="block text-xs uppercase text-text/70 mb-1">
                  Назва <span class="text-red-400">*</span>
                </label>
                <input
                  v-model="form.name"
                  type="text"
                  class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
                  placeholder="Наприклад: Рукавички М"
                />
              </div>

              <div>
                <label class="block text-xs uppercase text-text/70 mb-1">Код / Артикул</label>
                <input
                  v-model="form.code"
                  type="text"
                  class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
                  placeholder="Буде згенеровано автоматично, якщо не вказано"
                />
                <p class="mt-1 text-xs text-text/60">
                  Якщо не вказано, код буде створено автоматично
                </p>
              </div>

              <div>
                <label class="block text-xs uppercase text-text/70 mb-1">
                  Одиниця виміру <span class="text-red-400">*</span>
                </label>
                <select
                  v-model="form.unit"
                  class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
                >
                  <option v-for="u in unitOptions" :key="u" :value="u">{{ u }}</option>
                </select>
              </div>

              <div>
                <label class="block text-xs uppercase text-text/70 mb-1">Мінімальний залишок</label>
                <input
                  v-model.number="form.min_stock_level"
                  type="number"
                  min="0"
                  step="0.001"
                  class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
                  placeholder="0"
                />
              </div>

              <div v-if="!isEditing">
                <label class="block text-xs uppercase text-text/70 mb-1">Початковий залишок</label>
                <input
                  v-model.number="form.initial_stock"
                  type="number"
                  min="0"
                  step="0.001"
                  class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
                  placeholder="0"
                />
                <p class="mt-1 text-xs text-text/60">
                  Якщо вказано, створиться транзакція "Початковий залишок"
                </p>
              </div>
            </div>
          </div>

          <div class="p-6 border-t border-border flex justify-end gap-3">
            <UIButton variant="ghost" size="sm" @click="cancel">Скасувати</UIButton>
            <UIButton variant="primary" size="sm" :loading="loading" @click="save">
              {{ isEditing ? 'Зберегти' : 'Створити' }}
            </UIButton>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
