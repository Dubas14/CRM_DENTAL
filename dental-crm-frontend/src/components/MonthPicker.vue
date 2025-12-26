<template>
  <div ref="root" class="relative inline-flex items-center">
    <button
      type="button"
      class="text-lg font-semibold text-text transition-colors hover:text-text/80"
      @click="toggle"
    >
      {{ formattedLabel }}
    </button>

    <div
      v-if="open"
      class="absolute left-0 top-full z-20 mt-2 w-60 rounded-md border border-border/80 bg-card p-2 shadow-lg"
    >
      <div class="mb-2 flex items-center justify-between px-1 text-sm font-medium text-text/80">
        <button
          type="button"
          class="rounded-md px-2 py-1 transition hover:bg-card/80"
          @click="changeYear(-1)"
        >
          ◀
        </button>
        <span class="text-text/90">{{ selectedYear }}</span>
        <button
          type="button"
          class="rounded-md px-2 py-1 transition hover:bg-card/80"
          @click="changeYear(1)"
        >
          ▶
        </button>
      </div>
      <div class="grid grid-cols-4 gap-1 text-sm">
        <button
          v-for="(month, index) in months"
          :key="month"
          type="button"
          class="rounded-md px-2 py-1 text-text/90 transition hover:bg-emerald-600/10 hover:text-emerald-600"
          :class="index === currentMonth && selectedYear === currentYear ? 'bg-emerald-600/15 text-emerald-600 font-semibold' : ''"
          @click="selectMonth(index)"
        >
          {{ month }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps({
  date: {
    type: Date,
    required: true,
  },
})

const emit = defineEmits(['select'])

const months = [
  'Січ',
  'Лют',
  'Бер',
  'Кві',
  'Тра',
  'Чер',
  'Лип',
  'Сер',
  'Вер',
  'Жов',
  'Лис',
  'Гру',
]

const fullMonths = [
  'Січень',
  'Лютий',
  'Березень',
  'Квітень',
  'Травень',
  'Червень',
  'Липень',
  'Серпень',
  'Вересень',
  'Жовтень',
  'Листопад',
  'Грудень',
]

const open = ref(false)
const root = ref(null)
const selectedYear = ref(props.date.getFullYear())

const formattedLabel = computed(() => {
  const monthName = fullMonths[props.date.getMonth()]
  return `${monthName} ${props.date.getFullYear()} р.`
})

const currentMonth = computed(() => props.date.getMonth())
const currentYear = computed(() => props.date.getFullYear())

const toggle = () => {
  if (!open.value) {
    selectedYear.value = props.date.getFullYear()
  }
  open.value = !open.value
}

const selectMonth = (monthIndex) => {
  emit('select', { monthIndex, year: selectedYear.value })
  open.value = false
}

const changeYear = (delta) => {
  selectedYear.value += delta
}

const handleClickOutside = (event) => {
  if (!open.value) return
  if (root.value && !root.value.contains(event.target)) {
    open.value = false
  }
}

watch(
  () => props.date,
  (newDate) => {
    if (open.value) return
    selectedYear.value = newDate.getFullYear()
  }
)

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>
