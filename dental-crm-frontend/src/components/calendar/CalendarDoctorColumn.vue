<template>
  <div class="flex h-full flex-1 min-w-0 flex-col border-r calendar-grid-strong">
    <!-- Header -->
    <div
      v-if="showHeader"
      class="flex h-12 flex-col justify-center border-b calendar-grid-strong px-3"
    >
      <span
        class="text-sm font-semibold text-text"
        :title="doctorLabel"
        :style="{
          display: '-webkit-box',
          WebkitLineClamp: 2,
          WebkitBoxOrient: 'vertical',
          overflow: 'hidden'
        }"
      >
        {{ doctorLabel }}
      </span>
      <span v-if="doctor?.is_active === false" class="text-[11px] text-rose-300"> Неактивний </span>
    </div>

    <!-- Body -->
    <div
      ref="bodyRef"
      class="relative flex-1 transition-colors"
      :class="[
        items.length === 0 && interactive ? 'hover:bg-card/20' : '',
        interactive ? 'cursor-crosshair' : 'cursor-default'
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
        :is-drag-source="entry.isDragSource"
        :interactive="interactive"
        @click="emit('appointment-click', entry.item)"
        @interaction-start="handleInteractionStart"
        @contextmenu.prevent="(event: MouseEvent) => emit('contextmenu', { event, item: entry.item })"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, type PropType } from 'vue'
import CalendarAppointment from './CalendarAppointment.vue'

interface CalendarItem {
  id: string | number
  title: string
  startAt: string | Date
  endAt: string | Date
  doctorId: string | number
  type?: string
  status?: string
  isReadOnly?: boolean
}

interface ColumnEntry {
  item: CalendarItem
  top: number
  height: number
  isDragging: boolean
  isDragSource?: boolean
  stackOffset?: number
}

/* ===================== PROPS ===================== */

const props = defineProps({
  doctor: { type: Object, required: true },
  showHeader: { type: Boolean, default: true },
  items: { type: Array as PropType<ColumnEntry[]>, default: () => [] },

  startHour: { type: Number, default: 8 },
  endHour: { type: Number, default: 22 },
  activeStartHour: { type: Number, default: null },
  activeEndHour: { type: Number, default: null },

  hourHeight: { type: Number, default: 64 },
  baseDate: { type: Date, required: true },

  snapMinutes: { type: Number, default: 15 },
  interactive: { type: Boolean, default: true }
})

const emit = defineEmits([
  'select-slot',
  'appointment-click',
  'interaction-start',
  'draft-start',
  'draft-update',
  'draft-end',
  'contextmenu'
])

/* ===================== STATE ===================== */

const bodyRef = ref<HTMLElement | null>(null)
const draftState = ref<{
  doctorId: string | number
  originMinutes: number
  startMinutes: number
  endMinutes: number
  pointerId: number
  captureTarget: HTMLElement | null
} | null>(null)
const suppressClickUntil = ref(0)

/* ===================== COMPUTED ===================== */

const bodyHeight = computed(() => (props.endHour - props.startHour) * props.hourHeight)

const doctorLabel = computed(
  () => props.doctor?.label || props.doctor?.full_name || props.doctor?.name || 'Лікар'
)

const resolvedDoctorId = computed(() => props.doctor?.doctorId ?? props.doctor?.id)

const activeMinutesRange = computed(() => {
  const activeStart = props.activeStartHour ?? props.startHour
  const activeEnd = props.activeEndHour ?? props.endHour

  const min = Math.max(0, (activeStart - props.startHour) * 60)
  const max = Math.min((props.endHour - props.startHour) * 60, (activeEnd - props.startHour) * 60)

  return { min, max }
})

/* ===================== HELPERS ===================== */

const pixelsPerMinute = props.hourHeight / 60

const snapToMinutes = (minutes: number) => {
  const snap = props.snapMinutes
  return Math.round(minutes / snap) * snap
}

const clampMinutes = (value: number) => {
  const { min, max } = activeMinutesRange.value
  return Math.min(Math.max(value, min), max)
}

const normalizeMinutesFromOffset = (offsetY: number) => {
  const rawMinutes = offsetY / pixelsPerMinute
  return snapToMinutes(rawMinutes)
}

/* ===================== CLICK CREATE ===================== */

const handleBodyClick = (event: MouseEvent) => {
  if (!props.interactive) return
  if (props.doctor?.is_active === false) return
  if (Date.now() < suppressClickUntil.value) return
  if (!bodyRef.value) return

  const rect = (bodyRef.value as HTMLElement).getBoundingClientRect()
  const offsetY = event.clientY - rect.top
  if (offsetY < 0 || offsetY > rect.height) return

  const snappedMinutes = normalizeMinutesFromOffset(offsetY)
  const { min, max } = activeMinutesRange.value
  if (snappedMinutes < min || snappedMinutes + props.snapMinutes > max) return

  emit('select-slot', {
    doctorId: resolvedDoctorId.value,
    minutesFromStart: snappedMinutes,
    baseDate: props.baseDate
  })
}

/* ===================== APPOINTMENT INTERACTION ===================== */

const handleInteractionStart = (payload: any) => {
  suppressClickUntil.value = Date.now() + 250
  emit('interaction-start', payload)
}

/* ===================== DRAFT CREATE (DRAG) ===================== */

const updateDraft = (event: PointerEvent) => {
  if (!draftState.value || !bodyRef.value) return

  const rect = (bodyRef.value as HTMLElement).getBoundingClientRect()
  const offsetY = event.clientY - rect.top
  const currentMinutes = clampMinutes(normalizeMinutesFromOffset(offsetY))

  const origin = (draftState.value as any).originMinutes
  const startMinutes = Math.min(origin, currentMinutes)
  const endMinutes = Math.max(startMinutes + props.snapMinutes, currentMinutes + props.snapMinutes)

  ;(draftState.value as any).startMinutes = startMinutes
  ;(draftState.value as any).endMinutes = clampMinutes(endMinutes)

  emit('draft-update', {
    doctorId: (draftState.value as any).doctorId,
    startMinutes: (draftState.value as any).startMinutes,
    endMinutes: (draftState.value as any).endMinutes,
    baseDate: props.baseDate
  })
}

const finalizeDraft = () => {
  if (!draftState.value) return

  const ds = draftState.value as any
  const payload = {
    doctorId: ds.doctorId,
    startMinutes: ds.startMinutes,
    endMinutes: ds.endMinutes,
    baseDate: props.baseDate
  }

  const captureTarget = ds.captureTarget
  if (captureTarget?.releasePointerCapture) {
    captureTarget.releasePointerCapture(ds.pointerId)
  }

  emit('draft-end', payload)
  draftState.value = null

  window.removeEventListener('pointermove', handleDraftMove as any)
  window.removeEventListener('pointerup', handleDraftEnd as any)
}

const handleDraftMove = (event: PointerEvent) => updateDraft(event)
const handleDraftEnd = () => finalizeDraft()

const handlePointerDown = (event: PointerEvent) => {
  if (!props.interactive) return
  if (props.doctor?.is_active === false) return
  if (event.button !== 0) return
  const target = event.target as HTMLElement
  if (target?.closest?.('[data-calendar-item]')) return
  const currentTarget = event.currentTarget as HTMLElement
  if (!bodyRef.value || !currentTarget) return

  suppressClickUntil.value = Date.now() + 250

  const rect = (bodyRef.value as HTMLElement).getBoundingClientRect()
  const offsetY = event.clientY - rect.top
  if (offsetY < 0 || offsetY > rect.height) return

  const originMinutes = clampMinutes(normalizeMinutesFromOffset(offsetY))
  const { min, max } = activeMinutesRange.value
  if (originMinutes < min || originMinutes + props.snapMinutes > max) return

  const initialDraft = {
    doctorId: resolvedDoctorId.value as string | number,
    originMinutes,
    startMinutes: originMinutes,
    endMinutes: originMinutes + props.snapMinutes,
    pointerId: event.pointerId,
    captureTarget: currentTarget
  }
  
  draftState.value = initialDraft

  if (currentTarget.setPointerCapture) {
    currentTarget.setPointerCapture(event.pointerId)
  }

  emit('draft-start', {
    doctorId: initialDraft.doctorId,
    startMinutes: initialDraft.startMinutes,
    endMinutes: initialDraft.endMinutes,
    baseDate: props.baseDate
  })

  window.addEventListener('pointermove', handleDraftMove as any)
  window.addEventListener('pointerup', handleDraftEnd as any)
}

/* ===================== EXPOSE ===================== */

defineExpose({ bodyRef })

onBeforeUnmount(() => {
  window.removeEventListener('pointermove', handleDraftMove as any)
  window.removeEventListener('pointerup', handleDraftEnd as any)
})
</script>
