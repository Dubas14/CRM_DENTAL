<script setup lang="ts">
import { computed } from 'vue'
import { colorTokens } from './tokens'

type Variant = 'success' | 'info' | 'warning' | 'danger' | 'neutral'

const props = withDefaults(
  defineProps<{
    variant?: Variant
    small?: boolean
  }>(),
  {
    variant: 'neutral',
    small: false
  }
)

const classes = computed(() => {
  const base = colorTokens.badge[props.variant] || colorTokens.badge.neutral
  return [
    'inline-flex items-center gap-1 font-semibold',
    props.small ? 'text-[11px] px-2 py-0.5 rounded-md' : 'text-xs px-2.5 py-1 rounded-full',
    base
  ].join(' ')
})
</script>

<template>
  <span :class="classes">
    <slot />
  </span>
</template>

