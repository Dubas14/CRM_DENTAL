<template>
  <div class="flex flex-col gap-2">
    <div class="flex items-center justify-between text-sm text-text/80">
      <button
        type="button"
        class="rounded-md border border-border/50 px-2 py-1 hover:bg-card/60"
        @click="handlePrev"
      >
        ‹
      </button>
      <div class="flex items-center gap-2">
        <button
          v-if="mode === 'day'"
          type="button"
          class="font-semibold text-text hover:text-text/80"
          @click="setMode('month')"
        >
          {{ monthLabelWithYear }}
        </button>
        <button
          v-if="mode !== 'year'"
          type="button"
          class="font-semibold text-text hover:text-text/80"
          @click="setMode('year')"
        >
          {{ viewYear }}
        </button>
        <span v-else class="font-semibold text-text">{{ yearRangeLabel }}</span>
      </div>
      <button
        type="button"
        class="rounded-md border border-border/50 px-2 py-1 hover:bg-card/60"
        @click="handleNext"
      >
        ›
      </button>
    </div>

    <div v-if="mode === 'day'">
      <div class="grid grid-cols-7 gap-1 text-center text-[10px] uppercase text-text/50">
        <span v-for="day in weekDays" :key="day">{{ day }}</span>
      </div>
      <div class="mt-2 grid grid-cols-7 gap-1 text-center text-xs">
        <button
          v-for="day in calendarDays"
          :key="day.key"
          type="button"
          class="rounded-md px-1.5 py-1 transition"
          :class="[
            day.isCurrentMonth ? 'text-text/90' : 'text-text/40',
            day.isSelected ? 'bg-emerald-500/20 text-emerald-200' : 'hover:bg-card/70',
          ]"
          @click="selectDay(day.date)"
        >
          {{ day.label }}
        </button>
      </div>
    </div>

    <div v-else-if="mode === 'month'" class="grid grid-cols-3 gap-3 p-4 text-sm">
      <button
        v-for="(month, index) in months"
        :key="month"
        type="button"
        class="month-item rounded-[10px] border border-transparent px-[10px] py-[8px] text-center text-[13px] font-medium text-text/70 transition hover:bg-white/10"
        :class="isSelectedMonth(index) ? 'is-selected rounded-[9999px] bg-blue-600 text-white' : ''"
        @click="selectMonth(index)"
      >
        {{ month }}
      </button>
    </div>

    <div v-else class="grid grid-cols-3 gap-1 text-sm">
      <button
        v-for="year in yearRange"
        :key="year"
        type="button"
        class="rounded-md px-2 py-1 transition hover:bg-emerald-600/10 hover:text-emerald-600"
        :class="year === currentYear ? 'bg-emerald-600/15 text-emerald-600 font-semibold' : 'text-text/90'"
        @click="selectYear(year)"
      >
        {{ year }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
  currentDate: {
    type: Date,
    required: true,
  },
})

const emit = defineEmits(['select-date'])

const weekDays = ['ПН', 'ВТ', 'СР', 'ЧТ', 'ПТ', 'СБ', 'НД']
const monthFormatter = new Intl.DateTimeFormat('uk-UA', { month: 'long' })
const capitalize = (value) => value.charAt(0).toUpperCase() + value.slice(1)
const months = Array.from({ length: 12 }, (_, index) => capitalize(monthFormatter.format(new Date(2020, index, 1))))

const mode = ref('day')
const viewDate = ref(props.currentDate ? new Date(props.currentDate) : new Date())
const yearPageStart = ref(viewDate.value.getFullYear() - 5)

const normalizeDate = (value) => {
  if (!value) return new Date()
  const date = value instanceof Date ? value : new Date(value)
  return Number.isNaN(date.getTime()) ? new Date() : date
}

const currentDateNormalized = computed(() => normalizeDate(props.currentDate))
const viewYear = computed(() => viewDate.value.getFullYear())
const viewMonth = computed(() => viewDate.value.getMonth())

const monthLabel = computed(() => capitalize(monthFormatter.format(viewDate.value)))
const monthLabelWithYear = computed(() => `${monthLabel.value} ${viewYear.value}`)

const currentYear = computed(() => currentDateNormalized.value.getFullYear())

const yearRange = computed(() => {
  const start = yearPageStart.value
  return Array.from({ length: 11 }, (_, index) => start + index)
})

const yearRangeLabel = computed(() => {
  const start = yearRange.value[0]
  const end = yearRange.value[yearRange.value.length - 1]
  return `${start} - ${end}`
})

const calendarDays = computed(() => {
  const base = new Date(viewYear.value, viewMonth.value, 1)
  const dayOfWeek = base.getDay() || 7
  const offset = dayOfWeek - 1
  const start = new Date(base)
  start.setDate(base.getDate() - offset)

  const selected = currentDateNormalized.value

  const days = []
  for (let i = 0; i < 42; i += 1) {
    const date = new Date(start)
    date.setDate(start.getDate() + i)
    const isCurrentMonth = date.getMonth() === base.getMonth()
    const isSelected = date.getFullYear() === selected.getFullYear()
      && date.getMonth() === selected.getMonth()
      && date.getDate() === selected.getDate()

    days.push({
      key: `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`,
      label: date.getDate(),
      date,
      isCurrentMonth,
      isSelected,
    })
  }
  return days
})

const setMode = (nextMode) => {
  mode.value = nextMode
  if (nextMode === 'year') {
    yearPageStart.value = viewDate.value.getFullYear() - 5
  }
}

const handlePrev = () => {
  if (mode.value === 'day') {
    const next = new Date(viewDate.value)
    next.setMonth(next.getMonth() - 1)
    viewDate.value = next
    return
  }
  if (mode.value === 'month') {
    viewDate.value = new Date(viewDate.value.getFullYear() - 1, viewDate.value.getMonth(), 1)
    return
  }
  yearPageStart.value -= 10
}

const handleNext = () => {
  if (mode.value === 'day') {
    const next = new Date(viewDate.value)
    next.setMonth(next.getMonth() + 1)
    viewDate.value = next
    return
  }
  if (mode.value === 'month') {
    viewDate.value = new Date(viewDate.value.getFullYear() + 1, viewDate.value.getMonth(), 1)
    return
  }
  yearPageStart.value += 10
}

const clampDay = (year, month, day) => {
  const lastDay = new Date(year, month + 1, 0).getDate()
  return Math.min(day, lastDay)
}

const selectDay = (date) => {
  const selectedDate = new Date(date)
  emit('select-date', selectedDate)
  mode.value = 'day'
  if (selectedDate.getMonth() !== viewMonth.value || selectedDate.getFullYear() !== viewYear.value) {
    viewDate.value = new Date(selectedDate.getFullYear(), selectedDate.getMonth(), 1)
  }
}

const selectMonth = (monthIndex) => {
  const selectedDay = currentDateNormalized.value.getDate()
  const year = viewYear.value
  const day = clampDay(year, monthIndex, selectedDay)
  const next = new Date(year, monthIndex, day)
  viewDate.value = next
  emit('select-date', next)
  mode.value = 'day'
}

const selectYear = (year) => {
  const month = currentDateNormalized.value.getMonth()
  const selectedDay = currentDateNormalized.value.getDate()
  const day = clampDay(year, month, selectedDay)
  const next = new Date(year, month, day)
  viewDate.value = next
  emit('select-date', next)
  mode.value = 'month'
}

const isSelectedMonth = (monthIndex) => (
  currentDateNormalized.value.getMonth() === monthIndex
  && currentDateNormalized.value.getFullYear() === viewYear.value
)

watch(
  () => props.currentDate,
  (value) => {
    const normalized = normalizeDate(value)
    viewDate.value = new Date(normalized)
    if (mode.value === 'year') {
      yearPageStart.value = normalized.getFullYear() - 5
    }
  }
)
</script>
