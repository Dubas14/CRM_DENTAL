<template>
  <div
    class="absolute left-1 right-1 rounded-md border-l-4 px-2 py-1 text-left text-xs shadow-sm transition-all duration-200 ease-out hover:shadow-md hover:scale-[1.01] overflow-hidden group"
    :class="[
      backgroundClass,
      cursorClass,
      isDragging ? 'z-20 ring-2 ring-emerald-400/50 shadow-lg scale-[1.02]' : 'z-10',
      isDragSource ? 'opacity-40 grayscale pointer-events-none' : 'opacity-100'
    ]"
    :style="styleObject"
    data-calendar-item="appointment"
    @click.stop="handleClick"
    @pointerdown="handlePointerDown"
  >
    <!-- Resize Handles -->
    <div
      v-if="showResizeHandles"
      class="pointer-events-none absolute left-0 right-0 top-0 h-1.5 cursor-ns-resize opacity-0 group-hover:opacity-100 bg-black/5"
    ></div>
    <div
      v-if="showResizeHandles"
      class="pointer-events-none absolute bottom-0 left-0 right-0 h-1.5 cursor-ns-resize opacity-0 group-hover:opacity-100 bg-black/5"
    ></div>

    <div class="flex flex-col h-full overflow-hidden">
      <!-- Header: Time & Status -->
      <div class="flex items-center gap-1.5 min-w-0 mb-0.5">
        <span class="text-[10px] font-bold opacity-80 whitespace-nowrap tabular-nums tracking-tight">
          {{ timeLabel }}
        </span>
        <span v-if="item.status === 'done'" class="text-[10px] opacity-100" title="Виконано">✅</span>
      </div>

      <!-- Title / Patient Name -->
      <div class="font-semibold text-sm leading-tight truncate">
        {{ item.title }}
      </div>

      <!-- Optional: Icons or extra info -->
      <div v-if="item.type === 'block'" class="mt-auto text-[10px] opacity-70 italic">
        Блок
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps({
  item: {
    type: Object,
    required: true
  },
  top: {
    type: Number,
    required: true
  },
  height: {
    type: Number,
    required: true
  },
  stackOffset: {
    type: Number,
    default: 0
  },
  isDragging: {
    type: Boolean,
    default: false
  },
  readOnly: {
    type: Boolean,
    default: false
  },
  interactive: {
    type: Boolean,
    default: true
  },
  isDragSource: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['click', 'interaction-start'])

const normalizeDate = (value: string | Date | undefined): Date | null => {
  if (!value) return null
  const date = value instanceof Date ? value : new Date(value)
  return Number.isNaN(date.getTime()) ? null : date
}

const timeLabel = computed(() => {
  const start = normalizeDate(props.item.startAt)
  const end = normalizeDate(props.item.endAt)
  if (!start || !end) return ''
  const format = (date: Date) =>
    `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
  return `${format(start)}-${format(end)}`
})

const isPast = computed(() => {
  const endAt = normalizeDate(props.item.endAt)
  return Boolean(endAt && endAt < new Date())
})

const backgroundClass = computed(() => {
  const status = props.item.status || 'planned'
  
  if (props.item.type === 'draft') {
    return 'bg-sky-50 border-l-sky-400 text-sky-900 border border-sky-200 border-dashed animate-pulse'
  }
  
  if (props.item.type === 'block') {
    return 'bg-slate-100 border-l-slate-500 text-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:border-l-slate-400'
  }
  
  // Appointment Status Colors
  if (status === 'done' || status === 'completed') {
    return 'bg-emerald-50 border-l-emerald-500 text-emerald-900 border-r border-t border-b border-emerald-200/50'
  }
  if (status === 'cancelled') {
    return 'bg-red-50 border-l-red-500 text-red-900 border-r border-t border-b border-red-200/50 line-through decoration-red-900/30'
  }
  if (status === 'confirmed') {
    return 'bg-blue-50 border-l-blue-500 text-blue-900 border-r border-t border-b border-blue-200/50'
  }
  if (status === 'arrived') {
    return 'bg-purple-50 border-l-purple-500 text-purple-900 border-r border-t border-b border-purple-200/50'
  }

  // Default / Scheduled
  if (isPast.value) {
    return 'bg-slate-50 border-l-slate-400 text-slate-600 border-r border-t border-b border-slate-200/50'
  }
  
  return 'bg-white border-l-emerald-500 text-emerald-950 border-r border-t border-b border-emerald-200 shadow-sm'
})

const cursorClass = computed(() => {
  if (props.isDragging) return 'cursor-grabbing'
  if (!props.interactive || props.readOnly || props.item.type !== 'appointment')
    return 'cursor-default'
  return 'cursor-default group-hover:cursor-grab' // Only show grab on hover to reduce noise
})

const showResizeHandles = computed(() => !props.readOnly && props.item.type === 'appointment')

const styleObject = computed(() => ({
  top: `${props.top + props.stackOffset}px`,
  height: `${props.height}px`
}))

const handleClick = () => {
  emit('click', props.item)
}

const handlePointerDown = (event: PointerEvent) => {
  if (!props.interactive || props.readOnly || props.item.type !== 'appointment') return
  const target = event.currentTarget as HTMLElement
  const rect = target?.getBoundingClientRect?.()
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
