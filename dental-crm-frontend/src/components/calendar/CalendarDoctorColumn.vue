<template>
  <div class="flex h-full flex-1 min-w-0 flex-col border-r calendar-grid-strong">
    <div v-if="showHeader" class="flex h-12 flex-col justify-center border-b calendar-grid-strong px-3">
      <span
        class="text-sm font-semibold text-text"
        :title="doctorLabel"
        :style="{
          display: '-webkit-box',
          WebkitLineClamp: 2,
          WebkitBoxOrient: 'vertical',
          overflow: 'hidden',
        }"
      >
        {{ doctorLabel }}
      </span>
      <span v-if="doctor?.is_active === false" class="text-[11px] text-rose-300">Неактивний</span>
    </div>
    <div
      ref="bodyRef"
      class="relative flex-1 transition-colors"
      :class="[
        items.length === 0 && interactive ? 'hover:bg-card/20' : '',
        interactive ? 'cursor-crosshair' : 'cursor-default',
      ]"
      :style="{ height: `${bodyHeight}px` }"
      @click="handleBodyClick"
      @pointerdown="handlePointerDown"
    >
      <CalendarAppointment
        v-for="entry in items"
        :key="entry.item.id"
        :item="entry.item"
        :top="entry.top"
        :height="entry.height"
        :stack-offset="entry.stackOffset"
        :read-only="entry.item.isReadOnly"
        :is-dragging="entry.isDragging"
        :interactive="interactive"
        @click="emit('appointment-click', entry.item)"
        @interaction-start="handleInteractionStart"
      />
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import CalendarAppointment from './CalendarAppointment.vue'

const props = defineProps({
  doctor: {
    type: Object,
    required: true,
  },
  showHeader: {
    type: Boolean,
    default: true,
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
  interactive: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['select-slot', 'appointment-click', 'interaction-start', 'draft-start', 'draft-update', 'draft-end'])

const bodyRef = ref(null)
const draftState = ref(null)

const bodyHeight = computed(() => (props.endHour - props.startHour) * props.hourHeight)

const doctorLabel = computed(() => (
  props.doctor?.label
  || props.doctor?.full_name
  || props.doctor?.name
  || 'Лікар'
))

const suppressClickUntil = ref(0)

const handleBodyClick = (event) => {
  if (!props.interactive) return
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

const snapMinutes = (minutes) => {
  const snap = props.snapMinutes
  return Math.round(minutes / snap) * snap
}

const clampMinutes = (value) => {
  const max = (props.endHour - props.startHour) * 60
  return Math.min(Math.max(value, 0), max)
}

const normalizeMinutesFromOffset = (offsetY) => {
  const rawMinutes = offsetY / (props.hourHeight / 60)
  return snapMinutes(rawMinutes)
}

const updateDraft = (event) => {
  if (!draftState.value || !bodyRef.value) return
  const rect = bodyRef.value.getBoundingClientRect()
  const offsetY = event.clientY - rect.top
  const currentMinutes = clampMinutes(normalizeMinutesFromOffset(offsetY))
  const originMinutes = draftState.value.originMinutes

  const startMinutes = Math.min(originMinutes, currentMinutes)
  const endMinutes = Math.max(originMinutes + props.snapMinutes, currentMinutes + props.snapMinutes)
  draftState.value.startMinutes = startMinutes
  draftState.value.endMinutes = clampMinutes(endMinutes)

  emit('draft-update', {
    doctorId: draftState.value.doctorId,
    startMinutes: draftState.value.startMinutes,
    endMinutes: draftState.value.endMinutes,
    baseDate: props.baseDate,
  })
}

const finalizeDraft = () => {
  if (!draftState.value) return
  const payload = {
    doctorId: draftState.value.doctorId,
    startMinutes: draftState.value.startMinutes,
    endMinutes: draftState.value.endMinutes,
    baseDate: props.baseDate,
  }
  const captureTarget = draftState.value.captureTarget
  if (captureTarget?.releasePointerCapture) {
    captureTarget.releasePointerCapture(draftState.value.pointerId)
  }
  emit('draft-end', payload)
  draftState.value = null
  window.removeEventListener('pointermove', handleDraftMove)
  window.removeEventListener('pointerup', handleDraftEnd)
}

const handleDraftMove = (event) => {
  updateDraft(event)
}

const handleDraftEnd = () => {
  finalizeDraft()
}

const handlePointerDown = (event) => {
  if (!props.interactive) return
  if (props.doctor?.is_active === false) return
  if (event.button !== 0) return
  if (event.target?.closest?.('[data-calendar-item]')) return
  if (!bodyRef.value) return
  suppressClickUntil.value = Date.now() + 250
  const rect = bodyRef.value.getBoundingClientRect()
  const offsetY = event.clientY - rect.top
  if (offsetY < 0 || offsetY > rect.height) return

  const originMinutes = clampMinutes(normalizeMinutesFromOffset(offsetY))
  const startMinutes = originMinutes
  const endMinutes = clampMinutes(originMinutes + props.snapMinutes)

  draftState.value = {
    doctorId: props.doctor?.id,
    originMinutes,
    startMinutes,
    endMinutes,
    pointerId: event.pointerId,
    captureTarget: event.currentTarget,
  }

  const captureTarget = event.currentTarget
  if (captureTarget?.setPointerCapture) {
    captureTarget.setPointerCapture(event.pointerId)
  }

  emit('draft-start', {
    doctorId: props.doctor?.id,
    startMinutes,
    endMinutes,
    baseDate: props.baseDate,
  })

  window.addEventListener('pointermove', handleDraftMove)
  window.addEventListener('pointerup', handleDraftEnd)
}

defineExpose({
  bodyRef,
})

onBeforeUnmount(() => {
  window.removeEventListener('pointermove', handleDraftMove)
  window.removeEventListener('pointerup', handleDraftEnd)
})
</script>
