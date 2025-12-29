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
      <input
        type="date"
        :value="inputValue"
        class="rounded-md border border-border/70 bg-card px-3 py-2 text-sm text-text/90 shadow-sm focus:border-emerald-500 focus:outline-none"
        @change="handleDateChange"
      />
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

const emit = defineEmits(['select-date', 'prev', 'next', 'today'])

const normalizeDate = (value) => {
  if (!value) return null
  const date = value instanceof Date ? value : new Date(value)
  return Number.isNaN(date.getTime()) ? null : date
}

const normalizedDate = computed(() => normalizeDate(props.currentDate))

const inputValue = computed(() => {
  const date = normalizedDate.value
  if (!date) return ''
  const year = date.getFullYear()
  const month = `${date.getMonth() + 1}`.padStart(2, '0')
  const day = `${date.getDate()}`.padStart(2, '0')
  return `${year}-${month}-${day}`
})

const formattedLabel = computed(() => {
  const date = normalizedDate.value
  if (!date) return ''
  const formatter = new Intl.DateTimeFormat('uk-UA', {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
    year: 'numeric',
  })
  return formatter.format(date)
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

const handleDateChange = (event) => {
  const value = event?.target?.value
  if (!value) return
  const next = new Date(`${value}T00:00:00`)
  if (Number.isNaN(next.getTime())) return
  emit('select-date', next)
}
</script>
