<template>
  <div
      class="time-picker-wrapper"
      :class="{ 'is-disabled': disabled }"
  >
    <div ref="pickerRef" class="time-picker-container"></div>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import TimePicker from 'tui-time-picker'
import 'tui-time-picker/dist/tui-time-picker.css'

/**
 * PROPS
 */
const props = defineProps({
  modelValue: {
    type: String,
    default: null, // 'HH:mm'
  },
  minuteStep: {
    type: Number,
    default: 5,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  // резерв на майбутнє
  format: {
    type: String,
    default: 'HH:mm',
  },
})

/**
 * EMITS
 */
const emit = defineEmits([
  'update:modelValue',
  'change',
])

/**
 * REFS / STATE
 */
const pickerRef = ref(null)
let pickerInstance = null

// прапорець для захисту від update-loop
let isInternalUpdate = false

/**
 * CONSTANTS
 */
const DEFAULT_HOUR = 9
const DEFAULT_MINUTE = 0

/**
 * UTILS
 */
const pad = (val) => String(val).padStart(2, '0')

const parseTime = (value) => {
  if (!value || typeof value !== 'string') {
    return { hour: DEFAULT_HOUR, minute: DEFAULT_MINUTE }
  }

  const parts = value.split(':').map(Number)
  if (parts.length !== 2 || parts.some(isNaN)) {
    return { hour: DEFAULT_HOUR, minute: DEFAULT_MINUTE }
  }

  return {
    hour: parts[0],
    minute: parts[1],
  }
}

const setPickerTime = (value) => {
  if (!pickerInstance) return

  const { hour, minute } = parseTime(value)
  pickerInstance.setTime(hour, minute)
}

/**
 * MOUNT
 */
onMounted(() => {
  const { hour, minute } = parseTime(props.modelValue)

  pickerInstance = new TimePicker(pickerRef.value, {
    initialHour: hour,
    initialMinute: minute,
    inputType: 'spinbox',
    showMeridiem: false,        // ❌ AM/PM
    minuteStep: props.minuteStep,
    usageStatistics: false,
  })

  // 🔥 Обмеження робочого часу (медицина)
  if (typeof pickerInstance.setHourRange === 'function') {
    pickerInstance.setHourRange(8, 22)
  }

  // 🔁 Слухаємо зміни з picker
  pickerInstance.on('change', (evt) => {
    const newValue = `${pad(evt.hour)}:${pad(evt.minute)}`

    if (newValue !== props.modelValue) {
      isInternalUpdate = true
      emit('update:modelValue', newValue)
      emit('change', newValue)
    }
  })

  // disabled on init
  pickerInstance.setDisabled(props.disabled)
})

/**
 * WATCHERS
 */

// синхронізація з v-model (ЗОВНІ → В СЕРЕДИНУ)
watch(
    () => props.modelValue,
    (newValue) => {
      if (!pickerInstance) return

      // якщо це наш власний emit — ігноруємо
      if (isInternalUpdate) {
        isInternalUpdate = false
        return
      }

      setPickerTime(newValue)
    }
)

// disabled state
watch(
    () => props.disabled,
    (isDisabled) => {
      if (!pickerInstance) return
      pickerInstance.setDisabled(isDisabled)
    }
)

/**
 * UNMOUNT
 */
onBeforeUnmount(() => {
  if (pickerInstance) {
    pickerInstance.destroy()
    pickerInstance = null
  }
})
</script>

<style scoped>
.time-picker-wrapper {
  @apply w-full;
}

.time-picker-wrapper.is-disabled {
  @apply opacity-60 cursor-not-allowed;
}

/* ---- Toast UI deep styles ---- */

.time-picker-wrapper :deep(.tui-timepicker) {
  @apply w-full border-0 !important;
}

.time-picker-wrapper :deep(.tui-timepicker-input) {
  @apply
  w-full
  rounded-lg
  border
  border-slate-700
  bg-slate-900
  px-3
  py-2
  text-sm
  text-white
  shadow-sm
  transition-colors
  focus:border-emerald-500
  focus:outline-none
  !important;
  height: auto !important;
}

.time-picker-wrapper :deep(input) {
  @apply bg-transparent text-white border-0 p-0 m-0 !important;
}

.time-picker-wrapper :deep(.tui-timepicker-btn) {
  @apply
  border
  border-slate-600
  bg-slate-800
  text-slate-300
  hover:bg-slate-700
  !important;
}
</style>
