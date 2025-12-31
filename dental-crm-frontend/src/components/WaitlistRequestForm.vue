<script setup lang="ts">
import { ref, watch } from 'vue'
import calendarApi from '../services/calendarApi'

const props = defineProps({
  clinicId: { type: [Number, String], required: true },
  defaultDoctorId: { type: [Number, String, null], default: null },
  defaultProcedureId: { type: [Number, String, null], default: null }
})

const emit = defineEmits(['created'])

const form = ref({
  patient_id: '',
  doctor_id: props.defaultDoctorId || '',
  procedure_id: props.defaultProcedureId || '',
  preferred_date: '',
  notes: ''
})

const loading = ref(false)
const error = ref(null)

let lastDefaultDoctor = props.defaultDoctorId || ''
let lastDefaultProcedure = props.defaultProcedureId || ''

watch(
  () => props.defaultDoctorId,
  (val) => {
    const normalized = val || ''
    if (!form.value.doctor_id || form.value.doctor_id === lastDefaultDoctor) {
      form.value.doctor_id = normalized
    }
    lastDefaultDoctor = normalized
  }
)

watch(
  () => props.defaultProcedureId,
  (val) => {
    const normalized = val || ''
    if (!form.value.procedure_id || form.value.procedure_id === lastDefaultProcedure) {
      form.value.procedure_id = normalized
    }
    lastDefaultProcedure = normalized
  }
)

const submit = async () => {
  loading.value = true
  error.value = null
  try {
    const payload = {
      clinic_id: props.clinicId,
      patient_id: form.value.patient_id,
      doctor_id: form.value.doctor_id || null,
      procedure_id: form.value.procedure_id || null,
      preferred_date: form.value.preferred_date || null,
      notes: form.value.notes || null
    }
    const { data } = await calendarApi.createWaitlistEntry(payload)
    emit('created', data)
    form.value = {
      patient_id: '',
      doctor_id: props.defaultDoctorId || '',
      procedure_id: props.defaultProcedureId || '',
      preferred_date: '',
      notes: ''
    }
  } catch (e) {
    error.value = e.response?.data?.message || e.message
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="bg-card/60 rounded-xl shadow-sm shadow-black/10 dark:shadow-black/40 p-4 space-y-3">
    <div>
      <p class="text-xs uppercase tracking-wide text-text/70">
        Швидке додавання у список очікування
      </p>
      <p class="text-lg font-semibold text-text">Waitlist</p>
    </div>

    <div
      v-if="error"
      class="text-sm text-red-400 bg-red-900/20 border border-red-700/40 rounded-lg p-3"
    >
      {{ error }}
    </div>

    <div class="grid gap-3 md:grid-cols-2">
      <label class="space-y-1">
        <span class="text-sm text-text/80">ID пацієнта *</span>
        <input
          v-model="form.patient_id"
          type="number"
          class="w-full bg-bg border border-border/80 rounded-lg px-3 py-2 text-text"
          placeholder="Напр. 42"
        />
      </label>
      <label class="space-y-1">
        <span class="text-sm text-text/80">ID лікаря (опц.)</span>
        <input
          v-model="form.doctor_id"
          type="number"
          class="w-full bg-bg border border-border/80 rounded-lg px-3 py-2 text-text"
        />
      </label>
      <label class="space-y-1">
        <span class="text-sm text-text/80">ID процедури (опц.)</span>
        <input
          v-model="form.procedure_id"
          type="number"
          class="w-full bg-bg border border-border/80 rounded-lg px-3 py-2 text-text"
        />
      </label>
      <label class="space-y-1">
        <span class="text-sm text-text/80">Бажана дата</span>
        <input
          v-model="form.preferred_date"
          type="date"
          class="w-full bg-bg border border-border/80 rounded-lg px-3 py-2 text-text"
        />
      </label>
    </div>

    <label class="space-y-1 block">
      <span class="text-sm text-text/80">Коментар</span>
      <textarea
        v-model="form.notes"
        rows="2"
        class="w-full bg-bg border border-border/80 rounded-lg px-3 py-2 text-text"
      ></textarea>
    </label>

    <div class="flex justify-end">
      <button
        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-text rounded-lg disabled:opacity-60"
        :disabled="loading"
        @click="submit"
      >
        {{ loading ? 'Збереження...' : 'Додати у waitlist' }}
      </button>
    </div>
  </div>
</template>
