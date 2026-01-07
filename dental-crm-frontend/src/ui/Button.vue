<script setup lang="ts">
import { computed } from 'vue'
import { colorTokens, sizeTokens } from './tokens'

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger'
type Size = 'sm' | 'md' | 'lg'

const props = withDefaults(
  defineProps<{
    variant?: Variant
    size?: Size
    block?: boolean
    loading?: boolean
    disabled?: boolean
    type?: 'button' | 'submit' | 'reset'
  }>(),
  {
    variant: 'primary',
    size: 'md',
    block: false,
    loading: false,
    disabled: false,
    type: 'button'
  }
)

defineEmits<{ (e: 'click', ev: MouseEvent): void }>()

const classes = computed(() => {
  const variantClass =
    props.variant === 'danger'
      ? colorTokens.danger
      : props.variant === 'ghost'
        ? colorTokens.ghost
        : props.variant === 'secondary'
          ? colorTokens.secondary
          : colorTokens.primary

  const sizeClass = sizeTokens[props.size] || sizeTokens.md

  return [
    'inline-flex items-center justify-center gap-2 font-semibold transition focus:outline-none focus:ring-2 focus:ring-emerald-500/50 disabled:opacity-60 disabled:cursor-not-allowed',
    variantClass,
    sizeClass,
    props.block ? 'w-full' : ''
  ].join(' ')
})
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :class="classes"
    @click="$emit('click', $event)"
  >
    <span
      v-if="loading"
      class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
    />
    <slot />
  </button>
</template>
