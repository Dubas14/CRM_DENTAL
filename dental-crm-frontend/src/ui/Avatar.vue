<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { radiusTokens } from './tokens'

const props = withDefaults(
  defineProps<{
    src?: string
    alt?: string
    size?: 'sm' | 'md' | 'lg' | number
    fallbackText?: string
  }>(),
  {
    alt: 'avatar',
    size: 'md',
    fallbackText: '?'
  }
)

const errored = ref(false)
watch(
  () => props.src,
  () => {
    errored.value = false
  }
)

const sizePx = computed(() => {
  if (typeof props.size === 'number') return `${props.size}px`
  if (props.size === 'sm') return '32px'
  if (props.size === 'lg') return '72px'
  return '48px'
})
</script>

<template>
  <div
    class="relative flex items-center justify-center bg-card border border-border text-text/80 font-semibold overflow-hidden"
    :class="radiusTokens.full"
    :style="{ width: sizePx, height: sizePx }"
  >
    <img
      v-if="src && !errored"
      :src="src"
      :alt="alt"
      class="w-full h-full object-cover"
      loading="lazy"
      @error="errored = true"
    />
    <span v-else class="text-sm">{{ fallbackText }}</span>
  </div>
</template>
