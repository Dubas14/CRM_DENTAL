<template>
  <div class="calendar-time-grid calendar-time-grid--sticky pointer-events-none relative h-full border-r border-border/40">
    <div
      v-for="hour in hours"
      :key="hour"
      class="absolute left-0 right-0 flex items-start"
      :style="{ top: `${(hour - startHour) * hourHeight}px` }"
    >
      <div class="w-14 pr-2 text-right text-xs text-text/60">
        {{ formatHour(hour) }}
      </div>
      <div class="flex-1 border-t border-border/40"></div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  startHour: {
    type: Number,
    default: 8,
  },
  endHour: {
    type: Number,
    default: 22,
  },
  hourHeight: {
    type: Number,
    default: 64,
  },
})

const hours = computed(() => {
  const list = []
  for (let hour = props.startHour; hour <= props.endHour; hour += 1) {
    list.push(hour)
  }
  return list
})

const formatHour = (hour) => `${String(hour).padStart(2, '0')}:00`
</script>
