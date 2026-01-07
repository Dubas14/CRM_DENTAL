<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useInventoryStore } from '../../stores/useInventoryStore'
import { UIButton } from '../../ui'
import { useToast } from '../../composables/useToast'

const props = defineProps<{
  modelValue: boolean
  clinicId?: number | null
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'saved'): void
}>()

const { showToast } = useToast()
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

const selectedItem = computed(() => {
  if (!selectedItemId.value) return null
  return inventoryStore.items.find((i) => i.id === selectedItemId.value)
})

const canUse = computed(() => {
  if (activeTab.value !== 'usage' || !selectedItem.value) return true
  return selectedItem.value.current_stock >= quantity.value
})

const save = async () => {
  error.value = null

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
      clinic_id: props.clinicId!,
      inventory_item_id: selectedItemId.value,
      type: activeTab.value,
      quantity: quantity.value,
      cost_per_unit: activeTab.value === 'purchase' ? costPerUnit.value : null,
      note: note.value || null
    })

    emit('saved')
    open.value = false
    resetForm()
  } catch (err: any) {
    // Check for 422 error (insufficient stock)
    if (err.response?.status === 422) {
      const errorMessage = err.response?.data?.message || 'Неможливо списати: недостатньо товару'
      error.value = errorMessage
      showToast(errorMessage, 'error')
    } else {
      error.value = err.response?.data?.message || 'Не вдалося створити транзакцію'
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
    if (isOpen && props.clinicId) {
      await inventoryStore.fetchItems({ clinic_id: props.clinicId })
      resetForm()
    }
  }
)

watch(activeTab, () => {
  resetForm()
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
                    {{ item.name }} ({{ item.current_stock }} {{ item.unit }})
                  </option>
                </select>
              </div>

              <div>
                <label class="block text-xs uppercase text-text/70 mb-1">
                  Кількість <span class="text-red-400">*</span>
                </label>
                <input
                  v-model.number="quantity"
                  type="number"
                  min="0.001"
                  step="0.001"
                  class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
                />
                <p v-if="selectedItem" class="mt-1 text-xs text-text/60">
                  Одиниця: {{ selectedItem.unit }}
                </p>
                <p
                  v-if="activeTab === 'usage' && selectedItem && !canUse"
                  class="mt-1 text-xs text-red-400"
                >
                  На складі: {{ selectedItem.current_stock }} {{ selectedItem.unit }}
                </p>
              </div>

              <div v-if="activeTab === 'purchase'">
                <label class="block text-xs uppercase text-text/70 mb-1">
                  Ціна закупівлі (за одиницю) <span class="text-red-400">*</span>
                </label>
                <input
                  v-model.number="costPerUnit"
                  type="number"
                  min="0.01"
                  step="0.01"
                  class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
                  placeholder="0.00"
                />
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
