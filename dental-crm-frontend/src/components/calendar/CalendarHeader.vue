<template>
  <div class="flex flex-wrap items-center gap-4">
    <div class="flex items-center gap-2">
      <button
        type="button"
        aria-label="Попередній день"
        class="h-9 w-9 rounded-md border border-border/80 text-lg text-text/80 transition hover:bg-card/80"
        @click="$emit('prev')"
      >
        ‹
      </button>
      <button
        type="button"
        aria-label="Наступний день"
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

    <div class="flex items-center gap-3">
      <span class="text-sm text-text/70">{{ formattedLabel }}</span>
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
})

defineEmits(['select-date', 'prev', 'next', 'today'])

const normalizeDate = (value) => {
  if (!value) return null
  const date = value instanceof Date ? value : new Date(value)
  return Number.isNaN(date.getTime()) ? null : date
}

const normalizedDate = computed(() => normalizeDate(props.currentDate))

const formatter = new Intl.DateTimeFormat('uk-UA', {
  weekday: 'long',
  month: 'long',
  day: 'numeric',
  year: 'numeric',
})

const capitalize = (value) => value.charAt(0).toUpperCase() + value.slice(1)

const formattedLabel = computed(() => {
  const date = normalizedDate.value
  if (!date) return ''
  return formatter
    .formatToParts(date)
    .map((part) => (part.type === 'month' ? capitalize(part.value) : part.value))
    .join('')
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

</script>
