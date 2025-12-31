<template>
  <div class="flex h-full min-h-0 flex-col bg-bg">
    <div class="shrink-0 p-6 pb-2">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-text mb-2">Календар записів</h1>
          <p class="text-text/70 text-sm">
            Управління розкладом лікарів, бронювання та перегляд записів
          </p>
        </div>
      </div>
    </div>

    <div class="flex min-h-0 flex-1 px-6 pb-6">
      <div class="flex min-h-0 w-full gap-6">
        <CalendarSidebar
          :current-date="currentDate"
          :clinics="clinics"
          :doctors="doctors"
          :procedures="procedures"
          :selected-clinic-id="selectedClinicId"
          :selected-doctor-id="selectedDoctorId"
          :selected-procedure-id="selectedProcedureId"
          :loading-doctors="loadingDoctors"
          :is-doctor="isDoctor"
          @clinic-change="handleClinicSelection"
          @doctor-change="handleDoctorSelection"
          @procedure-change="handleProcedureSelection"
          @date-change="selectDate"
          @select-date="selectDate"
        />

        <div class="flex min-h-0 flex-1 min-w-0">
          <div
            class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border border-border/60 bg-card/30 dark:border-border/30"
          >
            <div class="shrink-0 border-b border-border/60 px-4 py-3 dark:border-border/30">
              <CalendarHeader
                :current-date="currentDate"
                :view-mode="view"
                :range-start="headerRangeStart"
                :range-end="headerRangeEnd"
                @select-date="selectDate"
                @prev="handlePrev"
                @next="handleNext"
                @today="handleToday"
                @view-change="setView"
              />
            </div>
            <div class="flex min-h-0 flex-1 overflow-hidden">
              <div
                v-if="!currentClinicId"
                class="flex h-full flex-1 items-center justify-center text-text/60"
              >
                ⬅ Будь ласка, оберіть клініку зі списку зліва
              </div>
              <div
                v-else-if="!selectedDoctorId"
                class="flex h-full flex-1 items-center justify-center text-text/60"
              >
                ⬅ Оберіть лікаря зі списку зліва
              </div>
              <div v-else class="flex min-h-0 flex-1 flex-col">
                <div
                  v-if="view === 'week'"
                  class="flex border-b calendar-grid-strong calendar-all-day-divider bg-card/30 text-xs font-semibold text-text/70"
                >
                  <div class="w-16 shrink-0"></div>
                  <div class="flex min-w-0 flex-1" :style="{ minWidth: `${weekMinWidth}px` }">
                    <div
                      v-for="day in weekColumns"
                      :key="day.id"
                      class="flex flex-1 flex-col justify-center border-r calendar-grid-strong px-3 py-2"
                      :style="{ minWidth: `${WEEK_COLUMN_WIDTH}px` }"
                    >
                      <span class="text-[10px] uppercase text-text/50">{{ day.weekday }}</span>
                      <span
                        class="text-sm text-text/90"
                        :class="day.isSunday ? 'text-rose-600 dark:text-rose-400' : ''"
                      >
                        {{ day.dayLabel }}
                      </span>
                    </div>
                  </div>
                </div>

                <div
                  v-if="view === 'day'"
                  ref="dayScrollRef"
                  class="flex min-h-0 flex-1 overflow-y-auto custom-scrollbar"
                >
                  <CalendarBoard
                    :date="currentDate"
                    :doctors="filteredDoctors"
                    :items="filteredCalendarItems"
                    :show-doctor-header="false"
                    :start-hour="DISPLAY_START_HOUR"
                    :end-hour="DISPLAY_END_HOUR"
                    :active-start-hour="CLINIC_START_HOUR"
                    :active-end-hour="CLINIC_END_HOUR"
                    :hour-height="HOUR_HEIGHT"
                    :snap-minutes="SNAP_MINUTES"
                    view-mode="day"
                    @select-slot="handleSelectSlot"
                    @appointment-click="handleAppointmentClick"
                    @appointment-update="handleAppointmentUpdate"
                    @appointment-drag-start="handleAppointmentDragStart"
                    @appointment-drag-end="handleAppointmentDragEnd"
                  />
                </div>

                <div
                  v-else-if="view === 'week'"
                  ref="weekScrollRef"
                  class="flex min-h-0 flex-1 overflow-x-auto overflow-y-auto custom-scrollbar"
                >
                  <div class="flex min-w-0 flex-1" :style="{ minWidth: `${weekMinWidth}px` }">
                    <CalendarBoard
                      :date="weekStart"
                      :columns="weekColumns"
                      group-by="date"
                      :items="filteredCalendarItems"
                      :show-doctor-header="false"
                      :start-hour="DISPLAY_START_HOUR"
                      :end-hour="DISPLAY_END_HOUR"
                      :active-start-hour="CLINIC_START_HOUR"
                      :active-end-hour="CLINIC_END_HOUR"
                      :hour-height="HOUR_HEIGHT"
                      :snap-minutes="SNAP_MINUTES"
                      :interactive="true"
                      view-mode="week"
                      @select-slot="handleSelectSlot"
                      @appointment-click="handleAppointmentClick"
                      @appointment-update="handleAppointmentUpdate"
                      @appointment-drag-start="handleAppointmentDragStart"
                      @appointment-drag-end="handleAppointmentDragEnd"
                    />
                  </div>
                </div>

                <div v-else class="flex min-h-0 flex-1 flex-col overflow-hidden">
                  <div
                    class="grid grid-cols-7 border-b calendar-grid-strong text-center text-xs font-semibold text-text/70"
                  >
                    <div
                      v-for="day in monthWeekdays"
                      :key="day"
                      class="border-r calendar-grid-strong bg-card/40 py-2 last:border-r-0"
                    >
                      {{ day }}
                    </div>
                  </div>
                  <div class="flex min-h-0 flex-1 overflow-hidden">
                    <div
                      class="grid h-full w-full grid-cols-7"
                      :style="{ gridTemplateRows: `repeat(${monthGridRows}, minmax(0, 1fr))` }"
                    >
                      <button
                        v-for="(cell, index) in monthCells"
                        :key="cell.key"
                        type="button"
                        class="group flex min-h-0 flex-col gap-2 border calendar-grid-strong bg-card/30 px-2 py-2 text-left text-xs transition hover:bg-card/60"
                        :class="[
                          cell.isCurrentMonth ? 'text-text/90' : 'text-text/40',
                          cell.isSelected ? 'bg-emerald-500/15 ring-1 ring-emerald-500/40' : '',
                          index >= 7 ? 'calendar-week-divider' : ''
                        ]"
                        @click="handleMonthDayClick(cell.date)"
                      >
                        <div class="flex items-center justify-between">
                          <span
                            class="text-sm font-semibold"
                            :class="[
                              cell.isToday ? 'text-emerald-300' : '',
                              cell.isWeekend ? 'text-rose-600 dark:text-rose-400' : ''
                            ]"
                          >
                            {{ cell.label }}
                          </span>
                          <span v-if="cell.items.length" class="text-[10px] text-text/50">
                            {{ cell.items.length }}
                          </span>
                        </div>
                        <div class="flex min-h-0 flex-1 flex-col gap-1 overflow-hidden">
                          <div
                            v-for="item in cell.items.slice(0, MAX_EVENTS_PER_DAY)"
                            :key="item.id"
                            class="rounded-md bg-emerald-500/15 px-2 py-1 text-[10px] text-emerald-100"
                            :class="item.type === 'block' ? 'bg-slate-500/20 text-slate-100' : ''"
                            @click.stop="handleMonthEventClick(item)"
                          >
                            <span class="truncate block">{{ item.title }}</span>
                          </div>
                          <button
                            v-if="cell.items.length > MAX_EVENTS_PER_DAY"
                            type="button"
                            class="text-left text-[10px] text-text/50 hover:text-emerald-200"
                            @click.stop="openMonthEvents(cell)"
                          >
                            +{{ cell.items.length - MAX_EVENTS_PER_DAY }} ще
                          </button>
                        </div>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <EventModal
      :open="isEventModalOpen"
      :event="activeEvent"
      :doctors="filteredDoctors"
      :default-doctor-id="selectedDoctorId || defaultDoctorId"
      @save="handleSaveEvent"
      @close="handleCloseModal"
    />

    <AppointmentModal
      :is-open="isAppointmentModalOpen"
      :appointment="selectedAppointment"
      :clinic-id="currentClinicId"
      @close="handleCloseAppointmentModal"
      @saved="handleAppointmentSaved"
    />

    <div
      v-if="isMonthEventsOpen"
      class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 px-4 py-6"
      @click.self="closeMonthEvents"
    >
      <div
        class="w-full max-w-md rounded-xl border border-border/60 bg-card p-4 shadow-xl dark:border-border/40"
      >
        <div class="flex items-center justify-between gap-3">
          <div>
            <p class="text-sm font-semibold text-text">Події дня</p>
            <p class="text-xs text-text/60">{{ monthEventsLabel }}</p>
          </div>
          <button
            type="button"
            class="rounded-lg border border-border/60 px-2 py-1 text-xs text-text/70 hover:text-text dark:border-border/40"
            @click="closeMonthEvents"
          >
            Закрити
          </button>
        </div>
        <div class="mt-3 max-h-[360px] space-y-2 overflow-y-auto">
          <button
            v-for="item in monthEventsItems"
            :key="item.id"
            type="button"
            class="flex w-full items-start gap-2 rounded-lg border border-border/50 px-3 py-2 text-left text-xs text-text/80 transition hover:border-emerald-400/60 hover:bg-emerald-500/10 dark:border-border/30"
            @click="handleMonthEventClick(item)"
          >
            <span class="mt-0.5 inline-flex h-2 w-2 shrink-0 rounded-full bg-emerald-400/80"></span>
            <div class="min-w-0">
              <p class="truncate font-semibold text-text/90">{{ item.title }}</p>
              <p class="text-[11px] text-text/60">
                {{ formatTimeHM(item.startAt) }} – {{ formatTimeHM(item.endAt) }}
              </p>
            </div>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, nextTick, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import CalendarBoard from '../components/calendar/CalendarBoard.vue'
import CalendarSidebar from '../components/calendar/CalendarSidebar.vue'
import CalendarHeader from '../components/calendar/CalendarHeader.vue'
import EventModal from '../components/EventModal.vue'
import AppointmentModal from '../components/AppointmentModal.vue'
import calendarApi from '../services/calendarApi'
import apiClient from '../services/apiClient'
import clinicApi from '../services/clinicApi'
import procedureApi from '../services/procedureApi'
import { useToast } from '../composables/useToast'
import { useAuth } from '../composables/useAuth'
import { usePermissions } from '../composables/usePermissions'

const DISPLAY_START_HOUR = 0
const DISPLAY_END_HOUR = 24
const CLINIC_START_HOUR = 8
const CLINIC_END_HOUR = 22
const SNAP_MINUTES = 15
const HOUR_HEIGHT = 64
const WEEK_COLUMN_WIDTH = 150
const MAX_EVENTS_PER_DAY = 3

const view = ref('day')
const currentDate = ref(new Date())
const isEventModalOpen = ref(false)
const activeEvent = ref({})
const isAppointmentModalOpen = ref(false)
const selectedAppointment = ref(null)
const isMonthEventsOpen = ref(false)
const monthEventsItems = ref([])
const monthEventsDate = ref(null)
const dayScrollRef = ref(null)
const weekScrollRef = ref(null)

const calendarItems = ref([])
const clinics = ref([])
const selectedClinicId = ref(null)
const doctors = ref([])
const selectedDoctorId = ref(null)
const loadingDoctors = ref(false)
const procedures = ref([])
const selectedProcedureId = ref(null)

const { error: toastError, success: toastSuccess } = useToast()
const { user, initAuth } = useAuth()
const { isDoctor } = usePermissions()
const route = useRoute()

const pendingAppointmentId = ref(null)

const currentClinicId = computed(() => {
  if (selectedClinicId.value) return selectedClinicId.value
  return user.value?.clinic_id || user.value?.doctor?.clinic_id || null
})

const defaultDoctorId = computed(() => user.value?.doctor_id || user.value?.doctor?.id || null)
const selectedDoctor = computed(() =>
  doctors.value.find((doctor) => Number(doctor.id) === Number(selectedDoctorId.value))
)

const filteredDoctors = computed(() => (selectedDoctor.value ? [selectedDoctor.value] : []))
const filteredCalendarItems = computed(() => {
  if (!selectedDoctorId.value) return []
  return calendarItems.value.filter(
    (item) => Number(item.doctorId) === Number(selectedDoctorId.value)
  )
})

const toDate = (value) => {
  if (!value) return null
  if (value.toDate) return value.toDate()
  const date = value instanceof Date ? value : new Date(value)
  return Number.isNaN(date.getTime()) ? null : date
}

const formatDateOnly = (date) => {
  const normalized = toDate(date)
  if (!normalized) return ''
  const year = normalized.getFullYear()
  const month = `${normalized.getMonth() + 1}`.padStart(2, '0')
  const day = `${normalized.getDate()}`.padStart(2, '0')
  return `${year}-${month}-${day}`
}

const formatDateTime = (date) => {
  const normalized = toDate(date)
  if (!normalized) return ''
  const year = normalized.getFullYear()
  const month = `${normalized.getMonth() + 1}`.padStart(2, '0')
  const day = `${normalized.getDate()}`.padStart(2, '0')
  const hour = `${normalized.getHours()}`.padStart(2, '0')
  const minute = `${normalized.getMinutes()}`.padStart(2, '0')
  return `${year}-${month}-${day} ${hour}:${minute}:00`
}

const formatTimeHM = (date) => {
  const normalized = toDate(date)
  if (!normalized) return ''
  const hour = `${normalized.getHours()}`.padStart(2, '0')
  const minute = `${normalized.getMinutes()}`.padStart(2, '0')
  return `${hour}:${minute}`
}

const capitalize = (value) => (value ? value.charAt(0).toUpperCase() + value.slice(1) : '')

const formatDateWithParts = (date, options) =>
  new Intl.DateTimeFormat('uk-UA', options)
    .formatToParts(date)
    .map((part) => {
      if (part.type === 'weekday' || part.type === 'month') return capitalize(part.value)
      return part.value
    })
    .join('')

const weekStart = computed(() => {
  const base = new Date(currentDate.value)
  const day = base.getDay() || 7
  base.setHours(0, 0, 0, 0)
  base.setDate(base.getDate() - day + 1)
  return base
})

const weekDays = computed(() =>
  Array.from({ length: 7 }, (_, index) => {
    const date = new Date(weekStart.value)
    date.setDate(weekStart.value.getDate() + index)
    return date
  })
)

const weekColumns = computed(() =>
  weekDays.value.map((date) => {
    const weekday = formatDateWithParts(date, { weekday: 'short' })
    const dayLabel = formatDateWithParts(date, { day: 'numeric', month: 'long' })
    return {
      id: formatDateOnly(date),
      date,
      doctorId: selectedDoctorId.value,
      label: dayLabel,
      weekday,
      dayLabel,
      isSunday: date.getDay() === 0,
      is_active: true
    }
  })
)

const weekMinWidth = computed(() => 64 + weekColumns.value.length * WEEK_COLUMN_WIDTH)

const monthWeekdays = computed(() => {
  const base = new Date(2021, 7, 2)
  return Array.from({ length: 7 }, (_, index) => {
    const date = new Date(base)
    date.setDate(base.getDate() + index)
    return formatDateWithParts(date, { weekday: 'short' })
  })
})

const monthEventsLabel = computed(() => {
  if (!monthEventsDate.value) return ''
  return formatDateWithParts(monthEventsDate.value, {
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  })
})

const itemsByDate = computed(() => {
  const map = {}
  filteredCalendarItems.value.forEach((item) => {
    const key = formatDateOnly(item.startAt)
    if (!key) return
    if (!map[key]) map[key] = []
    map[key].push(item)
  })
  return map
})

const monthGridRows = computed(() => {
  const base = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth(), 1)
  const startOffset = base.getDay() || 7
  const daysInMonth = new Date(base.getFullYear(), base.getMonth() + 1, 0).getDate()
  const totalCells = daysInMonth + (startOffset - 1)
  return Math.max(5, Math.ceil(totalCells / 7))
})

const monthCells = computed(() => {
  const base = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth(), 1)
  const start = new Date(base)
  const offset = start.getDay() || 7
  start.setDate(base.getDate() - offset + 1)

  return Array.from({ length: monthGridRows.value * 7 }, (_, index) => {
    const date = new Date(start)
    date.setDate(start.getDate() + index)
    const key = formatDateOnly(date)
    const isCurrentMonth = date.getMonth() === base.getMonth()
    const isSelected =
      date.getFullYear() === currentDate.value.getFullYear() &&
      date.getMonth() === currentDate.value.getMonth() &&
      date.getDate() === currentDate.value.getDate()
    const isToday = (() => {
      const now = new Date()
      return (
        date.getFullYear() === now.getFullYear() &&
        date.getMonth() === now.getMonth() &&
        date.getDate() === now.getDate()
      )
    })()

    return {
      key,
      date,
      label: date.getDate(),
      isCurrentMonth,
      isSelected,
      isToday,
      isWeekend: date.getDay() === 0 || date.getDay() === 6,
      items: itemsByDate.value[key] || []
    }
  })
})

const headerRangeStart = computed(() => (view.value === 'week' ? weekStart.value : null))
const headerRangeEnd = computed(() => {
  if (view.value !== 'week') return null
  const end = new Date(weekStart.value)
  end.setDate(end.getDate() + 6)
  return end
})

const getRangeForView = (date, mode) => {
  const base = new Date(date)
  if (mode === 'week') {
    const day = base.getDay() || 7
    base.setHours(0, 0, 0, 0)
    base.setDate(base.getDate() - day + 1)
    const end = new Date(base)
    end.setDate(end.getDate() + 6)
    return { start: base, end }
  }

  if (mode === 'month') {
    const start = new Date(base.getFullYear(), base.getMonth(), 1)
    const end = new Date(base.getFullYear(), base.getMonth() + 1, 0)
    return { start, end }
  }

  const start = new Date(base)
  start.setHours(0, 0, 0, 0)
  const end = new Date(start)
  end.setDate(end.getDate() + 1)
  return { start, end }
}

const mapBlockToItem = (block) => {
  const startAt = toDate(block.start_at)
  const endAt = toDate(block.end_at)
  if (!startAt || !endAt) return null
  const doctorId = block.doctor_id || block.doctor?.id
  if (!doctorId) return null

  return {
    id: String(block.id),
    type: 'block',
    title: block.note || 'Блок',
    startAt,
    endAt,
    doctorId,
    isReadOnly: false,
    raw: block
  }
}

const mapAppointmentToItem = (appt) => {
  const startAt = toDate(appt.start_at)
  const endAt = toDate(appt.end_at)
  if (!startAt || !endAt) return null
  const doctorId = appt.doctor_id || appt.doctor?.id
  if (!doctorId) return null

  return {
    id: String(appt.id),
    type: 'appointment',
    title: appt.patient?.full_name || 'Запис',
    startAt,
    endAt,
    doctorId,
    status: appt.status,
    isReadOnly: appt.status === 'done',
    raw: appt
  }
}

const updateCalendarItem = (updated) => {
  calendarItems.value = calendarItems.value.map((item) =>
    item.id === updated.id ? { ...item, ...updated } : item
  )
}

const getAppointmentDurationMinutes = (startAt, endAt) => {
  const start = toDate(startAt)
  const end = toDate(endAt)
  if (!start || !end) return 30
  const diff = Math.round((end.getTime() - start.getTime()) / 60000)
  return diff > 0 ? diff : 30
}

const isDoctorActive = (doctorId) => {
  const doctor = doctors.value.find((item) => item.id === doctorId)
  return doctor ? doctor.is_active !== false : true
}

const hasOverlap = (itemId, doctorId, startAt, endAt) => {
  const isBlockingBlock = (item) =>
    item?.type === 'block' &&
    (item.raw?.is_blocking === true ||
      item.raw?.blocking === true ||
      item.raw?.type === 'room_block')
  return calendarItems.value.some((item) => {
    if (item.id === itemId) return false
    if (item.doctorId !== doctorId) return false
    if (item.type === 'block' && !isBlockingBlock(item)) return false
    return startAt < item.endAt && endAt > item.startAt
  })
}

const isWithinClinicHours = (startAt, endAt) => {
  const startHour = startAt.getHours() + startAt.getMinutes() / 60
  const endHour = endAt.getHours() + endAt.getMinutes() / 60
  return startHour >= CLINIC_START_HOUR && endHour <= CLINIC_END_HOUR
}

const isDropAllowed = async (appointment, doctorId, startAt, endAt) => {
  if (!doctorId) return false
  try {
    const date = formatDateOnly(startAt)
    const durationMinutes = getAppointmentDurationMinutes(startAt, endAt)
    const { data } = await calendarApi.getDoctorSlots(doctorId, {
      date,
      procedure_id: appointment?.procedure_id || appointment?.procedure?.id || undefined,
      room_id: appointment?.room_id || appointment?.room?.id || undefined,
      equipment_id: appointment?.equipment_id || appointment?.equipment?.id || undefined,
      assistant_id: appointment?.assistant_id || appointment?.assistant?.id || undefined,
      duration_minutes: durationMinutes
    })
    const slots = Array.isArray(data?.slots) ? data.slots : []
    const allowed = new Set(slots.map((slot) => slot.start))
    return allowed.has(formatTimeHM(startAt))
  } catch (error) {
    console.error(error)
    toastError('Не вдалося перевірити доступний час лікаря')
    return false
  }
}

const loadClinics = async () => {
  try {
    if (user.value?.global_role === 'super_admin') {
      const { data } = await clinicApi.list()
      clinics.value = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
    } else {
      const { data } = await clinicApi.listMine()
      clinics.value = (data.clinics || []).map((c) => ({ id: c.clinic_id, name: c.clinic_name }))
    }

    if (!currentClinicId.value && clinics.value.length > 0) {
      selectedClinicId.value = clinics.value[0].id
    }
  } catch (e) {
    console.error('Failed to load clinics', e)
  }
}

const fetchEvents = async () => {
  if (!currentClinicId.value) return

  const { start, end } = getRangeForView(currentDate.value, view.value)
  const from = formatDateOnly(start)
  const to = formatDateOnly(end)

  try {
    const [blocksResponse, appointmentsResponse] = await Promise.all([
      calendarApi.getCalendarBlocks({ clinic_id: currentClinicId.value, from, to }),
      calendarApi.getAppointments({
        clinic_id: currentClinicId.value,
        from_date: from,
        to_date: to
      })
    ])

    const blocksData = blocksResponse.data?.data || blocksResponse.data || []
    const appointmentsData = appointmentsResponse.data?.data || appointmentsResponse.data || []

    const mappedBlocks = blocksData.map(mapBlockToItem).filter(Boolean)
    const mappedAppointments = appointmentsData.map(mapAppointmentToItem).filter(Boolean)

    calendarItems.value = [...mappedBlocks, ...mappedAppointments]
  } catch (error) {
    console.error(error)
    toastError('Помилка завантаження даних')
  }

  if (pendingAppointmentId.value) {
    const target = calendarItems.value.find(
      (item) => item.id === pendingAppointmentId.value && item.type === 'appointment'
    )
    if (target) {
      selectedAppointment.value = target.raw
      isAppointmentModalOpen.value = true
      pendingAppointmentId.value = null
    }
  }
}

const saveEvent = async (payload) => {
  if (!currentClinicId.value) {
    toastError('Оберіть клініку!')
    return
  }

  const payloadToSave = { ...payload }
  if (!payloadToSave.doctor_id && (selectedDoctorId.value || defaultDoctorId.value)) {
    payloadToSave.doctor_id = selectedDoctorId.value || defaultDoctorId.value
  }

  if (!payloadToSave.doctor_id) {
    toastError('Помилка: Потрібно обрати лікаря у формі.')
    return
  }

  const isEdit = payload.id && !String(payload.id).startsWith('new-')

  const apiPayload = {
    clinic_id: currentClinicId.value,
    type: payload.type || 'personal_block',
    start_at: formatDateTime(payload.start),
    end_at: formatDateTime(payload.end),
    note: payload.note ?? payload.title ?? '',
    doctor_id: payloadToSave.doctor_id
  }

  try {
    if (isEdit) {
      await calendarApi.updateCalendarBlock(payload.id, apiPayload)
      toastSuccess('Блок оновлено')
    } else {
      await calendarApi.createCalendarBlock(apiPayload)
      toastSuccess('Блок створено')
    }
    fetchEvents()
    handleCloseModal()
  } catch (e) {
    console.error(e)
    toastError('Не вдалося зберегти: ' + (e.response?.data?.message || 'Помилка'))
  }
}

const handleSaveEvent = (payload) => {
  saveEvent(payload)
}

const handleClinicChange = () => {
  nextTick(() => fetchEvents())
}

const selectDate = (date) => {
  if (!date) return
  currentDate.value = new Date(date)
}

const shiftDateByView = (direction) => {
  const next = new Date(currentDate.value)
  if (view.value === 'month') {
    next.setMonth(next.getMonth() + direction)
    currentDate.value = next
    return
  }
  if (view.value === 'week') {
    next.setDate(next.getDate() + direction * 7)
    currentDate.value = next
    return
  }
  next.setDate(next.getDate() + direction)
  currentDate.value = next
}

const handlePrev = () => shiftDateByView(-1)
const handleNext = () => shiftDateByView(1)

const scrollToCurrentTime = async () => {
  if (view.value !== 'day' && view.value !== 'week') return
  await nextTick()
  const container = view.value === 'day' ? dayScrollRef.value : weekScrollRef.value
  if (!container) return
  const now = new Date()
  const minutesFromStart = (now.getHours() - DISPLAY_START_HOUR) * 60 + now.getMinutes()
  if (minutesFromStart < 0) return
  const pixelsPerMinute = HOUR_HEIGHT / 60
  const targetTop = minutesFromStart * pixelsPerMinute
  const offset = Math.max(0, targetTop - container.clientHeight / 2)
  container.scrollTop = offset
}

const handleToday = () => {
  currentDate.value = new Date()
  scrollToCurrentTime()
}

const setView = (mode) => {
  if (!['day', 'week', 'month'].includes(mode)) return
  view.value = mode
}

const handleMonthDayClick = (date) => {
  if (!date) return
  currentDate.value = new Date(date)
  view.value = 'day'
}

const openMonthEvents = (cell) => {
  if (!cell?.items?.length) return
  monthEventsItems.value = cell.items
  monthEventsDate.value = cell.date
  isMonthEventsOpen.value = true
}

const closeMonthEvents = () => {
  isMonthEventsOpen.value = false
  monthEventsItems.value = []
  monthEventsDate.value = null
}

const handleMonthEventClick = (item) => {
  closeMonthEvents()
  handleAppointmentClick(item)
}

const createDefaultEvent = ({ start, end, doctorId }) => {
  const s = toDate(start) ?? new Date()
  const e = toDate(end) ?? new Date(s.getTime() + 30 * 60000)
  return {
    id: `new-${Date.now()}`,
    title: '',
    start: s,
    end: e,
    doctor_id: doctorId || selectedDoctorId.value || defaultDoctorId.value,
    type: 'personal_block',
    note: ''
  }
}

const loadDoctors = async () => {
  if (!currentClinicId.value) {
    doctors.value = []
    selectedDoctorId.value = null
    return
  }

  try {
    loadingDoctors.value = true
    const { data } = await apiClient.get('/doctors', {
      params: { clinic_id: currentClinicId.value }
    })
    doctors.value = (data?.data || data || []).filter(Boolean)
    if (!doctors.value.length) {
      selectedDoctorId.value = null
      return
    }

    if (isDoctor.value && defaultDoctorId.value) {
      selectedDoctorId.value = defaultDoctorId.value
      return
    }

    const hasSelected =
      selectedDoctorId.value &&
      doctors.value.some((doctor) => Number(doctor.id) === Number(selectedDoctorId.value))
    if (!hasSelected) {
      selectedDoctorId.value =
        selectedDoctorId.value || defaultDoctorId.value || doctors.value[0].id
    }
  } catch (error) {
    console.error(error)
    doctors.value = []
    selectedDoctorId.value = null
  } finally {
    loadingDoctors.value = false
  }
}

const loadProcedures = async () => {
  if (!currentClinicId.value) {
    procedures.value = []
    selectedProcedureId.value = null
    return
  }

  try {
    const { data } = await procedureApi.list({ clinic_id: currentClinicId.value })
    procedures.value = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
    if (
      selectedProcedureId.value &&
      !procedures.value.some((proc) => Number(proc.id) === Number(selectedProcedureId.value))
    ) {
      selectedProcedureId.value = null
    }
  } catch (error) {
    console.error(error)
    procedures.value = []
    selectedProcedureId.value = null
  }
}

const openEventModal = (data) => {
  activeEvent.value = data
  isEventModalOpen.value = true
}

const handleCloseModal = () => {
  isEventModalOpen.value = false
  activeEvent.value = {}
}

const handleCloseAppointmentModal = () => {
  isAppointmentModalOpen.value = false
  selectedAppointment.value = null
}

const handleAppointmentSaved = () => {
  fetchEvents()
}

const handleSelectSlot = ({ doctorId, start, end }) => {
  openEventModal(createDefaultEvent({ start, end, doctorId }))
}

const handleAppointmentClick = (item) => {
  if (item.type === 'appointment') {
    selectedAppointment.value = item.raw
    isAppointmentModalOpen.value = true
    return
  }

  openEventModal({
    id: item.id,
    title: item.title,
    start: item.startAt,
    end: item.endAt,
    doctor_id: item.doctorId,
    note: item.raw?.note || '',
    type: item.raw?.type || 'personal_block'
  })
}

const handleAppointmentUpdate = async ({ id, startAt, endAt, doctorId }) => {
  const item = calendarItems.value.find((entry) => entry.id === id)
  if (!item || item.type !== 'appointment') return
  if (
    item.startAt.getTime() === startAt.getTime() &&
    item.endAt.getTime() === endAt.getTime() &&
    item.doctorId === doctorId
  ) {
    return
  }

  const original = { ...item }

  updateCalendarItem({ ...item, startAt, endAt, doctorId })

  if (!doctorId) {
    toastError('Неможливо визначити лікаря для запису')
    updateCalendarItem(original)
    return
  }

  if (!isDoctorActive(doctorId)) {
    toastError('Лікар неактивний')
    updateCalendarItem(original)
    return
  }

  if (!isWithinClinicHours(startAt, endAt)) {
    toastError('Запис поза робочим часом клініки')
    updateCalendarItem(original)
    return
  }

  if (hasOverlap(item.id, doctorId, startAt, endAt)) {
    toastError('Перетин записів у лікаря')
    updateCalendarItem(original)
    return
  }

  if (item.raw?.status === 'done') {
    toastError('Завершений запис не можна переносити')
    updateCalendarItem(original)
    return
  }

  try {
    const { data } = await calendarApi.updateAppointment(item.id, {
      doctor_id: doctorId,
      start_at: formatDateTime(startAt),
      end_at: formatDateTime(endAt)
    })

    const updatedAppointment = data?.data || data?.appointment || data
    if (updatedAppointment) {
      const mapped = mapAppointmentToItem(updatedAppointment)
      if (mapped) {
        updateCalendarItem(mapped)
      } else {
        updateCalendarItem({ ...item, startAt, endAt, doctorId, raw: updatedAppointment })
      }
    }

    toastSuccess('Запис перенесено')
  } catch (error) {
    console.error(error)
    toastError('Не вдалося перенести запис')
    updateCalendarItem(original)
  }
}

const handleAppointmentDragStart = () => {
  // Reserved for future availability overlays
}

const handleAppointmentDragEnd = () => {
  // Reserved for future availability overlays
}

const handleClinicSelection = (clinicId) => {
  selectedClinicId.value = clinicId
  handleClinicChange()
}

const handleDoctorSelection = (doctorId) => {
  selectedDoctorId.value = doctorId
  fetchEvents()
}

const handleProcedureSelection = (procedureId) => {
  selectedProcedureId.value = procedureId
  fetchEvents()
}

const parseQueryDate = (value) => {
  if (typeof value !== 'string' || !value.trim()) return null
  const parsed = new Date(`${value}T00:00:00`)
  return Number.isNaN(parsed.getTime()) ? null : parsed
}

const parseQueryView = (value) => {
  if (value === 'day' || value === 'week' || value === 'month') return value
  return null
}

const parseQueryNumber = (value) => {
  const num = Number(value)
  return Number.isFinite(num) && num > 0 ? num : null
}

const applyRouteSelection = () => {
  const nextView = parseQueryView(route.query.view)
  if (nextView) {
    view.value = nextView
  }

  const nextDate = parseQueryDate(route.query.date)
  if (nextDate) {
    currentDate.value = new Date(nextDate)
  }

  const clinicId = parseQueryNumber(route.query.clinic || route.query.clinic_id)
  if (clinicId) {
    selectedClinicId.value = clinicId
  }

  const appointmentId = route.query.appointment_id || route.query.appointment
  if (appointmentId) {
    pendingAppointmentId.value = String(appointmentId)
  }
}

onMounted(async () => {
  await initAuth()
  await loadClinics()

  await nextTick()
  setTimeout(() => {
    applyRouteSelection()
    fetchEvents()
    loadDoctors()
    loadProcedures()
  }, 100)
})

watch(view, () => {
  handleCloseModal()
  handleCloseAppointmentModal()
  closeMonthEvents()
  fetchEvents()
})

watch(currentClinicId, () => {
  fetchEvents()
  loadDoctors()
  loadProcedures()
})

watch(currentDate, () => {
  fetchEvents()
})

watch(
  () => route.query,
  () => {
    handleCloseModal()
    handleCloseAppointmentModal()
    closeMonthEvents()
    applyRouteSelection()
    fetchEvents()
  }
)
</script>
