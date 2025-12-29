<template>
  <div
    class="absolute left-1 right-1 rounded-lg border px-2 py-1 text-left text-xs shadow-sm transition-shadow"
    :class="[backgroundClass, cursorClass, isDragging ? 'opacity-80' : '']"
    :style="styleObject"
    data-calendar-item="appointment"
    @click.stop="handleClick"
    @pointerdown="handlePointerDown"
  >
    <div v-if="showResizeHandles" class="pointer-events-none absolute left-0 right-0 top-0 h-2 cursor-ns-resize"></div>
    <div v-if="showResizeHandles" class="pointer-events-none absolute bottom-0 left-0 right-0 h-2 cursor-ns-resize"></div>

    <div class="flex items-start justify-between gap-2">
      <div class="font-semibold text-white/90">{{ item.title }}</div>
      <span v-if="item.type === 'block'" class="text-[10px] text-white/70">Блок</span>
      <span v-else-if="item.status === 'done'" class="text-[10px] text-white/70">✅</span>
    </div>
    <div class="text-[11px] text-white/80">{{ timeLabel }}</div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  item: {
    type: Object,
    required: true,
  },
  top: {
    type: Number,
    required: true,
  },
  height: {
    type: Number,
    required: true,
  },
  stackOffset: {
    type: Number,
    default: 0,
  },
  isDragging: {
    type: Boolean,
    default: false,
  },
  readOnly: {
    type: Boolean,
    default: false,
  },
  interactive: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['click', 'interaction-start'])

const normalizeDate = (value) => {
  if (!value) return null
  const date = value instanceof Date ? value : new Date(value)
  return Number.isNaN(date.getTime()) ? null : date
}

const timeLabel = computed(() => {
  const start = normalizeDate(props.item.startAt)
  const end = normalizeDate(props.item.endAt)
  if (!start || !end) return ''
  const format = (date) => `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
  return `${format(start)}–${format(end)}`
})

const isPast = computed(() => {
  const endAt = normalizeDate(props.item.endAt)
  return Boolean(endAt && endAt < new Date())
})

const backgroundClass = computed(() => {
  if (props.item.type === 'draft') {
    return 'bg-sky-500/30 border-sky-300/70 text-white/80 border-dashed'
  }
  if (props.item.type === 'block') {
    return 'bg-slate-500/80 border-slate-400/70 text-white'
  }
  if (props.item.status === 'done') {
    return 'bg-blue-600/90 border-blue-400/80 text-white'
  }
  if (isPast.value) {
    return 'bg-slate-600/80 border-slate-400/70 text-white'
  }
  return 'bg-emerald-500/90 border-emerald-400/80 text-white'
})

const cursorClass = computed(() => {
  if (props.isDragging) return 'cursor-grabbing'
  if (!props.interactive || props.readOnly || props.item.type !== 'appointment') return 'cursor-default'
  return 'cursor-grab'
})

const showResizeHandles = computed(() => !props.readOnly && props.item.type === 'appointment')

const styleObject = computed(() => ({
  top: `${props.top + props.stackOffset}px`,
  height: `${props.height}px`,
}))

const handleClick = () => {
  emit('click', props.item)
}

const handlePointerDown = (event) => {
  if (!props.interactive || props.readOnly || props.item.type !== 'appointment') return
  const rect = event.currentTarget?.getBoundingClientRect?.()
  if (!rect) return
  const offsetY = event.clientY - rect.top
  const edgeSize = 10
  if (offsetY <= edgeSize) {
    emit('interaction-start', { item: props.item, type: 'resize-start', pointerEvent: event })
    return
  }
  if (offsetY >= rect.height - edgeSize) {
    emit('interaction-start', { item: props.item, type: 'resize-end', pointerEvent: event })
    return
  }
  emit('interaction-start', { item: props.item, type: 'move', pointerEvent: event })
}
</script>
