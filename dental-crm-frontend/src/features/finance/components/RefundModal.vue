<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { UIButton } from '../../../ui'
import paymentApi from '../../../services/paymentApi'
import { useToast } from '../../../composables/useToast'

const props = defineProps<{
  modelValue: boolean
  payment: any
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'refunded', refund: any): void
}>()

const { showToast } = useToast()

const open = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

const reason = ref('')
const loading = ref(false)
const error = ref<string | null>(null)

const formatMoney = (amount: number) => {
  return new Intl.NumberFormat('uk-UA', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount)
}

const submit = async () => {
  if (!reason.value.trim()) {
    error.value = 'Вкажіть причину повернення'
    return
  }

  loading.value = true
  error.value = null

  try {
    const { data } = await paymentApi.refund(props.payment.id, reason.value)
    showToast('Кошти успішно повернено', 'success')
    emit('refunded', data.refund)
    open.value = false
    reason.value = ''
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Не вдалося виконати повернення'
    showToast(error.value, 'error')
  } finally {
    loading.value = false
  }
}

watch(
  () => props.modelValue,
  (isOpen) => {
    if (isOpen) {
      reason.value = ''
      error.value = null
    }
  }
)
</script>

<template>
  <Teleport to="body">
    <transition name="fade">
      <div
        v-if="open"
        class="fixed inset-0 z-[2000] flex items-center justify-center bg-black/60 backdrop-blur-sm px-4"
        @click.self="open = false"
      >
        <div class="w-full max-w-md rounded-2xl bg-card text-text shadow-2xl border border-red-500/30">
          <div class="p-6 border-b border-border flex items-center justify-between">
            <h2 class="text-xl font-semibold text-red-400">Повернення коштів</h2>
            <button
              type="button"
              class="text-text/70 hover:text-text transition"
              @click="open = false"
            >
              ✕
            </button>
          </div>

          <div class="p-6 space-y-6">
            <div v-if="payment" class="space-y-3 p-4 bg-red-500/10 rounded-lg border border-red-500/20">
              <div class="flex items-center justify-between">
                <span class="text-sm text-text/70">Сума платежу:</span>
                <span class="font-bold text-text">{{ formatMoney(payment.amount) }} грн</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-text/70">Метод:</span>
                <span class="text-text">{{ payment.method }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-text/70">Дата:</span>
                <span class="text-text">{{ new Date(payment.created_at).toLocaleDateString('uk-UA') }}</span>
              </div>
            </div>

            <div v-if="error" class="bg-red-900/30 border border-red-500/30 rounded-lg p-3">
              <p class="text-sm text-red-300">{{ error }}</p>
            </div>

            <div>
              <label class="block text-xs uppercase text-text/70 mb-2">
                Причина повернення <span class="text-red-400">*</span>
              </label>
              <textarea
                v-model="reason"
                rows="3"
                class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text focus:outline-none focus:ring-2 focus:ring-red-500/50"
                placeholder="Опишіть причину повернення коштів..."
              />
            </div>

            <div class="p-3 bg-yellow-500/10 border border-yellow-500/30 rounded-lg">
              <p class="text-xs text-yellow-300">
                ⚠️ Увага! Ця операція незворотна. Після повернення статус рахунку буде оновлено.
              </p>
            </div>
          </div>

          <div class="p-6 border-t border-border flex justify-end gap-3">
            <UIButton variant="ghost" size="sm" @click="open = false">Скасувати</UIButton>
            <UIButton
              variant="danger"
              size="sm"
              :loading="loading"
              :disabled="!reason.trim()"
              @click="submit"
            >
              Підтвердити повернення
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

