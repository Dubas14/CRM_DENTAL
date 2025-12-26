<template>
  <div ref="calendarEl" class="h-full w-full"></div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import Calendar from '@toast-ui/calendar'
import '@toast-ui/calendar/dist/toastui-calendar.css'
import '../assets/css/toast-calendar-theme.css'
import { defineExpose } from 'vue'

defineExpose({
  next: () => calendarInstance?.next(),
  prev: () => calendarInstance?.prev(),
  today: () => calendarInstance?.today(),
  changeView: (view) => calendarInstance?.changeView(view),
})

const props = defineProps({
  events: {
    type: Array,
    default: () => [],
  },
})

const calendarEl = ref(null)
let calendarInstance = null

onMounted(() => {
  calendarInstance = new Calendar(calendarEl.value, {
    defaultView: 'week',
    height: '100%',
    calendars: [
      {
        id: 'main',
        name: 'Основний',
        backgroundColor: '#2563eb',
        borderColor: '#2563eb',
      },
    ],
    week: {
      startDayOfWeek: 1,
      hourStart: 8,
      hourEnd: 22,
    },
    useDetailPopup: false,
    useFormPopup: false,
  })

  // 🔥 ОЦЕ КРИТИЧНО
  if (props.events.length) {
    calendarInstance.createEvents(props.events)
  }
})

watch(
    () => props.events,
    (events) => {
      if (!calendarInstance) return
      calendarInstance.clear()
      calendarInstance.createEvents(events)
    },
    { deep: true }
)

onBeforeUnmount(() => {
  calendarInstance?.destroy()
})
</script>
