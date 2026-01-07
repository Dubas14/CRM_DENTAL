<script setup lang="ts">
import { computed } from 'vue'
import UIButton from './Button.vue'

interface Props {
  modelValue: boolean
  title: string
  message: string
  confirmText?: string
  cancelText?: string
  variant?: 'danger' | 'warning' | 'default'
}

const props = withDefaults(defineProps<Props>(), {
  confirmText: 'Підтвердити',
  cancelText: 'Скасувати',
  variant: 'default',
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'confirm'): void
  (e: 'cancel'): void
}>()

const open = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

const confirm = () => {
  emit('confirm')
  open.value = false
}

const cancel = () => {
  emit('cancel')
  open.value = false
}

const variantClasses = computed(() => {
  const variants = {
    default: 'border-border',
    warning: 'border-yellow-500/30 bg-yellow-500/10',
    danger: 'border-red-500/30 bg-red-500/10',
  }
  return variants[props.variant]
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
        <div :class="['rounded-2xl border p-6 bg-card text-text shadow-2xl max-w-md w-full', variantClasses]">
          <h3 class="text-lg font-semibold mb-2">{{ title }}</h3>
          <p class="text-sm text-text/70 mb-6">{{ message }}</p>
          <div class="flex justify-end gap-3">
            <UIButton variant="ghost" size="sm" @click="cancel">
              {{ cancelText }}
            </UIButton>
            <UIButton :variant="variant === 'danger' ? 'danger' : 'primary'" size="sm" @click="confirm">
              {{ confirmText }}
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

