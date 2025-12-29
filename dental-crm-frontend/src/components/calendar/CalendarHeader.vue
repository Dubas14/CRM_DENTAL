<template>
  <div class="flex flex-wrap items-center gap-4">
    <div class="flex items-center gap-2">
      <button
        type="button"
        :aria-label="prevLabel"
        class="h-9 w-9 rounded-md border border-border/80 text-lg text-text/80 transition hover:bg-card/80"
        @click="$emit('prev')"
      >
        ‹
      </button>
      <button
        type="button"
        :aria-label="nextLabel"
        class="h-9 w-9 rounded-md border border-border/80 text-lg text-text/80 transition hover:bg-card/80"
        @click="$emit('next')"
      >
        ›
      </button>
      <button
        type="button"
        class="px-3 py-2 rounded-md border border-emerald-500/60 text-sm text-emerald-200 transition hover:bg-emerald-500/10 disabled:cursor-not-allowed disabled:opacity-50"
        :disabled="isToday"
        @click="$emit('today')"
      >
        Сьогодні
      </button>
    </div>

    <div class="flex flex-wrap items-center gap-3">
      <span class="text-sm text-text/70">{{ formattedLabel }}</span>
      <div class="flex items-center">
        <select
          :value="viewMode"
          class="rounded-md border border-border/60 bg-card/60 px-3 py-1.5 text-xs text-text/80 transition hover:border-border/80 focus:outline-none"
          @change="$emit('view-change', $event.target.value)"
        >
          <option v-for="option in viewOptions" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  currentDate: {
    type: Date,
    required: true,
  },
  viewMode: {
    type: String,
    default: 'day',
  },
  rangeStart: {
    type: Date,
    default: null,
  },
  rangeEnd: {
    type: Date,
    default: null,
  },
})

defineEmits(['select-date', 'prev', 'next', 'today', 'view-change'])

const normalizeDate = (value) => {
  if (!value) return null
  const date = value instanceof Date ? value : new Date(value)
  return Number.isNaN(date.getTime()) ? null : date
}

const normalizedDate = computed(() => normalizeDate(props.currentDate))

const viewOptions = [
  { value: 'day', label: 'День' },
  { value: 'week', label: 'Тиждень' },
  { value: 'month', label: 'Місяць' },
]

const capitalize = (value) => (value ? value.charAt(0).toUpperCase() + value.slice(1) : '')

const formatWithParts = (date, options) => (
  new Intl.DateTimeFormat('uk-UA', options)
    .formatToParts(date)
    .map((part) => {
      if (part.type === 'month' || part.type === 'weekday') return capitalize(part.value)
      return part.value
    })
    .join('')
)

const formattedLabel = computed(() => {
  const date = normalizedDate.value
  if (!date) return ''

  if (props.viewMode === 'month') {
    return formatWithParts(date, { month: 'long', year: 'numeric' })
  }

  if (props.viewMode === 'week' && props.rangeStart && props.rangeEnd) {
    const start = formatWithParts(props.rangeStart, { day: 'numeric', month: 'long', year: 'numeric' })
    const end = formatWithParts(props.rangeEnd, { day: 'numeric', month: 'long', year: 'numeric' })
    return `${start} – ${end}`
  }

  return formatWithParts(date, {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
    year: 'numeric',
  })
})

const isToday = computed(() => {
  const date = normalizedDate.value
  if (!date) return false
  const now = new Date()
  return (
    date.getFullYear() === now.getFullYear()
    && date.getMonth() === now.getMonth()
    && date.getDate() === now.getDate()
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
