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
      class="month-picker absolute left-0 top-full z-20 mt-2 min-w-[300px] rounded-md border border-border/80 bg-card p-4 shadow-lg"
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
      <div class="grid grid-cols-3 gap-3 text-sm">
        <button
          v-for="(month, index) in months"
          :key="month"
          type="button"
          class="month-item rounded-[10px] border border-transparent px-[10px] py-[8px] text-center text-[13px] font-medium text-text/70 transition hover:bg-white/10"
          :class="
            index === currentMonth && selectedYear === currentYear
              ? 'is-selected rounded-[9999px] bg-blue-600 text-white'
              : ''
          "
          @click="selectMonth(index)"
        >
          {{ month }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps({
  date: {
    type: Date,
    required: true
  }
})

const emit = defineEmits(['select'])

const monthFormatter = new Intl.DateTimeFormat('uk-UA', { month: 'long' })

const capitalize = (value) => value.charAt(0).toUpperCase() + value.slice(1)

const months = Array.from({ length: 12 }, (_, index) => {
  const label = monthFormatter.format(new Date(2020, index, 1))
  return capitalize(label)
})

const open = ref(false)
const root = ref(null)
const selectedYear = ref(props.date.getFullYear())

const formattedLabel = computed(() => {
  const monthName = months[props.date.getMonth()]
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
