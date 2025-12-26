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
      <div class="grid grid-cols-4 gap-1 text-sm">
        <button
          v-for="(month, index) in months"
          :key="month"
          type="button"
          class="rounded-md px-2 py-1 text-text/90 transition hover:bg-emerald-600/10 hover:text-emerald-600"
          :class="index === currentMonth ? 'bg-emerald-600/15 text-emerald-600 font-semibold' : ''"
          @click="selectMonth(index)"
        >
          {{ month }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

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

const formatter = new Intl.DateTimeFormat('uk-UA', {
  month: 'long',
  year: 'numeric',
})

const open = ref(false)
const root = ref(null)

const formattedLabel = computed(() => {
  const formatted = formatter.format(props.date)
  return formatted.charAt(0).toUpperCase() + formatted.slice(1)
})

const currentMonth = computed(() => props.date.getMonth())

const toggle = () => {
  open.value = !open.value
}

const selectMonth = (monthIndex) => {
  emit('select', monthIndex)
  open.value = false
}

const handleClickOutside = (event) => {
  if (!open.value) return
  if (root.value && !root.value.contains(event.target)) {
    open.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>
