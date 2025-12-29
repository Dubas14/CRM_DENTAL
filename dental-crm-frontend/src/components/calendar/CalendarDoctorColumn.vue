<template>
  <div class="flex h-full flex-col border-r border-border/30">
    <div class="flex h-12 flex-col justify-center border-b border-border/30 px-3">
      <span class="text-sm font-semibold text-text">{{ doctorLabel }}</span>
      <span v-if="doctor?.is_active === false" class="text-[11px] text-rose-300">Неактивний</span>
    </div>
    <div
      ref="bodyRef"
      class="relative flex-1 cursor-crosshair"
      :style="{ height: `${bodyHeight}px` }"
      @click="handleBodyClick"
    >
      <CalendarAppointment
        v-for="entry in items"
        :key="entry.item.id"
        :item="entry.item"
        :top="entry.top"
        :height="entry.height"
        :read-only="entry.item.isReadOnly"
        :is-dragging="entry.isDragging"
        @click="emit('appointment-click', entry.item)"
        @interaction-start="handleInteractionStart"
      />
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import CalendarAppointment from './CalendarAppointment.vue'

const props = defineProps({
  doctor: {
    type: Object,
    required: true,
  },
  items: {
    type: Array,
    default: () => [],
  },
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
  baseDate: {
    type: Date,
    required: true,
  },
  snapMinutes: {
    type: Number,
    default: 15,
  },
})

const emit = defineEmits(['select-slot', 'appointment-click', 'interaction-start'])

const bodyRef = ref(null)

const bodyHeight = computed(() => (props.endHour - props.startHour) * props.hourHeight)

const doctorLabel = computed(() => props.doctor?.full_name || props.doctor?.name || 'Лікар')

const suppressClickUntil = ref(0)

const handleBodyClick = (event) => {
  if (props.doctor?.is_active === false) return
  if (Date.now() < suppressClickUntil.value) return
  if (!bodyRef.value) return
  const rect = bodyRef.value.getBoundingClientRect()
  const offsetY = event.clientY - rect.top
  if (offsetY < 0 || offsetY > rect.height) return

  const minutesFromStart = Math.round(offsetY / (props.hourHeight / 60))
  const snappedMinutes = Math.round(minutesFromStart / props.snapMinutes) * props.snapMinutes
  emit('select-slot', {
    doctorId: props.doctor?.id,
    minutesFromStart: snappedMinutes,
  })
}

const handleInteractionStart = (payload) => {
  suppressClickUntil.value = Date.now() + 250
  emit('interaction-start', payload)
}

defineExpose({
  bodyRef,
})
</script>
