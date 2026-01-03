<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { radiusTokens, colorTokens } from './tokens'

type Item = { id: string; label: string; icon?: string }

const props = defineProps<{
  items: Item[]
  placement?: 'bottom-start' | 'bottom-end'
}>()

const emit = defineEmits<{ (e: 'select', id: string): void }>()
const open = ref(false)

const toggle = () => {
  open.value = !open.value
}

const close = () => {
  open.value = false
}

const handleClickOutside = (event: MouseEvent) => {
  const target = event.target as HTMLElement
  if (!target.closest('.ui-dropdown')) {
    close()
  }
}

onMounted(() => document.addEventListener('click', handleClickOutside))
onUnmounted(() => document.removeEventListener('click', handleClickOutside))

const onSelect = (id: string) => {
  emit('select', id)
  close()
}
</script>

<template>
  <div class="ui-dropdown relative inline-flex">
    <slot name="trigger" :open="open" :toggle="toggle">
      <button
        type="button"
        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-border bg-card text-sm text-text/80 hover:text-text hover:bg-card/80"
        @click.stop="toggle"
      >
        Actions
        <span aria-hidden="true">▾</span>
      </button>
    </slot>

    <transition name="fade">
      <div
        v-if="open"
        class="absolute z-50 mt-2 min-w-[180px] overflow-hidden border border-border bg-card shadow-lg"
        :class="[
          radiusTokens.lg,
          placement === 'bottom-end' ? 'right-0 origin-top-right' : 'left-0 origin-top-left'
        ]"
      >
        <ul class="py-1">
          <li v-for="item in items" :key="item.id">
            <button
              type="button"
              class="w-full flex items-center gap-2 px-3 py-2 text-sm text-text/80 hover:bg-card/80 hover:text-text"
              @click="onSelect(item.id)"
            >
              <span v-if="item.icon" aria-hidden="true">{{ item.icon }}</span>
              <span>{{ item.label }}</span>
            </button>
          </li>
        </ul>
      </div>
    </transition>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>

