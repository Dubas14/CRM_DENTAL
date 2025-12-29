<template>
  <aside class="w-[280px] shrink-0 rounded-xl border border-border/40 bg-card/40 p-4 text-text">
    <button
      type="button"
      class="flex w-full items-center justify-between rounded-lg border border-border/60 bg-card px-3 py-2 text-sm font-semibold text-text/90"
      @click="toggleCalendar"
    >
      <span>{{ monthLabel }}</span>
      <span class="text-lg transition-transform" :class="{ 'rotate-90': isCalendarOpen }">▸</span>
    </button>

    <div v-if="isCalendarOpen" class="mt-3 rounded-lg border border-border/60 bg-bg/40 p-3">
      <div class="mb-2 flex items-center justify-between text-sm text-text/80">
        <button
          type="button"
          class="rounded-md border border-border/50 px-2 py-1 hover:bg-card/60"
          @click="goPrevMonth"
        >
          ‹
        </button>
        <span class="font-semibold text-text">{{ monthLabel }}</span>
        <button
          type="button"
          class="rounded-md border border-border/50 px-2 py-1 hover:bg-card/60"
          @click="goNextMonth"
        >
          ›
        </button>
      </div>

      <div class="grid grid-cols-7 gap-1 text-center text-[10px] uppercase text-text/50">
        <span v-for="day in weekDays" :key="day">{{ day }}</span>
      </div>
      <div class="mt-2 grid grid-cols-7 gap-1 text-center text-xs">
        <button
          v-for="day in calendarDays"
          :key="day.key"
          type="button"
          class="rounded-md px-1.5 py-1 transition"
          :class="[
            day.isCurrentMonth ? 'text-text/90' : 'text-text/40',
            day.isSelected ? 'bg-emerald-500/20 text-emerald-200' : 'hover:bg-card/70',
          ]"
          @click="selectDate(day.date)"
        >
          {{ day.label }}
        </button>
      </div>
    </div>

    <div class="mt-6">
      <p class="text-xs font-semibold uppercase tracking-wide text-text/60">Filters</p>

      <div class="mt-4 space-y-4">
        <div>
          <label class="text-[11px] font-semibold uppercase tracking-wide text-text/60">Оберіть клініку</label>
          <select
            class="mt-2 w-full rounded-lg border border-border/70 bg-card px-3 py-2 text-sm text-text/90"
            :value="selectedClinicId"
            @change="onClinicChange"
          >
            <option :value="null" disabled>-- Оберіть клініку --</option>
            <option v-for="clinic in clinics" :key="clinic.id" :value="clinic.id">
              {{ clinic.name }}
            </option>
          </select>
        </div>

        <div>
          <label class="text-[11px] font-semibold uppercase tracking-wide text-text/60">Оберіть лікаря</label>
          <select
            class="mt-2 w-full rounded-lg border border-border/70 bg-card px-3 py-2 text-sm text-text/90"
            :value="selectedDoctorId"
            :disabled="loadingDoctors || !doctors.length || isDoctor"
            @change="onDoctorChange"
          >
            <option :value="null" disabled>-- Оберіть лікаря --</option>
            <option v-for="doctor in doctors" :key="doctor.id" :value="doctor.id">
              {{ doctor.full_name || doctor.name }}
            </option>
          </select>
        </div>

        <div>
          <label class="text-[11px] font-semibold uppercase tracking-wide text-text/60">Оберіть процедуру</label>
          <select
            class="mt-2 w-full rounded-lg border border-border/70 bg-card px-3 py-2 text-sm text-text/90"
            :value="selectedProcedureId"
            @change="onProcedureChange"
          >
            <option :value="null">Усі</option>
            <option v-for="procedure in procedures" :key="procedure.id" :value="procedure.id">
              {{ procedure.name }}
            </option>
          </select>
        </div>
      </div>
    </div>
  </aside>
</template>

<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
  currentDate: {
    type: Date,
    required: true,
  },
  clinics: {
    type: Array,
    default: () => [],
  },
  doctors: {
    type: Array,
    default: () => [],
  },
  procedures: {
    type: Array,
    default: () => [],
  },
  selectedClinicId: {
    type: [Number, String, null],
    default: null,
  },
  selectedDoctorId: {
    type: [Number, String, null],
    default: null,
  },
  selectedProcedureId: {
    type: [Number, String, null],
    default: null,
  },
  loadingDoctors: {
    type: Boolean,
    default: false,
  },
  isDoctor: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['clinic-change', 'doctor-change', 'procedure-change', 'date-change', 'select-date'])

const isCalendarOpen = ref(false)
const viewDate = ref(props.currentDate ? new Date(props.currentDate) : new Date())

const weekDays = ['ПН', 'ВТ', 'СР', 'ЧТ', 'ПТ', 'СБ', 'НД']

const monthFormatter = new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' })

const monthLabel = computed(() => monthFormatter.format(viewDate.value))

const normalizedSelectedDate = computed(() => {
  const value = props.currentDate
  if (!value) return null
  const date = value instanceof Date ? value : new Date(value)
  return Number.isNaN(date.getTime()) ? null : date
})

const calendarDays = computed(() => {
  const base = new Date(viewDate.value.getFullYear(), viewDate.value.getMonth(), 1)
  const dayOfWeek = base.getDay() || 7
  const offset = dayOfWeek - 1
  const start = new Date(base)
  start.setDate(base.getDate() - offset)

  const days = []
  for (let i = 0; i < 42; i += 1) {
    const date = new Date(start)
    date.setDate(start.getDate() + i)
    const isCurrentMonth = date.getMonth() === base.getMonth()
    const selected = normalizedSelectedDate.value
    const isSelected = selected
      ? date.getFullYear() === selected.getFullYear()
        && date.getMonth() === selected.getMonth()
        && date.getDate() === selected.getDate()
      : false

    days.push({
      key: `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`,
      label: date.getDate(),
      date,
      isCurrentMonth,
      isSelected,
    })
  }
  return days
})

const toggleCalendar = () => {
  isCalendarOpen.value = !isCalendarOpen.value
}

const goPrevMonth = () => {
  const next = new Date(viewDate.value)
  next.setMonth(next.getMonth() - 1)
  viewDate.value = next
}

const goNextMonth = () => {
  const next = new Date(viewDate.value)
  next.setMonth(next.getMonth() + 1)
  viewDate.value = next
}

const selectDate = (date) => {
  emit('date-change', date)
  emit('select-date', date)
  if (date?.getMonth() !== viewDate.value.getMonth() || date?.getFullYear() !== viewDate.value.getFullYear()) {
    viewDate.value = new Date(date)
  }
}

const onClinicChange = (event) => {
  const value = event?.target?.value
  emit('clinic-change', value && value !== 'null' ? Number(value) : null)
}

const onDoctorChange = (event) => {
  const value = event?.target?.value
  emit('doctor-change', value && value !== 'null' ? Number(value) : null)
}

const onProcedureChange = (event) => {
  const value = event?.target?.value
  emit('procedure-change', value && value !== 'null' ? Number(value) : null)
}

watch(
  () => props.currentDate,
  (value) => {
    if (!value) return
    const next = value instanceof Date ? value : new Date(value)
    if (!Number.isNaN(next.getTime())) {
      viewDate.value = new Date(next.getFullYear(), next.getMonth(), 1)
    }
  }
)
</script>
