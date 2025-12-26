<template>
  <Teleport to="body">
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
        @click.self="close"
    >
      <div class="w-full max-w-lg rounded-xl bg-card p-6 shadow-xl">
        <!-- Header -->
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-text">
            {{ isEdit ? 'Редагувати запис' : 'Новий запис' }}
          </h2>
          <button
              class="text-text/60 hover:text-text"
              @click="close"
          >
            ✕
          </button>
        </div>

        <!-- Body -->
        <div class="space-y-4">
          <!-- Дата -->
          <div>
            <label class="mb-1 block text-sm text-text/70">
              Дата
            </label>
            <div ref="datePickerRef"></div>
          </div>

          <!-- Час -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="mb-1 block text-sm text-text/70">
                Початок
              </label>
              <TimePicker v-model="startTime" />
            </div>
            <div>
              <label class="mb-1 block text-sm text-text/70">
                Кінець
              </label>
              <TimePicker v-model="endTime" />
            </div>
          </div>

          <!-- Коментар -->
          <div>
            <label class="mb-1 block text-sm text-text/70">
              Коментар
            </label>
            <textarea
                v-model="comment"
                rows="3"
                class="w-full rounded-lg border border-border bg-bg px-3 py-2 text-sm text-text focus:border-emerald-500 focus:outline-none"
            />
          </div>
        </div>

        <!-- Footer -->
        <div class="mt-6 flex justify-end gap-3">
          <button
              class="rounded-md border border-border px-4 py-2 text-sm text-text/70 hover:bg-card/80"
              @click="close"
          >
            Скасувати
          </button>
          <button
              class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500"
              @click="submit"
          >
            Зберегти
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, watch, onMounted, onBeforeUnmount, computed } from 'vue'
import DatePicker from 'tui-date-picker'
import 'tui-date-picker/dist/tui-date-picker.css'
import TimePicker from './TimePicker.vue'

/**
 * PROPS
 */
const props = defineProps({
  open: {
    type: Boolean,
    required: true,
  },
  event: {
    type: Object,
    default: null, // для edit
  },
})

/**
 * EMITS
 */
const emit = defineEmits([
  'close',
  'save',
])

/**
 * STATE
 */
const datePickerRef = ref(null)
let datePicker = null

const selectedDate = ref(new Date())
const startTime = ref('09:00')
const endTime = ref('09:30')
const comment = ref('')

const isEdit = computed(() => !!props.event)

/**
 * INIT FROM EVENT
 */
const initFromEvent = () => {
  if (!props.event) return

  const start = new Date(props.event.start)
  const end = new Date(props.event.end)

  selectedDate.value = start
  startTime.value = `${String(start.getHours()).padStart(2, '0')}:${String(start.getMinutes()).padStart(2, '0')}`
  endTime.value = `${String(end.getHours()).padStart(2, '0')}:${String(end.getMinutes()).padStart(2, '0')}`
  comment.value = props.event.title ?? ''
}

/**
 * DATE PICKER
 */
onMounted(() => {
  datePicker = new DatePicker(datePickerRef.value, {
    date: selectedDate.value,
    language: 'uk',
    input: {
      usageStatistics: false,
    },
  })

  datePicker.on('change', () => {
    selectedDate.value = datePicker.getDate()
  })

  initFromEvent()
})

onBeforeUnmount(() => {
  datePicker?.destroy()
})

/**
 * WATCH OPEN
 */
watch(
    () => props.open,
    (value) => {
      if (value) {
        initFromEvent()
        datePicker?.setDate(selectedDate.value)
      }
    }
)

/**
 * ACTIONS
 */
const close = () => {
  emit('close')
}

const submit = () => {
  const [sh, sm] = startTime.value.split(':').map(Number)
  const [eh, em] = endTime.value.split(':').map(Number)

  const start = new Date(selectedDate.value)
  start.setHours(sh, sm, 0, 0)

  const end = new Date(selectedDate.value)
  end.setHours(eh, em, 0, 0)

  emit('save', {
    start,
    end,
    title: comment.value,
  })

  close()
}
</script>
