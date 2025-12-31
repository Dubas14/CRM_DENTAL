<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { debounce } from 'lodash-es'
import apiClient from '../services/apiClient'
import calendarApi from '../services/calendarApi'
import { useAuth } from '../composables/useAuth'
import { usePermissions } from '../composables/usePermissions'
import AppointmentModal from '../components/AppointmentModal.vue'
import PatientCreateModal from '../components/PatientCreateModal.vue'
import CalendarSlotPicker from '../components/CalendarSlotPicker.vue'
import WaitlistCandidatesPanel from '../components/WaitlistCandidatesPanel.vue'
import WaitlistRequestForm from '../components/WaitlistRequestForm.vue'
import AppointmentCancellationCard from '../components/AppointmentCancellationCard.vue'
import assistantApi from '../services/assistantApi'
import clinicApi from '../services/clinicApi'
import procedureApi from '../services/procedureApi'
import doctorApi from '../services/doctorApi'
import roomApi from '../services/roomApi'
import equipmentApi from '../services/equipmentApi'

const route = useRoute()
const router = useRouter()

// --- Модальні вікна ---
const showModal = ref(false)
const showCreatePatientModal = ref(false)
const selectedEvent = ref(null)

// ---- Стани розкладу ----
const doctors = ref([])
const procedures = ref([])
const rooms = ref([])
const equipments = ref([])
const assistants = ref([])
const clinics = ref([])

const selectedDoctorId = ref('')
const selectedProcedureId = ref('')
const selectedRoomId = ref('')
const selectedEquipmentId = ref('')
const selectedAssistantId = ref('')
const selectedClinicId = ref('')
const isFollowUp = ref(false)
const allowSoftConflicts = ref(false)

const selectedDate = ref(new Date().toISOString().slice(0, 10))

const loadingDoctors = ref(true)
const loadingProcedures = ref(false)
const loadingRooms = ref(false)
const loadingEquipments = ref(false)
const loadingAssistants = ref(false)

// Guards to prevent concurrent requests
const isFetchingDoctors = ref(false)
const isFetchingProcedures = ref(false)
const isFetchingRooms = ref(false)
const isFetchingEquipments = ref(false)
const isFetchingAssistants = ref(false)
const isFetchingAppointments = ref(false)
const isFetchingCalendarBlocks = ref(false)

const error = ref(null)

const appointments = ref([])
const loadingAppointments = ref(false)
const appointmentsError = ref(null)

const slotsRefreshToken = ref(0)
const cancellationTarget = ref(null)

// Request guard to prevent concurrent requests
let isRefreshing = false

// ---- Calendar blocks ----
const calendarBlocks = ref([])
const loadingCalendarBlocks = ref(false)
const calendarBlocksError = ref(null)
const calendarBlockSaving = ref(false)
const calendarBlockFormError = ref(null)
const editingCalendarBlockId = ref(null)

const calendarBlockForm = ref({
  type: 'personal_block',
  start: '',
  end: '',
  note: ''
})

const calendarBlockTypes = [
  {
    value: 'personal_block',
    label: 'Особистий блок',
    badge: 'bg-amber-500/10 text-amber-300 border-amber-500/30'
  },
  { value: 'work', label: 'Робочий час', badge: 'bg-sky-500/10 text-sky-300 border-sky-500/30' },
  {
    value: 'vacation',
    label: 'Відпустка',
    badge: 'bg-rose-500/10 text-rose-300 border-rose-500/30'
  },
  {
    value: 'room_block',
    label: 'Блок кабінету',
    badge: 'bg-card/20 text-text/80 border-border/30'
  },
  {
    value: 'equipment_booking',
    label: 'Бронювання обладнання',
    badge: 'bg-amber-500/10 text-amber-300 border-amber-500/30'
  }
]

// ---- Бронювання ----
const bookingSlot = ref(null)
const bookingLoading = ref(false)
const bookingError = ref(null)
const bookingSuccess = ref(false)

const bookingName = ref('')
const bookingPhone = ref('')
const bookingComment = ref('')
const selectedPatientForBooking = ref(null)
const useProcedureSteps = ref(false)
const stepBookings = ref([])

// Змінна для збереження ID запису, до якого треба прив'язати створеного пацієнта
const appointmentToLink = ref(null)

// Пошук
const searchResults = ref([])
const isSearching = ref(false)
let searchTimeout = null
let autoRefreshInterval = null

// ---- Auth ----
const { user } = useAuth()
const { isDoctor } = usePermissions()
const doctorProfile = computed(() => user.value?.doctor || null)

const defaultClinicId = computed(
  () =>
    user.value?.clinic_id ||
    user.value?.doctor?.clinic_id ||
    user.value?.doctor?.clinic?.id ||
    user.value?.clinics?.[0]?.clinic_id ||
    ''
)

const clinicId = computed(() => selectedClinicId.value || defaultClinicId.value || null)

const showClinicSelector = computed(
  () => clinics.value.length > 1 || user.value?.global_role === 'super_admin'
)

const canOpenWeeklySettings = computed(() => {
  if (!selectedDoctorId.value) return false
  if (['super_admin', 'clinic_admin'].includes(user.value?.global_role)) return true

  if (isDoctor.value && doctorProfile.value?.id) {
    return Number(selectedDoctorId.value) === Number(doctorProfile.value.id)
  }

  return false
})

const linkedPatientId = computed(() => {
  const raw = route.query.patient_id || route.query.patient
  const num = Number(raw)
  return Number.isFinite(num) && num > 0 ? num : null
})

const selectedDoctor = computed(() =>
  doctors.value.find((d) => d.id === Number(selectedDoctorId.value))
)
const selectedProcedure = computed(() =>
  procedures.value.find((p) => p.id === Number(selectedProcedureId.value))
)
const selectedProcedureSteps = computed(() => selectedProcedure.value?.steps || [])
const hasProcedureSteps = computed(() => selectedProcedureSteps.value.length > 0)

const requiresAssistant = computed(() => !!selectedProcedure.value?.requires_assistant)
const requiresRoom = computed(() => !!selectedProcedure.value?.requires_room)
const defaultRoomId = computed(() => selectedProcedure.value?.default_room_id || '')
const defaultEquipmentId = computed(() => selectedProcedure.value?.equipment_id || '')

const padTime = (value) => String(value).padStart(2, '0')

const buildDateTimeFromParts = (date, time) => {
  if (!date || !time) return null
  const [year, month, day] = date.split('-').map(Number)
  const [hour, minute] = time.split(':').map(Number)
  return new Date(year, month - 1, day, hour, minute)
}

const formatDateTimeParts = (date) => ({
  date: `${date.getFullYear()}-${padTime(date.getMonth() + 1)}-${padTime(date.getDate())}`,
  time: `${padTime(date.getHours())}:${padTime(date.getMinutes())}`
})

const initializeStepBookings = (baseDate, baseTime) => {
  if (!baseDate || !baseTime || !hasProcedureSteps.value) {
    stepBookings.value = []
    return
  }

  let current = buildDateTimeFromParts(baseDate, baseTime)
  stepBookings.value = selectedProcedureSteps.value.map((step) => {
    const entry = formatDateTimeParts(current)
    current = new Date(current.getTime() + (step.duration_minutes || 0) * 60000)
    return {
      procedure_step_id: step.id,
      name: step.name,
      duration_minutes: step.duration_minutes,
      date: entry.date,
      time: entry.time
    }
  })
}

const assistantLabel = (assistant) =>
  assistant.full_name ||
  assistant.name ||
  `${assistant.first_name || ''} ${assistant.last_name || ''}`.trim() ||
  `#${assistant.id}`

const selectedAssistantLabel = computed(() => {
  if (!selectedAssistantId.value) return ''
  const match = assistants.value.find(
    (assistant) => Number(assistant.id) === Number(selectedAssistantId.value)
  )
  return match ? assistantLabel(match) : `#${selectedAssistantId.value}`
})

const parseQueryNumber = (value) => {
  const num = Number(value)
  return Number.isFinite(num) && num > 0 ? num : null
}

const queryDoctorId = computed(() => parseQueryNumber(route.query.doctor || route.query.doctor_id))
const queryClinicId = computed(() => parseQueryNumber(route.query.clinic || route.query.clinic_id))
const queryDate = computed(() => (typeof route.query.date === 'string' ? route.query.date : null))

const applyQuerySelections = () => {
  let changed = false
  
  if (queryDate.value && selectedDate.value !== queryDate.value) {
    selectedDate.value = queryDate.value
    changed = true
  }

  if (
    queryClinicId.value &&
    clinics.value.some((clinic) => Number(clinic.id) === Number(queryClinicId.value))
  ) {
    if (selectedClinicId.value !== String(queryClinicId.value)) {
      selectedClinicId.value = String(queryClinicId.value)
      changed = true
    }
  }

  if (!isDoctor.value && queryDoctorId.value) {
    const doctorExists = doctors.value.some(
      (doctor) => Number(doctor.id) === Number(queryDoctorId.value)
    )
    if (doctorExists && selectedDoctorId.value !== String(queryDoctorId.value)) {
      selectedDoctorId.value = String(queryDoctorId.value)
      changed = true
    }
  }
  
  // Only trigger refresh if something actually changed
  // The watch on selectedDoctorId/selectedDate will handle the refresh with debounce
}

// Фіксація лікаря для доктора
const ensureOwnDoctorSelected = () => {
  if (isDoctor.value && doctorProfile.value?.id) {
    selectedDoctorId.value = String(doctorProfile.value.id)
  }
}
watch(
  () => doctorProfile.value?.id,
  () => {
    ensureOwnDoctorSelected()
  },
  { immediate: true }
)

// === ЛОГІКА ПОШУКУ ===
watch(bookingName, (newVal) => {
  if (selectedPatientForBooking.value && selectedPatientForBooking.value.full_name === newVal)
    return

  if (selectedPatientForBooking.value && selectedPatientForBooking.value.full_name !== newVal) {
    selectedPatientForBooking.value = null
  }

  clearTimeout(searchTimeout)

  if (!newVal || newVal.length < 2) {
    searchResults.value = []
    return
  }

  isSearching.value = true
  searchTimeout = setTimeout(async () => {
    try {
      const { data } = await apiClient.get('/patients', { params: { search: newVal } })
      searchResults.value = data.data || []
    } catch (e) {
      console.error(e)
    } finally {
      isSearching.value = false
    }
  }, 300)
})

const selectPatientFromSearch = (patient) => {
  selectedPatientForBooking.value = patient
  bookingName.value = patient.full_name
  bookingPhone.value = patient.phone || ''
  searchResults.value = []
}

// === РОБОТА З МОДАЛКАМИ ===
function openAppointment(appt) {
  selectedEvent.value = appt
  showModal.value = true
}

function onOpenCreatePatientFromModal(nameFromModal) {
  showModal.value = false
  appointmentToLink.value = selectedEvent.value
  bookingName.value = nameFromModal
  bookingPhone.value = ''
  showCreatePatientModal.value = true
}

async function onPatientCreated(newPatient) {
  if (appointmentToLink.value) {
    try {
      const apptId =
        appointmentToLink.value.id ||
        (appointmentToLink.value.extendedProps && appointmentToLink.value.extendedProps.id)

      if (!apptId) throw new Error('Не знайдено ID запису')

      await apiClient.put(`/appointments/${apptId}`, { patient_id: newPatient.id })

      alert(`Пацієнт ${newPatient.full_name} створений і прив'язаний до запису!`)
      await refreshScheduleData()

      const updatedAppt = appointments.value.find((a) => a.id === apptId)
      if (updatedAppt) openAppointment(updatedAppt)
    } catch (e) {
      alert(
        "Пацієнта створено, але не вдалося прив'язати до запису: " +
          (e.response?.data?.message || e.message)
      )
    } finally {
      appointmentToLink.value = null
    }
    return
  }

  selectedPatientForBooking.value = newPatient
  bookingName.value = newPatient.full_name
  bookingPhone.value = newPatient.phone || ''
}

function onRecordSaved() {
  loadAppointments()
}

function clearBookingForm() {
  selectedPatientForBooking.value = null
  appointmentToLink.value = null
  bookingName.value = ''
  bookingPhone.value = ''
  bookingComment.value = ''
  bookingError.value = null
  bookingSuccess.value = false
  searchResults.value = []
  stepBookings.value = []
}

const openWeeklySettings = () => {
  if (!canOpenWeeklySettings.value) return
  router.push({ name: 'doctor-weekly-schedule', params: { id: selectedDoctorId.value } })
}

// === ЗАВАНТАЖЕННЯ ДАНИХ ===
const preloadedPatient = ref(null)

const loadLinkedPatient = async () => {
  if (!linkedPatientId.value) return
  try {
    const { data } = await apiClient.get(`/patients/${linkedPatientId.value}`)
    preloadedPatient.value = data
  } catch (e) {
    console.error('Не вдалося завантажити дані пацієнта', e)
  }
}

const loadDoctors = async (clinicIdValue = clinicId.value) => {
  if (isFetchingDoctors.value) return
  isFetchingDoctors.value = true
  loadingDoctors.value = true
  try {
    if (!clinicIdValue) {
      doctors.value = []
      selectedDoctorId.value = ''
      return
    }
    const { data } = await doctorApi.list({ clinic_id: clinicIdValue })
    doctors.value = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
    if (isDoctor.value && doctorProfile.value?.id) {
      selectedDoctorId.value = String(doctorProfile.value.id)
    } else {
      const doctorExists =
        selectedDoctorId.value &&
        doctors.value.some((doctor) => Number(doctor.id) === Number(selectedDoctorId.value))
      if (!doctorExists) {
        selectedDoctorId.value = doctors.value.length > 0 ? String(doctors.value[0].id) : ''
      }
    }
    applyQuerySelections()
  } catch (e) {
    console.error(e)
    error.value = 'Помилка завантаження лікарів'
  } finally {
    loadingDoctors.value = false
    isFetchingDoctors.value = false
  }
}

const loadProcedures = async (clinicIdValue = clinicId.value) => {
  if (isFetchingProcedures.value) return
  isFetchingProcedures.value = true
  loadingProcedures.value = true
  try {
    if (!clinicIdValue) {
      procedures.value = []
      selectedProcedureId.value = ''
      return
    }
    const { data } = await procedureApi.list({ clinic_id: clinicIdValue })
    procedures.value = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
    if (
      selectedProcedureId.value &&
      !procedures.value.some((proc) => Number(proc.id) === Number(selectedProcedureId.value))
    ) {
      selectedProcedureId.value = ''
    }
  } catch (e) {
    console.error('Не вдалося завантажити процедури', e)
  } finally {
    loadingProcedures.value = false
    isFetchingProcedures.value = false
  }
}

const loadClinics = async () => {
  if (user.value?.global_role === 'super_admin') {
    const { data } = await clinicApi.list()
    clinics.value = data.data ?? data
  } else {
    const { data } = await clinicApi.listMine()
    clinics.value = (data.clinics ?? []).map((clinic) => ({
      id: clinic.clinic_id,
      name: clinic.clinic_name
    }))
  }

  if (!selectedClinicId.value) {
    selectedClinicId.value = defaultClinicId.value || clinics.value[0]?.id || ''
  }

  applyQuerySelections()
}

const loadRooms = async () => {
  if (isFetchingRooms.value) return
  rooms.value = []
  if (!clinicId.value) return
  isFetchingRooms.value = true
  loadingRooms.value = true
  try {
    const { data } = await roomApi.list({ clinic_id: clinicId.value })
    rooms.value = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
  } catch (e) {
    console.warn('Rooms endpoint недоступний або помилка', e)
  } finally {
    loadingRooms.value = false
    isFetchingRooms.value = false
  }
}

const loadEquipments = async () => {
  if (isFetchingEquipments.value) return
  equipments.value = []
  if (!clinicId.value) return
  isFetchingEquipments.value = true
  loadingEquipments.value = true
  try {
    // ⚠️ Якщо твій бек має інший шлях (наприклад /equipment) — зміни тут 1 рядок.
    const { data } = await equipmentApi.list({ clinic_id: clinicId.value })
    equipments.value = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
  } catch (e) {
    console.warn('Equipments endpoint недоступний або помилка', e)
  } finally {
    loadingEquipments.value = false
    isFetchingEquipments.value = false
  }
}

const loadAssistants = async () => {
  if (isFetchingAssistants.value) return
  assistants.value = []
  if (!clinicId.value) return
  isFetchingAssistants.value = true
  loadingAssistants.value = true
  try {
    const { data } = await assistantApi.list({ clinic_id: clinicId.value })
    assistants.value = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
  } catch (e) {
    console.warn('Assistants endpoint недоступний або помилка', e)
  } finally {
    loadingAssistants.value = false
    isFetchingAssistants.value = false
  }
}

const loadAppointments = async (silent = false) => {
  if (!selectedDoctorId.value || !selectedDate.value) return
  if (isFetchingAppointments.value) return

  isFetchingAppointments.value = true
  if (!silent) loadingAppointments.value = true
  appointmentsError.value = null

  try {
    const { data } = await calendarApi.getDoctorAppointments(selectedDoctorId.value, {
      date: selectedDate.value
    })
    appointments.value = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
  } catch (e) {
    console.error(e)
    if (!silent) appointmentsError.value = 'Не вдалося завантажити записи'
  } finally {
    if (!silent) loadingAppointments.value = false
    isFetchingAppointments.value = false
  }
}

const loadCalendarBlocks = async (silent = false) => {
  if (!selectedDoctorId.value || !selectedDate.value) return
  if (!clinicId.value) return
  if (isFetchingCalendarBlocks.value) return

  isFetchingCalendarBlocks.value = true
  if (!silent) loadingCalendarBlocks.value = true
  calendarBlocksError.value = null
  try {
    const { data } = await calendarApi.getCalendarBlocks({
      clinic_id: clinicId.value,
      from: selectedDate.value,
      to: selectedDate.value
    })
    calendarBlocks.value = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
  } catch (e) {
    console.error(e)
    if (!silent) calendarBlocksError.value = 'Не вдалося завантажити блоки.'
  } finally {
    if (!silent) loadingCalendarBlocks.value = false
    isFetchingCalendarBlocks.value = false
  }
}

const refreshScheduleData = async (silent = false) => {
  if (!selectedDoctorId.value || !selectedDate.value) return
  if (isRefreshing) return // Prevent concurrent requests
  isRefreshing = true
  try {
    slotsRefreshToken.value += 1
    await loadCalendarBlocks(silent)
    await loadAppointments(silent)
  } finally {
    isRefreshing = false
  }
}

// Debounced version to prevent too many requests
const debouncedRefreshScheduleData = debounce(refreshScheduleData, 500)

const selectSlot = (slot) => {
  bookingSlot.value = slot
  clearBookingForm()
  bookingSuccess.value = false
  bookingError.value = null

  if (slot.date) selectedDate.value = slot.date
  if (useProcedureSteps.value) {
    initializeStepBookings(slot.date || selectedDate.value, slot.start)
  }

  if (preloadedPatient.value) {
    selectedPatientForBooking.value = preloadedPatient.value
    bookingName.value = preloadedPatient.value.full_name
    bookingPhone.value = preloadedPatient.value.phone || ''
  }
}

const bookSelectedSlot = async () => {
  if (!bookingSlot.value || !selectedDoctorId.value || !selectedDate.value) return

  const finalName = selectedPatientForBooking.value
    ? selectedPatientForBooking.value.full_name
    : bookingName.value.trim()

  if (!finalName && !selectedPatientForBooking.value && !linkedPatientId.value) {
    bookingError.value = 'Вкажіть ім’я пацієнта або виберіть анкету'
    return
  }

  let commentText = bookingComment.value.trim()
  if (!selectedPatientForBooking.value && bookingPhone.value) {
    commentText += ` (Тел: ${bookingPhone.value})`
  }

  bookingLoading.value = true
  try {
    const basePayload = {
      doctor_id: Number(selectedDoctorId.value),
      patient_id: selectedPatientForBooking.value?.id || linkedPatientId.value || null,
      procedure_id: selectedProcedureId.value || null,
      room_id: selectedRoomId.value || null,
      equipment_id: selectedEquipmentId.value || null,
      assistant_id: selectedAssistantId.value ? Number(selectedAssistantId.value) : null,
      clinic_id: clinicId.value ? Number(clinicId.value) : undefined,
      is_follow_up: !!isFollowUp.value,
      allow_soft_conflicts: !!allowSoftConflicts.value,
      comment: selectedPatientForBooking.value
        ? commentText || null
        : `Пацієнт: ${finalName}. ${commentText}`,
      source: 'crm'
    }

    if (useProcedureSteps.value && hasProcedureSteps.value) {
      const invalidStep = stepBookings.value.find((step) => !step.date || !step.time)
      if (invalidStep) {
        bookingError.value = 'Заповніть дату та час для кожного етапу.'
        bookingLoading.value = false
        return
      }

      await calendarApi.createAppointmentSeries({
        ...basePayload,
        procedure_id: selectedProcedureId.value,
        steps: stepBookings.value.map((step) => ({
          procedure_step_id: step.procedure_step_id,
          date: step.date,
          time: step.time
        }))
      })
    } else {
      await calendarApi.createAppointment({
        ...basePayload,
        date: bookingSlot.value.date || selectedDate.value,
        time: bookingSlot.value.start
      })
    }

    bookingSuccess.value = true
    await refreshScheduleData()
    setTimeout(() => {
      bookingSlot.value = null
    }, 1200)
  } catch (e) {
    bookingError.value = e.response?.data?.message || 'Не вдалося створити запис'
  } finally {
    bookingLoading.value = false
  }
}

const fmtTime = (iso) => {
  if (!iso) return ''
  return new Date(iso).toLocaleTimeString('uk-UA', { hour: '2-digit', minute: '2-digit' })
}

const fmtTimeInput = (iso) => {
  if (!iso) return ''
  return new Date(iso).toLocaleTimeString('uk-UA', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false
  })
}

const getCalendarBlockType = (type) =>
  calendarBlockTypes.find((entry) => entry.value === type) || {
    value: type,
    label: type || 'Блок',
    badge: 'bg-card/20 text-text/80 border-border/30'
  }

const buildDateTime = (date, time) => {
  if (!date || !time) return null
  return `${date}T${time}:00`
}

const resetCalendarBlockForm = () => {
  calendarBlockForm.value = {
    type: 'personal_block',
    start: '',
    end: '',
    note: ''
  }
  calendarBlockFormError.value = null
  editingCalendarBlockId.value = null
}

const applyCalendarBlockToForm = (block) => {
  if (!block) return
  editingCalendarBlockId.value = block.id
  calendarBlockForm.value = {
    type: block.type || 'personal_block',
    start: fmtTimeInput(block.start_at),
    end: fmtTimeInput(block.end_at),
    note: block.note || ''
  }
  calendarBlockFormError.value = null
}

const saveCalendarBlock = async () => {
  if (!selectedDoctorId.value || !selectedDate.value) return
  calendarBlockFormError.value = null

  if (
    !calendarBlockForm.value.type ||
    !calendarBlockForm.value.start ||
    !calendarBlockForm.value.end
  ) {
    calendarBlockFormError.value = 'Вкажіть тип блоку та час.'
    return
  }

  const startAt = buildDateTime(selectedDate.value, calendarBlockForm.value.start)
  const endAt = buildDateTime(selectedDate.value, calendarBlockForm.value.end)

  if (!startAt || !endAt) {
    calendarBlockFormError.value = 'Невірний час блоку.'
    return
  }

  calendarBlockSaving.value = true
  try {
    const payload = {
      doctor_id: Number(selectedDoctorId.value),
      clinic_id: clinicId.value ? Number(clinicId.value) : undefined,
      type: calendarBlockForm.value.type,
      start_at: startAt,
      end_at: endAt,
      note: calendarBlockForm.value.note?.trim() || null
    }

    if (editingCalendarBlockId.value) {
      await calendarApi.updateCalendarBlock(editingCalendarBlockId.value, payload)
    } else {
      await calendarApi.createCalendarBlock(payload)
    }

    resetCalendarBlockForm()
    await refreshScheduleData()
  } catch (e) {
    console.error(e)
    calendarBlockFormError.value = e.response?.data?.message || 'Не вдалося зберегти блок.'
  } finally {
    calendarBlockSaving.value = false
  }
}

const deleteCalendarBlock = async (block) => {
  if (!block?.id) return
  const confirmed = window.confirm('Видалити цей блок?')
  if (!confirmed) return
  try {
    await calendarApi.deleteCalendarBlock(block.id)
    if (editingCalendarBlockId.value === block.id) {
      resetCalendarBlockForm()
    }
    await refreshScheduleData()
  } catch (e) {
    console.error(e)
    calendarBlocksError.value = e.response?.data?.message || 'Не вдалося видалити блок.'
  }
}

const validatePhoneInput = (event) => {
  const val = event.target.value.replace(/[^0-9+\-() ]/g, '')
  bookingPhone.value = val
  event.target.value = val
}

const openCancellation = (appt) => {
  cancellationTarget.value = appt
}

const onAppointmentCancelled = () => {
  cancellationTarget.value = null
  refreshScheduleData()
}

// === LIFECYCLE ===
onMounted(async () => {
  await loadClinics()
  await Promise.all([
    loadDoctors(),
    loadProcedures(),
    loadRooms(),
    loadEquipments(),
    loadAssistants()
  ])
  await loadLinkedPatient()
  await refreshScheduleData()

  autoRefreshInterval = setInterval(() => {
    if (!showModal.value && !showCreatePatientModal.value && !bookingSlot.value) {
      refreshScheduleData(true)
    }
  }, 15000)
})

watch(
  () => route.query,
  () => {
    applyQuerySelections()
  },
  { deep: true }
)

watch(
  () => [selectedDoctorId.value, selectedDate.value],
  () => {
    if (selectedDoctorId.value && selectedDate.value) {
      debouncedRefreshScheduleData()
    }
  }
)

watch(clinicId, () => {
  loadDoctors()
  loadProcedures()
  loadRooms()
  loadEquipments()
  loadAssistants()
})

watch(selectedClinicId, () => {
  selectedRoomId.value = ''
  selectedEquipmentId.value = ''
  selectedAssistantId.value = ''
  selectedProcedureId.value = ''
})

watch(selectedProcedureId, () => {
  if (!hasProcedureSteps.value) {
    useProcedureSteps.value = false
    stepBookings.value = []
  } else if (useProcedureSteps.value && bookingSlot.value) {
    initializeStepBookings(bookingSlot.value.date || selectedDate.value, bookingSlot.value.start)
  }
})

watch(useProcedureSteps, (enabled) => {
  if (enabled && bookingSlot.value) {
    initializeStepBookings(bookingSlot.value.date || selectedDate.value, bookingSlot.value.start)
  }
  if (!enabled) {
    stepBookings.value = []
  }
})

watch([selectedProcedureId, assistants], () => {
  if (defaultRoomId.value && !selectedRoomId.value) {
    selectedRoomId.value = defaultRoomId.value
  }
  if (defaultEquipmentId.value && !selectedEquipmentId.value) {
    selectedEquipmentId.value = defaultEquipmentId.value
  }
  if (!requiresAssistant.value) {
    selectedAssistantId.value = ''
  } else if (!selectedAssistantId.value && assistants.value.length === 1) {
    selectedAssistantId.value = assistants.value[0].id
  }
})

onUnmounted(() => {
  if (autoRefreshInterval) clearInterval(autoRefreshInterval)
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold">Розклад та слот-менеджмент</h1>
        <p class="text-sm text-text/70">
          Бронювання, скасування та робота зі списком очікування в одному місці.
        </p>
      </div>
      <div class="flex items-center gap-3">
        <div class="text-xs text-text/60 animate-pulse">● Дані оновлюються автоматично</div>

        <button
          v-if="canOpenWeeklySettings"
          type="button"
          class="px-3 py-2 rounded-lg border border-border/80 text-sm text-text/90 hover:bg-card/80"
          @click="openWeeklySettings"
        >
          Налаштувати тижневий розклад
        </button>
      </div>
    </div>

    <div
      class="flex flex-wrap items-end gap-4 rounded-xl bg-card/60 shadow-sm shadow-black/10 dark:shadow-black/40 p-4"
    >
      <div v-if="showClinicSelector" class="flex flex-col gap-1 min-w-[200px]">
        <label
          for="doctor-schedule-clinic"
          class="text-xs uppercase tracking-wide text-text/70"
        >
          Клініка
        </label>
        <select
          v-model="selectedClinicId"
          id="doctor-schedule-clinic"
          name="clinic_id"
          class="rounded-lg bg-card border border-border/80 px-3 py-2 text-sm"
        >
          <option value="" disabled>Оберіть клініку</option>
          <option v-for="clinic in clinics" :key="clinic.id" :value="clinic.id">
            {{ clinic.name }}
          </option>
        </select>
      </div>

      <div v-if="!isDoctor" class="flex flex-col gap-1">
        <label
          for="doctor-schedule-doctor"
          class="text-xs uppercase tracking-wide text-text/70"
        >
          Лікар
        </label>
        <select
          v-model="selectedDoctorId"
          id="doctor-schedule-doctor"
          name="doctor_id"
          class="rounded-lg bg-card border border-border/80 px-3 py-2 text-sm"
          :disabled="loadingDoctors"
        >
          <option value="" disabled>Оберіть лікаря</option>
          <option v-for="doctor in doctors" :key="doctor.id" :value="doctor.id">
            {{ doctor.full_name }}
          </option>
        </select>
      </div>
      <div v-else class="flex flex-col gap-1">
        <span class="text-xs uppercase tracking-wide text-text/70">Лікар</span>
        <div
          class="rounded-lg bg-card shadow-sm shadow-black/10 dark:shadow-black/40 px-3 py-2 text-sm text-text/90"
        >
          {{ doctorProfile?.full_name || selectedDoctor?.full_name || '—' }}
        </div>
      </div>

      <div class="flex flex-col gap-1">
        <label
          for="doctor-schedule-date"
          class="text-xs uppercase tracking-wide text-text/70"
        >
          Дата
        </label>
        <input
          v-model="selectedDate"
          id="doctor-schedule-date"
          name="date"
          type="date"
          class="rounded-lg bg-card border border-border/80 px-3 py-2 text-sm"
        />
      </div>

      <div class="flex flex-col gap-1 min-w-[220px]">
        <label
          for="doctor-schedule-procedure"
          class="text-xs uppercase tracking-wide text-text/70"
        >
          Процедура
        </label>
        <select
          v-model="selectedProcedureId"
          id="doctor-schedule-procedure"
          name="procedure_id"
          class="rounded-lg bg-card border border-border/80 px-3 py-2 text-sm"
          :disabled="loadingProcedures"
        >
          <option value="">Без процедури</option>
          <option v-for="procedure in procedures" :key="procedure.id" :value="procedure.id">
            {{ procedure.name }}
          </option>
        </select>
      </div>

      <div class="flex flex-col gap-1 min-w-[200px]">
        <label
          for="doctor-schedule-room"
          class="text-xs uppercase tracking-wide text-text/70"
        >
          Кабінет<span v-if="requiresRoom" class="text-rose-400"> *</span>
        </label>
        <select
          v-model="selectedRoomId"
          id="doctor-schedule-room"
          name="room_id"
          class="rounded-lg bg-card border border-border/80 px-3 py-2 text-sm"
          :disabled="loadingRooms || !rooms.length"
        >
          <option value="">Будь-який</option>
          <option v-for="room in rooms" :key="room.id" :value="room.id">
            {{ room.name }}
          </option>
        </select>
        <span v-if="requiresRoom && !rooms.length" class="text-[10px] text-rose-400"
          >Немає кабінетів для вибору.</span
        >
      </div>

      <div class="flex flex-col gap-1 min-w-[220px]">
        <label
          for="doctor-schedule-equipment"
          class="text-xs uppercase tracking-wide text-text/70"
        >
          Обладнання
        </label>
        <select
          v-model="selectedEquipmentId"
          id="doctor-schedule-equipment"
          name="equipment_id"
          class="rounded-lg bg-card border border-border/80 px-3 py-2 text-sm"
          :disabled="loadingEquipments || !equipments.length"
        >
          <option value="">Будь-яке</option>
          <option v-for="eq in equipments" :key="eq.id" :value="eq.id">
            {{ eq.name }}
          </option>
        </select>
      </div>

      <div class="flex flex-col gap-1 min-w-[220px]">
        <label
          for="doctor-schedule-assistant"
          class="text-xs uppercase tracking-wide text-text/70"
        >
          Асистент<span v-if="requiresAssistant" class="text-rose-400"> *</span>
        </label>
        <select
          v-model="selectedAssistantId"
          id="doctor-schedule-assistant"
          name="assistant_id"
          class="rounded-lg bg-card border border-border/80 px-3 py-2 text-sm"
          :disabled="loadingAssistants || !assistants.length"
        >
          <option value="">Без асистента</option>
          <option v-for="assistant in assistants" :key="assistant.id" :value="assistant.id">
            {{ assistantLabel(assistant) }}
          </option>
        </select>
        <span v-if="requiresAssistant && !assistants.length" class="text-[10px] text-rose-400">
          Додайте асистента в налаштуваннях клініки.
        </span>
      </div>

      <div class="flex flex-col gap-2">
        <label class="flex items-center gap-2 text-sm text-text/80">
          <input
            v-model="isFollowUp"
            name="is_follow_up"
            type="checkbox"
            class="accent-emerald-500"
          />
          <span>Повторний</span>
        </label>
        <label class="flex items-center gap-2 text-sm text-text/80">
          <input
            v-model="allowSoftConflicts"
            name="allow_soft_conflicts"
            type="checkbox"
            class="accent-emerald-500"
          />
          <span>Дозволити soft</span>
        </label>
      </div>

      <button
        type="button"
        class="ml-auto px-3 py-2 rounded-lg border border-border/80 text-sm hover:bg-card/80"
        @click="refreshScheduleData"
      >
        Оновити
      </button>
    </div>

    <div v-if="error" class="text-red-400">❌ {{ error }}</div>

    <div class="grid xl:grid-cols-3 gap-6">
      <div class="xl:col-span-2 space-y-4">
        <CalendarSlotPicker
          v-if="selectedDoctorId"
          :doctor-id="selectedDoctorId"
          :procedure-id="selectedProcedureId"
          :room-id="selectedRoomId"
          :equipment-id="selectedEquipmentId"
          :assistant-id="selectedAssistantId"
          :assistants="assistants"
          :date="selectedDate"
          :refresh-token="slotsRefreshToken"
          @select-slot="selectSlot"
        />

        <div
          class="rounded-xl bg-card shadow-xl shadow-black/10 dark:shadow-black/40 p-5 space-y-4 relative"
        >
          <div class="flex justify-between items-center">
            <div>
              <h3 class="text-emerald-400 font-bold text-lg">Бронювання слота</h3>
              <p class="text-xs text-text/60">
                Оберіть слот, вкажіть пацієнта, процедуру та коментар.
              </p>
            </div>
            <button @click="bookingSlot = null" class="text-text/60 hover:text-text">✕</button>
          </div>

          <div
            v-if="!bookingSlot"
            class="text-sm text-text/70 bg-card/50 shadow-sm shadow-black/10 dark:shadow-black/40 rounded-lg p-3"
          >
            Спершу оберіть слот у календарі, щоб створити запис.
          </div>

          <div v-else class="space-y-4">
            <div class="flex flex-wrap items-center gap-3">
              <span
                class="text-sm bg-emerald-500/10 text-emerald-300 px-3 py-1 rounded border border-emerald-500/30"
              >
                {{ bookingSlot.date || selectedDate }} • {{ bookingSlot.start }} –
                {{ bookingSlot.end }}
              </span>
              <span
                v-if="selectedProcedureId"
                class="text-xs bg-card/80 px-2 py-1 rounded text-text/80"
              >
                {{ procedures.find((p) => p.id === Number(selectedProcedureId))?.name }}
              </span>
              <span v-if="selectedRoomId" class="text-xs bg-card/80 px-2 py-1 rounded text-text/80">
                Кабінет:
                {{ rooms.find((r) => r.id === Number(selectedRoomId))?.name || selectedRoomId }}
              </span>
              <span
                v-if="selectedEquipmentId"
                class="text-xs bg-card/80 px-2 py-1 rounded text-text/80"
              >
                Обладн.:
                {{
                  equipments.find((e) => e.id === Number(selectedEquipmentId))?.name ||
                  selectedEquipmentId
                }}
              </span>
              <span
                v-if="selectedAssistantId"
                class="text-xs bg-card/80 px-2 py-1 rounded text-text/80"
              >
                Асистент: {{ selectedAssistantLabel }}
              </span>
              <span
                v-if="isFollowUp"
                class="text-xs bg-emerald-900/60 px-2 py-1 rounded text-emerald-300"
              >
                Повторний
              </span>
            </div>

            <div v-if="hasProcedureSteps" class="flex flex-wrap items-center gap-3">
              <label class="flex items-center gap-2 text-sm text-text/80">
                <input v-model="useProcedureSteps" type="checkbox" class="accent-emerald-500" />
                <span>Створити серію по етапах</span>
              </label>
              <button
                v-if="useProcedureSteps && bookingSlot"
                type="button"
                class="text-xs text-emerald-300 hover:text-emerald-200"
                @click="initializeStepBookings(bookingSlot.date || selectedDate, bookingSlot.start)"
              >
                Заповнити за вибраним слотом
              </button>
            </div>

            <div
              v-if="useProcedureSteps && hasProcedureSteps"
              class="rounded-lg border border-border bg-bg/60 p-3 space-y-2"
            >
              <div class="text-xs uppercase text-text/70">Розклад етапів (можна змінювати)</div>
              <div
                v-for="(step, index) in stepBookings"
                :key="`step-booking-${index}`"
                class="grid md:grid-cols-4 gap-2 items-center"
              >
                <div class="text-sm text-text/90 md:col-span-2">
                  {{ step.name }} • {{ step.duration_minutes }} хв
                </div>
                <input
                  v-model="step.date"
                  type="date"
                  class="rounded-lg bg-card border border-border/80 px-2 py-1 text-sm"
                />
                <input
                  v-model="step.time"
                  type="time"
                  class="rounded-lg bg-card border border-border/80 px-2 py-1 text-sm"
                />
              </div>
            </div>

            <div
              v-if="bookingSuccess"
              class="bg-emerald-900/30 text-emerald-400 p-3 rounded border border-emerald-500/30"
            >
              ✅ Запис успішно створено!
            </div>
            <div
              v-if="bookingError"
              class="bg-red-900/30 text-red-400 p-3 rounded border border-red-500/30"
            >
              {{ bookingError }}
            </div>

            <div
              v-if="selectedPatientForBooking"
              class="flex items-center justify-between bg-blue-900/20 border border-blue-500/30 p-3 rounded-lg"
            >
              <div>
                <span class="block text-xs text-blue-400 uppercase font-bold mb-1"
                  >Обраний пацієнт</span
                >
                <div class="text-text text-lg font-bold">
                  {{ selectedPatientForBooking.full_name }}
                </div>
                <div class="text-text/70 text-sm">{{ selectedPatientForBooking.phone }}</div>
              </div>
              <button
                @click="
                  selectedPatientForBooking = null;
                  bookingName = ''
                "
                class="px-3 py-1 bg-card/80 hover:bg-card/70 text-text/90 text-xs rounded border border-border/70 transition-colors"
              >
                Змінити
              </button>
            </div>

            <div v-else class="grid md:grid-cols-2 gap-4 relative">
              <div class="relative">
                <label class="block text-xs text-text/70 mb-1 uppercase"
                  >Пошук пацієнта (Ім'я або Телефон)</label
                >
                <input
                  v-model="bookingName"
                  type="text"
                  placeholder="Почніть вводити..."
                  class="w-full bg-bg border border-border/80 rounded p-2 text-text focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none"
                />

                <div
                  v-if="searchResults.length > 0 && !selectedPatientForBooking"
                  class="absolute z-50 w-full bg-card/80 border border-border/70 rounded-lg shadow-xl mt-1 max-h-48 overflow-y-auto"
                >
                  <ul>
                    <li
                      v-for="p in searchResults"
                      :key="p.id"
                      @click="selectPatientFromSearch(p)"
                      class="px-3 py-2 hover:bg-card/70 cursor-pointer text-sm text-text/90 border-b border-border/80 last:border-0"
                    >
                      <div class="font-bold text-emerald-400">{{ p.full_name }}</div>
                      <div class="text-xs text-text/70">{{ p.phone }} | {{ p.birth_date }}</div>
                    </li>
                  </ul>
                </div>

                <div v-if="bookingName.length > 2 && !selectedPatientForBooking" class="mt-2">
                  <button
                    @click="showCreatePatientModal = true"
                    class="w-full flex items-center justify-center gap-2 text-xs bg-card/80 hover:bg-emerald-900/30 text-emerald-400 border border-emerald-500/30 border-dashed px-3 py-2 rounded transition-all font-semibold"
                  >
                    <span>+</span> Створити нову анкету для "{{ bookingName }}"
                  </button>
                </div>
              </div>

              <div>
                <label class="block text-xs text-text/70 mb-1 uppercase">Телефон (для гостя)</label>
                <input
                  v-model="bookingPhone"
                  @input="validatePhoneInput"
                  type="text"
                  placeholder="+380..."
                  class="w-full bg-bg border border-border/80 rounded p-2 text-text"
                />
              </div>
            </div>

            <div>
              <label class="block text-xs text-text/70 mb-1 uppercase">Коментар</label>
              <textarea
                v-model="bookingComment"
                placeholder="Скарги, деталі..."
                class="w-full bg-bg border border-border/80 rounded p-2 text-text h-20"
              ></textarea>
            </div>

            <div class="flex justify-end gap-3 pt-2">
              <button
                @click="bookingSlot = null"
                class="text-text/70 hover:text-text px-3 py-2 text-sm"
              >
                Скасувати
              </button>
              <button
                @click="bookSelectedSlot"
                class="bg-emerald-600 hover:bg-emerald-500 text-text px-6 py-2 rounded font-medium shadow-lg shadow-emerald-900/50"
                :disabled="bookingLoading"
              >
                {{ bookingLoading ? 'Створення...' : 'Підтвердити запис' }}
              </button>
            </div>
          </div>
        </div>

        <div class="mt-4 space-y-3">
          <div class="flex items-center justify-between">
            <h3 class="text-text/70 text-xs font-bold uppercase tracking-wider">
              Записи на цей день
            </h3>
            <span v-if="loadingAppointments" class="text-xs text-text/60 animate-pulse"
              >Оновлення...</span
            >
          </div>

          <div v-if="appointmentsError" class="text-red-400 text-sm">{{ appointmentsError }}</div>

          <div v-if="appointments.length === 0" class="text-text/60 text-sm italic">
            Немає записів.
          </div>

          <div
            v-else
            class="overflow-hidden rounded-xl bg-card/40 shadow-sm shadow-black/10 dark:shadow-black/40"
          >
            <table class="min-w-full text-sm">
              <thead class="bg-card/80 text-text/70 text-xs uppercase">
                <tr>
                  <th class="px-4 py-3 text-left font-medium">Час</th>
                  <th class="px-4 py-3 text-left font-medium">Пацієнт</th>
                  <th class="px-4 py-3 text-left font-medium">Деталі</th>
                  <th class="px-4 py-3 text-left font-medium">Статус</th>
                  <th class="px-4 py-3 text-left font-medium">Дії</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800">
                <tr
                  v-for="a in appointments"
                  :key="a.id"
                  class="hover:bg-card/50 transition-colors group"
                >
                  <td class="px-4 py-3 text-emerald-400 font-bold font-mono">
                    {{ fmtTime(a.start_at) }}
                  </td>

                  <td class="px-4 py-3 text-text/90">
                    <div class="font-medium">
                      {{ a.patient?.full_name || a.patient_name || a.comment || '—' }}
                    </div>
                    <div
                      v-if="a.patient_id"
                      class="text-[10px] text-blue-400 inline-flex items-center gap-1 mt-0.5"
                    >
                      <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span> АНКЕТА Є
                    </div>
                    <div
                      v-else
                      class="text-[10px] text-amber-500 inline-flex items-center gap-1 mt-0.5"
                    >
                      ⚠️ Гість
                    </div>
                  </td>

                  <td class="px-4 py-3 text-xs text-text/70">
                    <div>
                      Процедура: <span class="text-text/90">{{ a.procedure?.name || '—' }}</span>
                    </div>
                    <div v-if="a.procedure_step">
                      Етап: <span class="text-text/90">{{ a.procedure_step.name }}</span>
                    </div>
                    <div v-if="a.room">
                      Кабінет: <span class="text-sky-300">{{ a.room.name }}</span>
                    </div>
                    <div v-if="a.equipment">
                      Обладн.: <span class="text-amber-300">{{ a.equipment.name }}</span>
                    </div>
                    <div v-if="a.assistant">
                      Асистент:
                      <span class="text-indigo-300">{{
                        a.assistant.full_name || a.assistant.name || a.assistant.id
                      }}</span>
                    </div>
                    <div v-if="a.is_follow_up" class="text-emerald-300">↻ Повторний</div>
                  </td>

                  <td class="px-4 py-3">
                    <span
                      v-if="a.status === 'done'"
                      class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20"
                    >
                      Виконано
                    </span>
                    <span
                      v-else
                      class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-card/50 text-text/80 border border-border/70"
                    >
                      {{ a.status || 'planned' }}
                    </span>
                  </td>

                  <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                      <button
                        class="text-xs text-text/80 hover:text-text underline"
                        @click="openAppointment(a)"
                      >
                        Деталі
                      </button>
                      <button
                        class="text-xs text-red-400 hover:text-red-300"
                        @click="openCancellation(a)"
                      >
                        Скасувати
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <AppointmentCancellationCard
          v-if="cancellationTarget"
          :appointment="cancellationTarget"
          @cancelled="onAppointmentCancelled"
          @close="cancellationTarget = null"
        />

        <AppointmentModal
          :is-open="showModal"
          :appointment="selectedEvent"
          @close="showModal = false"
          @saved="onRecordSaved"
          @create-patient="onOpenCreatePatientFromModal"
        />

        <PatientCreateModal
          :is-open="showCreatePatientModal"
          :initial-name="bookingName"
          :initial-phone="bookingPhone"
          @close="showCreatePatientModal = false"
          @created="onPatientCreated"
        />
      </div>

      <div class="space-y-4">
        <div
          class="rounded-xl bg-card/60 shadow-sm shadow-black/10 dark:shadow-black/40 p-4 space-y-4"
        >
          <div class="flex items-start justify-between gap-3">
            <div>
              <h3 class="text-sm font-semibold text-emerald-300">Блоки календаря</h3>
              <p class="text-xs text-text/60">Додавайте паузи, зустрічі чи інші блоки.</p>
            </div>
            <button
              type="button"
              class="text-xs text-text/80 border border-border/80 rounded-lg px-2 py-1 hover:bg-card/80"
              @click="resetCalendarBlockForm"
            >
              Очистити
            </button>
          </div>

          <div class="grid gap-3">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <label class="space-y-1 block">
                <span class="text-[10px] uppercase tracking-wide text-text/70">Тип блоку</span>
                <select
                  v-model="calendarBlockForm.type"
                  class="w-full rounded-lg bg-card border border-border/80 px-3 py-2 text-sm"
                >
                  <option v-for="type in calendarBlockTypes" :key="type.value" :value="type.value">
                    {{ type.label }}
                  </option>
                </select>
              </label>

              <label class="space-y-1 block">
                <span class="text-[10px] uppercase tracking-wide text-text/70"
                  >Нотатка (optional)</span
                >
                <input
                  v-model="calendarBlockForm.note"
                  type="text"
                  placeholder="Коротка примітка"
                  class="w-full rounded-lg bg-card border border-border/80 px-3 py-2 text-sm"
                />
              </label>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <label class="space-y-1 block">
                <span class="text-[10px] uppercase tracking-wide text-text/70">Start</span>
                <input
                  v-model="calendarBlockForm.start"
                  type="time"
                  class="w-full rounded-lg bg-card border border-border/80 px-3 py-2 text-sm"
                />
              </label>
              <label class="space-y-1 block">
                <span class="text-[10px] uppercase tracking-wide text-text/70">End</span>
                <input
                  v-model="calendarBlockForm.end"
                  type="time"
                  class="w-full rounded-lg bg-card border border-border/80 px-3 py-2 text-sm"
                />
              </label>
            </div>

            <div v-if="calendarBlockFormError" class="text-xs text-rose-400">
              {{ calendarBlockFormError }}
            </div>

            <div class="flex flex-wrap items-center gap-2">
              <button
                type="button"
                class="px-3 py-2 rounded-lg bg-emerald-600 text-text text-sm hover:bg-emerald-500 disabled:opacity-60"
                :disabled="calendarBlockSaving"
                @click="saveCalendarBlock"
              >
                {{
                  calendarBlockSaving
                    ? 'Збереження...'
                    : editingCalendarBlockId
                      ? 'Оновити блок'
                      : 'Створити блок'
                }}
              </button>
              <span v-if="editingCalendarBlockId" class="text-xs text-text/70"
                >Редагування активне</span
              >
            </div>
          </div>

          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <h4 class="text-xs uppercase tracking-wide text-text/60">
                Блоки на {{ selectedDate }}
              </h4>
              <span v-if="loadingCalendarBlocks" class="text-xs text-text/60 animate-pulse"
                >Оновлення...</span
              >
            </div>

            <div v-if="calendarBlocksError" class="text-xs text-rose-400">
              {{ calendarBlocksError }}
            </div>

            <div
              v-if="calendarBlocks.length === 0 && !loadingCalendarBlocks"
              class="text-xs text-text/60 italic"
            >
              Немає блоків на цю дату.
            </div>

            <div v-else class="space-y-2">
              <div
                v-for="block in calendarBlocks"
                :key="block.id"
                class="flex items-start justify-between gap-3 rounded-lg border border-border bg-bg/60 px-3 py-2"
              >
                <div class="space-y-1">
                  <span
                    class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold border"
                    :class="getCalendarBlockType(block.type).badge"
                  >
                    {{ getCalendarBlockType(block.type).label }}
                  </span>
                  <div class="text-sm text-text/90">
                    {{ fmtTime(block.start_at) }} – {{ fmtTime(block.end_at) }}
                  </div>
                  <div v-if="block.note" class="text-xs text-text/60">
                    {{ block.note }}
                  </div>
                </div>
                <div class="flex flex-col items-end gap-2 text-xs">
                  <button
                    class="text-text/80 hover:text-text"
                    type="button"
                    @click="applyCalendarBlockToForm(block)"
                  >
                    Редагувати
                  </button>
                  <button
                    class="text-rose-400 hover:text-rose-300"
                    type="button"
                    @click="deleteCalendarBlock(block)"
                  >
                    Видалити
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <WaitlistCandidatesPanel
          v-if="clinicId"
          :clinic-id="clinicId"
          :doctor-id="selectedDoctorId"
          :procedure-id="selectedProcedureId"
          :preferred-date="selectedDate"
          @booked="refreshScheduleData"
          @refresh="refreshScheduleData"
        />
        <WaitlistRequestForm
          v-if="clinicId"
          :clinic-id="clinicId"
          :default-doctor-id="selectedDoctorId"
          :default-procedure-id="selectedProcedureId"
          @created="refreshScheduleData"
        />
        <div
          v-else
          class="bg-card/60 rounded-xl shadow-sm shadow-black/10 dark:shadow-black/40 p-4 text-sm text-text/70"
        >
          Потрібен clinic_id для роботи зі списком очікування.
        </div>
      </div>
    </div>
  </div>
</template>
