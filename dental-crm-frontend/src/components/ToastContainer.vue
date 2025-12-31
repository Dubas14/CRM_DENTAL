<script setup lang="ts">
import { computed } from 'vue'
import { X, CheckCircle, AlertCircle, Info, AlertTriangle } from 'lucide-vue-next'
import { useToast } from '../composables/useToast'

const { toasts, removeToast } = useToast()

const iconMap = {
  success: CheckCircle,
  error: AlertCircle,
  warning: AlertTriangle,
  info: Info
}

const variantClass = computed(() => ({
  success: 'bg-emerald-900/90 border-emerald-500/30 text-emerald-100 shadow-emerald-900/20',
  error: 'bg-red-900/90 border-red-500/30 text-red-100 shadow-red-900/20',
  warning: 'bg-amber-900/90 border-amber-500/30 text-amber-100 shadow-amber-900/20',
  info: 'bg-blue-900/90 border-blue-500/30 text-blue-100 shadow-blue-900/20'
}))
</script>

<template>
  <div class="fixed top-4 right-4 z-[100] flex flex-col gap-3 w-full max-w-xs pointer-events-none">
    <TransitionGroup name="toast">
      <div
        v-for="toast in toasts"
        :key="toast.id"
        class="pointer-events-auto flex items-start gap-3 px-4 py-3 rounded-xl shadow-xl border backdrop-blur-md transition-all duration-300"
        :class="variantClass[toast.type] || variantClass.info"
      >
        <component :is="iconMap[toast.type] || iconMap.info" size="20" class="mt-0.5 shrink-0" />

        <div class="flex-1 text-sm font-medium leading-tight pt-0.5">
          {{ toast.message }}
        </div>

        <button
          @click="removeToast(toast.id)"
          class="opacity-60 hover:opacity-100 transition-opacity"
        >
          <X size="16" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>
