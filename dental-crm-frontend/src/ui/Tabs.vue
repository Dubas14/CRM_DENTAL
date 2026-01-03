<script setup lang="ts">
import { computed } from 'vue'
import { colorTokens } from './tokens'

type Tab = { id: string; label: string; badge?: string | number }

const props = defineProps<{
  modelValue: string
  tabs: Tab[]
}>()

const emit = defineEmits<{ (e: 'update:modelValue', id: string): void; (e: 'change', id: string): void }>()

const activeId = computed(() => props.modelValue || props.tabs[0]?.id || '')

const onSelect = (id: string) => {
  emit('update:modelValue', id)
  emit('change', id)
}
</script>

<template>
  <div>
    <div class="flex items-center gap-2 border-b border-border/80">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        type="button"
        class="relative px-3 py-2 text-sm font-medium rounded-t-lg transition-colors"
        :class="
          tab.id === activeId
            ? 'text-emerald-400 border-b-2 border-emerald-400 -mb-px'
            : 'text-text/70 hover:text-text'
        "
        @click="onSelect(tab.id)"
      >
        <span>{{ tab.label }}</span>
        <span
          v-if="tab.badge !== undefined"
          class="ml-2 inline-flex items-center justify-center text-[10px] px-2 py-0.5 rounded-full"
          :class="colorTokens.badge.info"
        >
          {{ tab.badge }}
        </span>
      </button>
    </div>
    <div class="pt-4">
      <slot :active-id="activeId" />
    </div>
  </div>
</template>

