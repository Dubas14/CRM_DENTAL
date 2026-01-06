<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useFinanceStore } from '../../stores/useFinanceStore'
import { UIButton, UIBadge } from '../../ui'
import { useToast } from '../../composables/useToast'

const props = defineProps<{
  modelValue: boolean
  invoiceId: number | string
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'paid', payment: any): void
}>()

const { showToast } = useToast()
const financeStore = useFinanceStore()

const open = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v)
})

const amount = ref<number>(0)
const method = ref<'cash' | 'card' | 'bank_transfer' | 'insurance'>('cash')
const transactionId = ref<string>('')
const error = ref<string | null>(null)
const fieldError = ref<string | null>(null)

const invoice = computed(() => financeStore.currentInvoice)

const debtAmount = computed(() => {
  // Trust backend - use debt_amount from API response (always 2 decimal places)
  if (invoice.value?.debt_amount !== undefined) {
    return Number(parseFloat(String(invoice.value.debt_amount)).toFixed(2))
  }
  // Fallback calculation (shouldn't be needed if backend returns debt_amount)
  const total = invoice.value?.total_amount || 0
  const paid = invoice.value?.paid_amount || 0
  return Math.max(0, parseFloat((total - paid).toFixed(2)))
})

const statusBadge = computed(() => {
  const status = invoice.value?.status
  if (status === 'paid') return { variant: 'success' as const, label: 'Оплачено' }
  if (status === 'partially_paid') return { variant: 'warning' as const, label: 'Частково оплачено' }
  return { variant: 'danger' as const, label: 'Не оплачено' }
})

const formatMoney = (amount: number) => {
  return new Intl.NumberFormat('uk-UA', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(amount)
}

const paymentMethods = [
  { value: 'cash', label: 'Готівка' },
  { value: 'card', label: 'Карта' },
  { value: 'bank_transfer', label: 'Банківський переказ' },
  { value: 'insurance', label: 'Страхова' }
]

const save = async () => {
  error.value = null
  fieldError.value = null

  if (!amount.value || amount.value <= 0) {
    fieldError.value = 'Введіть суму оплати'
    return
  }

  if (amount.value > debtAmount.value) {
    fieldError.value = `Сума оплати перевищує залишок боргу (${formatMoney(debtAmount.value)} грн)`
    return
  }

  try {
    const payment = await financeStore.addPayment(props.invoiceId, {
      amount: amount.value,
      method: method.value,
      transaction_id: transactionId.value || null
    })

    emit('paid', payment)
    open.value = false

    // Reset form
    amount.value = debtAmount.value
    method.value = 'cash'
    transactionId.value = ''
  } catch (err: any) {
    // Check for 422 error (overpayment)
    if (err.response?.status === 422) {
      const errorMessage = err.response?.data?.message || 'Сума оплати перевищує залишок боргу'
      fieldError.value = errorMessage
      error.value = errorMessage
    } else {
      error.value = err.response?.data?.message || 'Не вдалося прийняти оплату'
    }
  }
}

watch(
  () => props.modelValue,
  async (isOpen) => {
    if (isOpen) {
      await financeStore.fetchInvoice(props.invoiceId)
      // Set default amount to debt
      amount.value = debtAmount.value
      error.value = null
      fieldError.value = null
    }
  }
)

watch(debtAmount, (newDebt) => {
  if (open.value && newDebt > 0) {
    amount.value = newDebt
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
        <div
          class="w-full max-w-md rounded-2xl bg-card text-text shadow-2xl border border-border"
        >
          <div class="p-6 border-b border-border flex items-center justify-between">
            <h2 class="text-xl font-semibold">Прийняти оплату</h2>
            <button
              type="button"
              class="text-text/70 hover:text-text transition"
              @click="open = false"
            >
              ✕
            </button>
          </div>

          <div class="p-6 space-y-6">
            <!-- Invoice Info -->
            <div v-if="invoice" class="space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-sm text-text/70">Рахунок:</span>
                <span class="font-medium text-text">{{ invoice.invoice_number }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-text/70">Сума рахунку:</span>
                <span class="font-medium text-text">{{ formatMoney(invoice.total_amount || 0) }} грн</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-text/70">Сплачено:</span>
                <span class="font-medium text-text">{{ formatMoney(invoice.paid_amount || 0) }} грн</span>
              </div>
              <div class="flex items-center justify-between border-t border-border pt-3">
                <span class="text-sm font-semibold text-text/90">Залишок боргу:</span>
                <span class="text-lg font-bold text-text">{{ formatMoney(debtAmount) }} грн</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-text/70">Статус:</span>
                <UIBadge :variant="statusBadge.variant" small>{{ statusBadge.label }}</UIBadge>
              </div>
            </div>

            <!-- Error Messages -->
            <div v-if="error" class="bg-red-900/30 border border-red-500/30 rounded-lg p-3">
              <p class="text-sm text-red-300">{{ error }}</p>
            </div>

            <!-- Payment Form -->
            <div class="space-y-4">
              <div>
                <label class="block text-xs uppercase text-text/70 mb-1">
                  Сума внеску <span class="text-red-400">*</span>
                </label>
                <input
                  v-model.number="amount"
                  type="number"
                  min="0.01"
                  :max="debtAmount"
                  step="0.01"
                  :class="[
                    'w-full rounded-lg bg-bg border px-3 py-2 text-sm text-text',
                    fieldError ? 'border-red-500' : 'border-border/80'
                  ]"
                  placeholder="0.00"
                />
                <p v-if="fieldError" class="mt-1 text-xs text-red-400">{{ fieldError }}</p>
                <p v-else class="mt-1 text-xs text-text/60">
                  Максимальна сума: {{ formatMoney(debtAmount) }} грн
                </p>
              </div>

              <div>
                <label class="block text-xs uppercase text-text/70 mb-1">
                  Метод оплати <span class="text-red-400">*</span>
                </label>
                <div class="grid grid-cols-2 gap-2">
                  <label
                    v-for="pm in paymentMethods"
                    :key="pm.value"
                    class="flex items-center gap-2 p-3 rounded-lg border cursor-pointer transition"
                    :class="
                      method === pm.value
                        ? 'border-emerald-500 bg-emerald-500/10'
                        : 'border-border/60 hover:bg-card/40'
                    "
                  >
                    <input
                      v-model="method"
                      type="radio"
                      :value="pm.value"
                      class="text-emerald-500"
                    />
                    <span class="text-sm text-text">{{ pm.label }}</span>
                  </label>
                </div>
              </div>

              <div v-if="method === 'card' || method === 'bank_transfer'">
                <label class="block text-xs uppercase text-text/70 mb-1">
                  ID транзакції (опціонально)
                </label>
                <input
                  v-model="transactionId"
                  type="text"
                  class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
                  placeholder="ID транзакції з терміналу"
                />
              </div>
            </div>
          </div>

          <div class="p-6 border-t border-border flex justify-end gap-3">
            <UIButton variant="ghost" size="sm" @click="open = false">Скасувати</UIButton>
            <UIButton
              variant="primary"
              size="sm"
              :loading="financeStore.loading"
              :disabled="debtAmount <= 0"
              @click="save"
            >
              Прийняти оплату
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

