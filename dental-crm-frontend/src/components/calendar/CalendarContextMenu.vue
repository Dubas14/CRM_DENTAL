<template>
  <div
    v-if="visible"
    ref="menuRef"
    class="fixed z-50 min-w-[160px] overflow-hidden rounded-lg border border-border bg-card shadow-xl shadow-black/20 animate-in fade-in zoom-in-95 duration-100"
    :style="{ top: `${y}px`, left: `${x}px` }"
    @contextmenu.prevent
  >
    <div class="p-1">
      <div v-if="title" class="px-2 py-1.5 text-xs font-semibold text-text/50 border-b border-border/50 mb-1">
        {{ title }}
      </div>
      
      <button
        v-for="action in actions"
        :key="action.key"
        type="button"
        class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-xs text-text transition-colors hover:bg-emerald-500/10 hover:text-emerald-500"
        :class="{ 'text-red-400 hover:text-red-500 hover:bg-red-500/10': action.danger }"
        @click="handleAction(action.key)"
      >
        <component :is="action.icon" v-if="action.icon" class="h-3.5 w-3.5" />
        {{ action.label }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, nextTick, type PropType } from 'vue'

interface Action {
  key: string
  label: string
  icon?: any
  danger?: boolean
}

const props = defineProps({
  visible: Boolean,
  x: Number,
  y: Number,
  title: String,
  actions: {
    type: Array as PropType<Action[]>,
    default: () => []
  }
})

const emit = defineEmits(['close', 'action'])

const menuRef = ref<HTMLElement | null>(null)

const handleAction = (key: string) => {
  emit('action', key)
  emit('close')
}

const handleClickOutside = (event: MouseEvent) => {
  if (menuRef.value && !menuRef.value.contains(event.target as Node)) {
    emit('close')
  }
}

const handleEsc = (event: KeyboardEvent) => {
  if (event.key === 'Escape') {
    emit('close')
  }
}

onMounted(() => {
  document.addEventListener('mousedown', handleClickOutside)
  document.addEventListener('keydown', handleEsc)
  // Disable scroll when menu is open? Maybe not needed for context menu
})

onUnmounted(() => {
  document.removeEventListener('mousedown', handleClickOutside)
  document.removeEventListener('keydown', handleEsc)
})
</script>
