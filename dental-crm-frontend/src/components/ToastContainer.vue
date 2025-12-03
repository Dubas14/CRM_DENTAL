<script setup>
import { useToast } from '../composables/useToast';
import { X, CheckCircle, AlertCircle, Info } from 'lucide-vue-next';

const { toasts, removeToast } = useToast();

const icons = {
  success: CheckCircle,
  error: AlertCircle,
  info: Info
};
</script>

<template>
  <div class="fixed top-4 right-4 z-[100] flex flex-col gap-3 w-full max-w-xs pointer-events-none">
    <transition-group name="toast">
      <div
          v-for="toast in toasts"
          :key="toast.id"
          class="pointer-events-auto flex items-start gap-3 px-4 py-3 rounded-xl shadow-xl border backdrop-blur-md transition-all duration-300"
          :class="{
          'bg-emerald-900/90 border-emerald-500/30 text-emerald-100 shadow-emerald-900/20': toast.type === 'success',
          'bg-red-900/90 border-red-500/30 text-red-100 shadow-red-900/20': toast.type === 'error',
          'bg-blue-900/90 border-blue-500/30 text-blue-100 shadow-blue-900/20': toast.type === 'info',
        }"
      >
        <component :is="icons[toast.type]" size="20" class="mt-0.5 shrink-0" />

        <div class="flex-1 text-sm font-medium leading-tight pt-0.5">
          {{ toast.message }}
        </div>

        <button @click="removeToast(toast.id)" class="opacity-60 hover:opacity-100 transition-opacity">
          <X size="16" />
        </button>
      </div>
    </transition-group>
  </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}
.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateX(30px) scale(0.9);
}
</style>