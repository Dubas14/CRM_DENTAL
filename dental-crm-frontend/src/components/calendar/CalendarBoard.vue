<template>
  <div class="flex h-full flex-col">
    <div class="flex flex-1 overflow-x-auto overflow-y-hidden">
      <div class="w-16 shrink-0">
        <CalendarTimeGrid :start-hour="startHour" :end-hour="endHour" :hour-height="hourHeight" />
      </div>

      <div ref="columnsWrapper" class="relative flex min-w-[640px] flex-1 bg-bg/40">
        <div class="absolute inset-0 pointer-events-none">
          <div
            v-for="line in gridLines"
            :key="line.index"
            class="absolute left-0 right-0 border-t border-border/30"
            :style="{ top: `${line.top}px` }"
          ></div>
        </div>

        <div class="relative z-10 flex w-full">
          <CalendarDoctorColumn
            v-for="doctor in doctors"
            :key="doctor.id"
            :ref="(el) => setColumnRef(doctor.id, el)"
            :doctor="doctor"
            :items="itemsByDoctor[doctor.id] || []"
            :start-hour="startHour"
            :end-hour="endHour"
            :hour-height="hourHeight"
            :base-date="date"
            :snap-minutes="snapMinutes"
            @select-slot="handleSelectSlot"
            @appointment-click="(item) => emit('appointment-click', item)"
            @interaction-start="handleInteractionStart"
          />
        </div>

        <CalendarNowLine
          v-if="showNowLine"
          :top="nowLineTop"
          :label="nowLabel"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import CalendarTimeGrid from './CalendarTimeGrid.vue'
import CalendarDoctorColumn from './CalendarDoctorColumn.vue'
import CalendarNowLine from './CalendarNowLine.vue'

const props = defineProps({
  date: {
    type: Date,
    required: true,
  },
  doctors: {
    type: Array,
    default: () => [],
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
  snapMinutes: {
    type: Number,
    default: 15,
  },
})

const emit = defineEmits(['select-slot', 'appointment-click', 'appointment-update', 'appointment-drag-start', 'appointment-drag-end'])

const columnsWrapper = ref(null)
const columnRefs = ref(new Map())
const dragState = ref(null)
const animationFrame = ref(null)
const pendingMoveEvent = ref(null)

const pixelsPerMinute = computed(() => props.hourHeight / 60)

const gridLines = computed(() => {
  const lines = []
  const totalMinutes = (props.endHour - props.startHour) * 60
  for (let minutes = 0; minutes <= totalMinutes; minutes += props.snapMinutes) {
    lines.push({ index: minutes, top: minutes * pixelsPerMinute.value })
  }
  return lines
})

const itemsByDoctor = computed(() => {
  const map = {}
  props.doctors.forEach((doctor) => {
    map[doctor.id] = []
  })

  props.items.forEach((item) => {
    const isDragging = dragState.value?.id === item.id
    const activeItem = isDragging
      ? { ...item, startAt: dragState.value.draftStart, endAt: dragState.value.draftEnd, doctorId: dragState.value.draftDoctorId }
      : item

    if (!activeItem.doctorId || !map[activeItem.doctorId]) return
    const startMinutes = getMinutesFromStart(activeItem.startAt)
    const endMinutes = getMinutesFromStart(activeItem.endAt)
    const height = Math.max((endMinutes - startMinutes) * pixelsPerMinute.value, pixelsPerMinute.value * props.snapMinutes)
    map[activeItem.doctorId].push({
      item: activeItem,
      top: startMinutes * pixelsPerMinute.value,
      height,
      isDragging,
    })
  })

  return map
})

const isToday = computed(() => {
  const now = new Date()
  return (
    now.getFullYear() === props.date.getFullYear()
    && now.getMonth() === props.date.getMonth()
    && now.getDate() === props.date.getDate()
  )
})

const showNowLine = computed(() => isToday.value)

const nowLineTop = computed(() => {
  const now = new Date()
  const minutes = (now.getHours() - props.startHour) * 60 + now.getMinutes()
  if (minutes < 0 || minutes > (props.endHour - props.startHour) * 60) return -999
  return minutes * pixelsPerMinute.value
})

const nowLabel = computed(() => {
  const now = new Date()
  return `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`
})

const setColumnRef = (doctorId, instance) => {
  if (!instance) {
    columnRefs.value.delete(doctorId)
    return
  }
  columnRefs.value.set(doctorId, instance)
}

const normalizeDate = (value) => {
  if (!value) return null
  const date = value instanceof Date ? value : new Date(value)
  return Number.isNaN(date.getTime()) ? null : date
}

const getMinutesFromStart = (date) => {
  const normalized = normalizeDate(date)
  if (!normalized) return 0
  const base = new Date(props.date)
  base.setHours(props.startHour, 0, 0, 0)
  return Math.max(0, Math.round((normalized.getTime() - base.getTime()) / 60000))
}

const getDateFromMinutes = (minutes) => {
  const base = new Date(props.date)
  base.setHours(props.startHour, 0, 0, 0)
  base.setMinutes(base.getMinutes() + minutes)
  return base
}

const handleSelectSlot = ({ doctorId, minutesFromStart }) => {
  const start = getDateFromMinutes(minutesFromStart)
  const end = getDateFromMinutes(minutesFromStart + props.snapMinutes * 2)
  emit('select-slot', { doctorId, start, end })
}

const buildColumnRects = () => {
  return props.doctors
    .map((doctor) => {
      const instance = columnRefs.value.get(doctor.id)
      const body = instance?.bodyRef?.value
      if (!body) return null
      const rect = body.getBoundingClientRect()
      return { doctorId: doctor.id, rect }
    })
    .filter(Boolean)
}

const handleInteractionStart = ({ item, type, pointerEvent }) => {
  if (!pointerEvent) return
  pointerEvent.preventDefault()
  pointerEvent.stopPropagation()

  const columnRects = buildColumnRects()
  if (!columnRects.length) return

  const captureTarget = pointerEvent.target
  if (captureTarget?.setPointerCapture) {
    captureTarget.setPointerCapture(pointerEvent.pointerId)
  }

  dragState.value = {
    id: item.id,
    type,
    pointerId: pointerEvent.pointerId,
    captureTarget,
    originStart: item.startAt,
    originEnd: item.endAt,
    originDoctorId: item.doctorId,
    startX: pointerEvent.clientX,
    startY: pointerEvent.clientY,
    columnRects,
    draftStart: item.startAt,
    draftEnd: item.endAt,
    draftDoctorId: item.doctorId,
  }

  emit('appointment-drag-start', item)

  window.addEventListener('pointermove', handlePointerMove)
  window.addEventListener('pointerup', handlePointerEnd)
}

const resolveDoctorAt = (x, columnRects, fallbackDoctorId) => {
  const found = columnRects.find(({ rect }) => x >= rect.left && x <= rect.right)
  return found?.doctorId || fallbackDoctorId
}

const snapMinutes = (minutes) => {
  const snap = props.snapMinutes
  return Math.round(minutes / snap) * snap
}

const clampMinutesRange = (startMinutes, endMinutes) => {
  const min = 0
  const max = (props.endHour - props.startHour) * 60
  const duration = endMinutes - startMinutes
  let nextStart = Math.max(min, Math.min(startMinutes, max - duration))
  let nextEnd = nextStart + duration
  if (nextEnd > max) {
    nextEnd = max
    nextStart = Math.max(min, max - duration)
  }
  return { startMinutes: nextStart, endMinutes: nextEnd }
}

const processPointerMove = (event) => {
  if (!dragState.value) return
  const deltaMinutes = snapMinutes((event.clientY - dragState.value.startY) / pixelsPerMinute.value)
  const originStartMinutes = getMinutesFromStart(dragState.value.originStart)
  const originEndMinutes = getMinutesFromStart(dragState.value.originEnd)

  let startMinutes = originStartMinutes
  let endMinutes = originEndMinutes

  if (dragState.value.type === 'move') {
    startMinutes = originStartMinutes + deltaMinutes
    endMinutes = originEndMinutes + deltaMinutes
    const clamped = clampMinutesRange(startMinutes, endMinutes)
    startMinutes = clamped.startMinutes
    endMinutes = clamped.endMinutes
  } else if (dragState.value.type === 'resize-start') {
    startMinutes = snapMinutes(originStartMinutes + deltaMinutes)
    startMinutes = Math.min(startMinutes, originEndMinutes - props.snapMinutes)
    startMinutes = Math.max(0, startMinutes)
    endMinutes = originEndMinutes
  } else if (dragState.value.type === 'resize-end') {
    endMinutes = snapMinutes(originEndMinutes + deltaMinutes)
    endMinutes = Math.max(endMinutes, originStartMinutes + props.snapMinutes)
    const max = (props.endHour - props.startHour) * 60
    endMinutes = Math.min(endMinutes, max)
    startMinutes = originStartMinutes
  }

  const doctorId = dragState.value.type === 'move'
    ? resolveDoctorAt(event.clientX, dragState.value.columnRects, dragState.value.originDoctorId)
    : dragState.value.originDoctorId

  dragState.value.draftStart = getDateFromMinutes(startMinutes)
  dragState.value.draftEnd = getDateFromMinutes(endMinutes)
  dragState.value.draftDoctorId = doctorId
}

const handlePointerMove = (event) => {
  pendingMoveEvent.value = event
  if (animationFrame.value) return
  animationFrame.value = requestAnimationFrame(() => {
    animationFrame.value = null
    if (pendingMoveEvent.value) {
      processPointerMove(pendingMoveEvent.value)
      pendingMoveEvent.value = null
    }
  })
}

const handlePointerEnd = () => {
  if (!dragState.value) return
  const payload = {
    id: dragState.value.id,
    startAt: dragState.value.draftStart,
    endAt: dragState.value.draftEnd,
    doctorId: dragState.value.draftDoctorId,
  }

  const captureTarget = dragState.value.captureTarget
  if (captureTarget?.releasePointerCapture) {
    captureTarget.releasePointerCapture(dragState.value.pointerId)
  }

  emit('appointment-update', payload)
  emit('appointment-drag-end')

  dragState.value = null
  window.removeEventListener('pointermove', handlePointerMove)
  window.removeEventListener('pointerup', handlePointerEnd)
}

watch(
  () => props.items,
  () => {
    if (!dragState.value) return
    const current = props.items.find((item) => item.id === dragState.value.id)
    if (!current) {
      dragState.value = null
    }
  }
)

onBeforeUnmount(() => {
  window.removeEventListener('pointermove', handlePointerMove)
  window.removeEventListener('pointerup', handlePointerEnd)
  if (animationFrame.value) cancelAnimationFrame(animationFrame.value)
})
</script>
