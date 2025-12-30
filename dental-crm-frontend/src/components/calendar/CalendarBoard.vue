<template>
  <div class="flex w-full flex-col">
    <div class="flex w-full">
      <div class="w-16 shrink-0">
        <CalendarTimeGrid :start-hour="startHour" :end-hour="endHour" :hour-height="hourHeight" />
      </div>

      <div
        ref="columnsWrapper"
        class="relative flex flex-1 min-w-0 bg-bg/40"
        @mousemove="handleHoverMove"
        @mouseleave="clearHover"
      >
        <div class="absolute inset-0 pointer-events-none">
          <div
            v-for="block in inactiveBlocks"
            :key="block.key"
            class="hour--inactive absolute left-0 right-0 bg-bg/60"
            :style="{ top: `${block.top}px`, height: `${block.height}px` }"
          ></div>
        </div>

        <div class="absolute inset-0 pointer-events-none">
          <div
              v-for="line in gridLines"
              :key="line.index"
              class="absolute left-0 right-0 border-t"
              :class="{
    'calendar-grid-strong': line.type === 'hour',
    'calendar-grid-medium': line.type === 'half',
    'calendar-grid-light': line.type === 'quarter',
  }"
              :style="{ top: `${line.top}px` }"
          />
        </div>

        <div
          v-if="hoverSlot.visible"
          class="absolute z-10 pointer-events-none rounded-md"
          :style="{
            top: `${hoverSlot.top}px`,
            left: `${hoverSlot.left}px`,
            width: `${hoverSlot.width}px`,
            height: `${hoverSlot.height}px`,
          }"
        >
          <div class="h-full w-full rounded-md bg-sky-500/15 ring-1 ring-sky-400/30"></div>
        </div>

        <div class="relative z-10 flex min-w-0 flex-1">
          <CalendarDoctorColumn
            v-for="column in resolvedColumns"
            :key="column.id"
            :ref="(el) => setColumnRef(column.id, el)"
            :doctor="column"
            :show-header="showDoctorHeader"
            :items="itemsByColumn[column.id] || []"
            :start-hour="startHour"
            :end-hour="endHour"
            :hour-height="hourHeight"
            :base-date="date"
            :snap-minutes="snapMinutes"
            :interactive="interactive"
            @select-slot="handleSelectSlot"
            @appointment-click="(item) => emit('appointment-click', item)"
            @interaction-start="handleInteractionStart"
            @draft-start="handleDraftStart"
            @draft-update="handleDraftUpdate"
            @draft-end="handleDraftEnd"
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
  columns: {
    type: Array,
    default: null,
  },
  groupBy: {
    type: String,
    default: 'doctor',
  },
  viewMode: {
    type: String,
    default: 'day',
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
  activeStartHour: {
    type: Number,
    default: null,
  },
  activeEndHour: {
    type: Number,
    default: null,
  },
  hourHeight: {
    type: Number,
    default: 64,
  },
  snapMinutes: {
    type: Number,
    default: 15,
  },
  showDoctorHeader: {
    type: Boolean,
    default: true,
  },
  interactive: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['select-slot', 'appointment-click', 'appointment-update', 'appointment-drag-start', 'appointment-drag-end'])

const columnsWrapper = ref(null)
const columnRefs = ref(new Map())
const dragState = ref(null)
const animationFrame = ref(null)
const pendingMoveEvent = ref(null)
const draftState = ref(null)
const hoverSlot = ref({
  visible: false,
  top: 0,
  left: 0,
  width: 0,
  height: 0,
})

const pixelsPerMinute = computed(() => props.hourHeight / 60)

const gridLines = computed(() => {
  const lines = []
  const totalMinutes = (props.endHour - props.startHour) * 60
  for (let minutes = 0; minutes <= totalMinutes; minutes += props.snapMinutes) {
    const isHour = minutes % 60 === 0
    const isHalfHour = minutes % 30 === 0 && !isHour

    lines.push({
      index: minutes,
      top: minutes * pixelsPerMinute.value,
      type: isHour
          ? 'hour'
          : isHalfHour
              ? 'half'
              : 'quarter',
    })
  }
  return lines
})

const inactiveBlocks = computed(() => {
  const blocks = []
  const activeStart = props.activeStartHour ?? props.startHour
  const activeEnd = props.activeEndHour ?? props.endHour
  if (activeStart > props.startHour) {
    blocks.push({
      key: 'inactive-start',
      top: 0,
      height: (activeStart - props.startHour) * props.hourHeight,
    })
  }
  if (activeEnd < props.endHour) {
    blocks.push({
      key: 'inactive-end',
      top: (activeEnd - props.startHour) * props.hourHeight,
      height: (props.endHour - activeEnd) * props.hourHeight,
    })
  }
  return blocks
})

const resolvedColumns = computed(() => {
  if (props.columns && props.columns.length) return props.columns
  return props.doctors || []
})

const resolvedGroupBy = computed(() => (props.viewMode === 'week' ? 'date' : props.groupBy))

const resolveGroupKey = (item) => {
  if (resolvedGroupBy.value === 'date') {
    const normalized = normalizeDate(item.startAt)
    if (!normalized) return null
    const year = normalized.getFullYear()
    const month = `${normalized.getMonth() + 1}`.padStart(2, '0')
    const day = `${normalized.getDate()}`.padStart(2, '0')
    return `${year}-${month}-${day}`
  }
  return item.doctorId || null
}

const itemsByColumn = computed(() => {
  const map = {}
  resolvedColumns.value.forEach((column) => {
    map[column.id] = []
  })

  props.items.forEach((item) => {
    const isDragging = dragState.value?.id === item.id
    const activeItem = isDragging
      ? { ...item, startAt: dragState.value.draftStart, endAt: dragState.value.draftEnd, doctorId: dragState.value.draftDoctorId }
      : item

    const groupKey = resolveGroupKey(activeItem)
    if (!groupKey || !map[groupKey]) return
    const startMinutes = getMinutesFromStart(activeItem.startAt)
    const endMinutes = getMinutesFromStart(activeItem.endAt)
    const height = Math.max((endMinutes - startMinutes) * pixelsPerMinute.value, pixelsPerMinute.value * props.snapMinutes)
    map[groupKey].push({
      item: activeItem,
      top: startMinutes * pixelsPerMinute.value,
      height,
      isDragging,
    })
  })

  if (draftState.value) {
    const draftKey = resolvedGroupBy.value === 'date'
      ? formatDateKey(draftState.value.baseDate)
      : draftState.value.doctorId
    if (draftKey && map[draftKey]) {
      const startMinutes = draftState.value.startMinutes
      const endMinutes = draftState.value.endMinutes
      map[draftKey].push({
        item: {
          id: 'draft-create',
          type: 'draft',
          title: 'Новий запис',
          startAt: draftState.value.startAt,
          endAt: draftState.value.endAt,
          doctorId: draftState.value.doctorId,
          isReadOnly: true,
        },
        top: startMinutes * pixelsPerMinute.value,
        height: Math.max((endMinutes - startMinutes) * pixelsPerMinute.value, pixelsPerMinute.value * props.snapMinutes),
        isDragging: true,
      })
    }
  }

  Object.values(map).forEach((entries) => {
    const overlapCounts = new Map()
    entries.forEach((entry) => {
      const index = overlapCounts.get(entry.top) || 0
      entry.stackOffset = index * 3
      overlapCounts.set(entry.top, index + 1)
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

const formatDateKey = (date) => {
  if (!date) return null
  const year = date.getFullYear()
  const month = `${date.getMonth() + 1}`.padStart(2, '0')
  const day = `${date.getDate()}`.padStart(2, '0')
  return `${year}-${month}-${day}`
}

const getMinutesFromStart = (date) => {
  const normalized = normalizeDate(date)
  if (!normalized) return 0
  const minutes = normalized.getHours() * 60 + normalized.getMinutes()
  const startMinutes = props.startHour * 60
  return Math.max(0, minutes - startMinutes)
}

const getDateFromMinutes = (minutes) => {
  const base = new Date(props.date)
  base.setHours(props.startHour, 0, 0, 0)
  base.setMinutes(base.getMinutes() + minutes)
  return base
}

const getDateFromMinutesForBase = (baseDate, minutes) => {
  const base = new Date(baseDate || props.date)
  base.setHours(props.startHour, 0, 0, 0)
  base.setMinutes(base.getMinutes() + minutes)
  return base
}

const handleSelectSlot = ({ doctorId, minutesFromStart }) => {
  if (!props.interactive) return
  const start = getDateFromMinutes(minutesFromStart)
  const end = getDateFromMinutes(minutesFromStart + props.snapMinutes * 2)
  emit('select-slot', { doctorId, start, end })
}

const buildColumnRects = () => {
  return resolvedColumns.value
    .map((column) => {
      const instance = columnRefs.value.get(column.id)
      const body = instance?.bodyRef?.value
      if (!body) return null
      const rect = body.getBoundingClientRect()
      return { doctorId: column.id, rect }
    })
    .filter(Boolean)
}

const handleInteractionStart = ({ item, type, pointerEvent }) => {
  if (!props.interactive) return
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

const computeHoverSlot = (event, columnRects) => {
  const wrapperRect = columnsWrapper.value?.getBoundingClientRect()
  if (!wrapperRect) return null
  const hovered = columnRects.find(({ rect }) => event.clientX >= rect.left && event.clientX <= rect.right)
  if (!hovered) return null

  const offsetY = event.clientY - hovered.rect.top
  if (offsetY < 0 || offsetY > hovered.rect.height) return null
  const minutesFromStart = Math.round(offsetY / pixelsPerMinute.value)
  const snappedMinutes = snapMinutes(minutesFromStart)
  const top = snappedMinutes * pixelsPerMinute.value
  return {
    top: hovered.rect.top - wrapperRect.top + top,
    left: hovered.rect.left - wrapperRect.left,
    width: hovered.rect.width,
    height: props.snapMinutes * pixelsPerMinute.value,
  }
}

const handleHoverMove = (event) => {
  if (!props.interactive || !columnsWrapper.value) return
  const columnRects = buildColumnRects()
  if (!columnRects.length) return
  const slot = computeHoverSlot(event, columnRects)
  if (!slot) {
    hoverSlot.value.visible = false
    return
  }
  hoverSlot.value = { ...slot, visible: true }
}

const clearHover = () => {
  hoverSlot.value.visible = false
}

const updateDraftState = (payload) => {
  const { doctorId, startMinutes, endMinutes, baseDate } = payload
  const startAt = getDateFromMinutesForBase(baseDate, startMinutes)
  const endAt = getDateFromMinutesForBase(baseDate, endMinutes)
  draftState.value = { doctorId, startMinutes, endMinutes, baseDate, startAt, endAt }
}

const handleDraftStart = (payload) => {
  if (!props.interactive) return
  updateDraftState(payload)
}

const handleDraftUpdate = (payload) => {
  if (!props.interactive || !draftState.value) return
  updateDraftState(payload)
}

const handleDraftEnd = (payload) => {
  if (!props.interactive) return
  updateDraftState(payload)
  emit('select-slot', { doctorId: payload.doctorId, start: draftState.value.startAt, end: draftState.value.endAt })
  draftState.value = null
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

<style scoped>
.hour--inactive {
  opacity: 0.35;
}
</style>
