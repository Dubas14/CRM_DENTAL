<template>
  <div
    class="absolute left-1 right-1 rounded-md border-l-4 px-2 py-1 text-left text-xs shadow-sm transition-all duration-200 ease-out hover:shadow-md hover:scale-[1.01] overflow-hidden group"
    :class="[
      statusClass,
      cursorClass
    ]"
    :style="styleObject"
    data-calendar-item="appointment"
    @click.stop="handleClick"
  >

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
  readOnly: {
    type: Boolean,
    default: false
  },
  interactive: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['click'])

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

const statusClass = computed(() => {
  if (props.item.type === 'draft') {
    return 'appointment-draft'
  }
  
  if (props.item.type === 'block') {
    return 'appointment-block'
  }
  
  const status = props.item.status || 'planned'
  const statusKey = status === 'completed' ? 'done' : status
  
  // Map frontend statuses to CSS classes
  const statusMap: Record<string, string> = {
    planned: 'appointment-status-planned',
    scheduled: 'appointment-status-planned',
    confirmed: 'appointment-status-confirmed',
    reminded: 'appointment-status-confirmed',
    waiting: 'appointment-status-waiting',
    done: 'appointment-status-done',
    completed: 'appointment-status-done',
    cancelled: 'appointment-status-cancelled',
    no_show: 'appointment-status-no-show',
    arrived: 'appointment-status-arrived'
  }
  
  const baseClass = statusMap[statusKey] || 'appointment-status-planned'
  
  // Add past modifier for planned/scheduled appointments
  if ((statusKey === 'planned' || statusKey === 'scheduled') && isPast.value) {
    return `${baseClass} appointment-status-past`
  }
  
  return baseClass
})

const cursorClass = computed(() => {
  if (!props.interactive || props.readOnly || props.item.type !== 'appointment')
    return 'appointment-cursor-default'
  return 'appointment-cursor-pointer'
})

const styleObject = computed(() => ({
  top: `${props.top + props.stackOffset}px`,
  height: `${props.height}px`
}))

const handleClick = () => {
  emit('click', props.item)
}
</script>

<style scoped>
/* Base appointment styles */
.appointment-draft {
  background-color: rgb(224 242 254);
  border-left: 4px solid rgb(56 189 248);
  color: rgb(12 74 110);
  border: 1px dashed rgb(186 230 253);
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

.appointment-block {
  background-color: rgb(241 245 249);
  border-left: 4px solid rgb(100 116 139);
  color: rgb(51 65 85);
}

/* Status: Planned/Scheduled */
.appointment-status-planned {
  background-color: rgb(255 255 255);
  border-left: 4px solid rgb(16 185 129);
  color: rgb(5 46 22);
  border-right: 1px solid rgba(16, 185, 129, 0.2);
  border-top: 1px solid rgba(16, 185, 129, 0.2);
  border-bottom: 1px solid rgba(16, 185, 129, 0.2);
  box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
}

.appointment-status-planned.appointment-status-past {
  background-color: rgb(248 250 252);
  border-left-color: rgb(148 163 184);
  color: rgb(71 85 105);
  border-right-color: rgba(148, 163, 184, 0.2);
  border-top-color: rgba(148, 163, 184, 0.2);
  border-bottom-color: rgba(148, 163, 184, 0.2);
}

/* Status: Confirmed */
.appointment-status-confirmed {
  background-color: rgb(239 246 255);
  border-left: 4px solid rgb(59 130 246);
  color: rgb(30 58 138);
  border-right: 1px solid rgba(59, 130, 246, 0.2);
  border-top: 1px solid rgba(59, 130, 246, 0.2);
  border-bottom: 1px solid rgba(59, 130, 246, 0.2);
}

/* Status: Waiting */
.appointment-status-waiting {
  background-color: rgb(254 252 232);
  border-left: 4px solid rgb(234 179 8);
  color: rgb(113 63 18);
  border-right: 1px solid rgba(234, 179, 8, 0.2);
  border-top: 1px solid rgba(234, 179, 8, 0.2);
  border-bottom: 1px solid rgba(234, 179, 8, 0.2);
}

/* Status: Done/Completed */
.appointment-status-done {
  background-color: rgb(236 253 245);
  border-left: 4px solid rgb(16 185 129);
  color: rgb(5 46 22);
  border-right: 1px solid rgba(16, 185, 129, 0.2);
  border-top: 1px solid rgba(16, 185, 129, 0.2);
  border-bottom: 1px solid rgba(16, 185, 129, 0.2);
}

/* Status: Cancelled */
.appointment-status-cancelled {
  background-color: rgb(254 242 242);
  border-left: 4px solid rgb(239 68 68);
  color: rgb(127 29 29);
  border-right: 1px solid rgba(239, 68, 68, 0.2);
  border-top: 1px solid rgba(239, 68, 68, 0.2);
  border-bottom: 1px solid rgba(239, 68, 68, 0.2);
  text-decoration: line-through;
  text-decoration-color: rgba(127, 29, 29, 0.3);
}

/* Status: No Show */
.appointment-status-no-show {
  background-color: rgb(255 247 237);
  border-left: 4px solid rgb(249 115 22);
  color: rgb(124 45 18);
  border-right: 1px solid rgba(249, 115, 22, 0.2);
  border-top: 1px solid rgba(249, 115, 22, 0.2);
  border-bottom: 1px solid rgba(249, 115, 22, 0.2);
}

/* Status: Arrived */
.appointment-status-arrived {
  background-color: rgb(250 245 255);
  border-left: 4px solid rgb(168 85 247);
  color: rgb(88 28 135);
  border-right: 1px solid rgba(168, 85, 247, 0.2);
  border-top: 1px solid rgba(168, 85, 247, 0.2);
  border-bottom: 1px solid rgba(168, 85, 247, 0.2);
}


/* Dark mode support */
@media (prefers-color-scheme: dark) {
  .appointment-draft {
    background-color: rgb(30 58 138);
    border-left-color: rgb(125 211 252);
    color: rgb(191 219 254);
    border-color: rgb(56 189 248);
  }

  .appointment-block {
    background-color: rgb(30 41 59);
    border-left-color: rgb(148 163 184);
    color: rgb(203 213 225);
  }

  .appointment-status-planned {
    background-color: rgb(6 78 59);
    border-left-color: rgb(5 150 105);
    color: rgb(167 243 208);
    border-right-color: rgba(5, 150, 105, 0.3);
    border-top-color: rgba(5, 150, 105, 0.3);
    border-bottom-color: rgba(5, 150, 105, 0.3);
  }

  .appointment-status-planned.appointment-status-past {
    background-color: rgb(30 41 59);
    border-left-color: rgb(100 116 139);
    color: rgb(148 163 184);
    border-right-color: rgba(100, 116, 139, 0.3);
    border-top-color: rgba(100, 116, 139, 0.3);
    border-bottom-color: rgba(100, 116, 139, 0.3);
  }

  .appointment-status-confirmed {
    background-color: rgb(30 64 175);
    border-left-color: rgb(96 165 250);
    color: rgb(191 219 254);
    border-right-color: rgba(96, 165, 250, 0.3);
    border-top-color: rgba(96, 165, 250, 0.3);
    border-bottom-color: rgba(96, 165, 250, 0.3);
  }

  .appointment-status-waiting {
    background-color: rgb(113 63 18);
    border-left-color: rgb(251 191 36);
    color: rgb(254 243 199);
    border-right-color: rgba(251, 191, 36, 0.3);
    border-top-color: rgba(251, 191, 36, 0.3);
    border-bottom-color: rgba(251, 191, 36, 0.3);
  }

  .appointment-status-done {
    background-color: rgb(6 78 59);
    border-left-color: rgb(52 211 153);
    color: rgb(167 243 208);
    border-right-color: rgba(52, 211, 153, 0.3);
    border-top-color: rgba(52, 211, 153, 0.3);
    border-bottom-color: rgba(52, 211, 153, 0.3);
  }

  .appointment-status-cancelled {
    background-color: rgb(127 29 29);
    border-left-color: rgb(248 113 113);
    color: rgb(254 226 226);
    border-right-color: rgba(248, 113, 113, 0.3);
    border-top-color: rgba(248, 113, 113, 0.3);
    border-bottom-color: rgba(248, 113, 113, 0.3);
    text-decoration-color: rgba(254, 226, 226, 0.4);
  }

  .appointment-status-no-show {
    background-color: rgb(124 45 18);
    border-left-color: rgb(251 146 60);
    color: rgb(254 215 170);
    border-right-color: rgba(251, 146, 60, 0.3);
    border-top-color: rgba(251, 146, 60, 0.3);
    border-bottom-color: rgba(251, 146, 60, 0.3);
  }

  .appointment-status-arrived {
    background-color: rgb(88 28 135);
    border-left-color: rgb(192 132 252);
    color: rgb(233 213 255);
    border-right-color: rgba(192, 132, 252, 0.3);
    border-top-color: rgba(192, 132, 252, 0.3);
    border-bottom-color: rgba(192, 132, 252, 0.3);
  }
}

/* Cursor classes */
.appointment-cursor-pointer {
  cursor: pointer;
}

.appointment-cursor-default {
  cursor: default;
}
</style>
