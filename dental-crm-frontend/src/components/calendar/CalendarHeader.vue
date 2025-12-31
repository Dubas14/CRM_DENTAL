<template>
  <div class="flex flex-wrap items-center justify-between gap-4 p-1">
    <!-- Left: Navigation -->
    <div class="flex items-center gap-2 bg-card/50 p-1 rounded-lg border border-border/50">
      <button
        type="button"
        class="h-8 w-8 flex items-center justify-center rounded-md text-text/70 transition hover:bg-bg hover:text-text hover:shadow-sm"
        :aria-label="prevLabel"
        @click="$emit('prev')"
      >
        <span class="text-lg leading-none">‹</span>
      </button>
      <button
        type="button"
        class="px-3 py-1 text-sm font-medium text-text/80 transition hover:bg-bg hover:text-text rounded-md hover:shadow-sm"
        :disabled="isToday"
        :class="{ 'opacity-50 cursor-not-allowed': isToday }"
        @click="$emit('today')"
      >
        Сьогодні
      </button>
      <button
        type="button"
        class="h-8 w-8 flex items-center justify-center rounded-md text-text/70 transition hover:bg-bg hover:text-text hover:shadow-sm"
        :aria-label="nextLabel"
        @click="$emit('next')"
      >
        <span class="text-lg leading-none">›</span>
      </button>
    </div>

    <!-- Center: Date Label -->
    <div class="text-lg font-bold text-text/90 tracking-tight">
      {{ formattedLabel }}
    </div>

    <!-- Right: View Switcher (Segmented Control) -->
    <div class="flex items-center bg-card/50 p-1 rounded-lg border border-border/50">
      <button
        v-for="option in viewOptions"
        :key="option.value"
        type="button"
        class="px-3 py-1.5 text-xs font-medium rounded-md transition-all duration-200"
        :class="[
          viewMode === option.value
            ? 'bg-emerald-500 text-white shadow-sm'
            : 'text-text/70 hover:text-text hover:bg-bg/50'
        ]"
        @click="$emit('view-change', option.value)"
      >
        {{ option.label }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps({
  currentDate: {
    type: Date,
    required: true
  },
  viewMode: {
    type: String,
    default: 'day'
  },
  rangeStart: {
    type: Date,
    default: null
  },
  rangeEnd: {
    type: Date,
    default: null
  }
})

defineEmits(['select-date', 'prev', 'next', 'today', 'view-change'])

const normalizeDate = (value: string | Date | undefined): Date | null => {
  if (!value) return null
  const date = value instanceof Date ? value : new Date(value)
  return Number.isNaN(date.getTime()) ? null : date
}

const normalizedDate = computed(() => normalizeDate(props.currentDate))

const viewOptions = [
  { value: 'day', label: 'День' },
  { value: 'week', label: 'Тиждень' },
  { value: 'month', label: 'Місяць' }
]

const capitalize = (value: string) => (value ? value.charAt(0).toUpperCase() + value.slice(1) : '')

const formatWithParts = (date: Date, options: Intl.DateTimeFormatOptions) =>
  new Intl.DateTimeFormat('uk-UA', options)
    .formatToParts(date)
    .map((part) => {
      if (part.type === 'month' || part.type === 'weekday') return capitalize(part.value)
      return part.value
    })
    .join('')

const formattedLabel = computed(() => {
  const date = normalizedDate.value
  if (!date) return ''

  if (props.viewMode === 'month') {
    return formatWithParts(date, { month: 'long', year: 'numeric' })
  }

  if (props.viewMode === 'week' && props.rangeStart && props.rangeEnd) {
    const start = formatWithParts(props.rangeStart, {
      day: 'numeric',
      month: 'long',
      year: 'numeric'
    })
    const end = formatWithParts(props.rangeEnd, { day: 'numeric', month: 'long', year: 'numeric' })
    return `${start} – ${end}`
  }

  return formatWithParts(date, {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
    year: 'numeric'
  })
})

const isToday = computed(() => {
  const date = normalizedDate.value
  if (!date) return false
  const now = new Date()
  return (
    date.getFullYear() === now.getFullYear() &&
    date.getMonth() === now.getMonth() &&
    date.getDate() === now.getDate()
  )
})

const prevLabel = computed(() => {
  if (props.viewMode === 'month') return 'Попередній місяць'
  if (props.viewMode === 'week') return 'Попередній тиждень'
  return 'Попередній день'
})

const nextLabel = computed(() => {
  if (props.viewMode === 'month') return 'Наступний місяць'
  if (props.viewMode === 'week') return 'Наступний тиждень'
  return 'Наступний день'
})
</script>
