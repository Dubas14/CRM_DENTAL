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
      <CalendarMiniMonth :current-date="currentDate" @select-date="selectDate" />
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
import { computed, ref } from 'vue'
import CalendarMiniMonth from './CalendarMiniMonth.vue'

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
const monthFormatter = new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' })

const normalizedCurrentDate = computed(() => {
  const value = props.currentDate
  if (!value) return new Date()
  const date = value instanceof Date ? value : new Date(value)
  return Number.isNaN(date.getTime()) ? new Date() : date
})

const monthLabel = computed(() => monthFormatter.format(normalizedCurrentDate.value))

const toggleCalendar = () => {
  isCalendarOpen.value = !isCalendarOpen.value
}

const selectDate = (date) => {
  emit('date-change', date)
  emit('select-date', date)
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

</script>
