<script setup lang="ts">
import { computed } from 'vue'
import { ArrowUp, ArrowDown } from 'lucide-vue-next'

interface Props {
  title: string
  value: string | number
  icon?: string
  trend?: {
    value: number // +5 або -3
    direction: 'up' | 'down'
    label?: string // "vs минулий місяць"
  }
  variant?: 'default' | 'success' | 'warning' | 'danger'
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'default',
})

const formattedValue = computed(() => {
  if (typeof props.value === 'number') {
    return new Intl.NumberFormat('uk-UA', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(props.value)
  }
  return props.value
})

const variantClasses = computed(() => {
  const variants = {
    default: 'bg-card border-border text-text',
    success: 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300',
    warning: 'bg-yellow-500/10 border-yellow-500/30 text-yellow-300',
    danger: 'bg-red-500/10 border-red-500/30 text-red-300',
  }
  return variants[props.variant]
})

const trendColor = computed(() => {
  if (!props.trend) return ''
  return props.trend.direction === 'up' ? 'text-emerald-400' : 'text-red-400'
})
</script>

<template>
  <div :class="['rounded-lg border p-4', variantClasses]">
    <div class="flex items-start justify-between">
      <div class="flex-1">
        <p class="text-xs font-semibold uppercase tracking-wider text-text/70 mb-1">
          {{ title }}
        </p>
        <p class="text-2xl font-bold">{{ formattedValue }}</p>
        <div v-if="trend" :class="['mt-2 flex items-center gap-1 text-sm', trendColor]">
          <ArrowUp v-if="trend.direction === 'up'" :size="14" />
          <ArrowDown v-else :size="14" />
          <span>{{ Math.abs(trend.value) }}%</span>
          <span v-if="trend.label" class="text-text/60 text-xs ml-1">{{ trend.label }}</span>
        </div>
      </div>
      <div v-if="icon" class="text-text/30">
        <!-- Icon slot for custom icons -->
        <slot name="icon">
          <span class="text-2xl">{{ icon }}</span>
        </slot>
      </div>
    </div>
  </div>
</template>

