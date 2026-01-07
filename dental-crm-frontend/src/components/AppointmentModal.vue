<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import apiClient from '../services/apiClient'
import calendarApi from '../services/calendarApi'
import procedureApi from '../services/procedureApi'
import equipmentApi from '../services/equipmentApi'
import assistantApi from '../services/assistantApi'
import doctorApi from '../services/doctorApi'
import roomApi from '../services/roomApi'
import CalendarSlotPicker from './CalendarSlotPicker.vue'
import SmartSlotPicker from './SmartSlotPicker.vue'

const props = defineProps({
  appointment: Object,
  isOpen: Boolean,
  clinicId: [Number, String, null]
})

// Додаємо подію 'create-patient'
const emit = defineEmits(['close', 'saved', 'create-patient', 'open-payment', 'open-invoice'])

const form = ref({
  diagnosis: '',
  treatment: '',
  complaints: '',
  tooth_number: '',
  update_tooth_status: ''
})

const loading = ref(false)
const appointmentSaving = ref(false)
const followUpSaving = ref(false)

// UI state (no business-logic changes)
const rescheduleMode = ref<'slots' | 'smart'>('slots')
const showDetails = ref(false)
const showFollowUp = ref(false)

const statuses = [
  { id: 'healthy', label: 'Здоровий' },
  { id: 'caries', label: 'Карієс' },
  { id: 'filled', label: 'Пломба' },
  { id: 'pulpitis', label: 'Пульпіт' },
  { id: 'missing', label: 'Відсутній' }
]

const getProp = (key) => {
  if (!props.appointment) return null
  if (props.appointment[key] !== undefined) return props.appointment[key]
  if (props.appointment.extendedProps && props.appointment.extendedProps[key] !== undefined) {
    return props.appointment.extendedProps[key]
  }
  return null
}

const toDate = (value) => {
  if (!value) return null
  if (value.toDate) return value.toDate()
  const parsed = value instanceof Date ? value : new Date(value)
  return Number.isNaN(parsed.getTime()) ? null : parsed
}

const formatDateTimeLocal = (value) => {
  const parsed = toDate(value)
  if (!parsed) return ''
  const year = parsed.getFullYear()
  const month = `${parsed.getMonth() + 1}`.padStart(2, '0')
  const day = `${parsed.getDate()}`.padStart(2, '0')
  const hour = `${parsed.getHours()}`.padStart(2, '0')
  const minute = `${parsed.getMinutes()}`.padStart(2, '0')
  return `${year}-${month}-${day}T${hour}:${minute}`
}

// Convert API datetime (YYYY-MM-DD HH:mm:ss) to datetime-local string (YYYY-MM-DDTHH:mm) without timezone shifts.
const apiDateTimeToLocalInput = (value) => {
  if (!value) return ''
  if (typeof value === 'string') {
    // already datetime-local
    if (value.includes('T')) return value.slice(0, 16)
    const m = value.match(/^(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2})(?::\d{2})?$/)
    if (m) return `${m[1]}T${m[2]}`
  }
  return formatDateTimeLocal(value)
}

const parseLocalInputToApi = (value) => {
  if (!value) return null
  if (typeof value === 'string') {
    // datetime-local: YYYY-MM-DDTHH:mm
    const m = value.match(/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})/)
    if (m) return `${m[1]} ${m[2]}:00`
    // fallback: already api-ish
    const m2 = value.match(/^(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2})(?::\d{2})?$/)
    if (m2) return `${m2[1]} ${m2[2]}:00`
  }
  return formatDateTimeApi(value)
}

const formatDateTimeApi = (value) => {
  const parsed = toDate(value)
  if (!parsed) return null
  const year = parsed.getFullYear()
  const month = `${parsed.getMonth() + 1}`.padStart(2, '0')
  const day = `${parsed.getDate()}`.padStart(2, '0')
  const hour = `${parsed.getHours()}`.padStart(2, '0')
  const minute = `${parsed.getMinutes()}`.padStart(2, '0')
  return `${year}-${month}-${day} ${hour}:${minute}:00`
}

const formatDateOnly = (value) => {
  const parsed = toDate(value)
  if (!parsed) return ''
  const year = parsed.getFullYear()
  const month = `${parsed.getMonth() + 1}`.padStart(2, '0')
  const day = `${parsed.getDate()}`.padStart(2, '0')
  return `${year}-${month}-${day}`
}

const formatTimeHM = (value) => {
  const parsed = toDate(value)
  if (!parsed) return ''
  const hour = `${parsed.getHours()}`.padStart(2, '0')
  const minute = `${parsed.getMinutes()}`.padStart(2, '0')
  return `${hour}:${minute}`
}

const patientName = computed(() => getProp('patient_name') || getProp('comment') || 'Пацієнт')
const patientId = computed(() => getProp('patient_id'))
const appointmentId = computed(() => props.appointment?.id)
const status = computed(() => getProp('status'))
const isReadOnly = computed(() => status.value === 'done')
const doctorName = computed(() => {
  const doctor = getProp('doctor')
  return doctor?.full_name || doctor?.name || getProp('doctor_name') || '—'
})

const periodLabel = computed(() => {
  const start = appointmentForm.value.start_at || appointmentDetails.value.start
  const end = appointmentForm.value.end_at || appointmentDetails.value.end
  if (!start || !end) return '—'
  try {
    const startDt = new Date(start)
    const endDt = new Date(end)
    if (Number.isNaN(startDt.getTime()) || Number.isNaN(endDt.getTime())) return '—'
    const date = startDt.toLocaleDateString('uk-UA')
    const startTime = startDt.toLocaleTimeString('uk-UA', { hour: '2-digit', minute: '2-digit' })
    const endTime = endDt.toLocaleTimeString('uk-UA', { hour: '2-digit', minute: '2-digit' })
    return `${date} • ${startTime} – ${endTime}`
  } catch {
    return '—'
  }
})
const appointmentDetails = computed(() => ({
  procedure: getProp('procedure')?.name || getProp('procedure_name'),
  room: getProp('room')?.name || getProp('room_name'),
  equipment: getProp('equipment')?.name || getProp('equipment_name'),
  assistant: getProp('assistant')?.full_name || getProp('assistant_name'),
  isFollowUp: !!getProp('is_follow_up'),
  start: getProp('start_at') || getProp('start'),
  end: getProp('end_at') || getProp('end')
}))

const doctors = ref([])
const procedures = ref([])
const rooms = ref([])
const equipments = ref([])
const assistants = ref([])
const loadingLookups = ref(false)

const appointmentForm = ref({
  doctor_id: '',
  procedure_id: '',
  room_id: '',
  equipment_id: '',
  assistant_id: '',
  start_at: '',
  end_at: '',
  status: ''
})

const followUpForm = ref({
  start_at: '',
  end_at: ''
})

const slotPickerDoctorId = computed(
  () => appointmentForm.value.doctor_id || getProp('doctor_id') || getProp('doctor')?.id || null
)
const slotPickerProcedureId = computed(
  () =>
    appointmentForm.value.procedure_id ||
    getProp('procedure_id') ||
    getProp('procedure')?.id ||
    null
)
const slotPickerRoomId = computed(
  () => appointmentForm.value.room_id || getProp('room_id') || getProp('room')?.id || null
)
const slotPickerEquipmentId = computed(
  () =>
    appointmentForm.value.equipment_id ||
    getProp('equipment_id') ||
    getProp('equipment')?.id ||
    null
)
const slotPickerAssistantId = computed(
  () =>
    appointmentForm.value.assistant_id ||
    getProp('assistant_id') ||
    getProp('assistant')?.id ||
    null
)
const slotPickerDate = computed(() =>
  formatDateOnly(appointmentForm.value.start_at || appointmentDetails.value.start)
)

const normalizeId = (value) => (value === null || value === undefined ? '' : value)

const appointmentStatuses = [
  { value: 'planned', label: 'Заплановано' },
  { value: 'confirmed', label: 'Підтверджено' },
  { value: 'reminded', label: 'Нагадано' },
  { value: 'waiting', label: 'Очікує' },
  { value: 'done', label: 'Завершено' },
  { value: 'cancelled', label: 'Скасовано' },
  { value: 'no_show', label: 'Не прийшов' }
]

const hydrateAppointmentForm = () => {
  appointmentForm.value = {
    doctor_id: normalizeId(getProp('doctor_id') || getProp('doctor')?.id || ''),
    procedure_id: normalizeId(getProp('procedure_id') || getProp('procedure')?.id || ''),
    room_id: normalizeId(getProp('room_id') || getProp('room')?.id || ''),
    equipment_id: normalizeId(getProp('equipment_id') || getProp('equipment')?.id || ''),
    assistant_id: normalizeId(getProp('assistant_id') || getProp('assistant')?.id || ''),
    start_at: apiDateTimeToLocalInput(getProp('start_at') || getProp('start')),
    end_at: apiDateTimeToLocalInput(getProp('end_at') || getProp('end')),
    status: getProp('status') || 'planned'
  }
}

const loadLookupData = async () => {
  if (!props.clinicId) return
  loadingLookups.value = true
  try {
    const [
      doctorsResponse,
      proceduresResponse,
      roomsResponse,
      equipmentResponse,
      assistantsResponse
    ] = await Promise.all([
      doctorApi.list({ clinic_id: props.clinicId }),
      procedureApi.list({ clinic_id: props.clinicId }),
      roomApi.list({ clinic_id: props.clinicId }),
      equipmentApi.list({ clinic_id: props.clinicId }),
      assistantApi.list({ clinic_id: props.clinicId })
    ])

    doctors.value = (doctorsResponse.data?.data || doctorsResponse.data || []).filter(Boolean)
    procedures.value = (proceduresResponse.data?.data || proceduresResponse.data || []).filter(
      (item) => item && item.is_active !== false
    )
    rooms.value = (roomsResponse.data?.data || roomsResponse.data || []).filter(
      (item) => item && item.is_active !== false
    )
    equipments.value = (equipmentResponse.data?.data || equipmentResponse.data || []).filter(
      (item) => item && item.is_active !== false
    )
    assistants.value = (assistantsResponse.data?.data || assistantsResponse.data || []).filter(
      (item) => item && item.is_active !== false
    )
  } catch {
    alert('Помилка завантаження даних довідників.')
  } finally {
    loadingLookups.value = false
  }
}

const applySlotToForm = (slot) => {
  const date = slot?.date || slotPickerDate.value
  if (!date || !slot?.start || !slot?.end) return
  appointmentForm.value.start_at = `${date}T${slot.start}`
  appointmentForm.value.end_at = `${date}T${slot.end}`
}

const ensureSlotAvailable = async () => {
  // Only validate slot availability when time/resources were changed.
  const originalStart = apiDateTimeToLocalInput(getProp('start_at') || getProp('start'))
  const originalEnd = apiDateTimeToLocalInput(getProp('end_at') || getProp('end'))
  const originalDoctor = normalizeId(getProp('doctor_id') || getProp('doctor')?.id || '')
  const originalProcedure = normalizeId(getProp('procedure_id') || getProp('procedure')?.id || '')
  const originalRoom = normalizeId(getProp('room_id') || getProp('room')?.id || '')
  const originalEquipment = normalizeId(getProp('equipment_id') || getProp('equipment')?.id || '')
  const originalAssistant = normalizeId(getProp('assistant_id') || getProp('assistant')?.id || '')

  const onlyStatusChanged =
    appointmentForm.value.start_at === originalStart &&
    appointmentForm.value.end_at === originalEnd &&
    String(appointmentForm.value.doctor_id || '') === String(originalDoctor || '') &&
    String(appointmentForm.value.procedure_id || '') === String(originalProcedure || '') &&
    String(appointmentForm.value.room_id || '') === String(originalRoom || '') &&
    String(appointmentForm.value.equipment_id || '') === String(originalEquipment || '') &&
    String(appointmentForm.value.assistant_id || '') === String(originalAssistant || '')

  if (onlyStatusChanged) return true

  const doctorId = slotPickerDoctorId.value
  if (!doctorId) return true

  const date = slotPickerDate.value
  const startTime = formatTimeHM(appointmentForm.value.start_at)
  if (!date || !startTime) return true

  try {
    const { data } = await calendarApi.getDoctorSlots(doctorId, {
      date,
      procedure_id: slotPickerProcedureId.value || undefined,
      room_id: slotPickerRoomId.value || undefined,
      equipment_id: slotPickerEquipmentId.value || undefined,
      assistant_id: slotPickerAssistantId.value || undefined
    })
    const slots = Array.isArray(data?.slots) ? data.slots : []
    const allowed = new Set(slots.map((slot) => slot.start))
    if (!allowed.has(startTime)) {
      alert('Неможливо змінити запис: вибраний час недоступний. Оберіть вільний слот.')
      return false
    }
    return true
  } catch {
    alert('Не вдалося перевірити доступні слоти. Спробуйте ще раз.')
    return false
  }
}

const updateAppointment = async () => {
  if (!appointmentId.value) return
  if (!appointmentForm.value.start_at || !appointmentForm.value.end_at) {
    alert('Вкажіть час початку та завершення прийому.')
    return
  }

  const slotAllowed = await ensureSlotAvailable()
  if (!slotAllowed) return

  appointmentSaving.value = true
  try {
    const originalStart = apiDateTimeToLocalInput(getProp('start_at') || getProp('start'))
    const originalEnd = apiDateTimeToLocalInput(getProp('end_at') || getProp('end'))
    const startChanged = appointmentForm.value.start_at !== originalStart
    const endChanged = appointmentForm.value.end_at !== originalEnd

    const startAtFormatted = startChanged
      ? parseLocalInputToApi(appointmentForm.value.start_at)
      : null
    const endAtFormatted = endChanged ? parseLocalInputToApi(appointmentForm.value.end_at) : null

    // Створюємо payload, виключаючи null/undefined значення
    const updatePayload: any = {}
    if (startAtFormatted && endAtFormatted) {
      updatePayload.start_at = startAtFormatted
      updatePayload.end_at = endAtFormatted
    }

    // Додаємо поля тільки якщо вони мають значення
    if (appointmentForm.value.doctor_id) {
      updatePayload.doctor_id = appointmentForm.value.doctor_id
    }
    if (appointmentForm.value.procedure_id) {
      updatePayload.procedure_id = appointmentForm.value.procedure_id
    }
    if (appointmentForm.value.room_id) {
      updatePayload.room_id = appointmentForm.value.room_id
    }
    if (appointmentForm.value.equipment_id) {
      updatePayload.equipment_id = appointmentForm.value.equipment_id
    }
    if (appointmentForm.value.assistant_id) {
      updatePayload.assistant_id = appointmentForm.value.assistant_id
    }
    if (appointmentForm.value.status) {
      updatePayload.status = appointmentForm.value.status
    }
    if (props.clinicId) {
      updatePayload.clinic_id = props.clinicId
    }

    await calendarApi.updateAppointment(appointmentId.value, updatePayload)
    emit('saved')
    emit('close')
  } catch (e) {
    console.error('Error updating appointment:', e)
    const errorMessage =
      e.response?.data?.message || e.response?.data?.error || e.message || 'Невідома помилка'
    alert('Помилка: ' + errorMessage)
  } finally {
    appointmentSaving.value = false
  }
}

const scheduleFollowUp = async () => {
  if (!patientId.value) {
    alert('Цей запис не привʼязаний до пацієнта.')
    return
  }
  if (!followUpForm.value.start_at || !followUpForm.value.end_at) {
    alert('Вкажіть час початку та завершення наступного прийому.')
    return
  }
  followUpSaving.value = true
  try {
    const startDate = new Date(followUpForm.value.start_at)
    await calendarApi.createAppointment({
      clinic_id: props.clinicId || undefined,
      doctor_id:
        appointmentForm.value.doctor_id || getProp('doctor_id') || getProp('doctor')?.id || null,
      patient_id: patientId.value,
      procedure_id:
        appointmentForm.value.procedure_id ||
        getProp('procedure_id') ||
        getProp('procedure')?.id ||
        null,
      room_id: appointmentForm.value.room_id || getProp('room_id') || getProp('room')?.id || null,
      equipment_id:
        appointmentForm.value.equipment_id ||
        getProp('equipment_id') ||
        getProp('equipment')?.id ||
        null,
      assistant_id:
        appointmentForm.value.assistant_id ||
        getProp('assistant_id') ||
        getProp('assistant')?.id ||
        null,
      is_follow_up: true,
      date: formatDateOnly(startDate),
      time: formatTimeHM(startDate)
    })
    emit('saved')
    followUpForm.value = { start_at: '', end_at: '' }
    alert('Наступний прийом заплановано.')
  } catch (e) {
    alert('Помилка: ' + (e.response?.data?.message || e.message))
  } finally {
    followUpSaving.value = false
  }
}

const saveRecord = async () => {
  // Валідація зубів
  if (form.value.tooth_number) {
    const t = Number(form.value.tooth_number)
    const isValidAdult = t >= 11 && t <= 48
    const isValidChild = t >= 51 && t <= 85
    if (!isValidAdult && !isValidChild) {
      alert('Невірний номер зуба! Використовуйте ISO (11-48).')
      return
    }
  }

  if (!patientId.value) {
    alert('Помилка: Цей запис не привʼязаний до пацієнта.')
    return
  }

  loading.value = true
  try {
    await apiClient.post(`/patients/${patientId.value}/records`, {
      ...form.value,
      appointment_id: appointmentId.value
    })

    // Варіант A: завершуємо прийом "зараз" і скорочуємо end_at, щоб слот одразу повернувся
    if (appointmentId.value) {
      const startLocal = apiDateTimeToLocalInput(getProp('start_at') || getProp('start'))
      const startDate = startLocal ? new Date(startLocal) : null
      const now = new Date()
      if (startDate && !Number.isNaN(startDate.getTime()) && startDate.getTime() > now.getTime()) {
        alert('Цей запис ще не почався. Неможливо завершити прийом раніше початку.')
      } else {
        const finishResponse = await calendarApi.finishAppointment(appointmentId.value)

        // Обробка пропозиції рахунку
        if (finishResponse.data?.invoice_suggestion) {
          const suggestion = finishResponse.data.invoice_suggestion
          if (suggestion.action === 'pay_existing') {
            const confirmPay = confirm(
              `Є неоплачений рахунок ${suggestion.invoice_number}. ` +
                `Борг: ${suggestion.debt_amount} грн. Відкрити для оплати?`
            )
            if (confirmPay) {
              // Emit event to open payment modal
              emit('open-payment', { invoiceId: suggestion.existing_invoice_id })
            }
          } else if (suggestion.action === 'create') {
            const confirmCreate = confirm(
              `Процедура: ${suggestion.procedure_name}. ` +
                `Ціна: ${suggestion.procedure_price} грн. Створити рахунок?`
            )
            if (confirmCreate) {
              // Emit event to open invoice form
              emit('open-invoice', {
                patientId: patientId.value,
                appointmentId: appointmentId.value,
                procedureId: suggestion.procedure_id,
                procedureName: suggestion.procedure_name,
                procedurePrice: suggestion.procedure_price
              })
            }
          }
        }
      }
    }

    alert('Прийом завершено успішно!')
    emit('saved')
    emit('close')
    form.value = {
      diagnosis: '',
      treatment: '',
      complaints: '',
      tooth_number: '',
      update_tooth_status: ''
    }
  } catch (e) {
    alert('Помилка: ' + (e.response?.data?.message || e.message))
  } finally {
    loading.value = false
  }
}

watch(
  () => [props.isOpen, props.appointment],
  () => {
    if (!props.isOpen) return
    hydrateAppointmentForm()
    followUpForm.value = { start_at: '', end_at: '' }
  },
  { immediate: true }
)

watch(
  () => [props.isOpen, props.clinicId],
  () => {
    if (props.isOpen) {
      loadLookupData()
    }
  },
  { immediate: true }
)
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center bg-text/20 dark:bg-bg/50 backdrop-blur-sm p-4"
  >
    <div
      class="bg-card rounded-xl shadow-sm shadow-black/10 dark:shadow-black/40 shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]"
    >
      <!-- Заголовок -->
      <div class="bg-bg p-4 flex justify-between items-center border-b border-border">
        <div>
          <h2 class="text-lg font-bold text-text">Прийом пацієнта</h2>
          <p class="text-sm text-text/70">
            {{ patientName }}
            <span v-if="!patientId" class="text-red-400 text-xs ml-2">(Гість)</span>
          </p>
        </div>
        <button
          type="button"
          @click="$emit('close')"
          class="text-text/70 hover:text-text text-2xl leading-none transition-colors"
        >
          ×
        </button>
      </div>

      <!-- Тіло форми -->
      <div class="p-6 overflow-y-auto custom-scrollbar space-y-4">
        <!-- Summary (compact, no duplicates) -->
        <section
          class="rounded-xl bg-card/60 shadow-sm shadow-black/10 dark:shadow-black/40 p-4 space-y-2"
        >
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="space-y-0.5">
              <p class="text-xs uppercase tracking-wide text-text/60">Поточний запис</p>
              <p class="text-sm font-semibold text-text">{{ periodLabel }}</p>
            </div>
            <div class="flex items-center gap-2">
              <label for="appointment-status" class="text-xs text-text/60">Статус:</label>
              <select
                v-if="!isReadOnly"
                id="appointment-status"
                v-model="appointmentForm.status"
                class="text-xs bg-card/80 px-2 py-1 rounded text-text/80 border border-border/40 focus:border-emerald-500/60 focus:outline-none"
              >
                <option v-for="s in appointmentStatuses" :key="s.value" :value="s.value">
                  {{ s.label }}
                </option>
              </select>
              <span v-else class="text-xs bg-card/80 px-2 py-1 rounded text-text/80">
                {{ appointmentStatuses.find((s) => s.value === status)?.label || status || '—' }}
              </span>
            </div>
          </div>

          <div class="grid gap-2 sm:grid-cols-2 text-sm text-text/80">
            <div class="rounded-lg bg-bg/40 border border-border/40 p-3">
              <p class="text-xs uppercase tracking-wide text-text/60">Лікар</p>
              <p class="font-semibold text-text">{{ doctorName }}</p>
            </div>
            <div class="rounded-lg bg-bg/40 border border-border/40 p-3">
              <p class="text-xs uppercase tracking-wide text-text/60">Процедура</p>
              <p class="font-semibold text-text">{{ appointmentDetails.procedure || '—' }}</p>
            </div>
          </div>

          <div class="flex flex-wrap gap-2 text-xs">
            <span v-if="appointmentDetails.room" class="bg-card/80 px-2 py-1 rounded text-text/80">
              Кабінет: {{ appointmentDetails.room }}
            </span>
            <span
              v-if="appointmentDetails.equipment"
              class="bg-card/80 px-2 py-1 rounded text-text/80"
            >
              Обладнання: {{ appointmentDetails.equipment }}
            </span>
            <span
              v-if="appointmentDetails.assistant"
              class="bg-card/80 px-2 py-1 rounded text-text/80"
            >
              Асистент: {{ appointmentDetails.assistant }}
            </span>
          </div>
        </section>

        <!-- Reschedule -->
        <section class="bg-card/60 rounded-lg border border-border/40 p-4 space-y-3">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p class="text-sm font-semibold text-text">Перенести / підібрати час</p>
              <p class="text-xs text-text/60">Оберіть один режим підбору, щоб не дублювати блоки</p>
            </div>
            <div class="flex items-center gap-2">
              <button
                type="button"
                class="px-3 py-1.5 rounded-lg border text-xs transition"
                :class="
                  rescheduleMode === 'slots'
                    ? 'border-emerald-500/60 bg-emerald-500/10 text-emerald-200'
                    : 'border-border/70 bg-card/50 text-text/70 hover:text-text'
                "
                @click="rescheduleMode = 'slots'"
              >
                Слоти
              </button>
              <button
                type="button"
                class="px-3 py-1.5 rounded-lg border text-xs transition"
                :class="
                  rescheduleMode === 'smart'
                    ? 'border-emerald-500/60 bg-emerald-500/10 text-emerald-200'
                    : 'border-border/70 bg-card/50 text-text/70 hover:text-text'
                "
                @click="rescheduleMode = 'smart'"
              >
                Рекомендації
              </button>
            </div>
          </div>

          <div v-if="rescheduleMode === 'smart'" class="space-y-2">
            <p class="text-xs text-text/60">Найближчі доступні варіанти</p>
            <SmartSlotPicker
              :doctor-id="slotPickerDoctorId"
              :procedure-id="slotPickerProcedureId"
              :from-date="slotPickerDate || undefined"
              @select="applySlotToForm"
            />
          </div>

          <div v-else class="space-y-2">
            <p v-if="!slotPickerDoctorId || !slotPickerDate" class="text-xs text-text/60">
              Оберіть лікаря та дату (у деталях), щоб побачити доступні слоти.
            </p>
            <CalendarSlotPicker
              v-else
              :doctor-id="slotPickerDoctorId"
              :clinic-id="clinicId"
              :procedure-id="slotPickerProcedureId"
              :room-id="slotPickerRoomId"
              :equipment-id="slotPickerEquipmentId"
              :assistant-id="slotPickerAssistantId"
              :assistants="assistants"
              :date="slotPickerDate"
              :disabled="isReadOnly"
              @select-slot="applySlotToForm"
            />
          </div>
        </section>

        <!-- Details (collapsed by default to remove visual duplicates) -->
        <details
          class="bg-card/60 rounded-lg border border-border/40 p-4"
          :open="showDetails"
          @toggle="showDetails = ($event.target as HTMLDetailsElement).open"
        >
          <summary class="cursor-pointer select-none text-sm font-semibold text-text">
            Деталі запису (лікар/процедура/ресурси/період)
          </summary>

          <div class="mt-4 grid gap-4 text-sm text-text/80 sm:grid-cols-2">
            <div>
              <label
                for="appointment-doctor"
                class="block text-xs uppercase tracking-wide text-text/70 mb-1"
                >Лікар</label
              >
              <select
                id="appointment-doctor"
                name="doctor_id"
                v-model="appointmentForm.doctor_id"
                class="w-full rounded-lg bg-card border border-border/80 px-3 py-2 text-sm"
                :disabled="loadingLookups || isReadOnly"
              >
                <option value="">-- Оберіть лікаря --</option>
                <option v-for="doc in doctors" :key="doc.id" :value="doc.id">
                  {{ doc.full_name || doc.name || doc.email }}
                </option>
              </select>
            </div>

            <div>
              <label
                for="appointment-procedure"
                class="block text-xs uppercase tracking-wide text-text/70 mb-1"
                >Процедура</label
              >
              <select
                id="appointment-procedure"
                name="procedure_id"
                v-model="appointmentForm.procedure_id"
                class="w-full rounded-lg bg-card border border-border/80 px-3 py-2 text-sm"
                :disabled="loadingLookups || isReadOnly"
              >
                <option value="">-- Оберіть процедуру --</option>
                <option v-for="proc in procedures" :key="proc.id" :value="proc.id">
                  {{ proc.name }}
                </option>
              </select>
            </div>

            <div>
              <label
                for="appointment-room"
                class="block text-xs uppercase tracking-wide text-text/70 mb-1"
                >Кабінет</label
              >
              <select
                id="appointment-room"
                name="room_id"
                v-model="appointmentForm.room_id"
                class="w-full rounded-lg bg-card border border-border/80 px-3 py-2 text-sm"
                :disabled="loadingLookups || isReadOnly"
              >
                <option value="">-- Оберіть кабінет --</option>
                <option v-for="room in rooms" :key="room.id" :value="room.id">
                  {{ room.name }}
                </option>
              </select>
            </div>

            <div>
              <label
                for="appointment-equipment"
                class="block text-xs uppercase tracking-wide text-text/70 mb-1"
              >
                Обладнання
              </label>
              <select
                id="appointment-equipment"
                name="equipment_id"
                v-model="appointmentForm.equipment_id"
                class="w-full rounded-lg bg-card border border-border/80 px-3 py-2 text-sm"
                :disabled="loadingLookups || isReadOnly"
              >
                <option value="">-- Оберіть обладнання --</option>
                <option v-for="equipment in equipments" :key="equipment.id" :value="equipment.id">
                  {{ equipment.name }}
                </option>
              </select>
            </div>

            <div>
              <label
                for="appointment-assistant"
                class="block text-xs uppercase tracking-wide text-text/70 mb-1"
              >
                Асистент
              </label>
              <select
                id="appointment-assistant"
                name="assistant_id"
                v-model="appointmentForm.assistant_id"
                class="w-full rounded-lg bg-card border border-border/80 px-3 py-2 text-sm"
                :disabled="loadingLookups || isReadOnly"
              >
                <option value="">-- Оберіть асистента --</option>
                <option v-for="assistant in assistants" :key="assistant.id" :value="assistant.id">
                  {{ assistant.full_name || assistant.name || assistant.email }}
                </option>
              </select>
            </div>

            <div class="sm:col-span-2">
              <p class="block text-xs uppercase tracking-wide text-text/70 mb-1">Період</p>
              <div class="grid gap-2 sm:grid-cols-2">
                <div>
                  <label for="appointment-start" class="sr-only">Початок</label>
                  <input
                    id="appointment-start"
                    name="start_at"
                    v-model="appointmentForm.start_at"
                    type="datetime-local"
                    class="w-full bg-bg border border-border/80 rounded-lg p-2 text-text outline-none focus:border-emerald-500 transition-colors text-sm"
                    :disabled="isReadOnly"
                  />
                </div>
                <div>
                  <label for="appointment-end" class="sr-only">Кінець</label>
                  <input
                    id="appointment-end"
                    name="end_at"
                    v-model="appointmentForm.end_at"
                    type="datetime-local"
                    class="w-full bg-bg border border-border/80 rounded-lg p-2 text-text outline-none focus:border-emerald-500 transition-colors text-sm"
                    :disabled="isReadOnly"
                  />
                </div>
              </div>
            </div>
          </div>
        </details>

        <div
          v-if="appointmentDetails.isFollowUp"
          class="bg-emerald-900/40 text-emerald-200 border border-emerald-600/40 px-3 py-2 rounded-md text-sm flex items-center gap-2"
        >
          <span class="text-lg">↻</span>
          <span>Повторний візит</span>
        </div>

        <details
          class="bg-card/60 rounded-lg border border-border/40 p-4 space-y-3"
          :open="showFollowUp"
          @toggle="showFollowUp = ($event.target as HTMLDetailsElement).open"
        >
          <summary
            class="cursor-pointer select-none text-sm font-semibold text-text flex items-center justify-between gap-3"
          >
            <span>Запланувати наступний прийом</span>
            <span class="text-xs text-text/60 font-normal">Для пацієнта {{ patientName }}</span>
          </summary>
          <div class="grid grid-cols-2 gap-2">
            <label for="followup-start" class="sr-only">Початок</label>
            <input
              v-model="followUpForm.start_at"
              id="followup-start"
              name="follow_up_start_at"
              type="datetime-local"
              class="w-full bg-bg border border-border/80 rounded-lg p-2 text-text outline-none focus:border-emerald-500 transition-colors text-sm"
              :disabled="!patientId"
            />
            <label for="followup-end" class="sr-only">Кінець</label>
            <input
              v-model="followUpForm.end_at"
              id="followup-end"
              name="follow_up_end_at"
              type="datetime-local"
              class="w-full bg-bg border border-border/80 rounded-lg p-2 text-text outline-none focus:border-emerald-500 transition-colors text-sm"
              :disabled="!patientId"
            />
          </div>
          <button
            @click="scheduleFollowUp"
            :disabled="followUpSaving || !patientId"
            class="px-4 py-2 bg-emerald-600 text-text rounded-lg hover:bg-emerald-500 disabled:opacity-50 text-sm"
          >
            {{ followUpSaving ? 'Збереження...' : 'Запланувати' }}
          </button>
        </details>

        <div
          v-if="status === 'done'"
          class="bg-emerald-900/30 text-emerald-400 border border-emerald-500/30 p-4 rounded-lg text-center font-bold"
        >
          ✅ Цей візит вже завершено
        </div>

        <!-- 🔥 ОСЬ ТУТ КНОПКА 🔥 -->
        <div
          v-else-if="!patientId"
          class="bg-amber-900/20 border border-amber-500/30 p-4 rounded-lg flex flex-col sm:flex-row items-center justify-between gap-4"
        >
          <div class="text-amber-400 text-sm">
            <span class="font-bold block mb-1">⚠️ Пацієнт не ідентифікований</span>
            Цей запис не прив'язаний до анкети. Створіть анкету, щоб внести історію лікування.
          </div>
          <button
            @click="$emit('create-patient', patientName)"
            class="whitespace-nowrap px-4 py-2 bg-amber-600 hover:bg-amber-500 text-text rounded-lg text-sm font-bold shadow-lg transition-colors"
          >
            + Створити анкету
          </button>
        </div>

        <!-- Форма лікування (показується тільки якщо є patientId) -->
        <div v-else class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label
                for="treatment-tooth-number"
                class="block text-sm font-medium text-text/70 mb-1"
                >Зуб №</label
              >
              <input
                v-model="form.tooth_number"
                id="treatment-tooth-number"
                name="tooth_number"
                type="number"
                placeholder="Напр. 46"
                class="w-full bg-bg border border-border/80 rounded-lg p-2 text-text outline-none focus:border-emerald-500 transition-colors"
              />
            </div>
            <div>
              <label
                for="treatment-tooth-status"
                class="block text-sm font-medium text-text/70 mb-1"
                >Статус</label
              >
              <select
                v-model="form.update_tooth_status"
                id="treatment-tooth-status"
                name="update_tooth_status"
                class="w-full bg-bg border border-border/80 rounded-lg p-2 text-text outline-none focus:border-emerald-500 transition-colors"
              >
                <option value="">-- Не змінювати --</option>
                <option v-for="s in statuses" :key="s.id" :value="s.id">{{ s.label }}</option>
              </select>
            </div>
          </div>
          <div>
            <label for="treatment-diagnosis" class="block text-sm font-medium text-text/70 mb-1"
              >Діагноз *</label
            >
            <input
              v-model="form.diagnosis"
              id="treatment-diagnosis"
              name="diagnosis"
              type="text"
              class="w-full bg-bg border border-border/80 rounded-lg p-2 text-text outline-none focus:border-emerald-500 transition-colors"
            />
          </div>
          <div>
            <label for="treatment-complaints" class="block text-sm font-medium text-text/70 mb-1"
              >Скарги</label
            >
            <textarea
              v-model="form.complaints"
              id="treatment-complaints"
              name="complaints"
              rows="2"
              class="w-full bg-bg border border-border/80 rounded-lg p-2 text-text outline-none focus:border-emerald-500 transition-colors"
            ></textarea>
          </div>
          <div>
            <label for="treatment-treatment" class="block text-sm font-medium text-text/70 mb-1"
              >Лікування *</label
            >
            <textarea
              v-model="form.treatment"
              id="treatment-treatment"
              name="treatment"
              rows="3"
              class="w-full bg-bg border border-border/80 rounded-lg p-2 text-text outline-none focus:border-emerald-500 transition-colors"
            ></textarea>
          </div>
        </div>
      </div>

      <!-- Футер -->
      <div class="p-4 border-t border-border bg-bg flex justify-end gap-3">
        <button
          @click="$emit('close')"
          class="px-4 py-2 text-text/70 hover:text-text hover:bg-card/80 rounded-lg transition-colors"
        >
          Закрити
        </button>

        <button
          v-if="appointmentId && status !== 'done'"
          @click="updateAppointment"
          :disabled="appointmentSaving"
          class="px-4 py-2 bg-sky-600 text-text rounded-lg hover:bg-sky-500 disabled:opacity-50 font-medium shadow-lg shadow-sky-500/20 transition-all"
        >
          {{ appointmentSaving ? 'Збереження...' : 'Оновити запис' }}
        </button>

        <button
          v-if="status !== 'done' && patientId"
          @click="saveRecord"
          :disabled="loading"
          class="px-6 py-2 bg-emerald-600 text-text rounded-lg hover:bg-emerald-500 disabled:opacity-50 font-medium shadow-lg shadow-emerald-500/20 transition-all"
        >
          {{ loading ? 'Збереження...' : 'Завершити прийом' }}
        </button>
      </div>
    </div>
  </div>
</template>
