<template>
  <div class="flex w-full flex-col">
    <div class="flex w-full">
      <div class="w-16 shrink-0">
        <CalendarTimeGrid :start-hour="startHour" :end-hour="endHour" :hour-height="hourHeight" />
      </div>

      <div
        ref="columnsWrapper"
        class="relative flex flex-1 min-w-0 bg-bg/40"
      >
        <div class="absolute inset-0 pointer-events-none">
          <div
            v-for="block in inactiveBlocks"
            :key="block.key"
            class="absolute left-0 right-0 bg-slate-100/40 dark:bg-slate-900/40"
            :class="{ 'opacity-100': block.type === 'non-work', 'opacity-50': block.type !== 'non-work' }"
            :style="{ top: `${block.top}px`, height: `${block.height}px` }"
          >
             <!-- Stripe pattern for non-working hours -->
             <div v-if="block.type === 'non-work'" class="w-full h-full opacity-30" style="background-image: repeating-linear-gradient(45deg, transparent, transparent 10px, #00000008 10px, #00000008 20px);"></div>
          </div>
        </div>

        <div class="absolute inset-0 pointer-events-none">
          <div
            v-for="line in gridLines"
            :key="line.index"
            class="absolute left-0 right-0"
            :class="{
              'border-t border-slate-200 dark:border-slate-700': line.type === 'hour',
              'border-t border-slate-100 dark:border-slate-800 border-dashed': line.type === 'half',
              'border-t border-slate-50 dark:border-slate-800/50 border-dotted': line.type === 'quarter'
            }"
            :style="{ top: `${line.top}px` }"
          />
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
            :active-start-hour="activeStartHour"
            :active-end-hour="activeEndHour"
            :hour-height="hourHeight"
            :base-date="normalizeDate(column.date) || date"
            :snap-minutes="snapMinutes"
            :interactive="interactive"
            @select-slot="handleSelectSlot"
            @appointment-click="(item) => emit('appointment-click', item)"
            @contextmenu="handleContextMenu"
          />
        </div>

        <CalendarNowLine v-if="showNowLine" :top="nowLineTop" :label="nowLabel" :show-head="true" />
      </div>
    </div>
    
    <CalendarContextMenu
      v-if="contextMenu.visible"
      :visible="contextMenu.visible"
      :x="contextMenu.x"
      :y="contextMenu.y"
      :title="contextMenu.title"
      :actions="contextMenu.actions"
      @close="closeContextMenu"
      @action="handleContextAction"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch, type PropType } from 'vue'
import CalendarTimeGrid from './CalendarTimeGrid.vue'
import CalendarDoctorColumn from './CalendarDoctorColumn.vue'
import CalendarNowLine from './CalendarNowLine.vue'
import CalendarContextMenu from './CalendarContextMenu.vue'
import { Trash2, Edit, CheckCircle, XCircle, Clock } from 'lucide-vue-next'

// ... existing interfaces ...

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

interface Doctor {
  id: string | number
  name: string
  date?: string | Date
}

interface Column {
  id: string | number
  date?: string | Date
  width?: number
}


const props = defineProps({
  date: {
    type: Date,
    required: true
  },
  doctors: {
    type: Array as PropType<Doctor[]>,
    default: () => []
  },
  columns: {
    type: Array as PropType<Column[]>,
    default: null
  },
  groupBy: {
    type: String,
    default: 'doctor'
  },
  viewMode: {
    type: String,
    default: 'day'
  },
  items: {
    type: Array as PropType<CalendarItem[]>,
    default: () => []
  },
  startHour: {
    type: Number,
    default: 8
  },
  endHour: {
    type: Number,
    default: 22
  },
  workDayStart: {
    type: Number,
    default: 9
  },
  workDayEnd: {
    type: Number,
    default: 18
  },
  activeStartHour: {
    type: Number,
    default: null
  },
  activeEndHour: {
    type: Number,
    default: null
  },
  hourHeight: {
    type: Number,
    default: 64
  },
  snapMinutes: {
    type: Number,
    default: 15
  },
  showDoctorHeader: {
    type: Boolean,
    default: true
  },
  interactive: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits([
  'select-slot',
  'appointment-click'
])

const columnsWrapper = ref<HTMLElement | null>(null)
const columnRefs = ref(new Map())
const nowTick = ref(Date.now())
const nowInterval = ref<ReturnType<typeof setInterval> | null>(null)

const contextMenu = ref({
  visible: false,
  x: 0,
  y: 0,
  title: '',
  item: null as CalendarItem | null,
  actions: [] as any[]
})

const handleContextMenu = ({ event, item }: { event: MouseEvent, item: CalendarItem }) => {
  event.preventDefault()
  
  const actions = []
  
  if (item.type === 'appointment') {
     // Status actions
     if (item.status !== 'done') {
        actions.push({ key: 'set_arrived', label: 'Пацієнт прийшов', icon: CheckCircle })
        actions.push({ key: 'set_done', label: 'Завершити прийом', icon: CheckCircle })
     }
     
     actions.push({ key: 'edit', label: 'Редагувати', icon: Edit })
     
     if (item.status !== 'cancelled') {
        actions.push({ key: 'cancel', label: 'Скасувати', icon: XCircle, danger: true })
     }
  } else if (item.type === 'block') {
     actions.push({ key: 'edit', label: 'Редагувати', icon: Edit })
     actions.push({ key: 'delete', label: 'Видалити', icon: Trash2, danger: true })
  }

  contextMenu.value = {
    visible: true,
    x: event.clientX,
    y: event.clientY,
    title: item.title,
    item,
    actions
  }
}

const closeContextMenu = () => {
  contextMenu.value.visible = false
}

const handleContextAction = (key: string) => {
  const item = contextMenu.value.item
  if (!item) return
  
  if (key === 'edit') {
    emit('appointment-click', item)
  } else if (key === 'set_arrived') {
    emit('appointment-update', { id: item.id, status: 'arrived' })
  } else if (key === 'set_done') {
    emit('appointment-update', { id: item.id, status: 'done' })
  } else if (key === 'cancel') {
    emit('appointment-update', { id: item.id, status: 'cancelled' })
  } else if (key === 'delete') {
     emit('appointment-update', { id: item.id, _delete: true }) 
  }
}

const pixelsPerMinute = computed(() => props.hourHeight / 60)
const activeMinutesRange = computed(() => {
  const activeStart = props.activeStartHour ?? props.startHour
  const activeEnd = props.activeEndHour ?? props.endHour
  const min = Math.max(0, (activeStart - props.startHour) * 60)
  const max = Math.min((props.endHour - props.startHour) * 60, (activeEnd - props.startHour) * 60)
  return { min, max }
})



const gridLines = computed(() => {
  const lines = []
  const totalMinutes = (props.endHour - props.startHour) * 60
  for (let minutes = 0; minutes <= totalMinutes; minutes += props.snapMinutes) {
    const isHour = minutes % 60 === 0
    const isHalfHour = minutes % 30 === 0 && !isHour

    lines.push({
      index: minutes,
      top: minutes * pixelsPerMinute.value,
      type: isHour ? 'hour' : isHalfHour ? 'half' : 'quarter'
    })
  }
  return lines
})

const inactiveBlocks = computed(() => {
  const blocks = []
  
  // Non-working hours (Morning)
  if (props.workDayStart > props.startHour) {
     const height = (props.workDayStart - props.startHour) * props.hourHeight
     blocks.push({
       key: 'non-work-morning',
       top: 0,
       height,
       type: 'non-work'
     })
  }

  // Non-working hours (Evening)
  if (props.workDayEnd < props.endHour) {
     const top = (props.workDayEnd - props.startHour) * props.hourHeight
     const height = (props.endHour - props.workDayEnd) * props.hourHeight
     blocks.push({
       key: 'non-work-evening',
       top,
       height,
       type: 'non-work'
     })
  }
  
  const activeStart = props.activeStartHour ?? props.startHour
  const activeEnd = props.activeEndHour ?? props.endHour
  if (activeStart > props.startHour) {
    blocks.push({
      key: 'inactive-start',
      top: 0,
      height: (activeStart - props.startHour) * props.hourHeight,
      type: 'inactive'
    })
  }
  if (activeEnd < props.endHour) {
    blocks.push({
      key: 'inactive-end',
      top: (activeEnd - props.startHour) * props.hourHeight,
      height: (props.endHour - activeEnd) * props.hourHeight,
      type: 'inactive'
    })
  }
  return blocks
})

const resolvedColumns = computed(() => {
  if (props.columns && props.columns.length) return props.columns
  return props.doctors || []
})

const resolvedGroupBy = computed(() => (props.viewMode === 'week' ? 'date' : props.groupBy))

const normalizeDate = (value: string | Date | undefined): Date | null => {
  if (!value) return null
  const date = value instanceof Date ? value : new Date(value)
  return Number.isNaN(date.getTime()) ? null : date
}

const resolveGroupKey = (item: CalendarItem): string | null => {
  if (resolvedGroupBy.value === 'date') {
    const normalized = normalizeDate(item.startAt)
    if (!normalized) return null
    const year = normalized.getFullYear()
    const month = `${normalized.getMonth() + 1}`.padStart(2, '0')
    const day = `${normalized.getDate()}`.padStart(2, '0')
    return `${year}-${month}-${day}`
  }
  return String(item.doctorId || '') || null
}

const formatDateKey = (date: Date): string | null => {
  if (!date) return null
  const year = date.getFullYear()
  const month = `${date.getMonth() + 1}`.padStart(2, '0')
  const day = `${date.getDate()}`.padStart(2, '0')
  return `${year}-${month}-${day}`
}

const getMinutesFromStart = (date: string | Date | undefined): number => {
  const normalized = normalizeDate(date)
  if (!normalized) return 0
  const minutes = normalized.getHours() * 60 + normalized.getMinutes()
  const startMinutes = props.startHour * 60
  return Math.max(0, minutes - startMinutes)
}

const itemsByColumn = computed(() => {
  const map: Record<string, any[]> = {}
  resolvedColumns.value.forEach((column) => {
    map[String(column.id)] = []
    if (column.date) {
      const key = formatDateKey(new Date(column.date))
      if (key) map[key] = []
    }
  })

  props.items.forEach((item) => {
    const groupKey = resolveGroupKey(item)
    if (!groupKey || !map[groupKey]) {
      // Try to find if we are in week view and grouping by date
      if (resolvedGroupBy.value === 'date' && groupKey) {
        if (!map[groupKey]) map[groupKey] = [] // dynamically create if week view
      } else {
        return
      }
    }
    
    if (groupKey && map[groupKey]) {
      const startMinutes = getMinutesFromStart(item.startAt)
      const endMinutes = getMinutesFromStart(item.endAt)
      const height = Math.max(
        (endMinutes - startMinutes) * pixelsPerMinute.value,
        pixelsPerMinute.value * props.snapMinutes
      )
      map[groupKey].push({
        item: item,
        top: startMinutes * pixelsPerMinute.value,
        height
      })
    }
  })

  Object.values(map).forEach((entries) => {
    const overlapCounts = new Map<number, number>()
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
    now.getFullYear() === props.date.getFullYear() &&
    now.getMonth() === props.date.getMonth() &&
    now.getDate() === props.date.getDate()
  )
})

const showNowLine = computed(() => isToday.value)

const nowLineTop = computed(() => {
  const now = new Date(nowTick.value)
  const minutes = (now.getHours() - props.startHour) * 60 + now.getMinutes()
  if (minutes < 0 || minutes > (props.endHour - props.startHour) * 60) return -999
  return minutes * pixelsPerMinute.value
})

const nowLabel = computed(() => {
  const now = new Date(nowTick.value)
  return `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`
})

const setColumnRef = (doctorId: string | number, instance: any) => {
  if (!instance) {
    columnRefs.value.delete(doctorId)
    return
  }
  columnRefs.value.set(doctorId, instance)
}

const parseDateKey = (key: string): Date | null => {
  if (typeof key !== 'string') return null
  const parsed = new Date(`${key}T00:00:00`)
  return Number.isNaN(parsed.getTime()) ? null : parsed
}

const getDateFromMinutesForBase = (baseDate: Date | string | undefined, minutes: number): Date => {
  const base = new Date(baseDate || props.date)
  base.setHours(props.startHour, 0, 0, 0)
  base.setMinutes(base.getMinutes() + minutes)
  return base
}

const handleSelectSlot = ({ doctorId, minutesFromStart, baseDate }: { doctorId: string | number, minutesFromStart: number, baseDate?: Date }) => {
  if (!props.interactive) return
  const range = activeMinutesRange.value
  if (minutesFromStart < range.min || minutesFromStart + props.snapMinutes > range.max) return
  const start = getDateFromMinutesForBase(baseDate || props.date, minutesFromStart)
  const end = getDateFromMinutesForBase(
    baseDate || props.date,
    minutesFromStart + props.snapMinutes * 2
  )
  emit('select-slot', { doctorId, start, end })
}


onMounted(() => {
  nowInterval.value = setInterval(() => {
    nowTick.value = Date.now()
  }, 60000)
})
</script>

<style scoped>
</style>
