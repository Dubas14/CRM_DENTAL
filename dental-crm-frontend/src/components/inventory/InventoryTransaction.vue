<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useInventoryStore } from '../../stores/useInventoryStore'
import { UIButton, UISelect } from '../../ui'
import { useToast } from '../../composables/useToast'
import { useAuth } from '../../composables/useAuth'
import clinicApi from '../../services/clinicApi'

const props = defineProps<{
  modelValue: boolean
  clinicId?: number | null
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'saved'): void
}>()

const { showToast } = useToast()
const { user } = useAuth()
const inventoryStore = useInventoryStore()

const open = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v)
})

const activeTab = ref<'purchase' | 'usage'>('purchase')
const selectedItemId = ref<number | null>(null)
const quantity = ref<number>(1)
const costPerUnit = ref<number | null>(null)
const note = ref<string>('')
const error = ref<string | null>(null)

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

const selectedItem = computed(() => {
  if (!selectedItemId.value) return null
  return inventoryStore.items.find((i) => i.id === selectedItemId.value)
})

const itemOptions = computed(() => {
  return inventoryStore.items.map((item) => ({
    value: item.id,
    label: `${item.name} (${item.current_stock} ${item.unit})${item.code ? ` [${item.code}]` : ''}`
  }))
})

const canUse = computed(() => {
  if (activeTab.value !== 'usage' || !selectedItem.value) return true
  return selectedItem.value.current_stock >= quantity.value
})

const save = async () => {
  error.value = null

  const clinicId = resolvedClinicId.value
  if (!clinicId) {
    error.value = 'Не обрано клініку'
    return
  }

  if (!selectedItemId.value) {
    error.value = 'Оберіть матеріал'
    return
  }

  if (!quantity.value || quantity.value <= 0) {
    error.value = 'Введіть кількість'
    return
  }

  if (activeTab.value === 'usage' && !canUse.value) {
    error.value = `Неможливо списати: на складі всього ${selectedItem.value?.current_stock} ${selectedItem.value?.unit}`
    return
  }

  if (activeTab.value === 'purchase' && (!costPerUnit.value || costPerUnit.value <= 0)) {
    error.value = 'Введіть ціну закупівлі'
    return
  }

  try {
    await inventoryStore.addTransaction({
      clinic_id: clinicId,
      inventory_item_id: selectedItemId.value,
      type: activeTab.value,
      quantity: quantity.value,
      cost_per_unit: activeTab.value === 'purchase' ? costPerUnit.value : null,
      note: note.value.trim() || null
    })

    emit('saved')
    open.value = false
    resetForm()
  } catch (err: any) {
    // Check for 422 error (validation error)
    if (err.response?.status === 422) {
      const errors = err.response?.data?.errors
      if (errors) {
        // Format validation errors
        const errorMessages = Object.entries(errors)
          .map(([field, messages]) => {
            const fieldNames: Record<string, string> = {
              clinic_id: 'Клініка',
              inventory_item_id: 'Матеріал',
              quantity: 'Кількість',
              cost_per_unit: 'Ціна закупівлі',
              type: 'Тип транзакції'
            }
            const fieldName = fieldNames[field] || field
            const msgs = Array.isArray(messages) ? messages : [messages]
            return `${fieldName}: ${msgs.join(', ')}`
          })
          .join('; ')
        error.value = errorMessages
      } else {
        error.value = err.response?.data?.message || 'Неможливо списати: недостатньо товару'
      }
      showToast(error.value, 'error')
    } else {
      error.value = err.response?.data?.message || 'Не вдалося створити транзакцію'
      showToast(error.value, 'error')
    }
  }
}

const resetForm = () => {
  selectedItemId.value = null
  quantity.value = 1
  costPerUnit.value = null
  note.value = ''
  error.value = null
}

watch(
  () => props.modelValue,
  async (isOpen) => {
    if (isOpen) {
      if (isSuperAdmin.value) {
        await loadClinics()
      }
      const clinicId = resolvedClinicId.value
      if (clinicId) {
        await inventoryStore.fetchItems({ clinic_id: clinicId })
      }
      resetForm()
    }
  }
)

watch(activeTab, () => {
  resetForm()
})

watch(resolvedClinicId, async (clinicId) => {
  if (clinicId && props.modelValue) {
    await inventoryStore.fetchItems({ clinic_id: clinicId })
  }
})

onMounted(async () => {
  if (props.modelValue && isSuperAdmin.value) {
    await loadClinics()
  }
})
</script>

<template>
  <Teleport to="body">
    <transition name="fade">
      <div
        v-if="open"
        class="fixed inset-0 z-[2000] flex items-center justify-center bg-black/60 backdrop-blur-sm px-4"
        @click.self="open = false"
      >
        <div class="w-full max-w-md rounded-2xl bg-card text-text shadow-2xl border border-border">
          <div class="p-6 border-b border-border flex items-center justify-between">
            <h2 class="text-xl font-semibold">Транзакція складу</h2>
            <button
              type="button"
              class="text-text/70 hover:text-text transition"
              @click="open = false"
            >
              ✕
            </button>
          </div>

          <div class="p-6 space-y-6">
            <!-- Tabs -->
            <div class="flex gap-2 border-b border-border">
              <button
                type="button"
                class="px-4 py-2 text-sm font-medium transition"
                :class="
                  activeTab === 'purchase'
                    ? 'text-emerald-400 border-b-2 border-emerald-400'
                    : 'text-text/70 hover:text-text'
                "
                @click="activeTab = 'purchase'"
              >
                Прихід товару
              </button>
              <button
                type="button"
                class="px-4 py-2 text-sm font-medium transition"
                :class="
                  activeTab === 'usage'
                    ? 'text-emerald-400 border-b-2 border-emerald-400'
                    : 'text-text/70 hover:text-text'
                "
                @click="activeTab = 'usage'"
              >
                Списання
              </button>
            </div>

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
                  Матеріал <span class="text-red-400">*</span>
                </label>
                <select
                  v-model="selectedItemId"
                  class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
                >
                  <option :value="null">Оберіть матеріал</option>
                  <option v-for="item in inventoryStore.items" :key="item.id" :value="item.id">
                    {{ item.name }} ({{ item.current_stock }} {{ item.unit }}){{ item.code ? ` [${item.code}]` : '' }}
                  </option>
                </select>
                <p v-if="selectedItem" class="mt-1 text-xs text-text/60">
                  Доступно на складі: <span class="font-medium text-text">{{ selectedItem.current_stock }} {{ selectedItem.unit }}</span>
                </p>
              </div>

              <div>
                <label class="block text-xs uppercase text-text/70 mb-1">
                  Кількість <span class="text-red-400">*</span>
                </label>
                <input
                  v-model.number="quantity"
                  type="number"
                  :min="activeTab === 'usage' ? 0.001 : 0.001"
                  :max="activeTab === 'usage' && selectedItem ? selectedItem.current_stock : undefined"
                  step="0.001"
                  class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
                />
                <p v-if="selectedItem" class="mt-1 text-xs text-text/60">
                  Одиниця: {{ selectedItem.unit }}
                  <span v-if="activeTab === 'usage'">
                    • Макс. можна списати: <span class="font-medium text-text">{{ selectedItem.current_stock }} {{ selectedItem.unit }}</span>
                  </span>
                </p>
                <p
                  v-if="activeTab === 'usage' && selectedItem && !canUse"
                  class="mt-1 text-xs text-red-400"
                >
                  ⚠️ Неможливо списати більше, ніж є на складі
                </p>
              </div>

              <div v-if="activeTab === 'purchase'">
                <label class="block text-xs uppercase text-text/70 mb-1">
                  Ціна закупівлі (за одиницю) <span class="text-red-400">*</span>
                </label>
                <div class="relative">
                  <input
                    v-model.number="costPerUnit"
                    type="number"
                    min="0.01"
                    step="0.01"
                    class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 pr-16 text-sm text-text"
                    placeholder="0.00"
                  />
                  <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-text/60">грн</span>
                </div>
                <p class="mt-1 text-xs text-text/60">
                  Введіть ціну з копійками (наприклад: 20.50)
                </p>
              </div>

              <div>
                <label class="block text-xs uppercase text-text/70 mb-1">Примітка</label>
                <textarea
                  v-model="note"
                  rows="2"
                  class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
                  placeholder="Додаткова інформація..."
                />
              </div>
            </div>
          </div>

          <div class="p-6 border-t border-border flex justify-end gap-3">
            <UIButton variant="ghost" size="sm" @click="open = false">Скасувати</UIButton>
            <UIButton
              variant="primary"
              size="sm"
              :loading="inventoryStore.loading"
              :disabled="!canUse"
              @click="save"
            >
              {{ activeTab === 'purchase' ? 'Зафіксувати прихід' : 'Списати' }}
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
